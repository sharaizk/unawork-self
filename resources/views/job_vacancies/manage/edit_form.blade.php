<style>
    .update-vacancy-form {
        padding: 20px;
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css">
<form class="update-vacancy-form" id="edit-pod-form" data-url="{{ route('api.job_vacancies.update', ['job_vacancy' => $vacancy->id]) }}">
    @csrf
    <div class="col-lg-12">
        <div class="row">
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="title" class="form-label">Title:</label>
                    <input type="text" class="form-control" name="title" id="title" value="{{ $vacancy->title }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="salary" class="form-label">Salary:</label>
                    <input type="number" class="form-control" name="salary" id="salary" min="0" value="{{ $vacancy->salary }}">
                </div>
            </div>
            <div class="form-group">
                <label for="skills[]" class="form-label">Skills:</label>
                <select class=" multi-select" id="skills" name="skills[]" data-toggle="select2" multiple="multiple" data-placeholder="{{ __('Select Skills ...') }}">
                    @foreach($skills as $skill)
                        <option value="{{ $skill->id }}" @if (in_array($skill->id, $vacancy->skills->pluck('id')->toArray())) selected @endif>
                            {{ $skill->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-12">
                <div class="form-group">
                    <label for="description" class="form-label">Description:</label>
                    <textarea class="form-control" name="description" id="description" rows="3">{{ $vacancy->description }}</textarea>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary">Update vacancy</button>
        </div>
    </div>
</form>