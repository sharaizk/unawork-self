{{-- Params: $index, $row ['skill_id','proficiency'], $skills, $proficiencies --}}
<div class="una-profile-row una-profile-row--inline" data-profile-row>
    <div class="row g-2 align-items-start">
        <div class="col-md-6">
            <label class="form-label visually-hidden" for="skills-{{ $index }}-skill">{{ __('Skill') }}</label>
            <select class="form-select js-skill-select js-industry-filtered @error("skills.$index.skill_id") is-invalid @enderror"
                name="skills[{{ $index }}][skill_id]" id="skills-{{ $index }}-skill">
                <option value="">{{ __('Select a skill') }}</option>
                @foreach ($skills as $skill)
                    <option value="{{ $skill->id }}" data-industries="{{ $skill->industries->pluck('id')->implode(',') }}" @selected((string) ($row['skill_id'] ?? '') === (string) $skill->id)>{{ $skill->name }}</option>
                @endforeach
            </select>
            @error("skills.$index.skill_id")
                <span class="invalid-feedback" role="alert">{{ $message }}</span>
            @enderror
        </div>
        <div class="col-md-5 col-10">
            <label class="form-label visually-hidden" for="skills-{{ $index }}-proficiency">{{ __('Proficiency') }}</label>
            <select class="form-select @error("skills.$index.proficiency") is-invalid @enderror"
                name="skills[{{ $index }}][proficiency]" id="skills-{{ $index }}-proficiency">
                <option value="">{{ __('Proficiency') }}</option>
                @foreach ($proficiencies as $value => $label)
                    <option value="{{ $value }}" @selected(($row['proficiency'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error("skills.$index.proficiency")
                <span class="invalid-feedback" role="alert">{{ $message }}</span>
            @enderror
        </div>
        <div class="col-md-1 col-2 text-end">
            <button type="button" class="btn btn-sm btn-light-danger una-profile-remove" data-profile-remove
                title="{{ __('Remove skill') }}" aria-label="{{ __('Remove skill') }}"><i class="ti ti-trash"></i></button>
        </div>
    </div>
</div>
