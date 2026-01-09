<?php
/**
 * AbroadWorks Management System - Login Redirect
 * 
 * @author ikinciadam@gmail.com
 */

// Redirect to the modular auth login page
header('Location: modules/auth/?action=login');
exit;
