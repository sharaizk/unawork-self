<form class="" method="post" action="{{ route('projects.store',$currentWorkspace->slug) }}">
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
                <option value="" selected disabled>{{ __('Select Category') }}</option>
                @foreach(App\Models\ProjectCategory::all() as $category)
                    <option value="{{ $category->id }}">
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
            <button type="button" id="addCategoryButton" class="btn btn-link">{{ __('+ Add New Category') }}</button>
            <div class="input-group mb-3" id="newCategoryInput" style="display: none;">
                <input type="text" id="newCategoryName" class="form-control" placeholder="{{ __('Category Name') }}" aria-label="{{ __('Category Name') }}">
                <button type="button" id="createCategoryButton" class="btn btn-outline-secondary">{{ __('Create') }}</button>
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
        <div class="col-md-12">
            <label for="users_list" class="col-form-label">{{ __('Users') }}</label>
            <select class=" multi-select" id="users_list" name="users_list[]" data-toggle="select2" multiple="multiple" data-placeholder="{{ __('Select Users ...') }}" required>
            @if ($currentWorkspace->ownerable_type == "App\Models\Pod")
                @foreach($currentWorkspace->ownerable->users as $user)
                    <option value="{{$user->email}}">{{$user->name}} - {{$user->email}}</option>
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
        let addCategoryButton = document.getElementById('addCategoryButton');
        addCategoryButton.addEventListener('click', function () {
            document.getElementById('newCategoryInput').style.display = "flex";
            document.getElementById('addCategoryButton').style.display = "none";
            
        });

        document.getElementById('createCategoryButton').addEventListener('click', function () {
            const newCategoryName = document.getElementById('newCategoryName').value;

            if (newCategoryName.trim() === '') {
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
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                const select = document.getElementById("project_category");
                const option = document.createElement("option");
                option.value = data.id;
                option.text = data.name;
                select.appendChild(option);
                select.value = data.id;

                // Сховати поле для введення нової категорії
                document.getElementById('newCategoryInput').style.display = "none";
                document.getElementById('newCategoryName').value = '';
                document.getElementById('addCategoryButton').style.display = "inline-block";

            })
            .catch(error => console.error('Error:', error));
        });
</script>