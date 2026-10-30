<?php
/**
 * API: Student Result Search
 *
 * Search student marksheet by Roll Number, Semester, and Session dropdown.
 * Returns individual marks (attendance, midterm, 4 CSE subjects), total marks, SGPA & CGPA.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/grading.php';

$rollNo   = trim($_GET['roll_no'] ?? ($_POST['roll_no'] ?? ''));
$semester = trim($_GET['semester'] ?? ($_POST['semester'] ?? ''));
$session  = trim($_GET['session'] ?? ($_POST['session'] ?? ''));

if (empty($rollNo) || empty($semester) || empty($session)) {
    sendJsonResponse(false, 'Please provide Roll Number, Semester, and Session.', null, 400);
}

$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, 'Database connection is currently unavailable.', null, 500);
}

try {
    // 1. Fetch target semester result
    $sql = "
        SELECT 
            m.*,
            s.full_name,
            s.registration_no,
            s.department
        FROM marksheet m
        JOIN students s ON m.student_id = s.id
        WHERE m.roll_no = :roll_no 
          AND m.semester = :semester 
          AND m.session = :session
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':roll_no'  => $rollNo,
        ':semester' => $semester,
        ':session'  => $session,
    ]);

    $result = $stmt->fetch();

    if (!$result) {
        sendJsonResponse(false, "No published result found for Roll No \"{$rollNo}\" in {$semester} ({$session}).", null, 404);
    }

    // 2. Fetch all semester records for calculating Cumulative CGPA
    $stmtAll = $pdo->prepare("
        SELECT semester, session, total_obtained, total_max, gpa, letter_grade, status 
        FROM marksheet 
        WHERE roll_no = :roll_no 
        ORDER BY id ASC
    ");
    $stmtAll->execute([':roll_no' => $rollNo]);
    $allSemesters = $stmtAll->fetchAll();

    // Calculate CGPA across all recorded semesters
    $totalSemGpa = 0.0;
    $countSem = count($allSemesters);
    foreach ($allSemesters as $sem) {
        $totalSemGpa += (float)$sem['gpa'];
    }
    $cgpa = $countSem > 0 ? round($totalSemGpa / $countSem, 2) : (float)$result['gpa'];

    // 3. Format individual subjects & assessments
    $responsePayload = [
        'student' => [
            'roll_no'         => $result['roll_no'],
            'full_name'       => $result['full_name'],
            'registration_no' => $result['registration_no'] ?: 'N/A',
            'department'      => $result['department'] ?: 'Computer Science & Engineering',
            'session'         => $result['session'],
            'semester'        => $result['semester'],
            'issue_date'      => date('F d, Y', strtotime($result['updated_at'] ?? $result['created_at'])),
        ],
        'summary' => [
            'total_obtained' => (float)$result['total_obtained'],
            'total_max'      => (float)$result['total_max'],
            'percentage'     => (float)$result['percentage'],
            'sgpa'           => (float)$result['gpa'],
            'cgpa'           => $cgpa,
            'letter_grade'   => $result['letter_grade'],
            'status'         => $result['status'],
            'remarks'        => $result['remarks'],
            'semesters_count'=> $countSem,
        ],
        'continuous_assessment' => [
            [
                'title'       => 'Class Attendance',
                'description' => 'Regularity, punctuality, and lecture participation',
                'obtained'    => (float)$result['attendance_marks'],
                'max'         => (float)$result['attendance_max'],
                'percentage'  => round(((float)$result['attendance_marks'] / max(1.0, (float)$result['attendance_max'])) * 100, 1),
            ],
            [
                'title'       => 'Midterm Examination',
                'description' => 'Mid-semester written & analytical evaluation',
                'obtained'    => (float)$result['midterm_marks'],
                'max'         => (float)$result['midterm_max'],
                'percentage'  => round(((float)$result['midterm_marks'] / max(1.0, (float)$result['midterm_max'])) * 100, 1),
            ],
        ],
        'subjects' => [
            [
                'sub_no'   => 1,
                'code'     => $result['cse_sub1_code'],
                'name'     => $result['cse_sub1_name'],
                'obtained' => (float)$result['cse_sub1_marks'],
                'max'      => (float)$result['cse_sub1_max'],
                'credit'   => (float)$result['cse_sub1_credit'],
                'grade'    => $result['cse_sub1_grade'],
                'point'    => (float)$result['cse_sub1_point'],
            ],
            [
                'sub_no'   => 2,
                'code'     => $result['cse_sub2_code'],
                'name'     => $result['cse_sub2_name'],
                'obtained' => (float)$result['cse_sub2_marks'],
                'max'      => (float)$result['cse_sub2_max'],
                'credit'   => (float)$result['cse_sub2_credit'],
                'grade'    => $result['cse_sub2_grade'],
                'point'    => (float)$result['cse_sub2_point'],
            ],
            [
                'sub_no'   => 3,
                'code'     => $result['cse_sub3_code'],
                'name'     => $result['cse_sub3_name'],
                'obtained' => (float)$result['cse_sub3_marks'],
                'max'      => (float)$result['cse_sub3_max'],
                'credit'   => (float)$result['cse_sub3_credit'],
                'grade'    => $result['cse_sub3_grade'],
                'point'    => (float)$result['cse_sub3_point'],
            ],
            [
                'sub_no'   => 4,
                'code'     => $result['cse_sub4_code'],
                'name'     => $result['cse_sub4_name'],
                'obtained' => (float)$result['cse_sub4_marks'],
                'max'      => (float)$result['cse_sub4_max'],
                'credit'   => (float)$result['cse_sub4_credit'],
                'grade'    => $result['cse_sub4_grade'],
                'point'    => (float)$result['cse_sub4_point'],
            ],
        ],
        'academic_history' => $allSemesters,
    ];

    sendJsonResponse(true, 'Result found successfully', $responsePayload);

} catch (Exception $e) {
    sendJsonResponse(false, 'Error retrieving result: ' . $e->getMessage(), null, 500);
}

