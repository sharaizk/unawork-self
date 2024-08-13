<style>
    .create-vacancy-form {
        padding: 20px;
    }
    .chosen-drop {
        width: 10px;
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css">

<form class="create-vacancy-form position-relative">
        @csrf
    <div class="col-lg-12">
        <div class="row">
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="title" class="form-label">Title:</label>
                    <input type="text" class="form-control" name="title" id="title" placeholder="Enter Title" required>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="form-group">
                    <label for="salary" class="form-label">Salary:</label>
                    <input type="number" class="form-control" name="salary" id="salary" min="0" placeholder="Enter Salary (e.g., 1000)" required>
                </div>
            </div>

            <div class="form-group">
                <label for="skills[]" class="form-label">Skills:</label>
                <select class=" multi-select" id="skills" name="skills[]" data-toggle="select2" multiple="multiple" data-placeholder="{{ __('Select Skills ...') }}">
                    @foreach($skills as $skill)
                        <option value="{{ $skill->id }}">
                            {{ $skill->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-lg-12">
                <div class="form-group">
                    <label for="description" class="form-label">Description:</label>
                    <textarea class="form-control" name="description" id="description" rows="3" placeholder="Enter Description" required></textarea>
                </div>
            </div>
        </div>
    </div>
    <br>
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary ">Create</button>
        </div>
    </div>
</form>