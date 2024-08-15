<form action="{{ route('employee.profile.update') }}" method="POST" enctype="multipart/form-data">
        @csrf

    <div class="col-lg-12">
        <div class="row">
            <h4>Profile:</h4>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="name" class="form-label">Country:</label>
                    <input type="text" class="form-control" name="country" id="country" value="{{ $profile->country }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="state" class="form-label">State:</label>
                    <input type="text" class="form-control" name="state" id="state" value="{{ $profile->state }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="city" class="form-label">City:</label>
                    <input type="text" class="form-control" name="city" id="city" value="{{ $profile->city }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="zip_code" class="form-label">Zip/Postal Code:</label>
                    <input type="text" class="form-control" name="zip_code" id="zip_code" value="{{ $profile->zip_code }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">   
                    <label for="address" class="form-label">Address:</label>
                    <input type="text" class="form-control" name="address" id="address" value="{{ $profile->address }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="phone_number" class="form-label">Phone number:</label>
                    <input type="text" class="form-control" name="phone_number" id="phone_number" value="{{ $profile->phone_number }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="row skillContainer">
                    <h4>Skills:</h4>
                    <select class="form-control chosen-select" multiple name="skill_ids[]" id="skillSelect">
                        @foreach($skills as $skill)
                            <option value="{{ $skill->id }}" {{ in_array($skill->id, \Auth::user()->skills->pluck('id')->toArray()) ? 'selected' : '' }}>
                                {{ $skill->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="button" id="addSkillButton" class="btn btn-link">{{ __('+ Add New Skill') }}</button>
                    <div class="input-group mb-3" id="newSkillInput" style="display: none;">
                        <input type="text" id="newSkillName" class="form-control" placeholder="{{ __('Skill Name') }}" aria-label="{{ __('Skill Name') }}">
                        <button type="button" id="createSkillButton" class="btn btn-outline-secondary">{{ __('Create') }}</button>
                    </div> 
            </div>
            
            <div class="form-group">
                <label for="about" class="form-label">{{ __('About') }}</label>
                <textarea class="form-control w-100" name="about" id="about" rows="4">{{ $profile->about }}</textarea>
            </div>
            <div class="form-group">
                <label for="cv_file" class="form-label">{{ __('CV File (PDF only)') }}</label>
                <input type="file" class="form-control" name="cv_file" id="cv_file">
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


<script>
        let addCategoryButton = document.getElementById('addSkillButton');
        addCategoryButton.addEventListener('click', function () {
            document.getElementById('newSkillInput').style.display = "flex";
            document.getElementById('addSkillButton').style.display = "none";
            
        });

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
                let skillSelect = document.getElementById('skillSelect');
                skillSelect.add(newOption);
                $(skillSelect).trigger("chosen:updated");

                document.getElementById('newSkillInput').style.display = "none";
                document.getElementById('newSkillName').value = '';
                document.getElementById('addSkillButton').style.display = "inline-block";
            })
            .catch(error => console.error('Error:', error));
        });
</script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.jquery.min.js"></script>
<script>
        $(".chosen-select").chosen({
            width: "100%",
            placeholder_text_multiple: "Select Skills"
        });
</script>