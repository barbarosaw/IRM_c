<?php
if (!defined('AW_SYSTEM')) {
    die('Direct access is not allowed.');
}

// Include database connection
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../models/Fingerprint.php';

try {
    $fingerprintModel = new Fingerprint($db);
    
    // Get today's fingerprint activities
    $stmt = $db->prepare("SELECT COUNT(*) FROM fingerprints WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayActivities = $stmt->fetchColumn();
    
    // Get suspicious IP count
    $suspiciousIPs = $fingerprintModel->getSuspiciousIPs();
    $suspiciousCount = count($suspiciousIPs);
    
    // Get rapid location changes count
    $rapidChanges = $fingerprintModel->getRapidLocationChanges();
    $rapidChangesCount = count($rapidChanges);
    
    // Get multiple sessions count
    $concurrentSessions = $fingerprintModel->getConcurrentSessions();
    $multipleSessionsCount = count($concurrentSessions);
    
} catch (Exception $e) {
    error_log("Fingerprint Widget Error: " . $e->getMessage());
    $todayActivities = 0;
    $suspiciousCount = 0;
    $rapidChangesCount = 0;
    $multipleSessionsCount = 0;
}
?>

<div class="card h-100">
    <div class="card-header bg-gradient-primary text-white">
        <h6 class="card-title mb-0">
            <i class="fas fa-fingerprint me-2"></i>
            Security Overview
        </h6>
    </div>
    <div class="card-body p-3">
        <div class="row g-3">
            <!-- Today's Activities -->
            <div class="col-6">
                <div class="d-flex align-items-center p-2 bg-light rounded">
                    <div class="icon-wrapper me-3">
                        <i class="fas fa-chart-line text-success fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-success fs-4"><?= number_format($todayActivities) ?></div>
                        <small class="text-muted">Today's Activities</small>
                    </div>
                </div>
            </div>
            
            <!-- Suspicious IPs -->
            <div class="col-6">
                <div class="d-flex align-items-center p-2 bg-light rounded">
                    <div class="icon-wrapper me-3">
                        <i class="fas fa-exclamation-triangle text-warning fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-warning fs-4"><?= number_format($suspiciousCount) ?></div>
                        <small class="text-muted">Suspicious IPs</small>
                    </div>
                </div>
            </div>
            
            <!-- Rapid Changes -->
            <div class="col-6">
                <div class="d-flex align-items-center p-2 bg-light rounded">
                    <div class="icon-wrapper me-3">
                        <i class="fas fa-exchange-alt text-danger fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-danger fs-4"><?= number_format($rapidChangesCount) ?></div>
                        <small class="text-muted">Rapid Changes</small>
                    </div>
                </div>
            </div>
            
            <!-- Multiple Sessions -->
            <div class="col-6">
                <div class="d-flex align-items-center p-2 bg-light rounded">
                    <div class="icon-wrapper me-3">
                        <i class="fas fa-users text-info fa-lg"></i>
                    </div>
                    <div>
                        <div class="fw-bold text-info fs-4"><?= number_format($multipleSessionsCount) ?></div>
                        <small class="text-muted">Multiple Sessions</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Action Button -->
        <div class="mt-3 text-center">
            <a href="modules/fingerprint/" class="btn btn-primary btn-sm">
                <i class="fas fa-eye me-1"></i>
                View Details
            </a>
        </div>
        
        <!-- Status Indicators -->
        <div class="mt-3">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Security Status:</small>
                        <?php 
                        $totalRisk = $suspiciousCount + $rapidChangesCount + $multipleSessionsCount;
                        if ($totalRisk == 0): 
                        ?>
                            <span class="badge bg-success">All Clear</span>
                        <?php elseif ($totalRisk <= 5): ?>
                            <span class="badge bg-warning">Low Risk</span>
                        <?php elseif ($totalRisk <= 15): ?>
                            <span class="badge bg-warning">Medium Risk</span>
                        <?php else: ?>
                            <span class="badge bg-danger">High Risk</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Card Footer with Last Update -->
    <div class="card-footer bg-light text-center py-2">
        <small class="text-muted">
            <i class="fas fa-clock me-1"></i>
            Last updated: <?= date('H:i:s') ?>
        </small>
    </div>
</div>

<style>
.icon-wrapper {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    background: rgba(255,255,255,0.3);
}

.card-header.bg-gradient-primary {
    background: linear-gradient(45deg, #007bff, #0056b3) !important;
}

.bg-light {
    transition: all 0.3s ease;
}

.bg-light:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}
</style>
