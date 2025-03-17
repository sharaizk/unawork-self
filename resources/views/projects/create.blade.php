<form id="createProjectForm" class="" method="post" action="{{ route('projects.store',$currentWorkspace->slug) }}">
    @csrf
     <div class="modal-body">
    <div class="row">
        @if ($currentWorkspace->ownerable_type != "App\Models\Pod" && $currentWorkspace->is_chagpt_enable())
        <div class="text-end col-12">
            <a href="#" data-size="lg" data-ajax-popup-over="true" class="btn btn-sm btn-primary" data-url="{{ route('generate',['project']) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Generate with AI') }}" data-title="{{ __('Generate Project Name & Description') }}">
                <i class="fas fa-robot px-1"></i>{{ __('Generate with AI') }}</a>
        </div>
        @endif

        <div class="form-group col-md-12">
            <label for="project_category" class="col-form-label">{{ __('Category') }}</label>
            <select id="project_category" name="project_category_id" class="form-control select">
                <option value="" selected>{{ __('Select Category') }}</option>
                @foreach(App\Models\ProjectCategory::all() as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <button type="button" id="addCategoryButton" class="btn btn-link">{{ __('+ Add New Category') }}</button>
            <div class="input-group mb-3 mt-2" id="newCategoryInput" style="display: none;">
                <input type="text" id="newCategoryName" class="form-control" placeholder="{{ __('Category Name') }}" aria-label="{{ __('Category Name') }}">
                <button type="button" id="createCategoryButton" class="btn btn-primary">{{ __('Create') }}</button>
            </div>
        </div>
        <div class="form-group col-md-12">
            <label for="projectname" class="col-form-label">{{ __('Name') }}</label>
            <input class="form-control" type="text" id="projectname" name="name" required="" placeholder="{{ __('Project Name') }}">
        </div>
        <div class="form-group col-md-12">
            <label for="description" class="col-form-label">{{ __('Description') }}</label>
            <textarea class="form-control" id="description" name="description" required="" placeholder="{{ __('Add Description') }}"></textarea>
        </div>
        <div class="form-group col-md-12">
                <label class="form-label">{{ __('Visibility') }}</label>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="private" name="private" value="1">
                    <label class="form-check-label" for="private">
                        {{ __('Private Project') }}
                    </label>
                </div>
            </div>
        <div class="col-md-12">
            <label for="users_list" class="col-form-label">{{ __('Users') }}</label>
            <select class="multi-select" id="users_list" name="users_list[]" data-toggle="select2" multiple="multiple" data-placeholder="{{ __('Select Users ...') }}">
            @if ($currentWorkspace->ownerable_type == "App\Models\Pod")
                @foreach($currentWorkspace->ownerable->users as $user)
                    @if ($user->pivot->status == App\Enums\PodUser\Status::ACTIVE->value)
                        <option value="{{$user->email}}">{{$user->name}} - {{$user->email}}</option>
                    @endif
                @endforeach
            @else
                @foreach($currentWorkspace->users($currentWorkspace->created_by) as $user)
                    <option value="{{$user->email}}">{{$user->name}} - {{$user->email}}</option>
                @endforeach
            @endif
            </select>
        </div>
    </div>
</div>
        <div class="modal-footer">
            <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{ __('Close')}}</button>
            <input type="submit" value="{{ __('Add New project')}}" class="btn  btn-primary">
        </div>

</form>

<script>
    $(document).on('shown.bs.modal', function() {
        // check form
        if ($('#createProjectForm').length) {
            $('#addCategoryButton').off('click').on('click', function() {
                $('#newCategoryInput').show();
                $('#addCategoryButton').hide();
            });

            $('#createCategoryButton').off('click').on('click', function() {
                const newCategoryName = $('#newCategoryName').val().trim();

                if (newCategoryName === '') {
                    alert("Please enter a category name.");
                    return;
                }

                fetch("{{ route('api.project-category.store') }}", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "X-CSRF-TOKEN": "{{ csrf_token() }}"
                    },
                    body: JSON.stringify({ name: newCategoryName })
                })
                .then(response => response.json())
                .then(data => {
                    const select = $("#project_category");
                    const option = $('<option></option>').val(data.id).text(data.name);
                    select.append(option).val(data.id);

                    $('#newCategoryInput').hide();
                    $('#newCategoryName').val('');
                    $('#addCategoryButton').show();
                })
                .catch(error => console.error('Error:', error));
            });
        }
    });
</script>
