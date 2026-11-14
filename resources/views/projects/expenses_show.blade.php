@extends('layouts.admin')
@php
    $permissions = Auth::user()->getPermission($project->id);
    $client_keyword = Auth::user()->isClient() ? 'client.' : '';
    $logo = \App\Models\Utility::get_file('users-avatar/');
    $attachment = \App\Models\Utility::get_file('expense/');
@endphp

@section('page-title')
    {{ __('Expenses') }}
@endsection
@section('links')
    @if (\Auth::guard('client')->check())
        <li class="breadcrumb-item"><a href="{{ route('client.home') }}">{{ __('Home') }}</a></li>
    @else
        <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    @endif
    @if (\Auth::guard('client')->check())
        <li class="breadcrumb-item"><a
                href="{{ route('client.projects.index', $currentWorkspace->slug) }}">{{ __('Project') }}</a></li>
    @else
        <li class="breadcrumb-item"><a href="{{ route('projects.index', $currentWorkspace->slug) }}">{{ __('Project') }}</a>
        </li>
    @endif
    <li class="breadcrumb-item"><a
            href="{{ route($client_keyword . 'projects.show', [$currentWorkspace->slug, $project->id]) }}">{{ __('Project Details') }}</a>
    </li>
    <li class="breadcrumb-item">{{ __('Expenses') }}</li>
@endsection
{{-- @dump($permissions) --}}
@section('action-button')
    @if (
        (isset($permissions) && in_array('create expenses', $permissions)) ||
            ($currentWorkspace && $currentWorkspace->permission == 'Owner'))
        <a href="#" class="btn btn-sm btn-primary" data-ajax-popup="true" data-size="lg"
            data-title="{{ __(' Add Expenses') }}" data-toggle="tooltip" title="{{ __(' Add Expenses') }}"
            data-url="{{ route($client_keyword . 'projects.expenses.report.create', [$currentWorkspace->slug, $project->id]) }}">
            <i class="ti ti-plus"></i>
        </a>
    @endif
    <button id="toggle-chart-view" class="btn btn-sm btn-primary mx-1" title="{{ __('Toggle Chart View') }}">
        <i class="fas fa-solid fa-chart-area"></i>
    </button>
    <a href="{{ route($client_keyword . 'projects.show', [$currentWorkspace->slug, $project->id]) }}"
        class="btn-submit btn btn-sm btn-primary mx-1" data-toggle="tooltip" title="{{ __('Back') }}">
        <i class="ti ti-arrow-back-up"></i>
    </a>
@endsection

@section('content')
    <section class="section">
        <div class="row">
            <div class="col-12">
                <div id="table-view">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-centered table-hover mb-0 animated" id="selection-datatable">
                                    <thead>
                                        <th>{{ __('Expense Name') }}</th>
                                        <th>{{ __('Task Name') }}</th>
                                        <th>{{ __('Date') }}</th>
                                        <th>{{ __('Amount') }}</th>
                                        <th>{{ __('Attchment') }}</th>
                                        @if (
                                            (isset($permissions) && in_array('edit expenses', $permissions)) ||
                                                ($currentWorkspace && $currentWorkspace->permission == 'Owner') ||
                                                in_array('delete expenses', $permissions))
                                            <th>{{ __('Action') }}</th>
                                        @endif
                                    </thead>
                                    <tbody>
                                        @foreach ($expenses as $expense)
                                            <tr>
                                                <td>{{ $expense->title }}</td>
                                                <td>
                                                    @foreach ($expense->taskExpenses as $task)
                                                        {{ $task->title }}
                                                    @endforeach
                                                </td>
                                                <td>{{ App\Models\Utility::dateFormat($expense->date) }}</td>
                                                <td>{{ $expense->amount }}</td>
                                                <td> <a href="{{ $attachment . $expense->image }}"
                                                        class="action-btn btn-primary  btn btn-sm d-inline-flex align-items-center"
                                                        download>
                                                        <i class="ti ti-download" data-toggle="popover"
                                                            title="{{ __('Download') }}"></i>
                                                    </a></td>
                                                <td class="text-right">
                                                    <a href="#"
                                                        class="action-btn btn-warning  btn btn-sm d-inline-flex align-items-center"
                                                        data-url="{{ route($client_keyword . 'projects.expense.report.view', [$currentWorkspace->slug, $expense->project_id, $expense->id]) }}"
                                                        data-size="lg" data-toggle="tooltip" title="{{ __('View Expenses') }}"
                                                        data-ajax-popup="true" data-title="{{ __('View Expense') }}">
                                                        <i class="ti ti-eye"></i>
                                                    </a>
                                                    @if (
                                                        (isset($permissions) && in_array('edit expenses', $permissions)) ||
                                                            ($currentWorkspace && $currentWorkspace->permission == 'Owner') ||
                                                            in_array('delete expenses', $permissions))
                                                        @if (
                                                            (isset($permissions) && in_array('edit expenses', $permissions)) ||
                                                                ($currentWorkspace && $currentWorkspace->permission == 'Owner'))
                                                            <a href="#"
                                                                class="action-btn btn-info  btn btn-sm d-inline-flex align-items-center"
                                                                data-url="{{ route($client_keyword . 'projects.expense.report.edit', [$currentWorkspace->slug, $expense->project_id, $expense->id]) }}"
                                                                data-size="lg" data-toggle="tooltip"
                                                                title="{{ __('Edit Expenses') }}" data-ajax-popup="true"
                                                                data-title="{{ __('Edit Expense') }}">
                                                                <i class="ti ti-pencil"></i>
                                                            </a>
                                                        @endif

                                                        @if (
                                                            (isset($permissions) && in_array('delete expenses', $permissions)) ||
                                                                ($currentWorkspace && $currentWorkspace->permission == 'Owner'))
                                                            <a href="#"
                                                                class="action-btn btn-danger  btn btn-sm d-inline-flex align-items-center bs-pass-para"
                                                                data-confirm="{{ __('Are You Sure?') }}"
                                                                data-text="{{ __('This action can not be undone. Do you want to continue?') }}"
                                                                data-confirm-yes="delete-form-{{ $expense->id }}"
                                                                data-toggle="tooltip" title="{{ __('Delete Invoices') }}">
                                                                <i class="ti ti-trash"></i>
                                                            </a>
                                                        @endif

                                                        {!! Form::open([
                                                            'method' => 'DELETE',
                                                            'route' => [
                                                                $client_keyword . 'projects.expense.report.destroy',
                                                                [$currentWorkspace->slug, $expense->project_id, $expense->id],
                                                            ],
                                                            'id' => 'delete-form-' . $expense->id,
                                                        ]) !!}
                                                        {!! Form::close() !!}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="chart-view" class="chart-view-dashboard">
                    <div class="month-filter-container">
                        <button id="prev-month" class="month-nav-btn">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span id="current-month" class="month-label">December 2024</span>
                        <button id="next-month" class="month-nav-btn">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                    <div class="expense-chart">
                        <canvas id="expense-chart"></canvas>
                    </div>
                    <div id="no-data-message" class="mt-3 text-center" style="display: none;">
                        <h4>{{ __('No expenses in the selected period') }}</h4>
                    </div>
                    <div id="total-amount-expenses" class="mt-3 text-center">
                        <h4>{{ __('Total:') }} <span id="total-amount"></span></h4>
                    </div>
                </div>
            </div>
        </div>

    </section>
@endsection
@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggleChartViewButton = document.getElementById('toggle-chart-view');
            const tableView = document.getElementById('table-view');
            const chartView = document.getElementById('chart-view');
            const ctx = document.getElementById('expense-chart').getContext('2d');

            function generatePastelColors(count) {
                const pastelColors = [];
                for (let i = 0; i < count; i++) {
                    const r = Math.floor((Math.random() * 127) + 127);
                    const g = Math.floor((Math.random() * 127) + 127);
                    const b = Math.floor((Math.random() * 127) + 127);
                    pastelColors.push(`rgb(${r}, ${g}, ${b})`);
                }
                return pastelColors;
            }

            function parseDate(dateString) {
                const [year, month, day] = dateString.split('-').map(Number);
                return new Date(year, month - 1, day); // JavaScript months are 0-based
            }

            const expenses = @json($expenses);
            const pastelColors = generatePastelColors(expenses.length);

            let currentDate = new Date();
            const monthNames = [
                "January", "February", "March", "April", "May", "June", "July", 
                "August", "September", "October", "November", "December"
            ];

            const expenseChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: [],
                    datasets: [{
                        data: [],
                        backgroundColor: pastelColors,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    const label = context.label || '';
                                    const value = context.raw || 0;
                                    return `${label}: ${value}`;
                                }
                            }
                        }
                    }
                }
            });

            function updateMonthLabel() {
                const monthLabel = document.getElementById('current-month');
                monthLabel.textContent = `${monthNames[currentDate.getMonth()]} ${currentDate.getFullYear()}`;
            }

            function filterExpensesByMonth() {
                const filteredExpenses = expenses.filter(expense => {
                    const expenseDate = parseDate(expense.date);
                    return (
                        expenseDate.getFullYear() === currentDate.getFullYear() &&
                        expenseDate.getMonth() === currentDate.getMonth()
                    );
                });

                const filteredData = filteredExpenses.map(expense => expense.amount);
                const filteredLabels = filteredExpenses.map(expense => expense.title);

                if (filteredData.length === 0) {
                    document.getElementById('expense-chart').style.display = 'none';
                    document.getElementById('total-amount-expenses').style.display = 'none';
                    document.getElementById('no-data-message').style.display = 'block';
                } else {
                    document.getElementById('total-amount-expenses').style.display = 'block';
                    document.getElementById('expense-chart').style.display = 'block';
                    document.getElementById('no-data-message').style.display = 'none';

                    expenseChart.data.labels = filteredLabels;
                    expenseChart.data.datasets[0].data = filteredData;

                    const totalAmount = filteredData.reduce((acc, value) => acc + value, 0);
                    document.getElementById('total-amount').textContent = totalAmount;

                    expenseChart.update();
                }
            }

            document.getElementById('prev-month').addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() - 1);
                updateMonthLabel();
                filterExpensesByMonth();
            });

            document.getElementById('next-month').addEventListener('click', () => {
                currentDate.setMonth(currentDate.getMonth() + 1);
                updateMonthLabel();
                filterExpensesByMonth();
            });

            toggleChartViewButton.addEventListener('click', function () {
                if (chartView.style.display === 'none') {
                    tableView.style.display = 'none';
                    chartView.style.display = 'block';
                } else {
                    tableView.style.display = 'block';
                    chartView.style.display = 'none';
                }
            });

            tableView.style.display = 'block';
            chartView.style.display = 'none';
            updateMonthLabel();
            filterExpensesByMonth();
        });
    </script>
@endpush