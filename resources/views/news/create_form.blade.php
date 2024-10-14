<style>
    .edit-news-form {
        padding: 20px;
    }
    .invalid-feedback {
        display: none;
        color: red;
    }
</style>

<form class="edit-news-form" id="edit-news-form" data-url="{{ route('api.news.store') }}">
    @csrf
    <div class="col-lg-12">
        <div class="row">
            <!-- Повідомлення про успішну операцію -->
            <div class="alert alert-success" id="success-message" style="display: none;">
                Post created successfully!
            </div>
            <!-- Повідомлення про помилки -->
            <div id="failed-message" class="alert alert-danger d-none">
                Failed to create post. Please fix the errors.
            </div>

            <!-- Поле Title -->
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="title" class="form-label">Title:</label>
                    <input type="text" class="form-control" name="title" id="title" value="{{ old('title') }}" required>
                    <input type="hidden" class="form-control" name="newsable_type" id="newsable_type" value="pod">
                    <input type="hidden" class="form-control" name="newsable_id" id="newsable_id" value="{{ $podId }}">
                    <div class="invalid-feedback" id="error-title"></div>
                </div>
            </div>

            <!-- Поле Image -->
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="image" class="form-label">Image:</label>
                    <input type="file" class="form-control" name="image" id="image">
                    <div class="invalid-feedback" id="error-image"></div>
                </div>
            </div>

            <!-- Поле Short Content -->
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="short_content" class="form-label">Short Content:</label>
                    <textarea class="form-control" name="short_content" id="short_content" rows="3">{{ old('short_content') }}</textarea>
                    <div class="invalid-feedback" id="error-short_content"></div>
                </div>
            </div>

            <!-- Поле Content -->
            <div class="col-lg-12">
                <div class="form-group">
                    <label for="content" class="form-label">Content:</label>
                    <textarea class="form-control" name="content" id="content" rows="3">{{ old('content') }}</textarea>
                    <div class="invalid-feedback" id="error-content"></div>
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