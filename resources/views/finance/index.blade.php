@extends('layouts.landing')

@section('body-class', 'tpl-finance')
@section('title', 'Finance')

@section('content')
<style>
    #filters-section {
        margin-bottom: 20px;
    }

    #filters-section .row {
        margin-bottom: 15px;
    }

    #filters-section label {
        font-weight: bold;
        margin-bottom: 5px;
        display: inline-block;
    }

    #filters-section input[type="date"] {
        height: 40px;
        border-radius: 5px;
    }

    .dropdown .btn {
        width: 100%;
        text-align: left;
    }

    .dropdown ul {
        width: 100%;
    }

    #apply-filters {
        margin-top: 30px;
        height: 40px;
    }
</style>
<main>
    <section class="topTitle">
        <div class="container-master">
            <h1 class="titlePage"><span>Finances</span></h1>
        </div>

    </section>
    <section class="section">
        <div class="row">

            <button id="toggle-filters" class="btn btn-sm btn-primary mx-1" title="{{ __('Toggle Filters') }}">
                <i class="ti ti-filter"></i> Toggle Filters
            </button>
            <div id="filters-section" style="display: none; margin-bottom: 20px;">
                <div class="row mb-3 align-items-center">
                    <div class="col-md-4">
                        <label for="project_filter">{{ __('Projects') }}</label>
                        <div class="dropdown">
                            <button class="btn btn-secondary dropdown-toggle skills-button" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                Select Projects
                            </button>
                            <ul class="dropdown-menu skills-dropdown-menu">
                                <li>
                                    <input class="form-check-input select-all-projects" type="checkbox" id="select_all_projects">
                                    <label class="form-check-label" for="select_all_projects">
                                        {{ __('Select All') }}
                                    </label>
                                </li>
                                @foreach($projects as $project)
                                    <li>
                                        <input class="form-check-input project-checkbox" type="checkbox" name="project_ids[]" id="project_{{ $project->id }}" value="{{ $project->id }}">
                                        <label class="form-check-label" for="project_{{ $project->id }}">
                                            {{ $project->name }}
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="start_date">{{ __('Start Date') }}</label>
                        <input type="date" id="start_date" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date">{{ __('End Date') }}</label>
                        <input type="date" id="end_date" class="form-control">
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="apply-filters" class="btn btn-primary w-100">{{ __('Apply Filters') }}</button>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-4 d-flex justify-content-between">
                    </div>

                    <div class="col-md-6 d-flex justify-content-between">
                        <button id="previous-week" class="btn btn-sm btn-primary">{{ __('Previous Week') }}</button>
                        <button id="current-week" class="btn btn-sm btn-primary">{{ __('Current Week') }}</button>
                        <button id="previous-month" class="btn btn-sm btn-primary">{{ __('Previous Month') }}</button>
                        <button id="current-month" class="btn btn-sm btn-primary">{{ __('Current Month') }}</button>
                    </div>
                    <div class="col-md-2 d-flex justify-content-between">
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <canvas id="finance-chart"></canvas>
                <div id="no-data" style="display: none; text-align: center; margin-top: 20px;">
                    <p>{{ __('No data available for the selected period') }}</p>
                </div>
            </div>
        </div>
    </section>
</main>
@endsection
@section('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const ctx = document.getElementById('finance-chart').getContext('2d');
            let chart;

            // Toggle Filters Section
            document.getElementById('toggle-filters').addEventListener('click', function () {
                const filtersSection = document.getElementById('filters-section');
                filtersSection.style.display = filtersSection.style.display === 'none' ? 'block' : 'none';
            });

            // Initialize Chart
            function initChart(margins) {
                const labels = margins.map(m => m.date);
                const expenseData = margins.map(m => m.expenseAmount);
                const profitData = margins.map(m => m.profitAmount);
                const data = margins.map(m => m.margin);

                if (labels.length === 0) {
                    document.getElementById('finance-chart').style.display = 'none';
                    document.getElementById('no-data').style.display = 'block';
                    return;
                }

                document.getElementById('finance-chart').style.display = 'block';
                document.getElementById('no-data').style.display = 'none';

                if (chart) {
                    chart.destroy();
                }

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
                                    // Tooltip Title
                                    title: function(tooltipItems) {
                                        return `Date: ${tooltipItems[0].label}`;
                                    },
                                    // Tooltip Label (Detail)
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

            // Filter Data By Selected Projects
            function filterByProjects(data, selectedProjects) {
                const expenses = data.expenses.filter(expense => selectedProjects.includes(expense.project_id));
                const profits = data.profits.filter(profit => selectedProjects.includes(profit.project_id));

                const dates = [...new Set([...expenses.map(e => e.date), ...profits.map(p => p.date)])].sort();

                const margins = dates.map(date => {
                    const expense = expenses.filter(e => e.date === date).reduce((sum, e) => sum + e.amount, 0);
                    const profit = profits.filter(p => p.date === date).reduce((sum, p) => sum + p.amount, 0);
                    return {
                        date,
                        margin: profit - expense,
                        expenseAmount: expense,
                        profitAmount: profit
                    };
                });

                return margins;
            }

            // Calculate Dates for Filters
            function calculateDateRange(type) {
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

                return { startDate, endDate };
            }

            // Fetch Data From Server
            async function fetchFinanceData(startDate, endDate, selectedProjects) {
                const url = `{{ route('finances.report') }}?start_date=${startDate}&end_date=${endDate}`;
                const response = await fetch(url);
                const data = await response.json();

                const margins = selectedProjects.length
                    ? filterByProjects(data, selectedProjects)
                    : filterByProjects(data, [...new Set(data.expenses.map(e => e.project_id).concat(data.profits.map(p => p.project_id)))]);

                initChart(margins);
            }

            // Initialize Date Inputs
            const today = new Date().toISOString().split('T')[0];
            const lastYear = new Date();
            lastYear.setFullYear(lastYear.getFullYear() - 1);
            const lastYearDate = lastYear.toISOString().split('T')[0];

            document.getElementById('start_date').value = lastYearDate;
            document.getElementById('end_date').value = today;

            // Checkbox Logic For Project Selection
            const selectAllCheckbox = document.getElementById('select_all_projects');
            const projectCheckboxes = document.querySelectorAll('.project-checkbox');

            // Toggle All Projects
            selectAllCheckbox.addEventListener('change', function () {
                const isChecked = this.checked;
                projectCheckboxes.forEach(checkbox => {
                    checkbox.checked = isChecked;
                });
            });

            // Update "Select All" State
            projectCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function () {
                    const allChecked = Array.from(projectCheckboxes).every(cb => cb.checked);
                    const noneChecked = Array.from(projectCheckboxes).every(cb => !cb.checked);

                    if (allChecked) {
                        selectAllCheckbox.checked = true;
                        selectAllCheckbox.indeterminate = false;
                    } else if (noneChecked) {
                        selectAllCheckbox.checked = false;
                        selectAllCheckbox.indeterminate = false;
                    } else {
                        selectAllCheckbox.indeterminate = true;
                    }
                });
            });

            // Event Listeners for Filter Buttons
            document.getElementById('previous-week').addEventListener('click', function () {
                const { startDate, endDate } = calculateDateRange('previous-week');
                document.getElementById('start_date').value = startDate;
                document.getElementById('end_date').value = endDate;
            });

            document.getElementById('current-week').addEventListener('click', function () {
                const { startDate, endDate } = calculateDateRange('current-week');
                document.getElementById('start_date').value = startDate;
                document.getElementById('end_date').value = endDate;
            });

            document.getElementById('previous-month').addEventListener('click', function () {
                const { startDate, endDate } = calculateDateRange('previous-month');
                document.getElementById('start_date').value = startDate;
                document.getElementById('end_date').value = endDate;
            });

            document.getElementById('current-month').addEventListener('click', function () {
                const { startDate, endDate } = calculateDateRange('current-month');
                document.getElementById('start_date').value = startDate;
                document.getElementById('end_date').value = endDate;
            });

            // Apply Filters
            document.getElementById('apply-filters').addEventListener('click', function () {
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                const selectedProjects = Array.from(projectCheckboxes)
                    .filter(checkbox => checkbox.checked)
                    .map(checkbox => parseInt(checkbox.value));
                fetchFinanceData(startDate, endDate, selectedProjects);
            });

            // Load Default Data For All Projects
            fetchFinanceData(lastYearDate, today, []);
        });
    </script>
@endsection
