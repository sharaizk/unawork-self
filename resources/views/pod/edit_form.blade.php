<style>
    .update-pod-form {
        padding: 20px;
    }
</style>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/chosen/1.8.7/chosen.min.css">
<form class="update-pod-form" id="edit-pod-form" data-url="./api/pod/{{ $pod->id }}">
    @csrf
    <div class="col-lg-12">
        <div class="row">
            <div class="alert alert-success" id="success-message" style="display: none;">
                Pod updated successfully!
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="title" class="form-label">Title:</label>
                    <input type="text" class="form-control" name="title" id="title" value="{{ $pod->title }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="image" class="form-label">Image:</label>
                    <input type="file" class="form-control" name="image" id="image">
                </div>
                @if ($pod->image)
                    <img src="{{ asset('storage/' . $pod->image) }}" alt="Pod Image" class="img-fluid mt-2">
                @endif
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="description" class="form-label">Description:</label>
                    <textarea class="form-control" name="description" id="description" rows="3">{{ $pod->description }}</textarea>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="row">
                    <h4>Skills:</h4>
                    <select class="form-control chosen-select" name="skills[]" multiple>
                        @foreach($skills as $skill)
                            <option value="{{ $skill->id }}" {{ $pod->skills->contains($skill->id) ? 'selected' : '' }}>
                                {{ $skill->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary">Update POD</button>
        </div>
    </div>
</form>
<!-- 

    <script>
        $(document).ready(function() {
            $(".chosen-select").chosen({
                width: "100%",
                placeholder_text_multiple: "Select Skills"
            });
        });
    </script> -->