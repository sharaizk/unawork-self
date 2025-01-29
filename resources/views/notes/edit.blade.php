@php
$setting = App\Models\Utility::getAdminPaymentSetting();
if ($setting['color']) {
    $color = $setting['color'];
}
else{
  $color = 'theme-3';
}
@endphp
<form class="" method="post" action="{{ route('notes.update',[$currentWorkspace->slug,$note->id]) }}" enctype="multipart/form-data">
    @csrf
     <div class="modal-body">
    <div class="row">
        @if ($currentWorkspace->is_chagpt_enable())
        <div class="text-end col-12">
            <a href="#" data-size="lg" data-ajax-popup-over="true" class="btn btn-sm btn-primary" data-url="{{ route('generate',['notes']) }}" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ __('Generate with AI') }}" data-title="{{ __('Generate Notes Title & Description') }}">
                <i class="fas fa-robot px-1"></i>{{ __('Generate with AI') }}</a>
        </div>
        @endif
        <div class="col-md-12">
            <label for="title" class="col-form-label">{{ __('Title') }}</label>
            <input class="form-control" type="text" id="title" name="title" placeholder="{{ __('Enter Title') }}" value="{{$note->title}}" required>
        </div>
        <div class="col-md-12">
            <label for="description" class="col-form-label">{{ __('Description') }}</label>
            <textarea rows="3" class="form-control grammer_textarea" id="description" name="text" placeholder="{{ __('Enter Description') }}" required>{{$note->text}}</textarea>
        </div>
        <div class="col-md-12">
            <label for="project_id" class="col-form-label">{{ __('Project') }}</label>
            <select class="form-control select" name="project_id" id="project_id">
                <option value="">{{ __('No Project') }}</option>
                @foreach($projects as $project)
                    <option value="{{ $project->id }}" {{ $note->project_id == $project->id ? 'selected' : '' }}>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-12">
            <label for="color" class="col-form-label">{{__('Color')}}</label>
            <select class="form-control select" name="color" id="color" required>
                <option value="bg-primary">{{ __('Primary')}}</option>
                <option @if($note->color == 'bg-secondary') selected @endif value="bg-secondary">{{ __('Secondary')}}</option>
                <option @if($note->color == 'bg-info') selected @endif value="bg-info">{{ __('Info')}}</option>
                <option @if($note->color == 'bg-warning') selected @endif value="bg-warning">{{ __('Warning')}}</option>
                <option @if($note->color == 'bg-danger') selected @endif value="bg-danger">{{ __('Danger')}}</option>
            </select>
        </div>
            <div class="col-md-12">
            <label for="type" class="col-form-label">{{__('Type')}}</label>
           <div class="selectgroup w-50 ">
                <label class="selectgroup-item">
                    <input type="radio" name="type" value="personal" class="selectgroup-input" {{ $note->type == 'personal' ? 'checked="checked"' : '' }}>
                    <span class="selectgroup-button">{{ __('Personal') }}</span>
                </label>
                <label class="selectgroup-item">
                    <input type="radio" name="type" value="shared" class="selectgroup-input" {{ $note->type == 'shared' ? 'checked="checked"' : '' }}>
                    <span class="selectgroup-button">{{ __('Shared') }}</span>
                </label>
            </div>
        </div>
        <div class="col-md-12 assign_to_selection">
            <label for="assign_to" class="col-form-label">{{__('Assign to')}}</label>
            <select     id="assign_to"    name="assign_to[]" class="multi-select" data-toggle="select2" multiple="multiple" data-placeholder="{{ __('Select Users ...') }}"  >
                @foreach($users as $u)
                        <option value="{{$u->id}}" @if(in_array($u->id, $note->assign_to)) selected @endif>{{$u->name}} - {{$u->email}}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-12">
            <label for="file" class="col-form-label">{{ __('Attach File') }}</label>
            @if($note->file)
                <p>
                    <a href="{{ asset('storage/notes_files/' . $note->file->file_path) }}" target="_blank">{{ $note->file->file_name }}</a>
                </p>
            @endif
            <input type="file" class="form-control" id="file" name="file" accept=".doc,.docx,.pdf">
        </div>
        @if($note->files)
            <div class="col-md-12 mt-3">
                <label class="col-form-label">{{ __('Attached Files') }}</label>
                <ul class="list-group">
                    @foreach($note->files as $file)
                        <li class="list-group-item d-flex justify-content-between align-items-center m-1">
                            <a href="{{ asset('storage/notes_files/' . $file->file_path) }}" target="_blank">{{ $file->file_name }}</a>
                            <a href="{{ route('notes.file.delete', [$currentWorkspace->slug, $file->id]) }}" class="btn btn-danger btn-sm">{{ __('Delete') }}</a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
        <div class="modal-footer">
            <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{ __('Close')}}</button>
            <input type="submit" value="{{ __('Save Changes')}}" class="btn  btn-primary">
        </div>

</form>
<script>
    $(document).ready(function() {
        $('#{{ $note->type }}').trigger('click');
    });
</script>
<script type="text/javascript">

    if ($(".multi-select").length > 0) {
            $( $(".multi-select") ).each(function( index,element ) {
                var id = $(element).attr('id');
                   var multipleCancelButton = new Choices(
                        '#'+id, {
                            removeItemButton: true,
                        }
                    );
            });
       }

</script>

@if($color == "theme-1")
<style type="text/css">
    .selectgroup-input:checked + .selectgroup-button {
    background-color: #51459d !important;
}
.selectgroup-button {

    border-color: #51459d !important;
    }
</style>
@endif

@if($color == "theme-2")
<style type="text/css">
    .selectgroup-input:checked + .selectgroup-button {
    background-color: #1f3996 !important;
}
.selectgroup-button {

    border-color: #1f3996 !important;
    }
</style>
@endif
@if($color == "theme-3")
<style type="text/css">
    .selectgroup-input:checked + .selectgroup-button {
    background-color: #022D4D !important;
}
.selectgroup-button {

    border-color: #022D4D !important;
    }
</style>
@endif
@if($color == "theme-4")
<style type="text/css">
    .selectgroup-input:checked + .selectgroup-button {
    background-color: #584ed2 !important;
}
.selectgroup-button {

    border-color: #584ed2 !important;
    }
</style>
@endif
