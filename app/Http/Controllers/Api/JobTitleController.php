<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobTitle;
use Illuminate\Http\Request;

class JobTitleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return JobTitle::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);
    
        $jobTitle = JobTitle::create($validated);
        return response()->json($jobTitle, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(JobTitle $jobTitle)
    {
        return $jobTitle;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, JobTitle $jobTitle)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
        ]);
    
        $jobTitle->update($validated);
        return response()->json($jobTitle);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(JobTitle $jobTitle)
    {
        $jobTitle->delete();
        return response()->json(['message' => 'Deleted successfully']);
    }
}
