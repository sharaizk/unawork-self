@extends('layouts.employee')

@section('content')
    <div class="container">
        <div class="row">
                <h1>Profile</h1>
                <p><strong>Name:</strong> {{ $employee->name }}</p>
                <p><strong>Email:</strong> {{ $employee->email }}</p>
                <p><strong>Country:</strong> {{ $profile->country }}</p>
                <p><strong>State:</strong> {{ $profile->state }}</p>
                <p><strong>City:</strong> {{ $profile->city }}</p>
                <p><strong>Zip Code:</strong> {{ $profile->zip_code }}</p>
                <p><strong>Address:</strong> {{ $profile->address }}</p>
                <p><strong>Phone Number:</strong> {{ $profile->phone_number }}</p>
                <p><strong>CV:</strong> <a href="{{ asset('storage/' . $profile->cv_file) }}">Download</a></p>
            
                <h1>Skills</h1>
                @if($skills->isEmpty())
                    <p>Select skills.</p>
                @else
                    <ul>
                        @foreach($skills as $skill)
                            <li>{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                @endif
        </div>
        <a href="{{ route('profile.update') }}" class="btn btn-primary">Update Profile</a>

    </div>
@endsection
