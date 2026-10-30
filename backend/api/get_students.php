<?php
/**
 * API: Get Students & Marksheets List
 *
 * Provides administration view with search, filtering, and summary statistics.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, 'Database connection unavailable', null, 500);
}

// Check if a single record details is requested
$singleId = !empty($_GET['id']) ? (int)$_GET['id'] : null;
if ($singleId) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, s.full_name, s.registration_no, s.department
            FROM marksheet m
            JOIN students s ON m.student_id = s.id
            WHERE m.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $singleId]);
        $record = $stmt->fetch();

        if (!$record) {
            sendJsonResponse(false, 'Marksheet record not found', null, 404);
        }

        sendJsonResponse(true, 'Record retrieved successfully', $record);
    } catch (Exception $e) {
        sendJsonResponse(false, 'Error fetching record: ' . $e->getMessage(), null, 500);
    }
}

// Filter parameters
$search   = trim($_GET['search'] ?? '');
$semester = trim($_GET['semester'] ?? '');
$session  = trim($_GET['session'] ?? '');

$where = ["1=1"];
$params = [];

if (!empty($search)) {
    $where[] = "(m.roll_no LIKE :search OR s.full_name LIKE :search_name OR s.registration_no LIKE :search_reg)";
    $params[':search']      = "%{$search}%";
    $params[':search_name'] = "%{$search}%";
    $params[':search_reg']  = "%{$search}%";
}

if (!empty($semester)) {
    $where[] = "m.semester = :semester";
    $params[':semester'] = $semester;
}

if (!empty($session)) {
    $where[] = "m.session = :session";
    $params[':session'] = $session;
}

$whereClause = implode(" AND ", $where);

try {
    // 1. Fetch list of marksheets
    $sql = "
        SELECT 
            m.id,
            m.student_id,
            m.roll_no,
            s.full_name,
            s.registration_no,
            s.department,
            m.session,
            m.semester,
            m.attendance_marks,
            m.attendance_max,
            m.midterm_marks,
            m.midterm_max,
            m.total_obtained,
            m.total_max,
            m.percentage,
            m.gpa,
            m.letter_grade,
            m.status,
            m.remarks,
            m.created_at,
            m.updated_at
        FROM marksheet m
        JOIN students s ON m.student_id = s.id
        WHERE {$whereClause}
        ORDER BY m.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // 2. Fetch dashboard summary statistics
    $statSql = "
        SELECT 
            COUNT(*) as total_records,
            COUNT(DISTINCT roll_no) as total_students,
            AVG(gpa) as average_gpa,
            SUM(CASE WHEN status = 'PASSED' THEN 1 ELSE 0 END) as passed_count,
            SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed_count
        FROM marksheet
    ";
    $stats = $pdo->query($statSql)->fetch();

    $statsSummary = [
        'total_records'  => (int)($stats['total_records'] ?? 0),
        'total_students' => (int)($stats['total_students'] ?? 0),
        'average_gpa'    => round((float)($stats['average_gpa'] ?? 0.0), 2),
        'passed_count'   => (int)($stats['passed_count'] ?? 0),
        'failed_count'   => (int)($stats['failed_count'] ?? 0),
        'pass_percentage'=> !empty($stats['total_records']) ? round(($stats['passed_count'] / $stats['total_records']) * 100, 1) : 0,
    ];

    sendJsonResponse(true, 'Students list retrieved', [
        'records' => $records,
        'stats'   => $statsSummary,
        'count'   => count($records),
    ]);

} catch (Exception $e) {
    sendJsonResponse(false, 'Database query error: ' . $e->getMessage(), null, 500);
}

