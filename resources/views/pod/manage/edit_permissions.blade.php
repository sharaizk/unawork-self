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
                            {{-- Project visibility follows the circle below; it is not edited directly (UNA-006). --}}
                            <input class="form-check-input" type="checkbox" id="permission-projects_index" disabled
                            @if($membership && $membership->state === \App\Enums\PodUser\State::INNER_CIRCLE) checked @endif>
                            <label class="form-check-label small text-muted" for="permission-projects_index">{{ __('Follows the circle') }}</label>
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
                <tr>
                    <td>Zoom Meetings</td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="zoom_meeting_index" id="permission-zoom_meeting_index"
                            @if($userWorkspace->hasPermissionTo('zoom_meeting_index')) checked @endif>
                            <label class="form-check-label" for="permission-zoom_meeting_index"></label>
                        </div>
                    </td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="permissions[]" value="zoom_meeting_edit" id="permission-zoom_meeting_edit"
                            @if($userWorkspace->hasPermissionTo('zoom_meeting_edit')) checked @endif>
                            <label class="form-check-label" for="permission-zoom_meeting_edit"></label>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        @if ($membership && in_array($membership->state, \App\Enums\PodUser\State::activeMembers(), true))
            {{-- Explicit circle target: the server moves the member through PodStateTransitionService. --}}
            <div class="row mb-3">
                <div class="col-md-5">
                    <label for="circle_state" class="form-label">{{ __('Circle') }}</label>
                    <select name="circle_state" id="circle_state" class="form-control">
                        <option value="{{ \App\Enums\PodUser\State::OUTER_CIRCLE->value }}" @selected($membership->state !== \App\Enums\PodUser\State::INNER_CIRCLE)>{{ \App\Enums\PodUser\State::OUTER_CIRCLE->label() }}</option>
                        <option value="{{ \App\Enums\PodUser\State::INNER_CIRCLE->value }}" @selected($membership->state === \App\Enums\PodUser\State::INNER_CIRCLE)>{{ \App\Enums\PodUser\State::INNER_CIRCLE->label() }}</option>
                    </select>
                    <small class="text-muted">{{ __('Current state') }}: {{ $membership->state->label() }}</small>
                </div>
                <div class="col-md-7">
                    <label for="circle_reason" class="form-label">{{ __('Reason (recorded in the membership history)') }}</label>
                    <input type="text" name="reason" id="circle_reason" class="form-control" maxlength="1000">
                </div>
            </div>
        @endif
        <div class="d-flex justify-content-between">
            <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
        </div>
    </form>
</div>