@extends('layouts.admin')

@section('content')
    <h1>Create Job Title</h1>
    
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <form action="{{ route('job-titles.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="title">Job Title Name:</label>
            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" required>

            {{-- Виведення помилки для конкретного поля --}}
            @error('title')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <button type="submit" class="btn btn-success mt-3">Create</button>
    </form>
@endsection