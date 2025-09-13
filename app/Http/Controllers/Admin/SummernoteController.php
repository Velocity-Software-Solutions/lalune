<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SummernoteController extends Controller
{
        public function store(Request $request)
    {
            $request->validate([
        'image' => 'required|image|max:20480',
    ]);
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('summernote-images', 'public');
            $url =  asset(Storage::url($path));
            return response()->json(['url' => $url]);
        }

        return response()->json(['error' => 'No image uploaded'], 400);
    }

        public function destroy(Request $request)
    {
        $request->validate([
            'url' => 'required|string',
        ]);

        // Get the path relative to "storage"
        $url = $request->input('url');
        $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json(['message' => 'Image deleted']);
        }

        return response()->json(['message' => 'File not found'], 404);
    }
}
