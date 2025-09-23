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
        {--dry-run : Coba dulu tanpa menulis ke DB/storage}
        {--download-name=original : original|basic|title (atur nama file saat diunduh)}
        {--report= : Simpan CSV report ke storage/app (default: import-reports/import-<timestamp>.csv)}
        {--report-json= : (Opsional) Simpan JSON report ke storage/app}
        {--no-csv : Jangan simpan CSV}
        {--show-limit=50 : Batas preview file di konsol saat DRY RUN}';

    protected $description = 'Import file dari folder KEMENDIKDASMEN (kategori dari folder level-1), dengan normalisasi nama & laporan.';

    /** ekstensi yang diizinkan */
    protected array $allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'rtf', 'odt'];

    /** alias folder → nama kategori di DB (agar match data kamu) */
    protected array $categoryAliases = [
        'spbe nasional'               => 'Kebijakan SPBE Nasional',
        'laporan hasil evaluasi spbe' => 'Laporan Hasil Evaluasi SPBE',
        'spbe kemendikdasmen'         => 'SPBE Kemendikdasmen',
    ];

    /** daftar akronim supaya tetap huruf besar pada TitleCase */
    protected array $acronyms = ['SPBE', 'BRIN', 'BSSN', 'PANRB', 'RI', 'PDN'];

    public function handle(): int
    {
        $base = $this->expandPath($this->argument('base'));
        if (!is_dir($base)) {
            $this->error("Folder tidak ditemukan: {$base}");
            return self::FAILURE;
        }

        $userId        = $this->option('user') ?: null;
        $isPublished   = ((int) $this->option('published') === 1);
        $doMove        = (bool) $this->option('move');
        $dryRun        = (bool) $this->option('dry-run');
        $downloadStyle = strtolower((string) $this->option('download-name') ?: 'original'); // original|basic|title
        $noCsv         = (bool) $this->option('no-csv');
        $showLimit     = max(0, (int) $this->option('show-limit'));

        // ====== Report holders ======
        $report = [];
        $planned = [];
        $newCategories = [];
        $addRow = function (array $row) use (&$report) {
            $report[] = $row;
        };

        // Kategori yang sudah ada di DB → map normalized(name) => id
        $catMap = [];
        foreach (Category::query()->get(['id', 'name']) as $c) {
            $catMap[$this->norm($c->name)] = (int) $c->id;
        }
        $catMapStartKeys = array_keys($catMap); // snapshot awal

        // Scan semua file
        $files = $this->scanRecursive($base);
        $files = array_values(array_filter($files, 'is_file'));
        if (!$files) {
            $this->warn('Tidak ada file ditemukan.');
            return self::SUCCESS;
        }

        $this->info("Ditemukan " . count($files) . " file. Mulai proses " . ($dryRun ? '(DRY RUN)' : '') . " ...");
        $bar = $this->output->createProgressBar(count($files));
        $bar->start();

        $imported = 0;
        $skipped = 0;
        $plannedCount = 0;

        foreach ($files as $srcPath) {
            try {
                $bn = basename($srcPath);

                // Hidden
                if ($bn !== '' && $bn[0] === '.') {
                    $skipped++;
                    $addRow($this->row('skipped', 'hidden', $srcPath, null, $bn));
                    $bar->advance();
                    continue;
                }

                $ext = strtolower(pathinfo($srcPath, PATHINFO_EXTENSION));
                if ($ext && !in_array($ext, $this->allowedExt, true)) {
                    $skipped++;
                    $addRow($this->row('skipped', 'unsupported_ext', $srcPath, null, $bn));
                    $bar->advance();
                    continue;
                }

                // Tentukan kategori dari folder level-1
                $relative  = ltrim(Str::after($srcPath, rtrim($base, DIRECTORY_SEPARATOR)), DIRECTORY_SEPARATOR);
                $topFolder = explode(DIRECTORY_SEPARATOR, $relative, 2)[0] ?? null;

                $categoryId = null;
                $displayName = null;
                if ($topFolder) {
                    $key = $this->norm($topFolder);
                    $displayName = $this->categoryAliases[$key] ?? $topFolder; // alias → nama kategori DB
                    $lookupKey = $this->norm($displayName);

                    // deteksi kategori baru saat DRY RUN
                    if (!isset($catMap[$lookupKey])) {
                        if ($dryRun) {
                            if (!in_array($lookupKey, $catMapStartKeys, true)) {
                                $newCategories[$lookupKey] = $displayName;
                            }
                            $catMap[$lookupKey] = -1; // placeholder
                        } else {
                            $cat = Category::firstOrCreate(['name' => $displayName]); // timestamps dimatikan di model
                            $catMap[$lookupKey] = (int) $cat->id;
                        }
                    }
                    $categoryId = $catMap[$lookupKey] ?? null;
                }

                // Metadata file
                $originalName = $bn;
                $titleRaw     = pathinfo($originalName, PATHINFO_FILENAME);
                // normalisasi judul/desc
                $normTitle = $this->normalizeBasic($titleRaw);
                // Atau jika ingin Title-Case:
                // $normTitle = $this->normalizeTitleCase($titleRaw);

                $mime = $this->guessMime($srcPath) ?? 'application/octet-stream';
                $size = @filesize($srcPath) ?: 0;

                // Cek duplikat sederhana
                $dup = FileModel::where('original_name', $originalName)
                    ->where('size_bytes', $size)
                    ->exists();
                if ($dup) {
                    $skipped++;
                    $addRow($this->row('skipped', 'duplicate', $srcPath, null, $originalName, $size, $displayName, $categoryId));
                    $bar->advance();
                    continue;
                }

                // Tentukan nama file untuk diunduh (original/basic/title)
                $downloadName = match ($downloadStyle) {
                    'basic' => $this->normalizeFilename($originalName, false),
                    'title' => $this->normalizeFilename($originalName, true),
                    default => $originalName,
                };

                if ($dryRun) {
                    // preview saja
                    $plannedCount++;
                    $planned[] = ['name' => $downloadName, 'category' => $displayName ?: '', 'size' => $size];
                    $addRow($this->row('planned', '', $srcPath, null, $downloadName, $size, $displayName, $categoryId));
                    $bar->advance();
                    continue;
                }

                // Simpan ke storage/public/files
                $targetName      = Str::random(40) . ($ext ? ".{$ext}" : '');
                $relativeStorage = 'files/' . $targetName;
                Storage::disk('public')->putFileAs('files', new HttpFile($srcPath), $targetName);
                if ($doMove) @unlink($srcPath);

                // Insert DB
                $m = new FileModel();
                $m->title         = $normTitle;                            // title = normalized filename (no ext)
                $m->slug          = Str::slug($normTitle) . '-' . Str::random(6);
                $m->description   = $normTitle;                            // description = same
                $m->category_id   = $categoryId;
                $m->storage_path  = $relativeStorage;
                $m->original_name = $downloadName;                         // control download filename here
                $m->mime_type     = $mime;
                $m->size_bytes    = $size;
                $m->uploaded_by   = $userId;
                $m->is_published  = $isPublished;
                $m->download_count = 0;
                $m->save();

                $imported++;
                $addRow($this->row('imported', '', $srcPath, $relativeStorage, $downloadName, $size, $displayName, $categoryId));
            } catch (\Throwable $e) {
                $skipped++;
                $addRow($this->row('skipped', 'exception', $srcPath, null, basename($srcPath), null, $displayName ?? null, $categoryId ?? null, $e->getMessage()));
            }

            $bar->advance();
        }

        $bar->finish();
        $this->line("\nSelesai. " . ($dryRun ? "Planned: {$plannedCount}" : "Berhasil: {$imported}") . ", Terlewat: {$skipped}");

        // ====== Ringkasan DRY RUN ======
        if ($dryRun) {
            $this->info("\nKategori baru yang akan dibuat:");
            if (empty($newCategories)) $this->line("- (Tidak ada)");
            else foreach ($newCategories as $name) $this->line("- {$name}");

            $this->info("\nPreview file yang AKAN di-import:");
            if (empty($planned)) $this->line("- (Tidak ada)");
            else {
                $max = $showLimit ?: count($planned);
                $shown = 0;
                $this->line(str_pad("Nama File", 60) . " | " . str_pad("Kategori", 35) . " | Size");
                $this->line(str_repeat("-", 60) . "-+-" . str_repeat("-", 35) . "-+------");
                foreach ($planned as $row) {
                    if ($shown >= $max) break;
                    $this->line(str_pad($row['name'], 60) . " | " . str_pad($row['category'], 35) . " | " . $row['size']);
                    $shown++;
                }
                if ($shown < count($planned)) $this->line("...dan " . (count($planned) - $shown) . " file lainnya.");
            }

            $this->warn("\nFile terlewat:");
            $hadSkipped = false;
            foreach ($report as $row) if ($row['status'] === 'skipped') {
                $hadSkipped = true;
                $this->line("• {$row['reason']} | {$row['source_path']}");
            }
            if (!$hadSkipped) $this->line("- (Tidak ada)");
        }

        // ====== Simpan REPORT (CSV &/ JSON) ======
        if (!$noCsv) {
            $csvPath = $this->option('report') ?: ('import-reports/import-' . now()->format('Ymd-His') . '.csv');
            Storage::disk('local')->put($csvPath, $this->toCsv($report));
            $this->info("\nReport CSV: storage/app/{$csvPath}");
        }
        if ($jsonPath = $this->option('report-json')) {
            Storage::disk('local')->put($jsonPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Report JSON: storage/app/{$jsonPath}");
        }

        $this->info("Contoh akses: GET /api/files/{idOrSlug}/download");
        return self::SUCCESS;
    }

    /* ---------------- helpers ---------------- */

    protected function row(
        string $status,
        string $reason,
        string $sourcePath,
        ?string $storedPath = null,
        ?string $originalName = null,
        $size = null,
        ?string $category = null,
        $categoryId = null,
        ?string $msg = null
    ): array {
        return [
            'status'        => $status,     // imported / skipped / planned
            'reason'        => $reason,     // hidden / unsupported_ext / duplicate / exception / ''
            'source_path'   => $sourcePath,
            'stored_path'   => $storedPath ?? '',
            'original_name' => $originalName ?? '',
            'size_bytes'    => (string)($size ?? ''),
            'category'      => $category ?? '',
            'category_id'   => (string)($categoryId ?? ''),
            'message'       => $msg ?? '',
        ];
    }

    /** Basic normalize: underscores -> spaces, tidy dashes, collapse spaces */
    protected function normalizeBasic(string $name): string
    {
        $name = preg_replace('/[_]+/u', ' ', $name);      // __ → space
        $name = preg_replace('/\s*-\s*/u', ' - ', $name); // space around dash
        $name = preg_replace('/\s{2,}/u', ' ', trim($name));
        return $name;
    }

    /** Title-Case, preserve acronyms, keep some Indonesian short words lower */
    protected function normalizeTitleCase(string $name): string
    {
        $n = $this->normalizeBasic($name);
        $n = mb_convert_case(mb_strtolower($n, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');

        foreach ($this->acronyms as $A) {
            $n = preg_replace('/\b' . preg_quote($A, '/') . '\b/ui', $A, $n);
        }

        $low = ['dan', 'yang', 'di', 'ke', 'dari', 'untuk', 'pada', 'atau', 'dengan', 'serta', 'tentang', 'nomor', 'tahun'];
        $n = preg_replace_callback('/\b(' . implode('|', $low) . ')\b/u', fn($m) => mb_strtolower($m[0], 'UTF-8'), $n);

        // ensure first char uppercase
        $n = mb_strtoupper(mb_substr($n, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($n, 1, null, 'UTF-8');
        return $n;
    }

    /** Normalize a full filename (keep extension) */
    protected function normalizeFilename(string $filename, bool $titleCase = false): string
    {
        $ext  = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        $name = $titleCase ? $this->normalizeTitleCase($name) : $this->normalizeBasic($name);
        $ext  = $ext ? strtolower($ext) : '';

        return $ext ? "{$name}.{$ext}" : $name;
    }

    protected function toCsv(array $rows): string
    {
        $cols = ['status', 'reason', 'source_path', 'stored_path', 'original_name', 'size_bytes', 'category', 'category_id', 'message'];
        $csv = '"' . implode('","', $cols) . '"' . "\n";
        foreach ($rows as $r) {
            $line = [];
            foreach ($cols as $c) {
                $v = str_replace('"', '""', (string)($r[$c] ?? ''));
                $line[] = $v;
            }
            $csv .= '"' . implode('","', $line) . '"' . "\n";
        }
        return $csv;
    }

    /** Scan rekursif semua file di bawah $dir */
    protected function scanRecursive(string $dir): array
    {
        $result = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($it as $fi) if ($fi->isFile()) $result[] = $fi->getPathname();
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

    /** Normalisasi string untuk lookup (trim, kompres spasi, lower) */
    protected function norm(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', trim($s));
        return mb_strtolower($s);
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
