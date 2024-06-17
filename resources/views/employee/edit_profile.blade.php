<form action="{{ route('employee.profile.update') }}" method="POST">
        @csrf

    <div class="col-lg-12">
        <div class="row">
            <h4>Profile:</h4>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="name" class="form-label">country:</label>
                    <input type="text" class="form-control" name="country" id="country" value="{{ $profile->country }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="state" class="form-label">state:</label>
                    <input type="text" class="form-control" name="state" id="state" value="{{ $profile->state }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="city" class="form-label">city:</label>
                    <input type="text" class="form-control" name="city" id="city" value="{{ $profile->city }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="zip_code" class="form-label">zip_code:</label>
                    <input type="text" class="form-control" name="zip_code" id="zip_code" value="{{ $profile->zip_code }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">   
                    <label for="address" class="form-label">address:</label>
                    <input type="text" class="form-control" name="address" id="address" value="{{ $profile->address }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="phone_number" class="form-label">phone_number:</label>
                    <input type="text" class="form-control" name="phone_number" id="phone_number" value="{{ $profile->phone_number }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="row">
                    <h4>Skills:</h4>
                    @foreach($skills as $skill)
                        <label class="form-check-label">
                            <input class="form-check-input" type="checkbox" name="skill_ids[]" value="{{ $skill->id }}" {{ in_array($skill->id, $user->skills->pluck('id')->toArray()) ? 'checked' : '' }}>
                            {{ $skill->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary ">Update Profile</button>
        </div>
    </div>
</form>