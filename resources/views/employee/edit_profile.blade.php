@extends('layouts.employee')

@section('content')
    <div class="container">
        <h1>Edit Profile</h1>
        <form action="{{ url('/api/profile/'.$profile->id) }}" method="POST">
            @csrf

            <label for="name">country:</label>
            <input type="text" name="country" id="country" value="{{ $profile->country }}" required>
            
            <label for="state">state:</label>
            <input type="text" name="state" id="state" value="{{ $profile->state }}" required>
            
            <label for="city">city:</label>
            <input type="text" name="city" id="city" value="{{ $profile->city }}" required>
           
            <label for="zip_code">zip_code:</label>
            <input type="text" name="zip_code" id="zip_code" value="{{ $profile->zip_code }}" required>
           
            <label for="address">address:</label>
            <input type="text" name="address" id="address" value="{{ $profile->address }}" required>
           
            <label for="phone_number">phone_number:</label>
            <input type="text" name="phone_number" id="phone_number" value="{{ $profile->phone_number }}" required>
           
            <h2>Skills:</h2>
            @foreach($skills as $skill)
                <label>
                    <input type="checkbox" name="skill_ids[]" value="{{ $skill->id }}" {{ in_array($skill->id, $employee->skills->pluck('id')->toArray()) ? 'checked' : '' }}>
                    {{ $skill->name }}
                </label>
            @endforeach

            <button type="submit">Update Profile</button>
        </form>
    </div>
@endsection
