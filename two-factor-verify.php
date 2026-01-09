<?php
/**
 * AbroadWorks Management System - Two-Factor Verification Redirect
 * 
 * @author ikinciadam@gmail.com
 */

// Redirect to the modular auth 2FA verification page
header('Location: /modules/auth/index.php?action=verify-2fa');
exit;
