<?php
/**
 * AbroadWorks Management System - Forgot Password Redirect
 * 
 * @author ikinciadam@gmail.com
 */

// Redirect to the modular auth forgot password page
header('Location: modules/auth/?action=forgot-password');
exit;
