<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
// Group Besar
class TagController extends Controller
{
    public function index()
    {
        return Tag::orderBy('name')->get();
    }

    public function store(Request $r)
    {
        $data = $r->validate(['name' => 'required|string|max:100|unique:tags,name']);
        return response()->api(Tag::create($data), 201);
    }

    public function destroy(Tag $tag)
    {
        $tag->delete();
        return response()->noContent();
    }
}
