<?php
/**
 * TimeWorks Module - Public FAQ Page
 *
 * Public page for TimeWorks FAQ
 * No authentication required
 *
 * @author ikinciadam@gmail.com
 */

// This is a standalone public page - no session required
define('AW_SYSTEM', true);
define('PUBLIC_API', true);

$root_path = dirname(dirname(__DIR__));
require_once $root_path . '/config/database.php';

$pageTitle = 'FAQ - TimeWorks';

// Fetch active FAQ entries
try {
    $stmt = $db->query("SELECT id, question, answer FROM twr_faq WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $faqs = $stmt->fetchAll();
} catch (Exception $e) {
    $faqs = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            padding: 40px 20px;
        }

        .faq-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .faq-header {
            background: var(--primary-gradient);
            color: white;
            padding: 40px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }

        .faq-header h1 {
            font-size: 2rem;
            margin: 0;
            font-weight: 600;
        }

        .faq-header p {
            margin: 15px 0 0 0;
            opacity: 0.9;
        }

        .faq-body {
            background: white;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            padding: 30px;
        }

        .faq-item {
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
        }

        .faq-item.active {
            border-color: #667eea;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .faq-question {
            background: #f8f9fa;
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: #333;
            transition: all 0.3s ease;
        }

        .faq-item.active .faq-question {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #667eea;
        }

        .faq-question:hover {
            background: #e9ecef;
        }

        .faq-item.active .faq-question:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
        }

        .faq-question .question-text {
            flex: 1;
            padding-right: 15px;
        }

        .faq-question .toggle-icon {
            transition: transform 0.3s ease;
            color: #667eea;
        }

        .faq-item.active .toggle-icon {
            transform: rotate(180deg);
        }

        .faq-answer {
            display: none;
            padding: 20px;
            background: white;
            color: #555;
            line-height: 1.7;
            border-top: 1px solid #e9ecef;
        }

        .faq-item.active .faq-answer {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .faq-link {
            font-size: 0.8rem;
            color: #999;
            margin-left: 10px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .faq-question:hover .faq-link {
            opacity: 1;
        }

        .faq-link:hover {
            color: #667eea;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            color: #667eea;
            text-decoration: none;
            margin-bottom: 20px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .back-link:hover {
            color: #764ba2;
            transform: translateX(-3px);
        }

        .back-link i {
            margin-right: 8px;
        }

        .no-faq {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .no-faq i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .search-box {
            margin-bottom: 25px;
        }

        .search-box input {
            border-radius: 10px;
            padding: 12px 20px;
            border: 2px solid #e9ecef;
            width: 100%;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.15);
            outline: none;
        }

        .highlight {
            background: rgba(102, 126, 234, 0.2);
            padding: 2px 4px;
            border-radius: 3px;
        }

        .footer-links {
            text-align: center;
            margin-top: 30px;
        }

        .footer-links a {
            color: #667eea;
            text-decoration: none;
            margin: 0 15px;
        }

        .footer-links a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="faq-container">
        <a href="password-reset.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Password Reset
        </a>

        <div class="faq-header">
            <h1><i class="fas fa-question-circle me-2"></i>Frequently Asked Questions</h1>
            <p>Find answers to common questions about TimeWorks</p>
        </div>

        <div class="faq-body">
            <?php if (empty($faqs)): ?>
                <div class="no-faq">
                    <i class="fas fa-inbox d-block"></i>
                    <p>No FAQ entries available at this time.</p>
                </div>
            <?php else: ?>
                <div class="search-box">
                    <input type="text" id="faq-search" placeholder="Search FAQ...">
                </div>

                <div id="faq-list">
                    <?php foreach ($faqs as $faq): ?>
                        <div class="faq-item" id="faq-<?php echo $faq['id']; ?>" data-question="<?php echo strtolower(htmlspecialchars($faq['question'])); ?>" data-answer="<?php echo strtolower(htmlspecialchars(strip_tags($faq['answer']))); ?>">
                            <div class="faq-question" onclick="toggleFaq(<?php echo $faq['id']; ?>)">
                                <span class="question-text"><?php echo htmlspecialchars($faq['question']); ?></span>
                                <a href="#faq-<?php echo $faq['id']; ?>" class="faq-link" onclick="event.stopPropagation(); copyFaqLink(<?php echo $faq['id']; ?>);" title="Copy link">
                                    <i class="fas fa-link"></i>
                                </a>
                                <span class="toggle-icon">
                                    <i class="fas fa-chevron-down"></i>
                                </span>
                            </div>
                            <div class="faq-answer">
                                <?php echo $faq['answer']; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div id="no-results" style="display: none;" class="no-faq">
                    <i class="fas fa-search d-block"></i>
                    <p>No matching questions found.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-links">
            <a href="password-reset.php"><i class="fas fa-key me-1"></i>Reset Password</a>
        </div>

        <div class="text-center mt-3">
            <small class="text-muted">
                <i class="fas fa-shield-alt me-1"></i>TimeWorks by AbroadWorks
            </small>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle FAQ item
        function toggleFaq(id) {
            const item = document.getElementById('faq-' + id);
            const wasActive = item.classList.contains('active');

            // Close all items
            document.querySelectorAll('.faq-item').forEach(el => {
                el.classList.remove('active');
            });

            // Open clicked item if it wasn't active
            if (!wasActive) {
                item.classList.add('active');
                // Update URL without reload
                history.replaceState(null, null, '#faq-' + id);
            } else {
                // Clear hash if closing
                history.replaceState(null, null, window.location.pathname);
            }
        }

        // Copy FAQ link to clipboard
        function copyFaqLink(id) {
            const url = window.location.origin + window.location.pathname + '#faq-' + id;
            navigator.clipboard.writeText(url).then(() => {
                // Show brief notification
                const link = document.querySelector('#faq-' + id + ' .faq-link');
                const originalHtml = link.innerHTML;
                link.innerHTML = '<i class="fas fa-check"></i>';
                link.style.opacity = '1';
                link.style.color = '#28a745';
                setTimeout(() => {
                    link.innerHTML = originalHtml;
                    link.style.color = '';
                    link.style.opacity = '';
                }, 1500);
            });
        }

        // Open FAQ from URL hash on page load
        function openFaqFromHash() {
            const hash = window.location.hash;
            if (hash && hash.startsWith('#faq-')) {
                const id = hash.replace('#faq-', '');
                const item = document.getElementById('faq-' + id);
                if (item) {
                    item.classList.add('active');
                    // Scroll to the item
                    setTimeout(() => {
                        item.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }, 100);
                }
            }
        }

        // Search functionality
        document.getElementById('faq-search')?.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            const items = document.querySelectorAll('.faq-item');
            const noResults = document.getElementById('no-results');
            let visibleCount = 0;

            items.forEach(item => {
                const question = item.dataset.question || '';
                const answer = item.dataset.answer || '';

                if (query === '' || question.includes(query) || answer.includes(query)) {
                    item.style.display = 'block';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });

            if (noResults) {
                noResults.style.display = visibleCount === 0 ? 'block' : 'none';
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', openFaqFromHash);

        // Handle hash change (e.g., when clicking browser back/forward)
        window.addEventListener('hashchange', openFaqFromHash);
    </script>
</body>
</html>
