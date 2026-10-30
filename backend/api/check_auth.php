<?php
/**
 * API: Check Admin Authentication Status
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';

if (!empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    sendJsonResponse(true, 'Authenticated', [
        'is_logged_in' => true,
        'username'     => $_SESSION['admin_username'] ?? 'admin',
        'full_name'    => $_SESSION['admin_name'] ?? 'Administrator',
    ]);
}

sendJsonResponse(false, 'Not authenticated', [
    'is_logged_in' => false
], 401);

