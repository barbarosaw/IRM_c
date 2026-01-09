<!-- PH Communications Module - Dashboard -->

<link rel="stylesheet" href="assets/css/ph-communications.css">

<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">
                        <i class="fas fa-globe-asia me-2"></i>PH Communications
                    </h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="<?php echo $root_path; ?>">Home</a></li>
                        <li class="breadcrumb-item active">PH Communications</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="container-fluid">

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">SMS Sent Today</h5>
                                    <h2 class="mt-2 mb-0" id="smsSentToday">-</h2>
                                </div>
                                <div>
                                    <i class="fas fa-paper-plane fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">SMS Received Today</h5>
                                    <h2 class="mt-2 mb-0" id="smsReceivedToday">-</h2>
                                </div>
                                <div>
                                    <i class="fas fa-inbox fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">Delivery Rate</h5>
                                    <h2 class="mt-2 mb-0" id="deliveryRate">-</h2>
                                </div>
                                <div>
                                    <i class="fas fa-chart-line fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-3 col-md-6">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0">Failed Messages</h5>
                                    <h2 class="mt-2 mb-0" id="failedMessages">-</h2>
                                </div>
                                <div>
                                    <i class="fas fa-exclamation-triangle fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-bolt me-2"></i>Quick Actions
                            </h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <a href="sms/compose.php" class="btn btn-lg btn-primary w-100 sms-card">
                                        <i class="fas fa-paper-plane fa-2x d-block mb-2"></i>
                                        <h5 class="mb-0">Send SMS</h5>
                                        <small>Compose and send new message</small>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="sms/inbox.php" class="btn btn-lg btn-success w-100 sms-card">
                                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                                        <h5 class="mb-0">Inbox</h5>
                                        <small>View received messages</small>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="sms/outbox.php" class="btn btn-lg btn-info w-100 sms-card">
                                        <i class="fas fa-paper-plane fa-2x d-block mb-2"></i>
                                        <h5 class="mb-0">Outbox</h5>
                                        <small>View sent messages</small>
                                    </a>
                                </div>
                                <div class="col-md-6">
                                    <a href="settings.php" class="btn btn-lg btn-secondary w-100 sms-card">
                                        <i class="fas fa-cog fa-2x d-block mb-2"></i>
                                        <h5 class="mb-0">Settings</h5>
                                        <small>Configure m360 credentials</small>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-info-circle me-2"></i>Module Information
                            </h3>
                        </div>
                        <div class="card-body">
                            <h5 class="mb-3">Features</h5>
                            <ul class="list-unstyled">
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Send SMS via m360 API</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Receive inbound SMS (MO)</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Delivery reports (DLR)</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Cross-telco support</li>
                                <li class="mb-2"><i class="fas fa-check text-success me-2"></i> Message history tracking</li>
                            </ul>

                            <hr>

                            <h5 class="mb-3 mt-3">Supported Networks</h5>
                            <div class="d-flex justify-content-around">
                                <span class="badge bg-primary">Globe</span>
                                <span class="badge bg-success">Smart</span>
                                <span class="badge bg-warning">Sun</span>
                                <span class="badge bg-info">DITO</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Load statistics
    loadStats();

    // Refresh stats every 60 seconds
    setInterval(loadStats, 60000);

    function loadStats() {
        // SMS Sent Today
        $.get('api/m360-sms/get-messages.php', {
            direction: 'outbound',
            date_from: new Date().toISOString().split('T')[0]
        }, function(response) {
            $('#smsSentToday').text(response.total || 0);
        });

        // SMS Received Today
        $.get('api/m360-sms/get-messages.php', {
            direction: 'inbound',
            date_from: new Date().toISOString().split('T')[0]
        }, function(response) {
            $('#smsReceivedToday').text(response.total || 0);
        });

        // Calculate delivery rate (last 7 days)
        $.get('api/m360-sms/get-messages.php', {
            direction: 'outbound',
            date_from: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]
        }, function(response) {
            if (response.data && response.data.length > 0) {
                const total = response.data.length;
                const delivered = response.data.filter(msg =>
                    msg.status === 'delivered' || msg.status === 'acknowledged'
                ).length;
                const rate = total > 0 ? Math.round((delivered / total) * 100) : 0;
                $('#deliveryRate').text(rate + '%');
            } else {
                $('#deliveryRate').text('0%');
            }
        });

        // Failed messages today
        $.get('api/m360-sms/get-messages.php', {
            direction: 'outbound',
            status: 'failed',
            date_from: new Date().toISOString().split('T')[0]
        }, function(response) {
            $('#failedMessages').text(response.total || 0);
        });
    }
});
</script>
