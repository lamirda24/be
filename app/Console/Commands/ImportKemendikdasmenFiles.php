<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\File as FileModel;
use Illuminate\Console\Command;
use Illuminate\Http\File as HttpFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportKemendikdasmenFiles extends Command
{
    protected $signature = 'files:import-kemendikdasmen
        {base : Path dasar folder, contoh: ~/Documents/KEMENDIKDASMEN}
        {--user= : ID user pengunggah (opsional)}
        {--published=1 : 1=publish, 0=draft}
        {--move : Pindahkan (hapus sumber) bukan copy}
        {--dry-run : Coba dulu tanpa menulis ke DB/storage}';

    protected $description = 'Import semua file dari folder KEMENDIKDASMEN ke storage/public & tabel files (kategori dari nama folder level-1).';

    /** ekstensi yang diizinkan (opsional) */
    protected array $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'rtf', 'odt'];

    public function handle(): int
    {
        // 1) expand ~
        $base = $this->expandPath($this->argument('base'));
        if (!is_dir($base)) {
            $this->error("Folder tidak ditemukan: {$base}");
            return self::FAILURE;
        }

        $userId      = $this->option('user') ?: null;
        $isPublished = ((int) $this->option('published') === 1);
        $doMove      = (bool) $this->option('move');
        $dryRun      = (bool) $this->option('dry-run');

        // 2) ambil mapping kategori (name -> id), case-insensitive
        $cats = Category::query()->get(['id', 'name']);
        $catMap = [];
        foreach ($cats as $c) $catMap[mb_strtolower(trim($c->name))] = (int) $c->id;

        $files = $this->scanRecursive($base);
        $files = array_values(array_filter($files, fn($p) => is_file($p))); // safety

        if (empty($files)) {
            $this->warn('Tidak ada file ditemukan.');
            return self::SUCCESS;
        }

        $this->info("Ditemukan " . count($files) . " file. Mulai proses " . ($dryRun ? '(DRY RUN)' : '') . " ...");

        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        $imported = 0;
        $skipped  = 0;

        foreach ($files as $srcPath) {
            try {
                // Abaikan file tersembunyi (.DS_Store dll.)
                if (basename($srcPath)[0] === '.') {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
                if ($ext && !in_array($ext, $this->allowedExt, true)) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // 3) tentukan kategori dari folder level-1
                $relative = ltrim(Str::after($srcPath, rtrim($base, DIRECTORY_SEPARATOR)), DIRECTORY_SEPARATOR);
                $topFolder = explode(DIRECTORY_SEPARATOR, $relative, 2)[0] ?? null;
                $categoryId = null;
                if ($topFolder) {
                    $key = mb_strtolower(trim($topFolder));
                    $categoryId = $catMap[$key] ?? null;
                    if (!$categoryId) {
                        // Jika nama folder belum ada di DB -> buat otomatis
                        $cat = Category::firstOrCreate(['name' => $topFolder]);
                        $categoryId = (int) $cat->id;
                        $catMap[$key] = $categoryId;
                    }
                }

                // 4) meta file
                $originalName = basename($srcPath);
                $title        = pathinfo($originalName, PATHINFO_FILENAME);
                $description  = $title;
                $mime         = $this->guessMime($srcPath);
                $size         = @filesize($srcPath) ?: 0;

                // Cegah duplikasi sederhana (berdasarkan original_name + size)
                $exists = FileModel::where('original_name', $originalName)
                    ->where('size_bytes', $size)
                    ->exists();
                if ($exists) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                // 5) simpan ke storage/public/files
                $targetName = Str::random(40) . ($ext ? ".{$ext}" : '');
                $relativeStorage = 'files/' . $targetName;

                if (!$dryRun) {
                    Storage::disk('public')->putFileAs(
                        'files',
                        new HttpFile($srcPath),
                        $targetName
                    );
                    if ($doMove) @unlink($srcPath);
                }

                // 6) insert DB
                if (!$dryRun) {
                    $m = new FileModel();
                    $m->title         = $title;
                    $m->slug          = Str::slug($title) . '-' . Str::random(6);
                    $m->description   = $description;
                    $m->category_id   = $categoryId;
                    $m->storage_path  = $relativeStorage;
                    $m->original_name = $originalName;
                    $m->mime_type     = $mime ?? 'application/octet-stream';
                    $m->size_bytes    = $size;
                    $m->uploaded_by   = $userId;
                    $m->is_published  = $isPublished;
                    $m->download_count = 0;
                    $m->save();
                }

                $imported++;
            } catch (\Throwable $e) {
                $this->warn("\nGagal import: {$srcPath} => {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line("\nSelesai. Berhasil: {$imported}, Dilewati: {$skipped}" . ($dryRun ? " (DRY RUN)" : ""));

        $this->info("Contoh akses setelah import: GET /api/files/{idOrSlug}/download");
        return self::SUCCESS;
    }

    /** Scan rekursif semua file di bawah $dir */
    protected function scanRecursive(string $dir): array
    {
        $result = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            if ($fileInfo->isFile()) $result[] = $fileInfo->getPathname();
        }
        return $result;
    }

    /** Expand "~" → HOME */
    protected function expandPath(string $path): string
    {
        if (str_starts_with($path, '~/')) {
            $home = getenv('HOME');
            if (!$home && function_exists('posix_getpwuid')) {
                $home = posix_getpwuid(posix_getuid())['dir'] ?? '';
            }
            if ($home) $path = $home . substr($path, 1);
        }
        return $path;
    }

    /** Guess MIME dengan fallback */
    protected function guessMime(string $path): ?string
    {
        if (function_exists('mime_content_type')) {
            $m = @mime_content_type($path);
            if ($m) return $m;
        }
        if (class_exists(\finfo::class)) {
            $f = new \finfo(FILEINFO_MIME_TYPE);
            return $f->file($path) ?: null;
        }
        return null;
    }
}
