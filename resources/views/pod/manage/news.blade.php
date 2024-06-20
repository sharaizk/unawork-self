@extends('pod.manage.index')

@section('content.inside')
<div class="row">
    @foreach ($news as $new)
        <div class="col-xl-3 col-lg-4 col-sm-6">
            <div class="card   text-center">
                <div class="card-header border-0 pb-0">
                    <div class="card-header-right">
                        <div class="btn-group card-option">
                            <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                aria-haspopup="true" aria-expanded="false">
                                <i class="feather icon-more-vertical"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a href="#" class="dropdown-item" data-ajax-popup="true" data-size="lg" 
                                    data-title="{{ __('Edit') }}" 
                                    data-url="{{ route('pods.news.edit', ['podId' => $pod->id, 'NewsId' => $new->id]) }}""
                                    data-title="{{ __('Edit this post') }}">
                                    <i class="ti ti-pencil"></i> 
                                    <span>{{ __('Edit this post') }}</span>
                                </a>
                                <a href="#" class="dropdown-item delete-post"
                                    data-post-id="{{ $new->id }}">
                                    <i class="ti ti-trash"></i> 
                                    <span>{{ __('Delete this post') }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size" avatar="{{ $new->title }}" >
                            
                    <h4 class="mt-2">{{ $new->title }}</h4>
                    <span>{{ $new->short_content }}</span>
                    <!-- <br>
                    <small>
                        {{ $new->content }}
                    </small> -->
                </div>
            </div>
        </div>
    @endforeach
    <div class="col-xl-3 col-lg-4 col-sm-6">
        <a href="#" class="btn-addnew-project" data-ajax-popup="true" data-size="lg"
            data-title="{{ __('Create new Post') }}" data-url="{{ route('pods.news.create', $podId=$pod->id) }}"
            data-toggle="tooltip" title="{{ __('Create new Post') }}">
            <div class="bg-primary proj-add-icon">
                <i class="ti ti-plus"></i>
            </div>
            <h6 class="mt-4 mb-2">{{__('Create New Post')}}</h6>
            <p class="text-muted text-center">{{__('Click here to create New Post')}}</p>
        </a>
    </div>
</div>

<script src="{{ asset('assets/js/plugins/tinymce/tinymce.min.js') }}"></script>
<script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        console.log (0);
        $(document).on('shown.bs.modal', function (e) {
            tinymce.init({
                selector: 'textarea#content',
                plugins: 'lists link image charmap print preview hr anchor pagebreak',
                toolbar_mode: 'floating',
                setup: function (editor) {
                    editor.on('init', function (e) {
                        console.log('Editor initialized');
                    });
                }
            });

            const form = document.querySelector('.edit-news-form');
            if (form) {console.log (1);}

            form.addEventListener('submit', async function(event) {
                event.preventDefault();

                const formData = new FormData(form);
                const dataUrl = form.getAttribute('data-url');
                const content = tinymce.get('content').getContent();
                formData.append('content', content);

                try {
                    const response = await axios.post(dataUrl, formData);
                    console.log(response);

                    form.reset();
                    document.getElementById('success-message').style.display = 'block';
                    setTimeout(function() {
                        location.reload(); 
                    }, 3000);
                } catch (error) {
                    console.log(error);

                    form.reset();
                    document.getElementById('failed-message').style.display = 'block';
                    
                    setTimeout(function() {
                        location.reload(); 
                    }, 3000);
                }
            });
        });
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteButtons = document.querySelectorAll('.delete-post');

        deleteButtons.forEach(button => {
            button.addEventListener('click', function(event) {
                event.preventDefault();
                const postId = button.getAttribute('data-post-id');
                const url = `{{ env('APP_URL') }}api/news/${postId}`;

                if (confirm('Are you sure you want to delete this post?')) {
                    fetch(url, {
                        method: 'DELETE',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Failed to delete post');
                        }
                        location.reload(); 
                    })
                    .catch(error => {
                        console.error(error);
                        alert('Failed to delete post');
                    });
                }
            });
        });
    });
</script>
@endsection