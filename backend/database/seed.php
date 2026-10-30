<?php
/**
 * Database Migration & Seeder CLI Tool
 *
 * Usage: php backend/database/seed.php
 * Executes schema.sql and populates sample students, marksheets, and admin account.
 */

declare(strict_types=1);

$host     = getenv('DB_HOST') ?: '127.0.0.1';
$port     = getenv('DB_PORT') ?: '3306';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

echo "\n🎓 University Marks Grading System - Database Installer\n";
echo "========================================================\n";
echo "Connecting to MySQL server on {$host}:{$port} as '{$username}'...\n";

try {
    // Connect without selecting database first
    $pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "✓ Connected to MySQL server successfully.\n";

    $sqlFile = __DIR__ . '/schema.sql';
    if (!file_exists($sqlFile)) {
        die("✗ Error: schema.sql not found at {$sqlFile}\n");
    }

    echo "Reading schema.sql file...\n";
    $sql = file_get_contents($sqlFile);

    echo "Executing database migration and table population...\n";
    $pdo->exec($sql);

    echo "✓ Database 'varsity_grading_db' and tables created successfully!\n";
    echo "✓ Sample students, marksheets, and administrator account seeded.\n";
    echo "--------------------------------------------------------\n";
    echo "Admin Login Credentials:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    echo "Sample Roll Numbers for Student Search:\n";
    echo " - Roll: CSE-2021-001 | Semester: 5th Semester | Session: 2021-2022\n";
    echo " - Roll: CSE-2021-002 | Semester: 5th Semester | Session: 2021-2022\n";
    echo " - Roll: 101          | Semester: 3rd Semester | Session: 2021-2022\n";
    echo "========================================================\n\n";

} catch (PDOException $e) {
    echo "✗ Database setup error: " . $e->getMessage() . "\n";
    echo "Tip: Make sure MySQL is running in XAMPP/WAMP/LAMP or specify DB_HOST, DB_USER, DB_PASS environment variables.\n\n";
    exit(1);
}

