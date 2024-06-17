<form class="create-pod-form">
        @csrf

    <div class="col-lg-12">
        <div class="row">
            <h4>Profile:</h4>
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
                    <label for="description" class="form-label">Description:</label>
                    <input type="text" class="form-control" name="description" id="description">
                </div>
            </div>
            <div class="col-lg-12">
                <div class="row">
                    <h4>Skills:</h4>
                    @foreach($skills as $skill)
                        <label class="form-check-label">
                            <input class="form-check-input" type="checkbox" name="skill_ids[]" value="{{ $skill->id }}">
                            {{ $skill->name }}
                        </label>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary ">Create POD</button>
        </div>
    </div>
</form>