<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GeneralSetup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GeneralSetupController extends Controller
{
    // Update the index hero section
    public function updateIndexHero(Request $request)
    {
        $request->validate([
            'content' => 'nullable|string',
            'background_image' => 'nullable|image|max:8048',
        ]);

        $setup = GeneralSetup::updateOrCreate(
            ['key' => 'index_hero'],      // how to find the record
            [                             // what to set/update
                'content' => $request->input('content'),
                // 'title' => $request->input('title'),
                // 'subtitle' => $request->input('subtitle'),
                // ...whatever fields belong to index_hero
            ]
        );

        $setup->content = $request->input('content');

        if ($request->hasFile('background_image')) {
            // remove old image if there is one
            if ($setup->background_image) {
                Storage::disk('public')->delete($setup->background_image);
            }

            $path = $request->file('background_image')->store('general', 'public');
            $setup->background_image = $path;
        }

        $setup->save();

        return back()->with('status', 'Index hero setup updated successfully.');
    }
    public function resetIndexHero()
    {
        $setup = GeneralSetup::where('key', 'index_hero')->first();

        if ($setup) {
            if ($setup->background_image) {
                Storage::disk('public')->delete($setup->background_image);
            }
            $setup->delete();
        }

        return back()->with('status', 'Hero reset to default.');
    }
}
