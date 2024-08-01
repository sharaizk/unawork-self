<div class="container p-2 m-2">
    <form method="POST" action="{{ route('pod.users.updatePermissions', ['pod' => $pod->id, 'user' => $user->id]) }}">
        @csrf
        @method('PUT')
        <table class="table">
            <thead>
                <tr>
                    <th>Permission Type</th>
                    <th>View All</th>
                    <th>Management</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Users</td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="users_index" id="permission-users_index"
                            @if($userWorkspace->hasPermissionTo('users_index')) checked @endif>
                            <label class="form-check-label" for="permission-users_index"></label>
                        </div>
                    </td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="users_edit" id="permission-users_edit"
                            @if($userWorkspace->hasPermissionTo('users_edit')) checked @endif>
                            <label class="form-check-label" for="permission-users_edit"></label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>News</td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="news_index" id="permission-news_index"
                            @if($userWorkspace->hasPermissionTo('news_index')) checked @endif>
                            <label class="form-check-label" for="permission-news_index"></label>
                        </div>
                    </td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="news_edit" id="permission-news_edit"
                            @if($userWorkspace->hasPermissionTo('news_edit')) checked @endif>
                            <label class="form-check-label" for="permission-news_edit"></label>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>Projects</td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="projects_index" id="permission-projects_index"
                            @if($userWorkspace->hasPermissionTo('projects_index')) checked @endif>
                            <label class="form-check-label" for="permission-projects_index"></label>
                        </div>
                    </td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="projects_edit" id="permission-projects_edit"
                            @if($userWorkspace->hasPermissionTo('projects_edit')) checked @endif>
                            <label class="form-check-label" for="permission-projects_edit"></label>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
</div>