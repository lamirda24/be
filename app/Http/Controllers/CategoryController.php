<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // CategoryController
    public function index(Request $r)
    {
        $limit = max(1, min((int) $r->query('limit', 20), 100));

        $categories = Category::query()
            ->select('id', 'name')
            ->orderBy('id')              // atau ->orderByDesc('id')
            ->paginate($limit)
            ->withQueryString();

        return response()->api($categories, 200); // atau return $categories;
    }


    public function store(Request $r)
    {
        $data = $r->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);
        return response()->api(Category::create($data), 201);
    }

    public function update(Request $r, Category $category)
    {
        $data = $r->validate([
            'name' => 'sometimes|string|max:100|unique:categories,name,' . $category->id,
            'description' => 'nullable|string',
            'color' => 'nullable|string|max:7',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
        ]);
        $category->update($data);
        return $category;
    }

    public function destroy(Category $category)
    {
        $category->delete();
        return response()->noContent();
    }
}
