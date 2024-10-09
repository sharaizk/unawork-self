@extends('layouts.admin')

@section('content')
    <h1>Industries</h1>
    <a href="{{ route('industries.create') }}" class="btn btn-primary mb-3">Add New Industry</a>
    
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($industries as $industry)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $industry->name }}</td>
                    <td>
                        <a href="{{ route('industries.edit', $industry->id) }}" class="btn btn-sm btn-warning">Edit</a>
                        <form action="{{ route('industries.destroy', $industry->id) }}" method="POST" style="display:inline;">
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