@php
    use App\Models\Utility;
    $setting = Utility::getAdminPaymentSetting();
@endphp

<form class="" method="post" enctype="multipart/form-data" action="{{ route('plans.update', $plan->id) }}">
    @csrf
    <div class="modal-body">
        <div class="row">
            <div class="text-end col-12">
                <a href="#" data-size="lg" data-ajax-popup-over="true" class="btn btn-sm btn-primary"
                    data-url="{{ route('generate', ['plan']) }}" data-bs-toggle="tooltip" data-bs-placement="top"
                    title="{{ __('Generate with AI') }}" data-title="{{ __('Generate Plan Name & Description') }}">
                    <i class="fas fa-robot px-1"></i>{{ __('Generate with AI') }}</a>
            </div>

            <div class="{{ $plan->id == 1 ? 'col-12' : 'col-md-6' }}">
                <div class="form-group">
                    <label for="name" class="form-label">{{ __('Name') }} *</label>
                    <input type="text" class="form-control" id="name" name="name" value="{{ $plan->name }}"
                        required />
                </div>
            </div>
    {{--   @if ($plan->id != 1) --}}
                <div class="form-group col-md-3 pt-3">
                    <div class="form-check form-switch d-inline-block py-4">
                        <input type="hidden" class="form-check-input" name="enable_chatgpt" id="enable_chatgpt"
                            value="off">
                        <input type="checkbox" class="form-check-input" name="enable_chatgpt" id="enable_chatgpt"
                            @if ($plan->enable_chatgpt == 'on') checked @endif>
                        <label class="custom-control-label form-check-label"
                            for="enable_chatgpt">{{ __('Enable Chatgpt') }}</label>
                    </div>
                </div>

                <div class="form-group col-md-3 pt-3">
                    <div class="form-check form-switch d-inline-block py-4">
                        <input type="checkbox" class="form-check-input" name="status" id="status"
                            @if ($plan->status) checked="checked" @endif>
                        <label class="custom-control-label form-control-label"
                            for="status">{{ __('Status') }}</label>
                    </div>
                </div>

                <div class="form-group col-md-6">
                    <label for="monthly_price" class="form-label">{{ __('Monthly Price') }} *</label>
                    <div class="form-icon-user">
                        <span
                            class="currency-icon bg-primary">{{ $setting['currency_symbol'] ? $setting['currency_symbol'] : '$' }}</span>
                        <input class="form-control currency_input" type="number" min="0" id="monthly_price"
                            name="monthly_price" value="{{ $plan->monthly_price }}"
                            placeholder="{{ __('Monthly Price') }}">
                    </div>
                </div>
                <div class="form-group col-md-6">
                    <label for="annual_price" class="form-label">{{ __('Annual Price') }} *</label>
                    <div class="form-icon-user">
                        <span
                            class="currency-icon bg-primary">{{ $setting['currency_symbol'] ? $setting['currency_symbol'] : '$' }}</span>
                        <input class="form-control currency_input" type="number" min="0" id="annual_price"
                            name="annual_price" value="{{ $plan->annual_price }}"
                            placeholder="{{ __('Annual Price') }}">
                    </div>
                </div>

                <div class="form-group col-md-6">
                    <div class="form-check form-switch d-inline-block py-4">
                        <input type="checkbox" class="form-check-input" name="is_custom" id="is_custom" {{ $plan->is_custom ? 'checked' : '' }}>
                        <label class="custom-control-label form-check-label" for="is_custom">
                            {{ __('Custom Plan') }}
                        </label>
                    </div>
                </div>

                <div class="form-group col-md-12" id="custom-users-select" style="{{ $plan->is_custom ? '' : 'display: none;' }}">
                    <label for="user_ids">{{ __('Assign to Users') }}</label>
                    <select class="multi-select" name="users_list[]" id="users_list" data-toggle="select2" multiple="multiple" data-placeholder="{{ __('Select Users ...') }}">
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" {{ $plan->users->contains($user->id) ? 'selected' : '' }}>
                                {{ $user->email }}
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- <div class="form-group col-md-6">
                        <label for="duration" class="form-label">{{ __('Trial Days') }} *</label>
                        <input type="number" class="form-control mb-0" id="trial_days" name="trial_days" value="{{$plan->trial_days}}" required/>
                        <span><small>{{__("Note: '-1' for unlimited")}}</small></span>
                    </div> --}}
                <div class="form-group col-md-6">
                    <label for="storage_limit" class="form-label">{{ __('Storage Limit') }} *</label>

                    <div class="input-group">
                        <input type="number" class="form-control mb-0" id="storage_limit" name="storage_limit"
                            value="{{ $plan->storage_limit }}" required />
                        <div class="input-group-append">
                            <span class="input-group-text">MB</span>
                        </div>
                    </div>
                    <span class="small">{{ __('Note: upload size (In MB)') }}</span>
                </div>
        {{--@endif  --}}
            <div class="form-group col-md-6">
                <label for="max_workspaces" class="form-label">{{ __('Maximum Workspaces') }} *</label>
                <input type="number" class="form-control mb-0" id="max_workspaces" name="max_workspaces"
                    value="{{ $plan->max_workspaces }}" required />
                <span><small>{{ __("Note: '-1' for unlimited") }}</small></span>
            </div>
            <div class="form-group col-md-6">
                <label for="max_users" class="form-label">{{ __('Maximum Users Per Workspace') }} *</label>
                <input type="number" class="form-control mb-0" id="max_users" name="max_users"
                    value="{{ $plan->max_users }}" required />
                <span><small>{{ __("Note: '-1' for unlimited") }}</small></span>
            </div>
            <div class="form-group col-md-6">
                <label for="max_projects" class="form-label">{{ __('Maximum Projects Per Workspace') }} *</label>
                <input type="number" class="form-control mb-0" id="max_projects" name="max_projects"
                    value="{{ $plan->max_projects }}" required />
                <span><small>{{ __("Note: '-1' for unlimited") }}</small></span>
            </div>
            <div class="form-group col-md-12 mb-0">
                <div class="form-group">
                    <label for="description" class="form-label">{{ __('Description') }}</label>
                    <textarea rows="3" class="form-control" id="description" name="description">{{ $plan->description }}</textarea>
                </div>
            </div>

            <div class="form-group col-md-12 pt-12">
                <div class="form-check form-switch d-inline-block py-4">
                    <input type="hidden" class="form-check-input" name="allow_chat_project_management" id="allow_chat_project_management"
                        value="0">
                    <input type="checkbox" class="form-check-input" name="allow_chat_project_management" id="allow_chat_project_management"
                        value="1" @if ($plan->allow_chat_project_management) checked @endif>
                    <label class="custom-control-label form-check-label"
                        for="allow_chat_project_management">{{ __('Enable Chat in project management') }}</label>
                </div>
                <div class="form-check form-switch d-inline-block py-4">
                    <input type="hidden" class="form-check-input" name="allow_files_project_management" id="allow_files_project_management"
                        value="0">
                    <input type="checkbox" class="form-check-input" name="allow_files_project_management" id="allow_files_project_management"
                        value="1"   @if ($plan->allow_files_project_management) checked @endif>
                    <label class="custom-control-label form-check-label"
                        for="allow_files_project_management">{{ __('Enable Files in project management') }}</label>
                </div>
                <div class="form-check form-switch d-inline-block py-4">
                    <input type="hidden" class="form-check-input" name="allow_expenses_project_management" id="allow_expenses_project_management"
                        value="0">
                    <input type="checkbox" class="form-check-input" name="allow_expenses_project_management" id="allow_expenses_project_management"
                        value="1" @if ($plan->allow_expenses_project_management) checked @endif>
                    <label class="custom-control-label form-check-label"
                        for="allow_expenses_project_management">{{ __('Enable Expenses in project management') }}</label>
                </div>
                <div class="form-check form-switch d-inline-block py-4">
                    <input type="hidden" class="form-check-input" name="allow_bug_report_project_management" id="allow_bug_report_project_management"
                        value="0">
                    <input type="checkbox" class="form-check-input" name="allow_bug_report_project_management" id="allow_bug_report_project_management"
                        value="1" @if ($plan->allow_bug_report_project_management) checked @endif>
                    <label class="custom-control-label form-check-label"
                        for="allow_bug_report_project_management">{{ __('Enable Bug Report in project management') }}</label>
                </div>
                <div class="form-check form-switch d-inline-block py-4">
                    <input type="hidden" class="form-check-input" name="allow_gannt_chart_project_management" id="allow_gannt_chart_project_management"
                        value="0">
                    <input type="checkbox" class="form-check-input" name="allow_gannt_chart_project_management" id="allow_gannt_chart_project_management"
                        value="1" @if ($plan->allow_gannt_chart_project_management) checked @endif>
                    <label class="custom-control-label form-check-label"
                        for="allow_gannt_chart_project_management">{{ __('Enable Gannt Chart in project management') }}</label>
                </div>
                <div class="form-check form-switch d-inline-block py-4">
                </div>
                <hr>
                <div class="form-check form-switch d-inline-block py-4">
                    <input type="hidden" class="form-check-input" name="allow_workspace_project_view" id="allow_workspace_project_view"
                        value="0">
                    <input type="checkbox" class="form-check-input" name="allow_workspace_project_view" id="allow_workspace_project_view"
                        value="1" @if ($plan->allow_workspace_project_view) checked @endif>
                    <label class="custom-control-label form-check-label"
                        for="allow_workspace_project_view">{{ __('Enable Inner Cirlce in own workspace') }}</label>
                </div>
                <div class="form-check form-switch d-inline-block py-4">
                    <input type="hidden" class="form-check-input" name="allow_pod_project_view" id="allow_pod_project_view"
                        value="0">
                    <input type="checkbox" class="form-check-input" name="allow_pod_project_view" id="allow_pod_project_view"
                        value="1" @if ($plan->allow_pod_project_view) checked @endif>
                    <label class="custom-control-label form-check-label"
                        for="allow_pod_project_view">{{ __('Enable Inner Cirlce in pods workspace') }}</label>
                </div>
                <div class="form-group col-md-6 ps_div">
                    <label for="max_inner_circles" class="form-control-label">{{ __('Number of Inner Cirlces in pods workspace') }} *</label>
                    <input type="number" class="form-control mb-0" id="max_inner_circles" name="max_inner_circles"
                        value="{{ isset($plan->max_inner_circles) ? $plan->max_inner_circles : '0' }}" />
                    <span><small>{{ __("Note: '-1' for unlimited") }}</small></span>
                </div>
                <div class="form-check form-switch d-inline-block py-4">
                    <input type="hidden" class="form-check-input" name="allow_zoom_meeting_creation" id="allow_zoom_meeting_creation"
                        value="0">
                    <input type="checkbox" class="form-check-input" name="allow_zoom_meeting_creation" id="allow_zoom_meeting_creation"
                        value="1" @if ($plan->allow_zoom_meeting_creation) checked @endif>
                    <label class="custom-control-label form-check-label"
                        for="allow_zoom_meeting_creation">{{ __('Enable Zoom Meeting Creation') }}</label>
                </div>
            </div>

{{--            @if ($plan->monthly_price > 0 && $plan->annual_price > 0)--}}
                <div class="form-group col-md-6 d-flex">
                    <label for="is_trial_disable">{{ __('Trial is enable(on/off)') }}</label>
                    <div class="form-check form-switch custom-switch-v1 mx-2">
                        <input type="hidden" name="is_trial_disable" value="off">
                        <input type="checkbox" name="is_trial_disable" class="form-check-input input-primary pointer"
                            id="is_trial_disable" {{ $plan->is_trial_disable == 1 ? 'checked' : '' }}>
                        <label class="form-check-label" for="is_trial_disable"></label>
                    </div>
                </div>
                <div class="form-group col-md-6 ps_div {{ $plan->is_trial_disable == 1 ? '' : 'd-none' }}">
                    <label for="duration" class="form-control-label">{{ __('Trial Days') }} *</label>
                    <input type="number" class="form-control mb-0" id="trial_days" name="trial_days"
                        value="{{ isset($plan->trial_days) ? $plan->trial_days : '0' }}" />
                    <span><small>{{ __("Note: '-1' for unlimited") }}</small></span>
                </div>
{{--            @endif--}}
        </div>
    </div>
    <div class=" modal-footer">

        <div class="text-end col-auto">
            <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
            <input type="submit" value="{{ __('Update') }}" class="btn  btn-primary">
        </div>
        {{-- <button type="button" class="btn  btn-light" data-bs-dismiss="modal">{{ __('Close')}}</button>
        <input type="submit" value="{{ __('Save')}}" class="btn  btn-primary"> --}}
    </div>
</form>
