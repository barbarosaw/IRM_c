<?php
/**
 * Operations Module - Suggest Candidates for Job Request
 * Shows candidates matching job request categories and status
 */
if (!defined('AW_SYSTEM')) define('AW_SYSTEM', true);
require_once '../../../includes/init.php';

if (!has_permission('operations-jobrequests-manage')) {
    header('Location: ../../../access-denied.php');
    exit;
}

$job_request_id = (int)($_GET['id'] ?? 0);
if (!$job_request_id) {
    header('Location: index.php');
    exit;
}

// Get job request categories
$cat_stmt = $db->prepare('SELECT category_id FROM job_request_categories WHERE job_request_id = ?');
$cat_stmt->execute([$job_request_id]);
$category_ids = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get candidates matching categories and status
$status_filter = ['New', 'In Review', 'Research', 'Candidates Proposed'];
$in_cat = $category_ids ? implode(',', array_fill(0, count($category_ids), '?')) : 'NULL';
$sql = "SELECT c.*, GROUP_CONCAT(cat.name) as categories
        FROM candidates c
        LEFT JOIN candidate_categories cc ON c.id = cc.candidate_id
        LEFT JOIN categories cat ON cc.category_id = cat.id
        WHERE c.status IN (" . implode(',', array_fill(0, count($status_filter), '?')) . ")
        " . ($category_ids ? "AND cc.category_id IN ($in_cat)" : '') . "
        GROUP BY c.id
        ORDER BY c.created_at DESC";
$params = array_merge($status_filter, $category_ids);
$stmt = $db->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll();

// Fetch job request details for smart filtering
$job_stmt = $db->prepare('SELECT jr.*, GROUP_CONCAT(cat.name) as categories FROM job_requests jr
    LEFT JOIN job_request_categories jrc ON jr.id = jrc.job_request_id
    LEFT JOIN categories cat ON jrc.category_id = cat.id
    WHERE jr.id = ? GROUP BY jr.id');
$job_stmt->execute([$job_request_id]);
$job = $job_stmt->fetch();
$job_categories = $job && $job['categories'] ? explode(',', $job['categories']) : [];
$job_description = $job['job_description'] ?? '';

// Fetch all candidates (no filter)
$stmt = $db->query('SELECT c.*, GROUP_CONCAT(cat.name) as categories FROM candidates c
    LEFT JOIN candidate_categories cc ON c.id = cc.candidate_id
    LEFT JOIN categories cat ON cc.category_id = cat.id
    GROUP BY c.id ORDER BY c.created_at DESC');
$all_candidates = $stmt->fetchAll();

// System suggestion: Sadece job request kategorileriyle eşleşen en yüksek skorlu 5 aday
$suggested = [];
foreach ($all_candidates as $cand) {
    $score = 0;
    $cand_cats = $cand['categories'] ? explode(',', $cand['categories']) : [];
    $cat_matches = array_intersect(array_map('trim', $job_categories), array_map('trim', $cand_cats));
    if (count($cat_matches) === 0) continue; // Sadece istenen kategorilerden adaylar
    $score += count($cat_matches) * 2;
    if ($job_description && $cand['profile']) {
        $desc_words = array_filter(explode(' ', strtolower($job_description)));
        foreach ($desc_words as $word) {
            if (strlen($word) > 3 && stripos($cand['profile'], $word) !== false) $score++;
        }
    }
    $cand['suggestion_score'] = $score;
    $suggested[] = $cand;
}
usort($suggested, function($a, $b) { return $b['suggestion_score'] <=> $a['suggestion_score']; });
$suggested = array_slice($suggested, 0, 5);

$page_title = 'Suggest Candidates';
$root_path = '../../../';
include $root_path . 'components/header.php';
include $root_path . 'components/sidebar.php';
?>
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0 text-primary">Suggest Candidates</h1>
                </div>
                <div class="col-sm-6 text-end">
                    <a href="../job_requests/view.php?id=<?= $job_request_id ?>" class="btn btn-secondary">Back to Job Request</a>
                </div>
            </div>
        </div>
    </div>
    <div class="container-fluid">
        <div class="row">
            <!-- Job Request Summary -->
            <div class="container-fluid mb-3">
                <div class="card border-primary shadow-sm mb-3">
                    <div class="card-header bg-primary text-white"><i class="fas fa-briefcase me-2"></i>Job Request Summary</div>
                    <div class="card-body row g-3">
                        <div class="col-md-6">
                            <div><strong>Job Title:</strong> <?= htmlspecialchars($job['job_title'] ?? '-') ?></div>
                            <div><strong>Client:</strong> <?= htmlspecialchars($job['client_id'] ?? '-') ?></div>
                            <div><strong>Status:</strong> <?= htmlspecialchars($job['status'] ?? '-') ?></div>
                            <div><strong>Created At:</strong> <?= isset($job['created_at']) ? date('m/d/Y', strtotime($job['created_at'])) : '-' ?></div>
                        </div>
                        <div class="col-md-6">
                            <div><strong>Categories:</strong> <?= htmlspecialchars($job['categories'] ?? '-') ?></div>
                            <div><strong>Description:</strong> <div class="small text-dark border rounded bg-light p-2"><?= nl2br(htmlspecialchars($job['job_description'] ?? '-')) ?></div></div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Sistem önerisi ve basket alanı aynı kalacak -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-success text-white"><strong>System Suggested Candidates</strong></div>
                        <div class="card-body">
                            <?php if (count($suggested) > 0): ?>
                                <ul class="list-group">
                                <?php foreach ($suggested as $candidate): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <span>
                                            <strong><?= htmlspecialchars($candidate['name']) ?></strong> <br>
                                            <small><?= htmlspecialchars($candidate['email']) ?> | <?= htmlspecialchars($candidate['categories']) ?> | <?= htmlspecialchars($candidate['status']) ?></small>
                                        </span>
                                        <button type="button" class="btn btn-outline-primary btn-sm add-to-basket" data-id="<?= $candidate['id'] ?>">Add to Basket</button>
                                    </li>
                                <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="text-muted">No suggested candidates found.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <!-- Sepet ve Karşılaştırma Alanı -->
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header bg-info text-white"><strong>Candidate Basket & Compare</strong></div>
                        <div class="card-body" id="basket-area">
                            <div class="text-muted">No candidates in basket.</div>
                        </div>
                        <div class="card-footer text-end">
                            <button type="button" class="btn btn-danger btn-sm" id="clear-basket">Clear Basket</button>
                            <button type="button" class="btn btn-primary btn-sm" id="open-propose-modal-btn" disabled>Send to Manager</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Akıllı filtreler ve sıralama (All Candidates üstüne taşındı) -->
            <div class="card mb-2 mt-2">
                <div class="card-header"><strong>Filters</strong></div>
                <div class="card-body row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" id="filter-category">
                            <option value="">All</option>
                            <?php
                            $all_cats = [];
                            foreach ($all_candidates as $cand) {
                                foreach (explode(',', $cand['categories']) as $cat) {
                                    $cat = trim($cat);
                                    if ($cat && !in_array($cat, $all_cats)) $all_cats[] = $cat;
                                }
                            }
                            sort($all_cats);
                            foreach ($all_cats as $cat) {
                                echo '<option value="' . htmlspecialchars($cat) . '">' . htmlspecialchars($cat) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" id="filter-status">
                            <option value="">All</option>
                            <option value="New">New</option>
                            <option value="In Review">In Review</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                            <option value="Active">Active</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Internal Status</label>
                        <select class="form-select" id="filter-internal-status">
                            <option value="">All</option>
                            <option value="Screened">Screened</option>
                            <option value="Shortlisted">Shortlisted</option>
                            <option value="Interviewed">Interviewed</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">External Status</label>
                        <select class="form-select" id="filter-external-status">
                            <option value="">All</option>
                            <option value="Available">Available</option>
                            <option value="Not Available">Not Available</option>
                            <option value="Offer Pending">Offer Pending</option>
                            <option value="Hired">Hired</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">Done</label>
                        <select class="form-select" id="filter-done">
                            <option value="">All</option>
                            <option value="1">Yes</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" id="filter-order">
                            <option value="created_at_desc">Newest</option>
                            <option value="created_at_asc">Oldest</option>
                            <option value="name_asc">Name A-Z</option>
                            <option value="name_desc">Name Z-A</option>
                            <option value="status_asc">Status A-Z</option>
                            <option value="status_desc">Status Z-A</option>
                        </select>
                    </div>
                    <div class="col-md-12 mt-2">
                        <label class="form-label">Keyword</label>
                        <input type="text" class="form-control" id="filter-keyword" placeholder="Name, email, profile, notes...">
                    </div>
                </div>
            </div>
            <!-- All Candidates tablosu -->
            <div class="card mb-4">
                <div class="card-header"><strong>All Candidates</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle" id="candidates-table">
                            <thead class="table-light">
                                <tr>
                                    <th></th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Categories</th>
                                    <th>Status</th>
                                    <th>Internal Status</th>
                                    <th>External Status</th>
                                    <th>Done</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody id="candidates-tbody">
                            <?php foreach ($all_candidates as $candidate): ?>
                                <tr class="candidate-row" data-id="<?= $candidate['id'] ?>">
                                    <td><button type="button" class="btn btn-outline-primary btn-sm add-to-basket" data-id="<?= $candidate['id'] ?>">Add</button></td>
                                    <td><?= htmlspecialchars($candidate['name']) ?></td>
                                    <td><?= htmlspecialchars($candidate['email']) ?></td>
                                    <td><?= htmlspecialchars($candidate['categories']) ?></td>
                                    <td><?= htmlspecialchars($candidate['status']) ?></td>
                                    <td><?= htmlspecialchars($candidate['internal_status']) ?></td>
                                    <td><?= htmlspecialchars($candidate['external_status']) ?></td>
                                    <td><?= $candidate['done'] ? '<span class=\'badge bg-success\'>Yes</span>' : '<span class=\'badge bg-secondary\'>No</span>' ?></td>
                                    <td><?= date('m/d/Y', strtotime($candidate['created_at'])) ?></td>
                                </tr>
                                <tr class="candidate-detail-row" style="display:none;"><td colspan="9">
                                    <div class="card shadow border-0 mb-2" style="background:#e9f3fb;">
                                        <div class="card-header bg-primary text-white py-2 px-3 rounded-top d-flex align-items-center" style="font-size:1.1rem;">
                                            <i class='fas fa-user me-2'></i> <strong><?= htmlspecialchars($candidate['name']) ?></strong>
                                            <span class='ms-auto small'><i class='fas fa-calendar-alt me-1'></i><?= date('m/d/Y', strtotime($candidate['created_at'])) ?></span>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <ul class="list-unstyled mb-0">
                                                        <li class='mb-2'><i class='fas fa-envelope text-primary me-1'></i> <strong>Email:</strong> <span class='text-dark'><?= htmlspecialchars($candidate['email']) ?></span></li>
                                                        <li class='mb-2'><i class='fas fa-tags text-info me-1'></i> <strong>Categories:</strong> <span class='text-dark'><?= htmlspecialchars($candidate['categories']) ?></span></li>
                                                        <li class='mb-2'><i class='fas fa-clipboard-check text-success me-1'></i> <strong>Status:</strong> <span class='text-dark'><?= htmlspecialchars($candidate['status']) ?></span></li>
                                                        <li class='mb-2'><i class='fas fa-user-shield text-warning me-1'></i> <strong>Internal Status:</strong> <span class='text-dark'><?= htmlspecialchars($candidate['internal_status']) ?></span></li>
                                                        <li class='mb-2'><i class='fas fa-user-tie text-secondary me-1'></i> <strong>External Status:</strong> <span class='text-dark'><?= htmlspecialchars($candidate['external_status']) ?></span></li>
                                                        <li class='mb-2'><i class='fas fa-check-circle text-success me-1'></i> <strong>Done:</strong> <span class='text-dark'><?= $candidate['done'] ? 'Yes' : 'No' ?></span></li>
                                                        <li class='mb-2'><i class='fas fa-user-edit text-secondary me-1'></i> <strong>Redactor:</strong> <span class='text-dark'><?= htmlspecialchars($candidate['redactor']) ?></span></li>
                                                    </ul>
                                                </div>
                                                <div class="col-md-6">
                                                    <ul class="list-unstyled mb-0">
                                                        <li class='mb-2'><i class='fas fa-id-card-alt text-primary me-1'></i> <strong>Profile:</strong> <div class="small text-dark border rounded bg-white p-2"><?= nl2br(htmlspecialchars($candidate['profile'])) ?></div></li>
                                                        <li class='mb-2'><i class='fas fa-sticky-note text-info me-1'></i> <strong>Notes:</strong> <div class="small text-dark border rounded bg-white p-2"><?= nl2br(htmlspecialchars($candidate['notes'])) ?></div></li>
                                                        <li class='mb-2'><i class='fas fa-comments text-secondary me-1'></i> <strong>Comments:</strong> <div class="small text-dark border rounded bg-white p-2"><?= nl2br(htmlspecialchars($candidate['comments'])) ?></div></li>
                                                        <li class='mb-2'><i class='fas fa-calendar-plus text-primary me-1'></i> <strong>Updated At:</strong> <span class='text-dark'><?= $candidate['updated_at'] ? date('m/d/Y', strtotime($candidate['updated_at'])) : '-' ?></span></li>
                                                        <li class='mb-2'><i class='fas fa-file-alt text-info me-1'></i> <strong>Resume File:</strong> <?= $candidate['resume_file'] ? ('<a href="/' . htmlspecialchars($candidate['resume_file']) . '" target="_blank" class="btn btn-sm btn-outline-primary ms-1"><i class="fas fa-download"></i> Download CV</a>') : '<span class="text-muted">No file</span>' ?></li>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Propose Candidates Modal -->
<div class="modal fade" id="proposeCandidatesModal" tabindex="-1" aria-labelledby="proposeCandidatesModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="proposeCandidatesModalLabel">Propose Candidates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="propose-form">
                    <div class="mb-3">
                        <label for="proposal-note" class="form-label">Note *</label>
                        <textarea class="form-control" id="proposal-note" rows="4" required placeholder="Add a note for the manager..."></textarea>
                        <div class="invalid-feedback">A note is required.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submit-proposal-btn">Submit Proposal</button>
            </div>
        </div>
    </div>
</div>

<?php include $root_path . 'components/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jobRequestId = <?= json_encode($job_request_id) ?>;
    let basket = []; // Array to hold candidate IDs

    const basketArea = document.getElementById('basket-area');
    const openProposeModalBtn = document.getElementById('open-propose-modal-btn');
    const clearBasketBtn = document.getElementById('clear-basket');
    const allCandidatesRows = document.querySelectorAll('#candidates-table .candidate-row');
    const allCandidatesData = <?= json_encode($all_candidates) ?>;

    // Modal elements
    const proposeModal = new bootstrap.Modal(document.getElementById('proposeCandidatesModal'));
    const submitProposalBtn = document.getElementById('submit-proposal-btn');
    const proposalNote = document.getElementById('proposal-note');

    function updateBasketUI() {
        basketArea.innerHTML = '';
        if (basket.length === 0) {
            basketArea.innerHTML = '<div class="text-muted">No candidates in basket.</div>';
            openProposeModalBtn.disabled = true;
        } else {
            const list = document.createElement('ul');
            list.className = 'list-group';
            basket.forEach(candidateId => {
                const candidate = allCandidatesData.find(c => c.id == candidateId);
                if (candidate) {
                    const item = document.createElement('li');
                    item.className = 'list-group-item d-flex justify-content-between align-items-center';
                    item.innerHTML = `<span>${escapeHtml(candidate.name)}</span> <button class="btn btn-danger btn-sm remove-from-basket" data-id="${candidate.id}"><i class="fas fa-times"></i></button>`;
                    list.appendChild(item);
                }
            });
            basketArea.appendChild(list);
            openProposeModalBtn.disabled = false;
        }
    }

    function toggleCandidateButton(candidateId, forceRemove = false) {
        const buttons = document.querySelectorAll(`.add-to-basket[data-id='${candidateId}']`);
        buttons.forEach(button => {
            if (forceRemove || basket.includes(candidateId)) {
                button.textContent = 'Remove';
                button.classList.remove('btn-outline-primary');
                button.classList.add('btn-outline-danger');
            } else {
                button.textContent = 'Add';
                button.classList.remove('btn-outline-danger');
                button.classList.add('btn-outline-primary');
            }
        });
    }

    // Event Delegation for adding/removing candidates
    document.body.addEventListener('click', function(e) {
        // Add to basket
        if (e.target.matches('.add-to-basket')) {
            const candidateId = e.target.dataset.id;
            const index = basket.indexOf(candidateId);

            if (index > -1) { // Already in basket, so remove
                basket.splice(index, 1);
            } else { // Not in basket, so add
                basket.push(candidateId);
            }
            updateBasketUI();
            toggleCandidateButton(candidateId);
        }

        // Remove from basket (from the basket list itself)
        if (e.target.matches('.remove-from-basket') || e.target.closest('.remove-from-basket')) {
            const button = e.target.closest('.remove-from-basket');
            const candidateId = button.dataset.id;
            const index = basket.indexOf(candidateId);
            if (index > -1) {
                basket.splice(index, 1);
                updateBasketUI();
                toggleCandidateButton(candidateId);
            }
        }
    });

    clearBasketBtn.addEventListener('click', function() {
        basket.forEach(id => toggleCandidateButton(id)); // Reset all buttons
        basket = [];
        updateBasketUI();
    });

    openProposeModalBtn.addEventListener('click', function() {
        if (basket.length > 0) {
            proposalNote.value = ''; // Clear previous notes
            proposalNote.classList.remove('is-invalid');
            proposeModal.show();
        }
    });

    submitProposalBtn.addEventListener('click', function() {
        const note = proposalNote.value.trim();
        if (note === '') {
            proposalNote.classList.add('is-invalid');
            return;
        }
        proposalNote.classList.remove('is-invalid');

        const formData = new FormData();
        formData.append('job_request_id', jobRequestId);
        formData.append('note', note);
        basket.forEach(id => formData.append('candidate_ids[]', id));

        submitProposalBtn.disabled = true;
        submitProposalBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...';

        fetch('propose_candidates.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            proposeModal.hide();
            if (data.status === 'success') {
                alert('Success: ' + data.message);
                window.location.href = '../workflow/index.php?job_request_id=' + jobRequestId;
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An unexpected error occurred. Please check the console and try again.');
        })
        .finally(() => {
            submitProposalBtn.disabled = false;
            submitProposalBtn.innerHTML = 'Submit Proposal';
        });
    });

    // --- Filtering Logic ---
    const filterInputs = document.querySelectorAll('#filter-category, #filter-status, #filter-internal-status, #filter-external-status, #filter-done, #filter-keyword');

    function applyFilters() {
        const filters = {
            category: document.getElementById('filter-category').value.toLowerCase(),
            status: document.getElementById('filter-status').value.toLowerCase(),
            internal_status: document.getElementById('filter-internal-status').value.toLowerCase(),
            external_status: document.getElementById('filter-external-status').value.toLowerCase(),
            done: document.getElementById('filter-done').value,
            keyword: document.getElementById('filter-keyword').value.toLowerCase()
        };

        allCandidatesRows.forEach(row => {
            const candidateId = row.dataset.id;
            const candidate = allCandidatesData.find(c => c.id == candidateId);
            if (!candidate) {
                row.style.display = 'none';
                return;
            }

            const keywordMatch = !filters.keyword || 
                (candidate.name && candidate.name.toLowerCase().includes(filters.keyword)) ||
                (candidate.email && candidate.email.toLowerCase().includes(filters.keyword)) ||
                (candidate.profile && candidate.profile.toLowerCase().includes(filters.keyword)) ||
                (candidate.notes && candidate.notes.toLowerCase().includes(filters.keyword));

            const categoryMatch = !filters.category || (candidate.categories && candidate.categories.toLowerCase().includes(filters.category));
            const statusMatch = !filters.status || (candidate.status && candidate.status.toLowerCase() === filters.status);
            const internalStatusMatch = !filters.internal_status || (candidate.internal_status && candidate.internal_status.toLowerCase() === filters.internal_status);
            const externalStatusMatch = !filters.external_status || (candidate.external_status && candidate.external_status.toLowerCase() === filters.external_status);
            const doneMatch = filters.done === '' || candidate.done == filters.done;

            if (keywordMatch && categoryMatch && statusMatch && internalStatusMatch && externalStatusMatch && doneMatch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    filterInputs.forEach(input => {
        input.addEventListener('keyup', applyFilters);
        input.addEventListener('change', applyFilters);
    });

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return str.replace(/[&<>'"/]/g, s => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;',
            '"': '&quot;', "'": '&#39;', '/': '&#x2F;'
        }[s]));
    }

    // Initial UI setup
    updateBasketUI();
});
</script>
