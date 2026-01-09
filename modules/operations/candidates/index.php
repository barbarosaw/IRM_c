<?php
/**
 * Operations Module - Candidates List
 * Modern, user-friendly candidate management UI with AJAX filtering and sorting
 */

// Prevent direct access
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);

require_once '../../../includes/init.php';

// Permission check
if (!has_permission('operations-candidates-access')) {
    header('Location: ../../../access-denied.php');
    exit;
}

// Fetch categories for filter dropdown
$cat_stmt = $db->query('SELECT id, name FROM categories ORDER BY name');
$categories = $cat_stmt->fetchAll();

// Fetch distinct statuses for filter dropdowns
$internal_statuses = $db->query("SELECT DISTINCT internal_status FROM candidates WHERE internal_status IS NOT NULL AND internal_status != '' ORDER BY internal_status")->fetchAll(PDO::FETCH_COLUMN);
$external_statuses = $db->query("SELECT DISTINCT external_status FROM candidates WHERE external_status IS NOT NULL AND external_status != '' ORDER BY external_status")->fetchAll(PDO::FETCH_COLUMN);

$page_title = 'Candidates';
$root_path = '../../../';

include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<style>
    .badge.bg-purple {
        color: #fff;
        background-color: #6f42c1;
    }
    .badge.bg-orange {
        color: #fff;
        background-color: #fd7e14;
    }
    .sortable-header {
        cursor: pointer;
        position: relative;
    }
    .sortable-header .sort-icon {
        position: absolute;
        right: 5px;
        top: 50%;
        transform: translateY(-50%);
        opacity: 0.5;
    }
    .sortable-header.active .sort-icon {
        opacity: 1;
    }
    .detail-row {
        background-color: #f8f9fa;
    }
    .detail-row .card {
        box-shadow: none;
    }
</style>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary"><i class="fas fa-users me-2"></i>Candidates</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <?php if (has_permission('operations-candidates-manage')): ?>
                        <a href="add.php" class="btn btn-success"><i class="fas fa-plus me-1"></i> Add Candidate</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <!-- Filters Card -->
        <div class="card card-outline card-primary mb-4">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-filter me-1"></i> Filters</h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <input type="text" class="form-control" id="filter-keyword" placeholder="Search by name, email, profile...">
                    </div>
                    <div class="col-md-2">
                        <select id="filter-internal-status" class="form-select">
                            <option value="">All Internal Statuses</option>
                            <?php foreach ($internal_statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="filter-external-status" class="form-select">
                            <option value="">All External Statuses</option>
                            <?php foreach ($external_statuses as $status): ?>
                                <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select id="filter-category" class="form-select">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex justify-content-end">
                        <button id="reset-filters" class="btn btn-secondary">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Candidates Table Card -->
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="candidates-table">
                        <thead class="table-light">
                            <tr>
                                <th></th>
                                <th class="sortable-header active" data-sort="name" data-dir="ASC">Name <i class="fas fa-sort-alpha-up sort-icon"></i></th>
                                <th class="sortable-header" data-sort="email">Email <i class="fas fa-sort sort-icon"></i></th>
                                <th>Categories</th>
                                <th class="sortable-header" data-sort="internal_status">Internal Status <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable-header" data-sort="external_status">External Status <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable-header" data-sort="done">Done <i class="fas fa-sort sort-icon"></i></th>
                                <th class="sortable-header" data-sort="created_at">Created <i class="fas fa-sort sort-icon"></i></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="candidates-tbody">
                            <!-- AJAX content will be loaded here -->
                        </tbody>
                    </table>
                </div>
                <div id="loading-spinner" class="text-center my-4" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
                <div id="no-results" class="text-center my-4" style="display: none;">
                    <p class="text-muted">No candidates found matching your criteria.</p>
                </div>
                <!-- Pagination -->
                <nav id="pagination-nav" aria-label="Page navigation"></nav>
            </div>
        </div>
    </div>
</div>
<?php include $root_path . 'components/footer.php'; ?>
<script>
$(document).ready(function() {
    let currentPage = 1;
    let sortBy = 'name';
    let sortDir = 'ASC';

    function fetchCandidates() {
        $('#loading-spinner').show();
        $('#candidates-tbody').hide();
        $('#no-results').hide();

        $.ajax({
            url: 'candidates_list_ajax.php',
            type: 'POST',
            data: {
                page: currentPage,
                keyword: $('#filter-keyword').val(),
                internal_status: $('#filter-internal-status').val(),
                external_status: $('#filter-external-status').val(),
                category_id: $('#filter-category').val(),
                sort_by: sortBy,
                sort_dir: sortDir
            },
            dataType: 'json',
            success: function(response) {
                $('#loading-spinner').hide();
                $('#candidates-tbody').empty().show();

                if (response.candidates && response.candidates.length > 0) {
                    response.candidates.forEach(c => {
                        let categoriesHtml = '';
                        if(c.categories) {
                            categoriesHtml = c.categories.split(',').map(cat => `<span class="badge bg-info me-1">${escapeHtml(cat)}</span>`).join(' ');
                        }

                        let internalStatusBadge = `<span class="badge ${getStatusBadgeClass(c.internal_status)}">${escapeHtml(c.internal_status || 'N/A')}</span>`;
                        let externalStatusBadge = `<span class="badge ${getStatusBadgeClass(c.external_status)}">${escapeHtml(c.external_status || 'N/A')}</span>`;

                        let doneBadge = c.done == 1 ? `<span class="badge bg-success">Yes</span>` : `<span class="badge bg-secondary">No</span>`;
                        let createdDate = new Date(c.created_at).toLocaleDateString('en-US', { year: 'numeric', month: '2-digit', day: '2-digit' });

                        let actionsHtml = `
                            <a href="view.php?id=${c.id}" class="btn btn-sm btn-info" title="View"><i class="fas fa-eye"></i></a>
                            <?php if (has_permission('operations-candidates-manage')): ?>
                                <a href="edit.php?id=${c.id}" class="btn btn-sm btn-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <a href="delete.php?id=${c.id}" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                            <?php endif; ?>`;

                        let row = `
                            <tr data-id="${c.id}">
                                <td><i class="fas fa-plus-circle text-primary toggle-details" style="cursor:pointer;"></i></td>
                                <td>${escapeHtml(c.name)}</td>
                                <td>${escapeHtml(c.email)}</td>
                                <td>${categoriesHtml}</td>
                                <td>${internalStatusBadge}</td>
                                <td>${externalStatusBadge}</td>
                                <td>${doneBadge}</td>
                                <td>${createdDate}</td>
                                <td>${actionsHtml}</td>
                            </tr>`;
                        $('#candidates-tbody').append(row);
                    });
                } else {
                    $('#no-results').show();
                }

                renderPagination(response.pagination);
            },
            error: function() {
                $('#loading-spinner').hide();
                $('#candidates-tbody').show();
                $('#candidates-tbody').append('<tr><td colspan="9" class="text-center text-danger">Error loading data. Please try again.</td></tr>');
            }
        });
    }

    function renderPagination(p) {
        let nav = $('#pagination-nav');
        nav.empty();
        if (p.total_pages <= 1) return;

        let ul = $('<ul class="pagination justify-content-center"></ul>');

        // Previous
        ul.append(`<li class="page-item ${p.page === 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${p.page - 1}">Previous</a></li>`);

        // Page numbers
        for (let i = 1; i <= p.total_pages; i++) {
            ul.append(`<li class="page-item ${p.page === i ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`);
        }

        // Next
        ul.append(`<li class="page-item ${p.page === p.total_pages ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${p.page + 1}">Next</a></li>`);

        nav.append(ul);
    }

    // Event Handlers
    $('#filter-keyword, #filter-internal-status, #filter-external-status, #filter-category').on('change keyup', function() {
        currentPage = 1;
        fetchCandidates();
    });

    $('#reset-filters').on('click', function() {
        $('#filter-keyword').val('');
        $('#filter-internal-status').val('');
        $('#filter-external-status').val('');
        $('#filter-category').val('');
        currentPage = 1;
        fetchCandidates();
    });

    $('.sortable-header').on('click', function() {
        let newSortBy = $(this).data('sort');
        if (sortBy === newSortBy) {
            sortDir = sortDir === 'ASC' ? 'DESC' : 'ASC';
        } else {
            sortBy = newSortBy;
            sortDir = 'ASC';
        }

        $('.sortable-header').removeClass('active');
        $(this).addClass('active');

        $('.sort-icon').removeClass('fa-sort-alpha-up fa-sort-alpha-down').addClass('fa-sort');
        if (sortDir === 'ASC') {
            $(this).find('.sort-icon').removeClass('fa-sort').addClass('fa-sort-alpha-up');
        } else {
            $(this).find('.sort-icon').removeClass('fa-sort').addClass('fa-sort-alpha-down');
        }

        fetchCandidates();
    });

    $(document).on('click', '.page-link', function(e) {
        e.preventDefault();
        let page = $(this).data('page');
        if (page) {
            currentPage = page;
            fetchCandidates();
        }
    });

    $(document).on('click', '.toggle-details', function() {
        let icon = $(this);
        let tr = icon.closest('tr');
        let candidateId = tr.data('id');
        let detailRow = tr.next('.detail-row');

        if (detailRow.length) {
            detailRow.toggle();
        } else {
            $.ajax({
                url: 'candidates_list_ajax.php',
                type: 'POST',
                data: { id: candidateId },
                dataType: 'json',
                success: function(response) {
                    let candidate = response.candidates[0];
                    let detailHtml = `
                        <div class="p-3 bg-light" style="border-left: 3px solid #0d6efd;">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong><i class="fas fa-id-card-alt me-1 text-primary"></i> Profile</strong>
                                    <p class="text-muted small mt-1">${nl2br(escapeHtml(candidate.profile || 'N/A'))}</p>
                                </div>
                                <div class="col-md-6">
                                    <strong><i class="fas fa-sticky-note me-1 text-primary"></i> Notes</strong>
                                    <p class="text-muted small mt-1">${nl2br(escapeHtml(candidate.notes || 'N/A'))}</p>
                                    <hr>
                                    <strong><i class="fas fa-comments me-1 text-primary"></i> Comments</strong>
                                    <p class="text-muted small mt-1">${nl2br(escapeHtml(candidate.comments || 'N/A'))}</p>
                                </div>
                            </div>
                        </div>`;
                    tr.after(`<tr class="detail-row"><td colspan="9">${detailHtml}</td></tr>`);
                }
            });
        }
        icon.toggleClass('fa-plus-circle fa-minus-circle');
    });

    function getStatusBadgeClass(status) {
        if (!status) return 'bg-secondary';
        switch (status) {
            // Internal Statuses
            case 'Active':
                return 'bg-primary';
            case 'Screened':
                return 'bg-info';
            case 'Shortlisted':
                return 'bg-purple';
            case 'Interviewed':
                return 'bg-warning text-dark';
            case 'Rejected':
                return 'bg-danger';

            // External Statuses
            case 'Available':
                return 'bg-success';
            case 'Not Available':
                return 'bg-secondary';
            case 'Offer Pending':
                return 'bg-orange';
            case 'Hired':
                return 'bg-success';

            default:
                return 'bg-secondary';
        }
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return str.replace(/[&<>'"/]/g, function (s) {
            return {
                '&': '&amp;', '<': '&lt;', '>': '&gt;',
                '"': '&quot;', "'": '&#39;', '/': '&#x2F;'
            }[s];
        });
    }
    function nl2br(str) {
        return str.replace(/\r\n|\n\r|\r|\n/g, '<br>');
    }

    // Initial fetch
    fetchCandidates();
});
</script>
