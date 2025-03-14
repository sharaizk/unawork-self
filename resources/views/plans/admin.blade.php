@extends('layouts.admin')
@php
    use App\Models\Utility;
    $setting = Utility::getAdminPaymentSetting();
@endphp
@section('page-title')
    {{ __('Plans') }}
@endsection
@section('links')
    @if (\Auth::guard('client')->check())
        <li class="breadcrumb-item"><a href="{{ route('client.home') }}">{{ __('Home') }}</a></li>
    @else
        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    @endif
    <li class="breadcrumb-item"> {{ __('plans') }}</li>
@endsection

@section('action-button')
    @if (Auth::user()->type == 'admin')
        <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="lg" data-title="{{ __('Add Plan') }}"
            data-toggle="tooltip" title="{{ __('Add Plan') }}" data-url="{{ route('plans.create') }}">
            <i class="ti ti-plus"></i>
        </a>
        <button id="toggleView" class="btn btn-sm btn-primary">
            <i class="fas fa-solid fa-table"></i>
        </button>
    @endif
@endsection
<style type="text/css">
    .price-card .p-price {
        font-size: 44px !important;
    }
</style>
@section('content')
    <div id="cardsView" class="row">
        @foreach ($plans as $key => $plan)
            <div class="col-xl-3 col-lg-4 col-md-6 price-wrapper">
                <div class="card price-card price-1 wow animate__fadeInUp" data-wow-delay="0.2s"
                    style="visibility: visible; animation-delay: 0.2s; animation-name: fadeInUp;">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div class="form-check form-switch pt-2">
                                @if ($plan->monthly_price > 0 && $plan->annual_price > 0)
                                    <input type="hidden" name="plan_enable" value="off">
                                    <input id="switch-shadow" class="form-check-input" name="plan_enable"
                                        data-bs-toggle="tooltip" data-bs-placement="top" title="enable/disable"
                                        data-plan-id="{{ $plan->id }}" onclick="togglePlan(this)" type="checkbox"
                                        {{ isset($plan['is_plan_enable']) && $plan['is_plan_enable'] == 1 ? 'checked="checked"' : '' }}>
                                    <label class="form-check-label" for="switch-shadow"></label>
                                @endif
                            </div>

                            <div class="text-end">
                                @if (
                                    (isset($paymentSetting['is_stripe_enabled']) && $paymentSetting['is_stripe_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_paypal_enabled']) && $paymentSetting['is_paypal_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_paystack_enabled']) && $paymentSetting['is_paystack_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_flutterwave_enabled']) && $paymentSetting['is_flutterwave_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_razorpay_enabled']) && $paymentSetting['is_razorpay_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_mercado_enabled']) && $paymentSetting['is_mercado_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_paytm_enabled']) && $paymentSetting['is_paytm_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_mollie_enabled']) && $paymentSetting['is_mollie_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_skrill_enabled']) && $paymentSetting['is_skrill_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_coingate_enabled']) && $paymentSetting['is_coingate_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_paymentwall_enabled']) && $paymentSetting['is_paymentwall_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_toyyibpay_enabled']) && $paymentSetting['is_toyyibpay_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_payfast_enabled']) && $paymentSetting['is_payfast_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_iyzipay_enabled']) && $paymentSetting['is_iyzipay_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_sspay_enabled']) && $paymentSetting['is_sspay_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_paytab_enabled']) && $paymentSetting['is_paytab_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_benefit_enabled']) && $paymentSetting['is_benefit_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_cashfree_enabled']) && $paymentSetting['is_cashfree_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_aamarpay_enabled']) && $paymentSetting['is_aamarpay_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_paytr_enabled']) && $paymentSetting['is_paytr_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_midtrans_enabled']) && $paymentSetting['is_midtrans_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_xendit_enabled']) && $paymentSetting['is_xendit_enabled'] == 'on') ||
                                        (isset($paymentSetting['is_yookassa_enabled']) && $paymentSetting['is_yookassa_enabled'] == 'on'))
                                    <span class="btn btn-sm btn-primary px-2 py-2">
                                        <a href="#" class="" data-url="{{ route('plans.edit', $plan->id) }}"
                                            data-ajax-popup="true" data-title="{{ __('Edit Plan') }}" data-toggle="tooltip"
                                            data-size="lg" title="{{ __('Edit') }}">
                                            <span class=""> <i class="ti ti-pencil text-white"></i></span>

                                        </a></span>
                                @endif
                                {{-- @if ($plan->monthly_price > 0 && $plan->annual_price > 0) --}}
                                    <span class="btn btn-sm btn-danger px-2 py-2">
                                        <a href="#" class="dropdown-item bs-pass-para"
                                            data-confirm="{{ __('Are You Sure?') }}"
                                            data-text="{{ __('This action can not be undone. Do you want to continue?') }}"
                                            data-confirm-yes="delete_user_{{ $plan->id }}"><i
                                                class="ti ti-trash"></i></span>
                                    </a>
                                    <form action="{{ route('plans.delete', ['planId' => encrypt($plan->id)]) }}"
                                        method="post" id="delete_user_{{ $plan->id }}" style="display: none;">
                                        @csrf
                                        @method('DELETE')
                                    </form>
                                    </span>
                                {{-- @endif --}}
                            </div>


                        </div>

                        <span class="price-badge bg-primary"> {{ $plan->name }}</span>

                        <span
                            class="mb-4 f-w-600 p-price">{{ $setting['currency_symbol'] ? $setting['currency_symbol'] : '$' }}{{ $plan->monthly_price }}
                            <small class="text-sm">/{{ __('Monthly Price') }}</small></span><br>
                        <span
                            class="mb-4 f-w-600 p-price">{{ $setting['currency_symbol'] ? $setting['currency_symbol'] : '$' }}{{ $plan->annual_price }}
                            <small class="text-sm">/{{ __('Annual Price') }}</small></span>
                        
                        
                        @if ($plan->is_custom) 
                            <p class="m-1">
                                <b>
                                    <i class="fas fa-solid fa-wrench"></i>
                                    Custom Plan
                                </b>
                            </p>
                        @endif
                        <p class="mb-0">
                            {{ $plan->description }}
                        </p>
                        <ul class="list-unstyled my-5">
                            @if ($plan->id != 1)
                                <li>
                                    <span class="theme-avtar">
                                        <i class="text-primary ti ti-circle-plus"></i></span>

                                    {{ $plan->trial_days < 0 ? __('lifetime') : $plan->trial_days }}
                                    {{ __('Trial Days') }}

                                </li>
                                <li>
                                    <span class="theme-avtar">
                                        <i class="text-primary ti ti-circle-plus"></i></span>

                                    {{ $plan->enable_chatgpt == 'on' ? __('Chatgpt enabled') : __('Chatgpt disabled') }}
                                </li>
                            @endif
                            <li>
                               
                                <span class="theme-avtar">
                                    <i class="text-primary ti ti-circle-plus"></i></span>
                                {{ $plan->storage_limit  < 0 ? __('Unlimited') : $plan->storage_limit }}
                                {{ __('Storage Limit') }}
                            </li>
                            <li>
                                <span class="theme-avtar">
                                    <i class="text-primary ti ti-circle-plus"></i></span>
                                {{ $plan->max_workspaces < 0 ? __('Unlimited') : $plan->max_workspaces }}
                                {{ __('Workspaces') }}
                            </li>
                            <li>
                                <span class="theme-avtar">
                                    <i class="text-primary ti ti-circle-plus"></i></span>
                                {{ $plan->max_users < 0 ? __('Unlimited') : $plan->max_users }}
                                {{ __('Users Per Workspace') }}
                            </li>
                            <li>
                                <span class="theme-avtar">
                                    <i class="text-primary ti ti-circle-plus"></i></span>
                                {{ $plan->max_clients < 0 ? __('Unlimited') : $plan->max_clients }}
                                {{ __('Clients Per Workspace') }}
                            </li>
                            <li>
                                <span class="theme-avtar">
                                    <i class="text-primary ti ti-circle-plus"></i></span>
                                {{ $plan->max_projects < 0 ? __('Unlimited') : $plan->max_projects }}
                                {{ __('Projects Per Workspace') }}
                            </li>
                        </ul>

                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <div id="tableView" class="card" style="display: none;">
        <div class="card-body table-responsive">
            <table class="table table-bordered">
                <thead>
                <tr>
                    <th>#</th>
                    <th>{{ __('Name') }}</th>
                    <th>{{ __('Enable') }}</th>
                    <th>{{ __('Monthly Price') }}</th>
                    <th>{{ __('Annual Price') }}</th>
                    <th>{{ __('Trial Days') }}</th>
                    <th>{{ __('Max Workspaces') }}</th>
                    <th>{{ __('Max Users') }}</th>
                    <th>{{ __('Max Projects') }}</th>
                    <th>{{ __('Storage Limit') }}</th>
                    <th>{{ __('Projects in Pods') }}</th>
                    <th>{{ __('Projects in Workspace') }}</th>
                    <th>{{ __('Zoom Meetings') }}</th>
                    <th>{{ __('Gantt Chart') }}</th>
                    <th>{{ __('Bug Reports') }}</th>
                    <th>{{ __('Expenses') }}</th>
                    <th>{{ __('Files') }}</th>
                    <th>{{ __('Chat') }}</th>
                    <th>{{ __('Enable ChatGPT') }}</th>
                    <th>{{ __('Custom Plan') }}</th>
                    <th>{{ __('Description') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @foreach ($plans as $plan)
                    <tr>
                        <td>{{ $plan->id }}</td>
                        <td>{{ $plan->name }}</td>
                        <td>
                            @if ($plan->monthly_price > 0 && $plan->annual_price > 0)
                                <input type="checkbox" data-plan-id="{{ $plan->id }}" onclick="togglePlan(this)"
                                        {{ $plan['is_plan_enable'] == 1 ? 'checked' : '' }}>
                            @else
                                <small>~</small>
                            @endif
                        </td>
                        <td>{{ $plan->monthly_price }}</td>
                        <td>{{ $plan->annual_price }}</td>
                        <td>{{ $plan->is_trial_disable ?  $plan->trial_days : __('---') }}</td>
                        <td>{{ $plan->max_workspaces < 0 ? __('Unlimited') : $plan->max_workspaces }}</td>
                        <td>{{ $plan->max_users < 0 ? __('Unlimited') : $plan->max_users }}</td>
                        <td>{{ $plan->max_projects < 0 ? __('Unlimited') : $plan->max_projects }}</td>
                        <td>{{ $plan->storage_limit < 0 ? __('Unlimited') : $plan->storage_limit . ' MB' }}</td>
                        <td>{{ $plan->allow_pod_project_view ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->allow_workspace_project_view ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->allow_zoom_meeting_creation ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->allow_gannt_chart_project_management ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->allow_bug_report_project_management ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->allow_expenses_project_management ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->allow_files_project_management ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->allow_chat_project_management ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->enable_chatgpt ? __('Enabled') : __('Disabled') }}</td>
                        <td>{{ $plan->is_custom ? __('Yes') : __('No') }}</td>
                        <td>{{ $plan->description }}</td>
                        <td>
                            <a href="#" class="btn btn-sm btn-primary" data-url="{{ route('plans.edit', $plan->id) }}"
                               data-ajax-popup="true" data-title="{{ __('Edit Plan') }}" data-toggle="tooltip">
                                <i class="ti ti-pencil"></i>
                            </a>
                            <a href="#" class="btn btn-sm btn-danger bs-pass-para"
                               data-confirm="{{ __('Are You Sure?') }}"
                               data-text="{{ __('This action cannot be undone. Do you want to continue?') }}"
                               data-confirm-yes="delete_plan_{{ $plan->id }}">
                                <i class="ti ti-trash"></i>
                            </a>
                            <form id="delete_plan_{{ $plan->id }}"
                                  action="{{ route('plans.delete', ['planId' => encrypt($plan->id)]) }}" method="post"
                                  style="display: none;">
                                @csrf
                                @method('DELETE')
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleButton = document.getElementById('toggleView');
            const cardsView = document.getElementById('cardsView');
            const tableView = document.getElementById('tableView');

            toggleButton.addEventListener('click', function () {
                if (cardsView.style.display === 'none') {
                    cardsView.style.display = 'flex';
                    tableView.style.display = 'none';
                    toggleButton.innerHTML = '<i class="fas fa-solid fa-table"></i>';
                } else {
                    cardsView.style.display = 'none';
                    tableView.style.display = 'block';
                    toggleButton.innerHTML = '<i class="fas fa-solid fa-address-card"></i>';
                }
            });
        });

        function togglePlan(plan) {
            var planId = $(plan).data('plan-id');
            var enableValue = $(plan).prop('checked') ? '1' : '0';
            console.log(planId, enableValue);
            $.ajax({
                type: 'POST',
                url: '{{ route('update.plan.status') }}',
                data: {
                    'plan_id': planId,
                    'enable': enableValue
                },
                success: function(response) {
                    console.log(response);
                    if (response.success == true) {
                        show_toastr('Success', response.message, 'success');
                    } else {
                        show_toastr('error', response.message, 'error');
                    }
                },

            });
        }

        $(document).ready(function() {
            $(document).on('change', '#is_trial_disable', function() {
                if ($(this).is(':checked')) {
                    $('.ps_div').removeClass('d-none');
                    $('#trial_days').attr("required", true);
                } else {
                    $('.ps_div').addClass('d-none');
                    //  $('#trial_days').val(null);
                    $('#trial_days').removeAttr("required");
                }
            });
            
            $(document).on('change', '#is_custom', function() {
              
                
                if ($(this).is(':checked')) {
                    $('#custom-users-select').show();
                } else {
                    $('#custom-users-select').hide();
                }
            });
        });
    </script>
@endpush
