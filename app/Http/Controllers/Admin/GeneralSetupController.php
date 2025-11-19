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

        // Get existing row or create a new instance (not saved yet)
        $setup = GeneralSetup::firstOrNew(['key' => 'index_hero']);

        // ✅ Only update content if it's actually present in the request
        // This avoids wiping previous content with null when the field is left empty.
        if ($request->has('content')) {
            $setup->content = $request->input('content');
        }

        // ✅ Handle background image upload
        if ($request->hasFile('background_image')) {
            // remove old image if there is one
            if (!empty($setup->background_image)) {
                Storage::disk('public')->delete($setup->background_image);
            }

            $path = $request->file('background_image')->store('general', 'public');
            $setup->background_image = $path;
        }

        // ✅ Make sure we actually save the changes
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
            $setup->delete();
        }

        return back()->with('status', 'Hero reset to default.');
    }
}
