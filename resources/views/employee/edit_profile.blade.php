<form action="{{ route('employee.profile.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

    <div class="col-lg-12">
        <div class="row">
            <h4>Profile:</h4>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="name" class="form-label">Country:</label>
                    <input type="text" class="form-control @error('country') is-invalid @enderror"  name="country" id="country" value="{{ old('country', $profile->country) }}" required>
                    @error('country')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="state" class="form-label">State:</label>
                    <input type="text" class="form-control @error('state') is-invalid @enderror" name="state" id="state" value="{{ $profile->state }}" required>
                    @error('state')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="city" class="form-label">City:</label>
                    <input type="text" class="form-control @error('city') is-invalid @enderror" name="city" id="city" value="{{ $profile->city }}" required>
                    @error('city')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="zip_code" class="form-label">Zip/Postal Code:</label>
                    <input type="text" class="form-control @error('zip_code') is-invalid @enderror" name="zip_code" id="zip_code" value="{{ $profile->zip_code }}" required>
                    @error('zip_code')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">   
                    <label for="address" class="form-label">Address:</label>
                    <input type="text" class="form-control @error('address') is-invalid @enderror" name="address" id="address" value="{{ $profile->address }}" required>
                    @error('address')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="phone_number" class="form-label">Phone number:</label>
                    <input type="text" class="form-control @error('phone_number') is-invalid @enderror" name="phone_number" id="phone_number" value="{{ $profile->phone_number }}" required>
                    @error('phone_number')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
            </div>

            @php
                $jobTitles = \App\Models\JobTitle::all();
                $currentUserJobTitleId = auth()->user()->profile->job_title_id ?? null;
            @endphp
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="job_title_id" class="form-label">Job Title:</label>
                    <select class="form-control" name="job_title_id" id="job_title_id">
                        <option value="">Select your job title</option>
                        @foreach($jobTitles as $jobTitle)
                            <option value="{{ $jobTitle->id }}" {{ $currentUserJobTitleId == $jobTitle->id ? 'selected' : '' }}>
                                {{ $jobTitle->title }}
                            </option>@
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="row skillContainer">
                    <label for="skill_ids" class="form-label">Skills:</label>
                    <select class="form-control chosen-select @error('skill_ids') is-invalid @enderror" multiple name="skill_ids[]" id="skillSelect">
                        @foreach($skills as $skill)
                            <option value="{{ $skill->id }}" {{ in_array($skill->id, \Auth::user()->skills->pluck('id')->toArray()) ? 'selected' : '' }}>
                                {{ $skill->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('skill_ids')
                        <span class="invalid-feedback" role="alert">
                            <strong>{{ $message }}</strong>
                        </span>
                    @enderror
                </div>
                <button type="button" id="addSkillButton" class="btn btn-link">{{ __('+ Add New Skill') }}</button>
                    <div class="input-group mb-3" id="newSkillInput" style="display: none;">
                        <input type="text" id="newSkillName" class="form-control" placeholder="{{ __('Skill Name') }}" aria-label="{{ __('Skill Name') }}">
                        <button type="button" id="createSkillButton" class="btn btn-outline-secondary">{{ __('Create') }}</button>
                    </div> 
            </div>
            
            <div class="form-group">
                <label for="about" class="form-label">{{ __('About') }}</label>
                <textarea class="form-control w-100 @error('about') is-invalid @enderror" name="about" id="about" rows="4">{{ $profile->about }}</textarea>
                @error('about')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
            </div>
            <div class="form-group">
                <label for="cv_file" class="form-label">{{ __('CV File (PDF only)') }}</label>
                <input type="file" class="form-control @error('cv_file') is-invalid @enderror" name="cv_file" id="cv_file">
                @error('cv_file')
                    <span class="invalid-feedback" role="alert">
                        <strong>{{ $message }}</strong>
                    </span>
                @enderror
                @if($profile->cv_file)
                    <p class="mt-2">{{ __('Current CV File:') }} <a href="{{ asset('storage/'.$profile->cv_file) }}" target="_blank">{{ $profile->cv_file }}</a></p>
                @endif
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary ">Update Profile</button>
        </div>
    </div>
</form>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js"></script>
<script>
    $(document).ready(function() {
        // Initialize Chosen on page load
        $(".chosen-select").chosen({
            width: "100%",
            placeholder_text_multiple: "Select Skills"
        });

        // Event listener for the Add Skill button
        let addCategoryButton = document.getElementById('addSkillButton');
        addCategoryButton.addEventListener('click', function () {
            document.getElementById('newSkillInput').style.display = "flex";
            document.getElementById('addSkillButton').style.display = "none";
        });

        // Event listener for the Create Skill button
        document.getElementById('createSkillButton').addEventListener('click', function () {
            const newSkillName = document.getElementById('newSkillName').value;

            if (newSkillName.trim() === '') {
                alert("Please enter a skill name.");
                return;
            }

            fetch("{{ route('api.skill.store') }}", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": "{{ csrf_token() }}"
                },
                body: JSON.stringify({ name: newSkillName })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                let newOption = new Option(data.name, data.id, true, true);
                let skillSelect = $('#skillSelect');
                skillSelect.append(newOption);
                
                // Update Chosen to recognize the new option
                skillSelect.trigger("chosen:updated");

                document.getElementById('newSkillInput').style.display = "none";
                document.getElementById('newSkillName').value = '';
                document.getElementById('addSkillButton').style.display = "inline-block";
            })
            .catch(error => console.error('Error:', error));
        });
    });
</script>
@endpush
