@extends('layouts.admin')

@php
    $permissions = Auth::user()->getPermission($project->id);
    $client_keyword = Auth::user()->isClient() ? 'client.' : '';
    $logo = \App\Models\Utility::get_file('users-avatar/');
    $attachment = \App\Models\Utility::get_file('profit/');
@endphp

@section('page-title')
    {{ __('Margin') }}
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
    <li class="breadcrumb-item">{{ __('Margin') }}</li>
@endsection
{{-- @dump($permissions) --}}
@section('action-button')
    <a href="{{ route($client_keyword . 'projects.show', [$currentWorkspace->slug, $project->id]) }}"
        class="btn-submit btn btn-sm btn-primary mx-1" data-toggle="tooltip" title="{{ __('Back') }}">
        <i class="ti ti-arrow-back-up"></i>
    </a>
    <button id="toggle-filters" class="btn btn-sm btn-primary mx-1" title="{{ __('Toggle Filters') }}">
        <i class="ti ti-filter"></i>
    </button>

@endsection

@section('content')
    <section class="section">
        <div id="filters-section" style="display: none;">
            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="start_date">{{ __('Start Date') }}</label>
                    <input type="date" id="start_date" class="form-control">
                </div>
                <div class="col-md-6">
                    <label for="end_date">{{ __('End Date') }}</label>
                    <input type="date" id="end_date" class="form-control">
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-12 d-flex justify-content-between">
                    <button id="previous-week" class="btn btn-sm btn-primary">{{ __('Previous Week') }}</button>
                    <button id="current-week" class="btn btn-sm btn-primary">{{ __('Current Week') }}</button>
                    <button id="previous-month" class="btn btn-sm btn-primary">{{ __('Previous Month') }}</button>
                    <button id="current-month" class="btn btn-sm btn-primary">{{ __('Current Month') }}</button>
                    <button id="apply-filters" class="btn btn-sm btn-primary">{{ __('Apply Filters') }}</button>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12">
                <canvas id="finance-chart"></canvas>
                <div id="no-data" style="display: none; text-align: center; margin-top: 20px;">
                    <p>Add in the cost per project as you task items to your team. Then, add in your revenue or profit to the same project. When you visit this page next, we’ll show you the profit and loss margins so you can automatically keep track of your finances  - all in one platform.</p>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('finance-chart').getContext('2d');
            let chart;

            document.getElementById('toggle-filters').addEventListener('click', function () {
                const filtersSection = document.getElementById('filters-section');
                filtersSection.style.display = filtersSection.style.display === 'none' ? 'block' : 'none';
            });

            // Function to initialize the chart
            function initChart(margins) {
                const labels = margins.map(m => m.date);
                const data = margins.map(m => m.margin);

                // Check if there's data to display
                if (labels.length === 0) {
                    document.getElementById('finance-chart').style.display = 'none';
                    document.getElementById('no-data').style.display = 'block';
                    return;
                }

                // Display the chart and hide the "no data" message
                document.getElementById('finance-chart').style.display = 'block';
                document.getElementById('no-data').style.display = 'none';

                // Destroy the previous chart instance if it exists
                if (chart) {
                    chart.destroy();
                }

                // Initialize the chart
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'Margin',
                            data: data,
                            borderColor: 'rgb(75, 192, 192)',
                            backgroundColor: 'rgba(75, 192, 192, 0.2)',
                            fill: true,
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
                                    title: function(tooltipItems) {
                                        // Display the date as the title
                                        return tooltipItems[0].label;
                                    },
                                    label: function(tooltipItem) {
                                        const index = tooltipItem.dataIndex;
                                        const margin = margins[index];
                                        return [
                                            `Margin: ${margin.margin}`,
                                            `Profits: ${margin.profitAmount}`,
                                            `Expenses: ${margin.expenseAmount}`
                                        ];
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                title: {
                                    display: true,
                                    text: 'Date',
                                },
                                ticks: {
                                    callback: function (value, index, values) {
                                        // Display dates in 'YYYY-MM-DD' format on the x-axis
                                        return this.getLabelForValue(value);
                                    }
                                }
                            },
                            y: {
                                title: {
                                    display: true,
                                    text: 'Margin Amount',
                                },
                            },
                        },
                    }
                });
            }

            // Function to calculate margins (profits - expenses) with details for tooltips
            function calculateMargins(expenses, profits) {
                const dates = [...new Set([...expenses.map(e => e.date), ...profits.map(p => p.date)])].sort();

                return dates.map(date => {
                    const expense = expenses.filter(e => e.date === date).reduce((sum, e) => sum + e.amount, 0);
                    const profit = profits.filter(p => p.date === date).reduce((sum, p) => sum + p.amount, 0);
                    return {
                        date,
                        margin: profit - expense,
                        profitAmount: profit,
                        expenseAmount: expense
                    };
                });
            }

            // Function to group expenses by date and sum them
            function groupExpensesByDate(expenses) {
                const grouped = {};
                expenses.forEach(expense => {
                    if (!grouped[expense.date]) {
                        grouped[expense.date] = 0;
                    }
                    grouped[expense.date] += expense.amount;
                });
                return Object.entries(grouped).map(([date, amount]) => ({ date, amount }));
            }

            // Function to fetch financial data from the server
            async function fetchFinanceData(startDate, endDate) {
                const url = `{{ route('projects.finance.report', [$currentWorkspace->slug, $project->id]) }}?start_date=${startDate}&end_date=${endDate}`;
                const response = await fetch(url);
                const data = await response.json();

                // Group expenses and calculate margins
                const groupedExpenses = groupExpensesByDate(data.expenses);
                const margins = calculateMargins(groupedExpenses, data.profits);

                // Initialize the chart with the calculated margins
                initChart(margins);
            }

            // Calculate the default date range for the last year
            function getDefaultDateRange() {
                const endDate = new Date();
                const startDate = new Date();
                startDate.setFullYear(startDate.getFullYear() - 1);
                return {
                    startDate: startDate.toISOString().split('T')[0],
                    endDate: endDate.toISOString().split('T')[0],
                };
            }

            function setDefaultDates() {
                const { startDate, endDate } = getDefaultDateRange();
                document.getElementById('start_date').value = startDate;
                document.getElementById('end_date').value = endDate;
                return { startDate, endDate };
            }

            // Function to set filter dates for predefined ranges
            function setFilterDates(type) {
                const now = new Date();
                let startDate, endDate;

                if (type === 'previous-week') {
                    const firstDay = new Date(now.setDate(now.getDate() - now.getDay() - 7));
                    const lastDay = new Date(now.setDate(firstDay.getDate() + 6));
                    startDate = firstDay.toISOString().split('T')[0];
                    endDate = lastDay.toISOString().split('T')[0];
                } else if (type === 'current-week') {
                    const firstDay = new Date(now.setDate(now.getDate() - now.getDay()));
                    const lastDay = new Date(now.setDate(firstDay.getDate() + 6));
                    startDate = firstDay.toISOString().split('T')[0];
                    endDate = lastDay.toISOString().split('T')[0];
                } else if (type === 'previous-month') {
                    const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                    const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
                    startDate = firstDay.toISOString().split('T')[0];
                    endDate = lastDay.toISOString().split('T')[0];
                } else if (type === 'current-month') {
                    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
                    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
                    startDate = firstDay.toISOString().split('T')[0];
                    endDate = lastDay.toISOString().split('T')[0];
                }

                document.getElementById('start_date').value = startDate;
                document.getElementById('end_date').value = endDate;
            }

            // Event listeners for filter buttons
            document.getElementById('previous-week').addEventListener('click', () => setFilterDates('previous-week'));
            document.getElementById('current-week').addEventListener('click', () => setFilterDates('current-week'));
            document.getElementById('previous-month').addEventListener('click', () => setFilterDates('previous-month'));
            document.getElementById('current-month').addEventListener('click', () => setFilterDates('current-month'));
            document.getElementById('apply-filters').addEventListener('click', () => {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                fetchFinanceData(startDate, endDate);
                const filtersSection = document.getElementById('filters-section');
                filtersSection.style.display = filtersSection.style.display === 'none' ? 'block' : 'none';
            });

            // Load data for the last year by default
            const { startDate, endDate } = setDefaultDates();
            fetchFinanceData(startDate, endDate);
        });
    </script>
@endpush
