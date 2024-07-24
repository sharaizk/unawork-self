<div class="container p-2 m-2">
    <form method="POST" action="{{ route('pod.users.updatePermissions', ['pod' => $pod->id, 'user' => $user->id]) }}">
        @csrf
        @method('PUT')
        <div class="form-group">
            @foreach($permissions as $permission)
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->name }}" id="permission-{{ $permission->id }}"
                    @if($userWorkspace->hasPermissionTo($permission->name)) checked @endif>
                    <label class="form-check-label" for="permission-{{ $permission->id }}">
                        {{ $permission->name }}
                    </label>
                </div>
            @endforeach
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>
