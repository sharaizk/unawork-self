@extends('layouts.admin')

@section('content')
    <h1>Edit Job Title</h1>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('job-titles.update', $jobTitle->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="title">Job Title Name:</label>
            <input type="text" name="title" id="title" class="form-control" value="{{ $jobTitle->title }}" required>
        </div>
        <button type="submit" class="btn btn-primary mt-3">Update</button>
    </form>
@endsection