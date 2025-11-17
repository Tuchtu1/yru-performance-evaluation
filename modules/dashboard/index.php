<?php

/**
 * /modules/dashboard/index.php
 * Dashboard Router - Redirect to appropriate dashboard based on user role
 */

session_start();
require_once '../../config/app.php';
require_once '../../config/database.php';
require_once '../../config/permission.php';

// Check authentication
requireAuth();

// Get user role
$user_role = $_SESSION['user']['role'] ?? null;

// Redirect to appropriate dashboard
switch ($user_role) {
    case 'admin':
        redirect('modules/dashboard/admin-dashboard.php');
        break;

    case 'manager':
        redirect('modules/dashboard/manager-dashboard.php');
        break;

    case 'staff':
        redirect('modules/dashboard/staff-dashboard.php');
        break;

    default:
        // If role is not recognized, redirect to login
        session_destroy();
        redirect('modules/auth/login.php');
        break;
}
