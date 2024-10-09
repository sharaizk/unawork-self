@extends('layouts.admin')

@section('content')
    <h1>Create Industry</h1>
    
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    
    <form action="{{ route('industries.store') }}" method="POST">
        @csrf
        <label for="name">Industry Name:</label>
        <input type="text" name="name" id="name">
        <button type="submit">Create</button>
    </form>
@endsection