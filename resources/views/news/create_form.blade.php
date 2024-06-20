<style>
    .edit-news-form {
        padding: 20px;
    }
</style>
<form class="edit-news-form" id="edit-news-form" data-url="{{ env('APP_URL') }}api/news">
    @csrf
    <div class="col-lg-12">
        <div class="row">
            <div class="alert alert-success" id="success-message" style="display: none;">
                Post created successfully!
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="title" class="form-label">Title:</label>
                    <input type="text" class="form-control" name="title" id="title" value="" required>
                    <input type="hidden" class="form-control" name="newsable_type" id="newsable_type" value="pod">
                    <input type="hidden" class="form-control" name="newsable_id" id="newsable_id" value="{{ $podId }}">
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
                    <label for="content" class="form-label">Content:</label>
                    <textarea class="form-control" name="content" id="content" rows="3"></textarea>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="short_content" class="form-label">Short Content:</label>
                    <textarea class="form-control" name="short_content" id="short_content" rows="3"></textarea>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary">Create Post</button>
        </div>
    </div>
</form>



