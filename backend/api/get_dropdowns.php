<?php
/**
 * API: Get Dropdown Options
 *
 * Returns list of academic semesters, sessions, and semester-specific subjects.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/grading.php';

$pdo = getDatabaseConnection();

$semesters = [];
$sessions = [];

if ($pdo) {
    try {
        // Fetch active sessions
        $stmtSessions = $pdo->query("SELECT session_name FROM sessions WHERE is_active = 1 ORDER BY id ASC");
        $sessions = $stmtSessions->fetchAll(PDO::FETCH_COLUMN);

        // Fetch semesters
        $stmtSemesters = $pdo->query("SELECT semester_name FROM semesters ORDER BY display_order ASC");
        $semesters = $stmtSemesters->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {
        error_log("Dropdown fetch error: " . $e->getMessage());
    }
}

// Fallback defaults if DB tables are not yet populated
if (empty($sessions)) {
    $sessions = ['2020-2021', '2021-2022', '2022-2023', '2023-2024', '2024-2025'];
}

if (empty($semesters)) {
    $semesters = [
        '1st Semester', '2nd Semester', '3rd Semester', '4th Semester',
        '5th Semester', '6th Semester', '7th Semester', '8th Semester'
    ];
}

// Optional: If client requested subject suggestions for a specific semester
$requestedSemester = trim($_GET['semester'] ?? '');
$defaultSubjects = [];
if (!empty($requestedSemester)) {
    $defaultSubjects = getDefaultSubjectsForSemester($requestedSemester);
}

sendJsonResponse(true, 'Dropdown data retrieved successfully', [
    'semesters'        => $semesters,
    'sessions'         => $sessions,
    'default_subjects' => $defaultSubjects,
]);

