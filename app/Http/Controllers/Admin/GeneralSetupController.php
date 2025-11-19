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
            'content'          => 'nullable|string',
            'background_image' => 'nullable|image|max:8048',
        ]);

        // 1) EXPLICITLY fetch existing row
        $setup = GeneralSetup::where('key', 'index_hero')->first();

        // 2) If none exists, create a new instance
        if (! $setup) {
            $setup = new GeneralSetup();
            $setup->key = 'index_hero';
        }

        // 3) Only update content if the field is actually present
        //    (so a missing field does NOT wipe existing content)
        if ($request->has('content')) {
            $setup->content = $request->input('content');
        }

        // 4) Background image: replace file, keep record
        if ($request->hasFile('background_image')) {
            // delete old file from disk only
            if (!empty($setup->background_image)) {
                Storage::disk('public')->delete($setup->background_image);
            }

            $path = $request->file('background_image')->store('general', 'public');
            $setup->background_image = $path;
        }

        // 5) Save changes to the *same* row (or new one if it didn't exist)
        $setup->save();

        return back()->with('status', 'Index hero setup updated successfully.');
    }

    public function resetIndexHero()
    {
        $setup = GeneralSetup::where('key', 'index_hero')->first();

        if ($setup) {
            if (!empty($setup->background_image)) {
                Storage::disk('public')->delete($setup->background_image);
            }
            $setup->delete(); // <- THIS is the only place we ever delete the row
        }

        return back()->with('status', 'Hero reset to default.');
    }
}
