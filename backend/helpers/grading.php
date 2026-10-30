<?php
/**
 * University Grading and Academic Calculation Helper
 *
 * Implements the standard 4.00 CGPA grading policy used by
 * accredited Computer Science & Engineering university departments.
 */

declare(strict_types=1);

/**
 * Maps a percentage score to Letter Grade, Grade Point, and Remark
 *
 * Standard 4.00 UGC / University Scale:
 * 80% - 100% : A+ (4.00)
 * 75% - 79%  : A  (3.75)
 * 70% - 74%  : A- (3.50)
 * 65% - 69%  : B+ (3.25)
 * 60% - 64%  : B  (3.00)
 * 55% - 59%  : B- (2.75)
 * 50% - 54%  : C+ (2.50)
 * 45% - 49%  : C  (2.25)
 * 40% - 44%  : D  (2.00)
 * < 40%      : F  (0.00)
 */
function calculateSubjectGrade(float $obtained, float $maxMarks = 100.0): array
{
    if ($maxMarks <= 0) {
        $maxMarks = 100.0;
    }

    $percentage = ($obtained / $maxMarks) * 100.0;

    if ($percentage >= 80.0) {
        return ['grade' => 'A+', 'point' => 4.00, 'remark' => 'Outstanding'];
    } elseif ($percentage >= 75.0) {
        return ['grade' => 'A',  'point' => 3.75, 'remark' => 'Excellent'];
    } elseif ($percentage >= 70.0) {
        return ['grade' => 'A-', 'point' => 3.50, 'remark' => 'Very Good'];
    } elseif ($percentage >= 65.0) {
        return ['grade' => 'B+', 'point' => 3.25, 'remark' => 'Good'];
    } elseif ($percentage >= 60.0) {
        return ['grade' => 'B',  'point' => 3.00, 'remark' => 'Satisfactory'];
    } elseif ($percentage >= 55.0) {
        return ['grade' => 'B-', 'point' => 2.75, 'remark' => 'Above Average'];
    } elseif ($percentage >= 50.0) {
        return ['grade' => 'C+', 'point' => 2.50, 'remark' => 'Average'];
    } elseif ($percentage >= 45.0) {
        return ['grade' => 'C',  'point' => 2.25, 'remark' => 'Marginal Pass'];
    } elseif ($percentage >= 40.0) {
        return ['grade' => 'D',  'point' => 2.00, 'remark' => 'Pass'];
    } else {
        return ['grade' => 'F',  'point' => 0.00, 'remark' => 'Fail'];
    }
}

/**
 * Calculates Semester GPA (SGPA) based on subject grade points and credit hours
 * Formula: Sum(Grade Point * Credit) / Sum(Credits)
 *
 * @param array $subjects Array of items: [['point' => 3.75, 'credit' => 3.0], ...]
 * @return float SGPA rounded to 2 decimal places
 */
function calculateSemesterGPA(array $subjects): float
{
    $totalCreditPoints = 0.0;
    $totalCredits = 0.0;

    foreach ($subjects as $sub) {
        $credit = (float)($sub['credit'] ?? 3.0);
        $point = (float)($sub['point'] ?? 0.0);

        $totalCreditPoints += ($point * $credit);
        $totalCredits += $credit;
    }

    if ($totalCredits <= 0.0) {
        return 0.00;
    }

    return round($totalCreditPoints / $totalCredits, 2);
}

/**
 * Calculates Cumulative CGPA across all recorded semesters for a student
 *
 * @param PDO $pdo
 * @param string $rollNo
 * @param float $currentGPA Fallback if only 1 semester exists
 * @return float
 */
function calculateCumulativeCGPA(PDO $pdo, string $rollNo, float $currentGPA): float
{
    try {
        $stmt = $pdo->prepare("SELECT gpa FROM marksheet WHERE roll_no = :roll_no");
        $stmt->execute([':roll_no' => $rollNo]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($rows)) {
            return $currentGPA;
        }

        $sumGPA = 0.0;
        foreach ($rows as $row) {
            $sumGPA += (float)$row['gpa'];
        }

        return round($sumGPA / count($rows), 2);
    } catch (Exception $e) {
        return $currentGPA;
    }
}

/**
 * Returns overall letter grade and standing for a given GPA
 */
function getOverallStanding(float $gpa, bool $hasFailedSubject = false): array
{
    if ($hasFailedSubject || $gpa < 2.00) {
        return [
            'letter' => 'F',
            'status' => 'FAILED',
            'remarks' => 'Needs Improvement / Referred'
        ];
    }

    if ($gpa >= 3.80) {
        return ['letter' => 'A+', 'status' => 'PASSED', 'remarks' => 'First Class Honours (Outstanding)'];
    } elseif ($gpa >= 3.50) {
        return ['letter' => 'A',  'status' => 'PASSED', 'remarks' => 'First Class (Excellent)'];
    } elseif ($gpa >= 3.00) {
        return ['letter' => 'B+', 'status' => 'PASSED', 'remarks' => 'Second Class (Upper Division)'];
    } elseif ($gpa >= 2.50) {
        return ['letter' => 'B',  'status' => 'PASSED', 'remarks' => 'Second Class (Lower Division)'];
    } else {
        return ['letter' => 'C',  'status' => 'PASSED', 'remarks' => 'Third Division / Passing'];
    }
}

/**
 * Predefined 4 CSE Core Subjects per Semester
 * Allows automatic form population while allowing administrative override
 */
function getDefaultSubjectsForSemester(string $semester): array
{
    $semester = trim($semester);

    $curriculum = [
        '1st Semester' => [
            ['code' => 'CSE-111', 'name' => 'Structured Programming Language (C)', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-112', 'name' => 'Discrete Mathematics', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-113', 'name' => 'Electrical Circuits & Electronics', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-114', 'name' => 'Introduction to Computing & Logic', 'credit' => 3.0, 'max' => 100.0],
        ],
        '2nd Semester' => [
            ['code' => 'CSE-121', 'name' => 'Object Oriented Programming (Java)', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-122', 'name' => 'Data Structures', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-123', 'name' => 'Digital Logic & Circuit Design', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-124', 'name' => 'Linear Algebra & Calculus', 'credit' => 3.0, 'max' => 100.0],
        ],
        '3rd Semester' => [
            ['code' => 'CSE-211', 'name' => 'Algorithms & Complexity Analysis', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-212', 'name' => 'Database Management Systems', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-213', 'name' => 'Computer Architecture & Org.', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-214', 'name' => 'Theory of Computation', 'credit' => 3.0, 'max' => 100.0],
        ],
        '4th Semester' => [
            ['code' => 'CSE-221', 'name' => 'Operating Systems', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-222', 'name' => 'Software Engineering & UML', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-223', 'name' => 'Microprocessors & Interfacing', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-224', 'name' => 'Data Communications', 'credit' => 3.0, 'max' => 100.0],
        ],
        '5th Semester' => [
            ['code' => 'CSE-311', 'name' => 'Computer Networks', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-312', 'name' => 'Web Technologies & Applications', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-313', 'name' => 'Artificial Intelligence', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-314', 'name' => 'Compiler Design', 'credit' => 3.0, 'max' => 100.0],
        ],
        '6th Semester' => [
            ['code' => 'CSE-321', 'name' => 'Information & Cyber Security', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-322', 'name' => 'Machine Learning', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-323', 'name' => 'Computer Graphics & Multimedia', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-324', 'name' => 'Distributed Systems & Cloud', 'credit' => 3.0, 'max' => 100.0],
        ],
        '7th Semester' => [
            ['code' => 'CSE-411', 'name' => 'Cloud Computing & Virtualization', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-412', 'name' => 'Internet of Things (IoT)', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-413', 'name' => 'Big Data Analytics', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-414', 'name' => 'Mobile Application Development', 'credit' => 3.0, 'max' => 100.0],
        ],
        '8th Semester' => [
            ['code' => 'CSE-421', 'name' => 'Senior Capstone Project / Thesis', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-422', 'name' => 'Neural Networks & Deep Learning', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-423', 'name' => 'Natural Language Processing', 'credit' => 3.0, 'max' => 100.0],
            ['code' => 'CSE-424', 'name' => 'Software Testing & Quality Assurance', 'credit' => 3.0, 'max' => 100.0],
        ],
    ];

    // Case-insensitive / partial match support
    foreach ($curriculum as $semKey => $subjects) {
        if (stripos($semester, explode(' ', $semKey)[0]) !== false || stripos($semester, $semKey) !== false) {
            return $subjects;
        }
    }

    // Default fallback
    return [
        ['code' => 'CSE-301', 'name' => 'CSE Subject 1', 'credit' => 3.0, 'max' => 100.0],
        ['code' => 'CSE-302', 'name' => 'CSE Subject 2', 'credit' => 3.0, 'max' => 100.0],
        ['code' => 'CSE-303', 'name' => 'CSE Subject 3', 'credit' => 3.0, 'max' => 100.0],
        ['code' => 'CSE-304', 'name' => 'CSE Subject 4', 'credit' => 3.0, 'max' => 100.0],
    ];
}

