<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
class CollectionController extends Controller
{
        public function index()
    {
        $collections = Collection::where('status',1)->paginate(10);
        $collections_count = $collections->count();

        return view('admin.collections', compact('collections', 'collections_count'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('collection_id', null); // No ID since it's a new row
        }

        Collection::create([
            'name' => $request->name,
        ]);

        return redirect()->back()->with('success', 'Collection added successfully.');
    }

    public function update(Request $request, string $id)
    {
        $fieldName = 'name_' . $id;

        $validator = Validator::make($request->all(), [
            $fieldName => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput()
                ->with('collection_id', $id);
        }

        $collection = Collection::findOrFail($id);
        $collection->name = $request->input($fieldName);
        $collection->save();
        return redirect()->back()->with('success', 'Collection updated successfully.');
    }

    public function destroy(string $id)
    {
        $collection = Collection::findOrFail($id);
        $collection->status = 0;

        $collection->save();

        return redirect()->back()->with('success', 'Collection deleted.');
    }
}
