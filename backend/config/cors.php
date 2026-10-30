<?php
/**
 * CORS & JSON Response Helper
 *
 * Ensures clean REST API communication between decoupled Frontend and Backend.
 */

declare(strict_types=1);

// Ensure session directory is writable to prevent read-only filesystem warnings
if (session_status() === PHP_SESSION_NONE) {
    $savePath = session_save_path();
    if (empty($savePath) || !is_writable($savePath)) {
        $customSessionDir = sys_get_temp_dir() . '/varsity_sessions';
        if (!is_dir($customSessionDir)) {
            @mkdir($customSessionDir, 0777, true);
        }
        if (is_dir($customSessionDir) && is_writable($customSessionDir)) {
            @session_save_path($customSessionDir);
        }
    }
    @session_start();
}

// Set CORS headers so frontend (even on different port/domain) can communicate smoothly
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");
header("Content-Type: application/json; charset=UTF-8");

// Handle preflight OPTIONS request
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

/**
 * Sends a standardized JSON API response
 *
 * @param bool $success Whether the request succeeded
 * @param string $message User/developer facing message
 * @param mixed $data Optional payload data
 * @param int $statusCode HTTP status code (200, 400, 401, 404, 500)
 */
function sendJsonResponse(bool $success, string $message, $data = null, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success'   => $success,
        'message'   => $message,
        'data'      => $data,
        'timestamp' => date('Y-m-d H:i:s'),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Helper to parse incoming JSON request body or POST data
 *
 * @return array
 */
function getRequestBody(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        if (is_array($json) && !empty($json)) {
            return $json;
        }
    }

    return $_POST;
}
