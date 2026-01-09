<div class="content-wrapper">
    <section class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1><i class="fas fa-file-invoice-dollar mr-2"></i><?= $page_title ?></h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="/modules/timeworks/">TimeWorks</a></li>
                        <li class="breadcrumb-item active">Billing</li>
                    </ol>
                </div>
            </div>
        </div>
    </section>

    <section class="content">
        <div class="container-fluid">
            <!-- Filter Card -->
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-filter mr-2"></i>Filters</h3>
                </div>
                <div class="card-body">
                    <form id="filterForm" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Client</label>
                            <select name="client_id" id="clientFilter" class="form-select select2" multiple>
                                <?php foreach ($clients as $client): ?>
                                <option value="<?= $client['id'] ?>"><?= htmlspecialchars($client['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Employee</label>
                            <select name="user_id" id="employeeFilter" class="form-select select2">
                                <option value="">All Employees</option>
                                <?php foreach ($employees as $emp): ?>
                                <option value="<?= htmlspecialchars($emp['user_id']) ?>">
                                    <?= htmlspecialchars($emp['full_name']) ?>
                                    <?php if ($emp['current_bill_rate']): ?>
                                        ($<?= number_format($emp['current_bill_rate'], 2) ?>/hr)
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Time Period</label>
                            <select id="periodFilter" class="form-select">
                                <option value="this_month">This Month to Date</option>
                                <option value="last_15">Last 15 Days</option>
                                <option value="previous_15">Previous 15 Days</option>
                                <option value="earlier_15">Earlier 15 Days</option>
                                <option value="last_month">Last Month</option>
                                <option value="custom">Custom Range</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search mr-1"></i> Apply
                                </button>
                                <button type="button" id="btnExportExcel" class="btn btn-success">
                                    <i class="fas fa-file-excel mr-1"></i> Export
                                </button>
                                <?php if (has_permission('timeworks_billing_manage')): ?>
                                <button type="button" id="btnSync" class="btn btn-info">
                                    <i class="fas fa-sync mr-1"></i> Sync
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Custom Date Range (Hidden by default) -->
                        <div class="col-md-6" id="customDateRange" style="display: none;">
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" name="start_date" id="startDate" class="form-control">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">End Date</label>
                                    <input type="date" name="end_date" id="endDate" class="form-control">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Main Content Row -->
            <div class="row">
                <!-- Data Table Column -->
                <div class="col-lg-9">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-users mr-2"></i>Employee Summary
                                <span id="userCount" class="badge bg-secondary ml-2">0 employees</span>
                                <span id="entryCount" class="badge bg-info ml-1">0 entries</span>
                            </h3>
                            <div class="card-tools">
                                <button type="button" id="btnExpandAll" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-expand-alt"></i> Expand All
                                </button>
                                <button type="button" id="btnCollapseAll" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-compress-alt"></i> Collapse All
                                </button>
                            </div>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table id="billingTable" class="table table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Employee</th>
                                        <th class="text-center">Entries</th>
                                        <th class="text-right">Hours</th>
                                        <th class="text-right">Bill Rate</th>
                                        <th class="text-right">Pay Rate</th>
                                        <th class="text-right">Bill Amount</th>
                                        <th class="text-right">Pay Amount</th>
                                        <th class="text-right">Profit</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody">
                                    <tr>
                                        <td colspan="9" class="text-center py-4">
                                            <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                            <p class="text-muted mt-2">Loading data...</p>
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="table-secondary">
                                    <tr>
                                        <th colspan="3" class="text-right">TOTALS:</th>
                                        <th class="text-right" id="footerHours">0.00</th>
                                        <th class="text-right">-</th>
                                        <th class="text-right">-</th>
                                        <th class="text-right text-success" id="footerBillAmount">$0.00</th>
                                        <th class="text-right" id="footerPayAmount">$0.00</th>
                                        <th class="text-right text-primary" id="footerProfit">$0.00</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Summary Panel Column -->
                <div class="col-lg-3">
                    <div class="card card-outline card-success sticky-top" style="top: 70px;">
                        <div class="card-header">
                            <h3 class="card-title"><i class="fas fa-chart-pie mr-2"></i>Period Summary</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="text-muted small">Date Range</label>
                                <p class="mb-0 font-weight-bold" id="summaryDateRange">-</p>
                            </div>
                            <hr>
                            <div class="mb-3">
                                <label class="text-muted small">Total Employees</label>
                                <p class="h5 mb-0" id="summaryUsers">0</p>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small">Total Hours</label>
                                <p class="h4 mb-0" id="summaryHours">0.00</p>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small">Total Bill Amount</label>
                                <p class="h4 mb-0 text-success" id="summaryBillAmount">$0.00</p>
                            </div>
                            <div class="mb-3">
                                <label class="text-muted small">Total Pay Amount</label>
                                <p class="h4 mb-0" id="summaryPayAmount">$0.00</p>
                            </div>
                            <hr>
                            <div class="mb-0">
                                <label class="text-muted small">Total Profit</label>
                                <p class="h3 mb-0 text-primary" id="summaryProfit">$0.00</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<style>
.table td, .table th {
    vertical-align: middle;
}
.text-success {
    color: #28a745 !important;
}
.text-primary {
    color: #007bff !important;
}
.sticky-top {
    z-index: 100;
}
.user-row {
    cursor: pointer;
    background-color: #f8f9fa;
}
.user-row:hover {
    background-color: #e9ecef;
}
.user-row td {
    font-weight: 600;
}
.detail-row {
    display: none;
}
.detail-row.show {
    display: table-row;
}
.detail-row td {
    padding: 0 !important;
    background-color: #fff;
}
.detail-table {
    margin: 0;
    border: none;
}
.detail-table thead {
    background-color: #e9ecef;
}
.detail-table th, .detail-table td {
    font-size: 0.85rem;
    padding: 0.5rem !important;
}
.expand-icon {
    transition: transform 0.2s;
}
.user-row.expanded .expand-icon {
    transform: rotate(90deg);
}
.badge-entries {
    min-width: 50px;
}
</style>

<script>
$(document).ready(function() {
    // Initialize Select2
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'All Clients',
        allowClear: true
    });

    // Period filter change
    $('#periodFilter').on('change', function() {
        if ($(this).val() === 'custom') {
            $('#customDateRange').show();
        } else {
            $('#customDateRange').hide();
            loadData();
        }
    });

    // Form submit
    $('#filterForm').on('submit', function(e) {
        e.preventDefault();

        // Validate custom date range
        if ($('#periodFilter').val() === 'custom') {
            const startDate = new Date($('#startDate').val());
            const endDate = new Date($('#endDate').val());

            if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
                toastr.error('Please select both start and end dates');
                return;
            }

            if (endDate < startDate) {
                toastr.error('End date must be after start date');
                return;
            }
        }

        loadData();
    });

    // Export Excel
    $('#btnExportExcel').on('click', function() {
        const params = new URLSearchParams(getFilterParams());
        params.append('action', 'export_excel');
        window.location.href = '/modules/timeworks/api/billing.php?' + params.toString();
    });

    // Sync button
    $('#btnSync').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Syncing...');

        $.ajax({
            url: '/modules/timeworks/api/billing.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ action: 'sync_now' }),
            success: function(response) {
                if (response.success) {
                    toastr.success('Sync completed: ' + response.stats.entries_synced + ' entries synced');
                    loadData();
                } else {
                    toastr.error(response.message || 'Sync failed');
                }
            },
            error: function() {
                toastr.error('Sync request failed');
            },
            complete: function() {
                btn.prop('disabled', false).html('<i class="fas fa-sync mr-1"></i> Sync');
            }
        });
    });

    // Expand/Collapse All
    $('#btnExpandAll').on('click', function() {
        $('.user-row').addClass('expanded');
        $('.detail-row').addClass('show');
    });

    $('#btnCollapseAll').on('click', function() {
        $('.user-row').removeClass('expanded');
        $('.detail-row').removeClass('show');
    });

    // Get filter parameters
    function getFilterParams() {
        const params = {
            period: $('#periodFilter').val()
        };

        const clientIds = $('#clientFilter').val();
        if (clientIds && clientIds.length > 0) {
            params.client_id = clientIds;
        }

        const userId = $('#employeeFilter').val();
        if (userId) {
            params.user_id = userId;
        }

        if (params.period === 'custom') {
            params.start_date = $('#startDate').val();
            params.end_date = $('#endDate').val();
        }

        return params;
    }

    // Load data
    function loadData() {
        const params = getFilterParams();
        params.action = 'get_entries_grouped';

        $('#tableBody').html(`
            <tr>
                <td colspan="9" class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                    <p class="text-muted mt-2">Loading data...</p>
                </td>
            </tr>
        `);

        $.ajax({
            url: '/modules/timeworks/api/billing.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(params),
            success: function(response) {
                if (response.success) {
                    renderTable(response.users);
                    updateSummary(response.totals, response.date_range, response.user_count);
                } else {
                    $('#tableBody').html(`
                        <tr>
                            <td colspan="9" class="text-center py-4 text-danger">
                                <i class="fas fa-exclamation-triangle mr-2"></i>${response.message || 'Error loading data'}
                            </td>
                        </tr>
                    `);
                }
            },
            error: function() {
                $('#tableBody').html(`
                    <tr>
                        <td colspan="9" class="text-center py-4 text-danger">
                            <i class="fas fa-exclamation-triangle mr-2"></i>Failed to load data
                        </td>
                    </tr>
                `);
            }
        });
    }

    // Store users data for lazy loading
    let usersData = [];

    // Render table with grouped users
    function renderTable(users) {
        usersData = users; // Store for lazy loading

        if (!users || users.length === 0) {
            $('#tableBody').html(`
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p>No entries found for the selected filters</p>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        let totalEntries = 0;

        users.forEach((user, index) => {
            totalEntries += user.entry_count;

            // User summary row
            html += `
                <tr class="user-row" data-user-index="${index}" data-user-id="${user.user_id}" data-loaded="false">
                    <td class="text-center">
                        <i class="fas fa-chevron-right expand-icon text-muted"></i>
                    </td>
                    <td>
                        <strong>${escapeHtml(user.employee_name)}</strong>
                        <br><small class="text-muted">${escapeHtml(user.employee_email)}</small>
                    </td>
                    <td class="text-center">
                        <span class="badge bg-info badge-entries">${user.entry_count}</span>
                    </td>
                    <td class="text-right">${user.total_hours_formatted}</td>
                    <td class="text-right">${user.current_bill_rate_formatted}</td>
                    <td class="text-right">${user.current_pay_rate_formatted}</td>
                    <td class="text-right text-success">${user.total_bill_amount_formatted}</td>
                    <td class="text-right">${user.total_pay_amount_formatted}</td>
                    <td class="text-right text-primary">${user.total_profit_formatted}</td>
                </tr>
            `;

            // Detail row - entries loaded on demand
            html += `
                <tr class="detail-row" data-user-index="${index}">
                    <td colspan="9">
                        <div class="entries-container" id="entries-${index}">
                            <div class="text-center py-3 loading-entries">
                                <i class="fas fa-spinner fa-spin"></i> Loading entries...
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        });

        $('#tableBody').html(html);
        $('#userCount').text(users.length + ' employees');
        $('#entryCount').text(totalEntries + ' entries');

        // Bind click events to user rows - lazy load entries
        $('.user-row').on('click', function() {
            const $row = $(this);
            const index = $row.data('user-index');
            const userId = $row.data('user-id');
            const loaded = $row.data('loaded');

            $row.toggleClass('expanded');
            $(`.detail-row[data-user-index="${index}"]`).toggleClass('show');

            // Load entries on first expand
            if (!loaded && $row.hasClass('expanded')) {
                loadUserEntries(index, userId);
                $row.data('loaded', true);
            }
        });
    }

    // Load entries for a specific user (lazy loading)
    function loadUserEntries(index, userId) {
        const params = getFilterParams();
        params.action = 'get_user_entries';
        params.user_id = userId;

        $.ajax({
            url: '/modules/timeworks/api/billing.php',
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(params),
            success: function(response) {
                if (response.success) {
                    renderUserEntries(index, response.entries);
                } else {
                    $(`#entries-${index}`).html(`<div class="text-danger text-center py-2">${response.message || 'Failed to load entries'}</div>`);
                }
            },
            error: function() {
                $(`#entries-${index}`).html('<div class="text-danger text-center py-2">Error loading entries</div>');
            }
        });
    }

    // Render entries for a user
    function renderUserEntries(index, entries) {
        if (!entries || entries.length === 0) {
            $(`#entries-${index}`).html('<div class="text-muted text-center py-2">No entries found</div>');
            return;
        }

        let html = `
            <table class="table table-sm detail-table mb-0">
                <thead>
                    <tr>
                        <th style="width: 100px;">Date</th>
                        <th>Client</th>
                        <th>Description</th>
                        <th class="text-right">Hours</th>
                        <th class="text-right">Bill Rate</th>
                        <th class="text-right">Pay Rate</th>
                        <th class="text-right">Bill Amt</th>
                        <th class="text-right">Pay Amt</th>
                        <th class="text-right">Profit</th>
                    </tr>
                </thead>
                <tbody>
        `;

        entries.forEach(entry => {
            html += `
                <tr>
                    <td>${entry.entry_date}</td>
                    <td>${escapeHtml(entry.client_name)}</td>
                    <td>${escapeHtml(entry.description)}</td>
                    <td class="text-right">${entry.hours.toFixed(2)}</td>
                    <td class="text-right">${entry.bill_rate_formatted}</td>
                    <td class="text-right">${entry.pay_rate_formatted}</td>
                    <td class="text-right text-success">${entry.bill_amount_formatted}</td>
                    <td class="text-right">${entry.pay_amount_formatted}</td>
                    <td class="text-right text-primary">${entry.profit_amount_formatted}</td>
                </tr>
            `;
        });

        html += '</tbody></table>';
        $(`#entries-${index}`).html(html);
    }

    // Update summary
    function updateSummary(totals, dateRange, userCount) {
        // Update summary panel
        $('#summaryDateRange').text(formatDateRange(dateRange.start, dateRange.end));
        $('#summaryUsers').text(userCount);
        $('#summaryHours').text(totals.total_hours_formatted);
        $('#summaryBillAmount').text(totals.total_bill_amount_formatted);
        $('#summaryPayAmount').text(totals.total_pay_amount_formatted);
        $('#summaryProfit').text(totals.total_profit_formatted);

        // Update footer
        $('#footerHours').text(totals.total_hours_formatted);
        $('#footerBillAmount').text(totals.total_bill_amount_formatted);
        $('#footerPayAmount').text(totals.total_pay_amount_formatted);
        $('#footerProfit').text(totals.total_profit_formatted);
    }

    // Format date range for display
    function formatDateRange(start, end) {
        const startDate = new Date(start + 'T00:00:00');
        const endDate = new Date(end + 'T00:00:00');
        const options = { month: 'short', day: 'numeric', year: 'numeric' };
        return startDate.toLocaleDateString('en-US', options) + ' - ' + endDate.toLocaleDateString('en-US', options);
    }

    // Escape HTML
    function escapeHtml(text) {
        if (!text) return '-';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize - load default data
    loadData();
});
</script>
