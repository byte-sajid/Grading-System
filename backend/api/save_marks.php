<?php
/**
 * API: Save or Update Student Marksheet
 *
 * Saves attendance, midterm, and 4 CSE subject marks.
 * Performs server-side validation, grade calculations, and GPA computation.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/cors.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/grading.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(false, 'Only POST requests allowed', null, 405);
}

// Ensure database is available
$pdo = getDatabaseConnection();
if (!$pdo) {
    sendJsonResponse(false, 'Database connection is not available. Please verify MySQL configuration.', null, 500);
}

$input = getRequestBody();

// 1. Sanitize & validate student info
$recordId       = !empty($input['id']) ? (int)$input['id'] : null;
$rollNo         = trim($input['roll_no'] ?? '');
$fullName       = trim($input['full_name'] ?? '');
$regNo          = trim($input['registration_no'] ?? '');
$session        = trim($input['session'] ?? '');
$semester       = trim($input['semester'] ?? '');
$department     = trim($input['department'] ?? 'Computer Science & Engineering');

if (empty($rollNo) || empty($session) || empty($semester)) {
    sendJsonResponse(false, 'Roll Number, Session, and Semester are required fields.', null, 400);
}

if (empty($fullName)) {
    $fullName = "Student " . $rollNo;
}

// 2. Validate & parse continuous assessment marks
$attendanceMarks = max(0.0, (float)($input['attendance_marks'] ?? 0.0));
$attendanceMax   = max(1.0, (float)($input['attendance_max'] ?? 10.0));
if ($attendanceMarks > $attendanceMax) {
    sendJsonResponse(false, "Attendance marks ({$attendanceMarks}) cannot exceed maximum ({$attendanceMax}).", null, 400);
}

$midtermMarks    = max(0.0, (float)($input['midterm_marks'] ?? 0.0));
$midtermMax      = max(1.0, (float)($input['midterm_max'] ?? 20.0));
if ($midtermMarks > $midtermMax) {
    sendJsonResponse(false, "Midterm marks ({$midtermMarks}) cannot exceed maximum ({$midtermMax}).", null, 400);
}

// 3. Validate & parse 4 CSE subjects
$subjects = [];
for ($i = 1; $i <= 4; $i++) {
    $subCode   = trim($input["cse_sub{$i}_code"] ?? "CSE-30{$i}");
    $subName   = trim($input["cse_sub{$i}_name"] ?? "CSE Subject {$i}");
    $subMarks  = max(0.0, (float)($input["cse_sub{$i}_marks"] ?? 0.0));
    $subMax    = max(1.0, (float)($input["cse_sub{$i}_max"] ?? 100.0));
    $subCredit = max(0.5, (float)($input["cse_sub{$i}_credit"] ?? 3.0));

    if ($subMarks > $subMax) {
        sendJsonResponse(false, "Marks for {$subCode} ({$subMarks}) cannot exceed maximum ({$subMax}).", null, 400);
    }

    $gradeData = calculateSubjectGrade($subMarks, $subMax);

    $subjects[$i] = [
        'code'   => $subCode,
        'name'   => $subName,
        'marks'  => $subMarks,
        'max'    => $subMax,
        'credit' => $subCredit,
        'grade'  => $gradeData['grade'],
        'point'  => $gradeData['point'],
        'remark' => $gradeData['remark'],
    ];
}

// 4. Calculate aggregates
$totalObtained = $attendanceMarks + $midtermMarks + 
                 $subjects[1]['marks'] + $subjects[2]['marks'] + 
                 $subjects[3]['marks'] + $subjects[4]['marks'];

$totalMax      = $attendanceMax + $midtermMax + 
                 $subjects[1]['max'] + $subjects[2]['max'] + 
                 $subjects[3]['max'] + $subjects[4]['max'];

$percentage    = round(($totalObtained / $totalMax) * 100.0, 2);

// Calculate Semester GPA
$gpa = calculateSemesterGPA([
    ['point' => $subjects[1]['point'], 'credit' => $subjects[1]['credit']],
    ['point' => $subjects[2]['point'], 'credit' => $subjects[2]['credit']],
    ['point' => $subjects[3]['point'], 'credit' => $subjects[3]['credit']],
    ['point' => $subjects[4]['point'], 'credit' => $subjects[4]['credit']],
]);

$hasFailed = ($subjects[1]['grade'] === 'F' || 
              $subjects[2]['grade'] === 'F' || 
              $subjects[3]['grade'] === 'F' || 
              $subjects[4]['grade'] === 'F');

$standing = getOverallStanding($gpa, $hasFailed);
$letterGrade = $standing['letter'];
$status      = $standing['status'];
$remarks     = $standing['remarks'];

try {
    $pdo->beginTransaction();

    // Step A: Ensure student exists or update profile
    $stmtStudent = $pdo->prepare("SELECT id FROM students WHERE roll_no = :roll_no LIMIT 1");
    $stmtStudent->execute([':roll_no' => $rollNo]);
    $studentId = $stmtStudent->fetchColumn();

    if ($studentId) {
        $stmtUpd = $pdo->prepare("
            UPDATE students 
            SET full_name = :full_name, 
                registration_no = COALESCE(NULLIF(:reg_no, ''), registration_no),
                session = :session,
                department = :department
            WHERE id = :id
        ");
        $stmtUpd->execute([
            ':full_name'   => $fullName,
            ':reg_no'      => $regNo,
            ':session'     => $session,
            ':department'  => $department,
            ':id'          => $studentId
        ]);
    } else {
        $stmtIns = $pdo->prepare("
            INSERT INTO students (roll_no, registration_no, full_name, department, session)
            VALUES (:roll_no, :reg_no, :full_name, :department, :session)
        ");
        $stmtIns->execute([
            ':roll_no'     => $rollNo,
            ':reg_no'      => $regNo ?: null,
            ':full_name'   => $fullName,
            ':department'  => $department,
            ':session'     => $session
        ]);
        $studentId = $pdo->lastInsertId();
    }

    // Step B: Insert or Update Marksheet
    if ($recordId) {
        $sqlMarksheet = "
            UPDATE marksheet SET
                student_id = :student_id,
                roll_no = :roll_no,
                session = :session,
                semester = :semester,
                attendance_marks = :att_marks,
                attendance_max = :att_max,
                midterm_marks = :mid_marks,
                midterm_max = :mid_max,
                cse_sub1_code = :s1_code, cse_sub1_name = :s1_name, cse_sub1_marks = :s1_marks, cse_sub1_max = :s1_max, cse_sub1_credit = :s1_credit, cse_sub1_grade = :s1_grade, cse_sub1_point = :s1_point,
                cse_sub2_code = :s2_code, cse_sub2_name = :s2_name, cse_sub2_marks = :s2_marks, cse_sub2_max = :s2_max, cse_sub2_credit = :s2_credit, cse_sub2_grade = :s2_grade, cse_sub2_point = :s2_point,
                cse_sub3_code = :s3_code, cse_sub3_name = :s3_name, cse_sub3_marks = :s3_marks, cse_sub3_max = :s3_max, cse_sub3_credit = :s3_credit, cse_sub3_grade = :s3_grade, cse_sub3_point = :s3_point,
                cse_sub4_code = :s4_code, cse_sub4_name = :s4_name, cse_sub4_marks = :s4_marks, cse_sub4_max = :s4_max, cse_sub4_credit = :s4_credit, cse_sub4_grade = :s4_grade, cse_sub4_point = :s4_point,
                total_obtained = :total_obtained,
                total_max = :total_max,
                percentage = :percentage,
                gpa = :gpa,
                letter_grade = :letter_grade,
                status = :status,
                remarks = :remarks
            WHERE id = :id
        ";
        $params = [
            ':id'             => $recordId,
            ':student_id'     => $studentId,
            ':roll_no'        => $rollNo,
            ':session'        => $session,
            ':semester'       => $semester,
            ':att_marks'      => $attendanceMarks,
            ':att_max'        => $attendanceMax,
            ':mid_marks'      => $midtermMarks,
            ':mid_max'        => $midtermMax,
            ':s1_code'        => $subjects[1]['code'], ':s1_name' => $subjects[1]['name'], ':s1_marks' => $subjects[1]['marks'], ':s1_max' => $subjects[1]['max'], ':s1_credit' => $subjects[1]['credit'], ':s1_grade' => $subjects[1]['grade'], ':s1_point' => $subjects[1]['point'],
            ':s2_code'        => $subjects[2]['code'], ':s2_name' => $subjects[2]['name'], ':s2_marks' => $subjects[2]['marks'], ':s2_max' => $subjects[2]['max'], ':s2_credit' => $subjects[2]['credit'], ':s2_grade' => $subjects[2]['grade'], ':s2_point' => $subjects[2]['point'],
            ':s3_code'        => $subjects[3]['code'], ':s3_name' => $subjects[3]['name'], ':s3_marks' => $subjects[3]['marks'], ':s3_max' => $subjects[3]['max'], ':s3_credit' => $subjects[3]['credit'], ':s3_grade' => $subjects[3]['grade'], ':s3_point' => $subjects[3]['point'],
            ':s4_code'        => $subjects[4]['code'], ':s4_name' => $subjects[4]['name'], ':s4_marks' => $subjects[4]['marks'], ':s4_max' => $subjects[4]['max'], ':s4_credit' => $subjects[4]['credit'], ':s4_grade' => $subjects[4]['grade'], ':s4_point' => $subjects[4]['point'],
            ':total_obtained' => $totalObtained,
            ':total_max'      => $totalMax,
            ':percentage'     => $percentage,
            ':gpa'            => $gpa,
            ':letter_grade'   => $letterGrade,
            ':status'         => $status,
            ':remarks'        => $remarks,
        ];
        $stmtUpdate = $pdo->prepare($sqlMarksheet);
        $stmtUpdate->execute($params);
        $savedId = $recordId;
    } else {
        $sqlMarksheet = "
            INSERT INTO marksheet (
                student_id, roll_no, session, semester,
                attendance_marks, attendance_max, midterm_marks, midterm_max,
                cse_sub1_code, cse_sub1_name, cse_sub1_marks, cse_sub1_max, cse_sub1_credit, cse_sub1_grade, cse_sub1_point,
                cse_sub2_code, cse_sub2_name, cse_sub2_marks, cse_sub2_max, cse_sub2_credit, cse_sub2_grade, cse_sub2_point,
                cse_sub3_code, cse_sub3_name, cse_sub3_marks, cse_sub3_max, cse_sub3_credit, cse_sub3_grade, cse_sub3_point,
                cse_sub4_code, cse_sub4_name, cse_sub4_marks, cse_sub4_max, cse_sub4_credit, cse_sub4_grade, cse_sub4_point,
                total_obtained, total_max, percentage, gpa, letter_grade, status, remarks
            ) VALUES (
                :student_id, :roll_no, :session, :semester,
                :att_marks, :att_max, :mid_marks, :mid_max,
                :s1_code, :s1_name, :s1_marks, :s1_max, :s1_credit, :s1_grade, :s1_point,
                :s2_code, :s2_name, :s2_marks, :s2_max, :s2_credit, :s2_grade, :s2_point,
                :s3_code, :s3_name, :s3_marks, :s3_max, :s3_credit, :s3_grade, :s3_point,
                :s4_code, :s4_name, :s4_marks, :s4_max, :s4_credit, :s4_grade, :s4_point,
                :total_obtained, :total_max, :percentage, :gpa, :letter_grade, :status, :remarks
            )
            ON DUPLICATE KEY UPDATE
                attendance_marks = VALUES(attendance_marks),
                attendance_max = VALUES(attendance_max),
                midterm_marks = VALUES(midterm_marks),
                midterm_max = VALUES(midterm_max),
                cse_sub1_code = VALUES(cse_sub1_code), cse_sub1_name = VALUES(cse_sub1_name), cse_sub1_marks = VALUES(cse_sub1_marks), cse_sub1_max = VALUES(cse_sub1_max), cse_sub1_credit = VALUES(cse_sub1_credit), cse_sub1_grade = VALUES(cse_sub1_grade), cse_sub1_point = VALUES(cse_sub1_point),
                cse_sub2_code = VALUES(cse_sub2_code), cse_sub2_name = VALUES(cse_sub2_name), cse_sub2_marks = VALUES(cse_sub2_marks), cse_sub2_max = VALUES(cse_sub2_max), cse_sub2_credit = VALUES(cse_sub2_credit), cse_sub2_grade = VALUES(cse_sub2_grade), cse_sub2_point = VALUES(cse_sub2_point),
                cse_sub3_code = VALUES(cse_sub3_code), cse_sub3_name = VALUES(cse_sub3_name), cse_sub3_marks = VALUES(cse_sub3_marks), cse_sub3_max = VALUES(cse_sub3_max), cse_sub3_credit = VALUES(cse_sub3_credit), cse_sub3_grade = VALUES(cse_sub3_grade), cse_sub3_point = VALUES(cse_sub3_point),
                cse_sub4_code = VALUES(cse_sub4_code), cse_sub4_name = VALUES(cse_sub4_name), cse_sub4_marks = VALUES(cse_sub4_marks), cse_sub4_max = VALUES(cse_sub4_max), cse_sub4_credit = VALUES(cse_sub4_credit), cse_sub4_grade = VALUES(cse_sub4_grade), cse_sub4_point = VALUES(cse_sub4_point),
                total_obtained = VALUES(total_obtained),
                total_max = VALUES(total_max),
                percentage = VALUES(percentage),
                gpa = VALUES(gpa),
                letter_grade = VALUES(letter_grade),
                status = VALUES(status),
                remarks = VALUES(remarks),
                updated_at = CURRENT_TIMESTAMP
        ";
        $params = [
            ':student_id'     => $studentId,
            ':roll_no'        => $rollNo,
            ':session'        => $session,
            ':semester'       => $semester,
            ':att_marks'      => $attendanceMarks,
            ':att_max'        => $attendanceMax,
            ':mid_marks'      => $midtermMarks,
            ':mid_max'        => $midtermMax,
            ':s1_code'        => $subjects[1]['code'], ':s1_name' => $subjects[1]['name'], ':s1_marks' => $subjects[1]['marks'], ':s1_max' => $subjects[1]['max'], ':s1_credit' => $subjects[1]['credit'], ':s1_grade' => $subjects[1]['grade'], ':s1_point' => $subjects[1]['point'],
            ':s2_code'        => $subjects[2]['code'], ':s2_name' => $subjects[2]['name'], ':s2_marks' => $subjects[2]['marks'], ':s2_max' => $subjects[2]['max'], ':s2_credit' => $subjects[2]['credit'], ':s2_grade' => $subjects[2]['grade'], ':s2_point' => $subjects[2]['point'],
            ':s3_code'        => $subjects[3]['code'], ':s3_name' => $subjects[3]['name'], ':s3_marks' => $subjects[3]['marks'], ':s3_max' => $subjects[3]['max'], ':s3_credit' => $subjects[3]['credit'], ':s3_grade' => $subjects[3]['grade'], ':s3_point' => $subjects[3]['point'],
            ':s4_code'        => $subjects[4]['code'], ':s4_name' => $subjects[4]['name'], ':s4_marks' => $subjects[4]['marks'], ':s4_max' => $subjects[4]['max'], ':s4_credit' => $subjects[4]['credit'], ':s4_grade' => $subjects[4]['grade'], ':s4_point' => $subjects[4]['point'],
            ':total_obtained' => $totalObtained,
            ':total_max'      => $totalMax,
            ':percentage'     => $percentage,
            ':gpa'            => $gpa,
            ':letter_grade'   => $letterGrade,
            ':status'         => $status,
            ':remarks'        => $remarks,
        ];
        $stmtInsert = $pdo->prepare($sqlMarksheet);
        $stmtInsert->execute($params);
        $savedId = (int)$pdo->lastInsertId();
    }

    $pdo->commit();

    // Calculate Cumulative CGPA
    $cgpa = calculateCumulativeCGPA($pdo, $rollNo, $gpa);

    sendJsonResponse(true, 'Student marksheet saved successfully!', [
        'id'             => $savedId,
        'roll_no'        => $rollNo,
        'full_name'      => $fullName,
        'session'        => $session,
        'semester'       => $semester,
        'total_obtained' => $totalObtained,
        'total_max'      => $totalMax,
        'percentage'     => $percentage,
        'gpa'            => $gpa,
        'cgpa'           => $cgpa,
        'letter_grade'   => $letterGrade,
        'status'         => $status,
        'remarks'        => $remarks,
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Failed to save marks: " . $e->getMessage());
    sendJsonResponse(false, 'Database error while saving marks: ' . $e->getMessage(), null, 500);
}

