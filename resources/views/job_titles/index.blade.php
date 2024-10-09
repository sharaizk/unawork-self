@extends('layouts.admin')

@section('content')
    <h1>Job Titles</h1>
    <a href="{{ route('job-titles.create') }}" class="btn btn-primary mb-3">Add New Job Title</a>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($jobTitles as $jobTitle)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $jobTitle->title }}</td>
                    <td>
                        <a href="{{ route('job-titles.edit', $jobTitle->id) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form action="{{ route('job-titles.destroy', $jobTitle->id) }}" method="POST" style="display:inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection