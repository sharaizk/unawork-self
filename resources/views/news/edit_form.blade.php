<style>
    .edit-news-form {
        padding: 20px;
    }
</style>
<form class="edit-news-form" id="edit-news-form" data-url="{{ route('api.news.update', ['id' => $post->id]) }}">
    @csrf
    <div class="col-lg-12">
        <div class="row">
            <div class="alert alert-success" id="success-message" style="display: none;">
                Post updated successfully!
            </div>
            <div class="alert alert-success" id="failed-message" style="display: none;">
                Post update failed!
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="title" class="form-label">Title:</label>
                    <input type="text" class="form-control" name="title" id="title" value="{{ $post->title }}" required>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="image" class="form-label">Image:</label>
                    <input type="file" class="form-control" name="image" id="image">
                </div>
                @if ($post->image)
                    <img src="{{ asset('storage/' . $post->image) }}" alt="post Image" class="img-fluid mt-2">
                @endif
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="short_content" class="form-label">Short Content:</label>
                    <textarea class="form-control" name="short_content" id="short_content" rows="3">{{ $post->short_content }}</textarea>
                </div>
            </div>
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="content" class="form-label">Content:</label>
                    <textarea class="form-control" name="content" id="content" rows="3">{{ old('content', $post->content ?? '') }}</textarea>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="text-end">
            <button type="submit" class="btn-submit btn btn-primary">Update Post</button>
        </div>
    </div>
</form>


