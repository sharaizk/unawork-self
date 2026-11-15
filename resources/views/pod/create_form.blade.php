<style>
    .create-pod-form {
        padding: 20px;
    }
    .chosen-drop {
        width: 10px;
    }
</style>
<form class="create-pod-form position-relative">
        @csrf
    {{-- Filled by pods.js with the server's message (e.g. incomplete company profile, UNA-010). --}}
    <div class="alert alert-danger d-none" role="alert" data-pod-form-error></div>

    <div class="col-lg-12">
        <div class="row">
            <div class="alert alert-success" id="success-message" style="display: none;">
                Pod created successfully!
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="title" class="form-label">Title:</label>
                    <input type="text" class="form-control" name="title" id="title" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="image" class="form-label">Image:</label>
                    <input type="file" class="form-control" name="image" id="image">
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="skills[]" class="form-label">Skills:</label>
                    <select class="form-control chosen-select" name="skills[]" multiple>
                        @foreach($skills as $skill)
                            <option value="{{ $skill->id }}">
                                {{ $skill->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="col-lg-12">
                <div class="form-group">
                    <label for="description" class="form-label">Description:</label>
                    <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                </div>
            </div>
        </div>
    </div>
    <br>
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary ">Create POD</button>
        </div>
    </div>
</form>