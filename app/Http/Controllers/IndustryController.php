<?php

namespace App\Http\Controllers;

use App\Models\Industry;
use Illuminate\Http\Request;

class IndustryController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Industry::class);
        
        $industries = Industry::all();
        return view('industries.index', compact('industries'));
    }

    public function create()
    {
        $this->authorize('create', Industry::class);

        return view('industries.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Industry::class);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        Industry::create($data);

        return redirect()->route('industries.index')->with('success', 'Industry created successfully.');
    }

    public function edit(Industry $industry)
    {
        $this->authorize('update', $industry);

        return view('industries.edit', compact('industry'));
    }

    public function update(Request $request, Industry $industry)
    {
        $this->authorize('update', $industry);

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $industry->update($data);

        return redirect()->route('industries.index')->with('success', 'Industry updated successfully.');
    }

    public function destroy(Industry $industry)
    {
        $this->authorize('delete', $industry);

        $industry->delete();

        return redirect()->route('industries.index')->with('success', 'Industry deleted successfully.');
    }
}