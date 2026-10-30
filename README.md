# University Student Marks & Grading System

A simple, decoupled academic grading system for university Computer Science & Engineering (CSE) departments. Built with pure **HTML, CSS (Flexbox), and Vanilla JavaScript** on the frontend, and **PHP & MySQL** on the backend.

---

## Features

### Student Portal (`frontend/index.html`)
- **Search Criteria**: Search results by **Semester dropdown**, **Roll Number input**, and **Session dropdown**.
- **Result Marksheet**:
  - Displays student details (Name, Roll, Registration No, Session, Semester, Status).
  - Displays **Total Marks** (Obtained / Maximum) and **Percentage**.
  - Displays **Continuous Assessment**: Class Attendance marks and Midterm Exam marks.
  - Displays **4 CSE Subjects**: Course Code, Course Title, Credits, Marks Obtained, Maximum Marks, Letter Grade, and Grade Point.
  - Displays **Semester GPA (SGPA)** and **Cumulative CGPA**.
  - Standard university 4.00 grading scale reference.

### Admin Portal (`frontend/admin.html`)
- **Admin Login**: Simple controller login (`admin` / `admin123`).
- **Marks Entry Form**:
  - Enter student Roll, Name, Session (dropdown), and Semester (dropdown).
  - Enter Continuous Assessment (Attendance & Midterms).
  - Enter 4 CSE subjects marks (course codes and titles auto-adapt to selected semester).
  - **Live Calculation**: Updates Total Marks, Percentage, Subject Grades, and SGPA in real-time as you type.
- **Manage Marksheet Records**: Filter by text search, semester, or session. Edit existing records, view details, or delete.

### Design Principles
- **Simple & Direct**: Clean borders, clear typography, no fancy animations.
- **CSS Flexbox**: Built using `display: flex` across headers, forms, result cards, and tables.
- **Pure SVGs**: Crisp inline SVG icons, no emoji characters.

---

## Directory Structure

```
marks-grading-system/
├── backend/
│   ├── api/
│   │   ├── admin_login.php      # Admin authentication
│   │   ├── admin_logout.php     # Session logout
│   │   ├── check_auth.php       # Verifies admin login state
│   │   ├── delete_marks.php     # Delete marksheet record
│   │   ├── get_dropdowns.php    # Active sessions, semesters & curriculum
│   │   ├── get_students.php     # List marksheet records for admin
│   │   ├── save_marks.php       # Validates and records marks + calculates GPA
│   │   └── search_result.php    # Student search by Roll, Semester, and Session
│   ├── config/
│   │   ├── cors.php             # JSON API headers, CORS & request parser
│   │   └── database.php         # PDO database connection
│   ├── database/
│   │   ├── schema.sql           # Database schema & sample seed data
│   │   └── seed.php             # CLI database installer tool
│   └── helpers/
│       └── grading.php          # 4.00 grading formulas & GPA logic
│
├── frontend/
│   ├── index.html               # Student Result Search & Marksheet Portal
│   ├── admin.html               # Admin Marks Entry & Management Portal
│   ├── css/
│   │   ├── style.css            # Flexbox layout & marksheet styling
│   │   └── admin.css            # Admin flexbox split layout
│   └── js/
│       ├── student.js           # Student search logic & dynamic DOM rendering
│       └── admin.js             # Real-time live GPA calculation & CRUD
│
├── docs/
│   └── SYSTEM_DOCUMENTATION.md  # Detailed system design & UI/UX documentation
└── README.md
```

---

## Quick Start

### 1. Database Setup
```bash
# Using CLI seeder:
php backend/database/seed.php

# Or using MySQL CLI:
mysql -u root -p < backend/database/schema.sql
```

### 2. Start Web Server
```bash
php -S localhost:8000
```
*(Or put the `marks-grading-system` folder in XAMPP `htdocs` or LAMP `/var/www/html`).*

### 3. Open in Browser
- **Student Search**: `http://localhost:8000/frontend/index.html`
- **Admin Portal**: `http://localhost:8000/frontend/admin.html`

### 4. Default Credentials & Sample Searches
- **Admin Login**: `admin` / `admin123`
- **Sample Students for Instant Search**:
  - Roll: `CSE-2021-001` | Semester: `5th Semester` | Session: `2021-2022`
  - Roll: `CSE-2021-002` | Semester: `5th Semester` | Session: `2021-2022`
  - Roll: `101` | Semester: `3rd Semester` | Session: `2021-2022`
