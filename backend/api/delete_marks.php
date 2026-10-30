<?php
/**
 * API: Delete Marksheet Record
 *
 * Deletes a student marksheet record by ID.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    sendJsonResponse(false, 'Only POST or DELETE requests allowed', null, 405);
}

$input = getRequestBody();
$id = !empty($input['id']) ? (int)$input['id'] : (!empty($_GET['id']) ? (int)$_GET['id'] : 0);

if ($id <= 0) {
    sendJsonResponse(false, 'Valid record ID is required', null, 400);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, 'Database connection unavailable', null, 500);
}

try {
    $stmt = $pdo->prepare("DELETE FROM marksheet WHERE id = :id");
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() > 0) {
        sendJsonResponse(true, 'Marksheet record deleted successfully');
    } else {
        sendJsonResponse(false, 'Record not found or already deleted', null, 404);
    }
} catch (Exception $e) {
    sendJsonResponse(false, 'Failed to delete record: ' . $e->getMessage(), null, 500);
}

