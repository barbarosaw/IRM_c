<?php
/**
 * TimeWorks Module - Billing
 *
 * Displays time entries with billing calculations.
 *
 * @author ikinciadam@gmail.com
 */

define('AW_SYSTEM', true);
require_once '../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Check permission
if (!has_permission('timeworks_billing_view')) {
    header('Location: ../../access-denied.php');
    exit;
}

$page_title = "TimeWorks - Billing";
$root_path = "../../";
$root_dir = dirname(__DIR__, 2);

// Load model
require_once 'models/BillingModel.php';
$billing = new BillingModel($db);

// Get filter options
$clients = $billing->getClients();
$employees = $billing->getEmployees();

include '../../components/header.php';
include '../../components/sidebar.php';
include 'views/billing/index.php';
include '../../components/footer.php';
