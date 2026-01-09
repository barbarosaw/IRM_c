<?php
/**
 * AbroadWorks Management System - Logout Redirect
 * 
 * @author ikinciadam@gmail.com
 */

// Redirect to the modular auth logout page
header('Location: modules/auth/?action=logout');
exit;
