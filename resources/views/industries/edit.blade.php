@extends('layouts.admin')

@section('content')
    <h1>Edit Industry</h1>
    
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <form action="{{ route('industries.update', $industry->id) }}" method="POST">
        @csrf
        @method('PUT')
        <label for="name">Industry Name:</label>
        <input type="text" name="name" id="name" value="{{ $industry->name }}">
        <button type="submit">Update</button>
    </form>
@endsection