<?php

namespace App\Http\Controllers;

use App\Models\JobTitle;
use Illuminate\Http\Request;

class JobTitleController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', JobTitle::class);

        $jobTitles = JobTitle::all();
        return view('job_titles.index', compact('jobTitles'));
    }

    public function create()
    {
        $this->authorize('create', JobTitle::class);
        
        return view('job_titles.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', JobTitle::class);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        JobTitle::create($data);

        return redirect()->route('job-titles.index')->with('success', 'Job Title created successfully.');
    }

    public function edit(JobTitle $jobTitle)
    {
        $this->authorize('update', $jobTitle);
        
        return view('job_titles.edit', compact('jobTitle'));
    }

    public function update(Request $request, JobTitle $jobTitle)
    {
        $this->authorize('update', $jobTitle);

        $data = $request->validate([
            'title' => 'required|string|max:255',
        ]);

        $jobTitle->update($data);

        return redirect()->route('job-titles.index')->with('success', 'Job Title updated successfully.');
    }

    public function destroy(JobTitle $jobTitle)
    {
        $this->authorize('delete', $jobTitle);

        $jobTitle->delete();

        return redirect()->route('job-titles.index')->with('success', 'Job Title deleted successfully.');
    }
}