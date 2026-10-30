/**
 * University Marks Grading System - Admin Portal Client Script
 *
 * Simple Flexbox UI logic (No Animations, No Emoji, Pure SVG)
 */

'use strict';

const API_BASE = window.location.pathname.includes('/frontend/') 
    ? '../backend/api' 
    : 'backend/api';

let currentEditingId = null;
let allRecords = [];

document.addEventListener('DOMContentLoaded', () => {
    initAdmin();
});

async function initAdmin() {
    setupAuthListeners();
    setupTabListeners();
    setupMarksFormListeners();
    setupRecordsListeners();

    await checkAuthStatus();
}

/**
 * Checks session authentication status
 */
async function checkAuthStatus() {
    try {
        const response = await fetch(`${API_BASE}/check_auth.php`, { credentials: 'same-origin' });
        const json = await response.json();

        if (json.success && json.data && json.data.is_logged_in) {
            showDashboard(json.data.full_name || 'Administrator');
        } else {
            showLoginForm();
        }
    } catch (err) {
        showLoginForm();
    }
}

/**
 * Auth listeners (Login, Logout, Demo fill)
 */
function setupAuthListeners() {
    const loginForm = document.getElementById('loginForm');
    const logoutBtn = document.getElementById('adminLogoutBtn');
    const demoFillBtn = document.getElementById('demoFillBtn');

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const username = document.getElementById('loginUsername').value.trim();
            const password = document.getElementById('loginPassword').value.trim();
            const submitBtn = document.getElementById('loginSubmitBtn');

            if (!username || !password) {
                showAuthAlert('Please enter both username and password.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.textContent = 'Signing In...';

            try {
                const response = await fetch(`${API_BASE}/admin_login.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                const json = await response.json();

                if (json.success) {
                    showToast('Welcome, Administrator!', 'success');
                    showDashboard(json.data ? json.data.full_name : 'Administrator');
                } else {
                    showAuthAlert(json.message || 'Invalid credentials');
                }
            } catch (err) {
                showAuthAlert('Unable to connect to authentication server.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Sign In to Dashboard';
            }
        });
    }

    if (demoFillBtn) {
        demoFillBtn.addEventListener('click', () => {
            document.getElementById('loginUsername').value = 'admin';
            document.getElementById('loginPassword').value = 'admin123';
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            try {
                await fetch(`${API_BASE}/admin_logout.php`, { method: 'POST' });
            } catch (err) {
                console.warn(err);
            }
            showToast('Logged out successfully', 'info');
            showLoginForm();
        });
    }
}

function showLoginForm() {
    document.getElementById('authSection').style.display = 'block';
    document.getElementById('dashboardSection').style.display = 'none';
    document.getElementById('adminNavRight').style.display = 'none';
}

function showDashboard(adminName) {
    document.getElementById('authSection').style.display = 'none';
    document.getElementById('dashboardSection').style.display = 'block';
    document.getElementById('adminNavRight').style.display = 'flex';
    document.getElementById('adminDisplayUsername').textContent = adminName || 'Admin';

    loadAdminDropdowns();
    loadRecordsAndStats();
}

function showAuthAlert(msg) {
    const box = document.getElementById('loginAlert');
    if (box) {
        box.textContent = msg;
        box.style.display = 'block';
    }
}

/**
 * Tab Switching (Marks Entry vs Manage Marksheets)
 */
function setupTabListeners() {
    const tabBtns = document.querySelectorAll('.tab-btn');
    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            tabBtns.forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(c => c.classList.remove('active'));

            btn.classList.add('active');
            const target = btn.getAttribute('data-tab');
            const targetEl = document.getElementById(target);
            if (targetEl) targetEl.classList.add('active');

            if (target === 'tabRecords') {
                loadRecordsAndStats();
            }
        });
    });
}

/**
 * Loads dynamic dropdown options
 */
async function loadAdminDropdowns() {
    try {
        const res = await fetch(`${API_BASE}/get_dropdowns.php`);
        const json = await res.json();

        if (json.success && json.data) {
            const sessions = json.data.sessions || [];
            const semesters = json.data.semesters || [];

            populateSelect('entrySession', sessions, '-- Select Session --');
            populateSelect('filterSession', sessions, 'All Sessions');

            populateSelect('entrySemester', semesters, '-- Select Semester --');
            populateSelect('filterSemester', semesters, 'All Semesters');
        }
    } catch (e) {
        console.warn('Failed to load admin dropdowns', e);
    }
}

function populateSelect(elemId, items, placeholder) {
    const el = document.getElementById(elemId);
    if (!el) return;
    const currentVal = el.value;
    el.innerHTML = `<option value="">${placeholder}</option>`;
    items.forEach(item => {
        const opt = document.createElement('option');
        opt.value = item;
        opt.textContent = item;
        if (item === currentVal) opt.selected = true;
        el.appendChild(opt);
    });
}

/**
 * Setup Marks Form & Live Preview Calculations
 */
function setupMarksFormListeners() {
    const form = document.getElementById('marksEntryForm');
    const semesterSelect = document.getElementById('entrySemester');
    const resetBtn = document.getElementById('resetFormBtn');

    if (semesterSelect) {
        semesterSelect.addEventListener('change', async () => {
            const chosenSem = semesterSelect.value;
            if (!chosenSem) return;

            try {
                const res = await fetch(`${API_BASE}/get_dropdowns.php?semester=${encodeURIComponent(chosenSem)}`);
                const json = await res.json();
                if (json.success && json.data && Array.isArray(json.data.default_subjects)) {
                    applyDefaultSubjects(json.data.default_subjects);
                }
            } catch (err) {
                console.warn('Could not auto-populate semester subjects:', err);
            }
            updateLivePreview();
        });
    }

    const liveInputs = form.querySelectorAll('input[type="number"], input[type="text"], select');
    liveInputs.forEach(input => {
        input.addEventListener('input', updateLivePreview);
        input.addEventListener('change', updateLivePreview);
    });

    if (form) {
        form.addEventListener('submit', handleMarksSubmit);
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', resetMarksForm);
    }
}

function applyDefaultSubjects(subjects) {
    if (!Array.isArray(subjects) || subjects.length < 4) return;

    for (let i = 1; i <= 4; i++) {
        const sub = subjects[i - 1];
        if (sub) {
            const codeEl = document.getElementById(`sub${i}Code`);
            const nameEl = document.getElementById(`sub${i}Name`);
            const creditEl = document.getElementById(`sub${i}Credit`);
            const maxEl = document.getElementById(`sub${i}Max`);

            if (codeEl) codeEl.value = sub.code || `CSE-30${i}`;
            if (nameEl) nameEl.value = sub.name || `CSE Subject ${i}`;
            if (creditEl) creditEl.value = sub.credit || 3.0;
            if (maxEl) maxEl.value = sub.max || 100.0;
        }
    }
}

/**
 * Real-time Live Calculation
 */
function updateLivePreview() {
    const attMarks = Math.max(0, parseFloat(document.getElementById('entryAttendanceMarks').value) || 0);
    const attMax = Math.max(1, parseFloat(document.getElementById('entryAttendanceMax').value) || 10);

    const midMarks = Math.max(0, parseFloat(document.getElementById('entryMidtermMarks').value) || 0);
    const midMax = Math.max(1, parseFloat(document.getElementById('entryMidtermMax').value) || 20);

    let totalObtained = attMarks + midMarks;
    let totalMax = attMax + midMax;
    let totalCreditPoints = 0;
    let totalCredits = 0;
    let hasFailed = false;

    for (let i = 1; i <= 4; i++) {
        const marks = Math.max(0, parseFloat(document.getElementById(`sub${i}Marks`).value) || 0);
        const max = Math.max(1, parseFloat(document.getElementById(`sub${i}Max`).value) || 100);
        const credit = Math.max(0.5, parseFloat(document.getElementById(`sub${i}Credit`).value) || 3.0);

        totalObtained += marks;
        totalMax += max;

        const gradeData = calculateClientGrade(marks, max);
        totalCreditPoints += (gradeData.point * credit);
        totalCredits += credit;

        if (gradeData.grade === 'F') {
            hasFailed = true;
        }

        const liveSubBadge = document.getElementById(`liveSub${i}Grade`);
        if (liveSubBadge) {
            liveSubBadge.textContent = `${gradeData.grade} (${gradeData.point.toFixed(2)})`;
            liveSubBadge.className = `badge ${getGradeBadge(gradeData.grade)}`;
        }
    }

    const percentage = totalMax > 0 ? ((totalObtained / totalMax) * 100) : 0;
    const sgpa = totalCredits > 0 ? (totalCreditPoints / totalCredits) : 0;

    const elTotal = document.getElementById('liveTotalMarks');
    const elPct = document.getElementById('livePercentage');
    const elSgpa = document.getElementById('liveSGPA');
    const elStanding = document.getElementById('liveStanding');

    if (elTotal) elTotal.textContent = `${totalObtained.toFixed(1)} / ${totalMax.toFixed(0)}`;
    if (elPct) elPct.textContent = `${percentage.toFixed(1)}%`;
    if (elSgpa) elSgpa.textContent = sgpa.toFixed(2);

    if (elStanding) {
        if (hasFailed || sgpa < 2.0) {
            elStanding.textContent = 'Status: FAILED / REFERRED';
            elStanding.style.color = 'var(--danger)';
        } else if (sgpa >= 3.8) {
            elStanding.textContent = 'Status: Outstanding (A+)';
            elStanding.style.color = 'var(--success)';
        } else if (sgpa >= 3.5) {
            elStanding.textContent = 'Status: Excellent (A)';
            elStanding.style.color = 'var(--success)';
        } else if (sgpa >= 3.0) {
            elStanding.textContent = 'Status: Very Good (B+)';
            elStanding.style.color = 'var(--primary)';
        } else {
            elStanding.textContent = 'Status: Passing';
            elStanding.style.color = 'var(--text-muted)';
        }
    }
}

function calculateClientGrade(obtained, max) {
    const pct = (obtained / max) * 100;
    if (pct >= 80) return { grade: 'A+', point: 4.00 };
    if (pct >= 75) return { grade: 'A',  point: 3.75 };
    if (pct >= 70) return { grade: 'A-', point: 3.50 };
    if (pct >= 65) return { grade: 'B+', point: 3.25 };
    if (pct >= 60) return { grade: 'B',  point: 3.00 };
    if (pct >= 55) return { grade: 'B-', point: 2.75 };
    if (pct >= 50) return { grade: 'C+', point: 2.50 };
    if (pct >= 45) return { grade: 'C',  point: 2.25 };
    if (pct >= 40) return { grade: 'D',  point: 2.00 };
    return { grade: 'F', point: 0.00 };
}

/**
 * Saves or updates student marksheet
 */
async function handleMarksSubmit(e) {
    e.preventDefault();

    const submitBtn = document.getElementById('saveMarksBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = `<span>Saving Record...</span>`;

    const payload = {
        id: currentEditingId,
        roll_no: document.getElementById('entryRollNo').value.trim(),
        full_name: document.getElementById('entryFullName').value.trim(),
        registration_no: document.getElementById('entryRegNo').value.trim(),
        session: document.getElementById('entrySession').value.trim(),
        semester: document.getElementById('entrySemester').value.trim(),
        department: document.getElementById('entryDepartment').value.trim() || 'Computer Science & Engineering',
        attendance_marks: document.getElementById('entryAttendanceMarks').value,
        attendance_max: document.getElementById('entryAttendanceMax').value,
        midterm_marks: document.getElementById('entryMidtermMarks').value,
        midterm_max: document.getElementById('entryMidtermMax').value,

        cse_sub1_code: document.getElementById('sub1Code').value.trim(),
        cse_sub1_name: document.getElementById('sub1Name').value.trim(),
        cse_sub1_credit: document.getElementById('sub1Credit').value,
        cse_sub1_marks: document.getElementById('sub1Marks').value,
        cse_sub1_max: document.getElementById('sub1Max').value,

        cse_sub2_code: document.getElementById('sub2Code').value.trim(),
        cse_sub2_name: document.getElementById('sub2Name').value.trim(),
        cse_sub2_credit: document.getElementById('sub2Credit').value,
        cse_sub2_marks: document.getElementById('sub2Marks').value,
        cse_sub2_max: document.getElementById('sub2Max').value,

        cse_sub3_code: document.getElementById('sub3Code').value.trim(),
        cse_sub3_name: document.getElementById('sub3Name').value.trim(),
        cse_sub3_credit: document.getElementById('sub3Credit').value,
        cse_sub3_marks: document.getElementById('sub3Marks').value,
        cse_sub3_max: document.getElementById('sub3Max').value,

        cse_sub4_code: document.getElementById('sub4Code').value.trim(),
        cse_sub4_name: document.getElementById('sub4Name').value.trim(),
        cse_sub4_credit: document.getElementById('sub4Credit').value,
        cse_sub4_marks: document.getElementById('sub4Marks').value,
        cse_sub4_max: document.getElementById('sub4Max').value,
    };

    try {
        const response = await fetch(`${API_BASE}/save_marks.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await response.json();

        if (json.success) {
            showToast(currentEditingId ? 'Marksheet updated successfully!' : 'New marksheet saved successfully!', 'success');
            resetMarksForm();
            loadRecordsAndStats();
        } else {
            showToast(json.message || 'Failed to save marks', 'error');
        }
    } catch (err) {
        showToast('Server communication error. Check MySQL database connection.', 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `
            <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
            <span>Save Marksheet &amp; Calculate</span>
        `;
    }
}

function resetMarksForm() {
    currentEditingId = null;
    document.getElementById('marksEntryForm').reset();
    document.getElementById('formModeBadge').textContent = 'New Entry';
    document.getElementById('formModeBadge').className = 'badge badge-green';
    document.getElementById('saveMarksBtn').innerHTML = `
        <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
        <span>Save Marksheet &amp; Calculate</span>
    `;
    updateLivePreview();
}

/**
 * Loads records and statistics
 */
async function loadRecordsAndStats() {
    const search = document.getElementById('filterSearch').value.trim();
    const semester = document.getElementById('filterSemester').value.trim();
    const session = document.getElementById('filterSession').value.trim();

    try {
        const url = `${API_BASE}/get_students.php?search=${encodeURIComponent(search)}&semester=${encodeURIComponent(semester)}&session=${encodeURIComponent(session)}`;
        const res = await fetch(url);
        const json = await res.json();

        if (json.success && json.data) {
            allRecords = json.data.records || [];
            renderStats(json.data.stats || {});
            renderRecordsTable(allRecords);
        }
    } catch (err) {
        console.warn('Could not load student records:', err);
    }
}

function renderStats(stats) {
    document.getElementById('statTotalRecords').textContent = stats.total_records || 0;
    document.getElementById('statTotalStudents').textContent = stats.total_students || 0;
    document.getElementById('statAvgGPA').textContent = (stats.average_gpa || 0).toFixed(2);
    document.getElementById('statPassRate').textContent = `${(stats.pass_percentage || 0).toFixed(1)}%`;
}

function renderRecordsTable(records) {
    const tbody = document.getElementById('recordsTableBody');
    tbody.innerHTML = '';

    if (!records.length) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center" style="padding: 1.5rem; color: #64748b;">No records found.</td></tr>`;
        return;
    }

    records.forEach(rec => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${escapeHtml(rec.roll_no)}</strong></td>
            <td>${escapeHtml(rec.full_name)}</td>
            <td>${escapeHtml(rec.session)}</td>
            <td>${escapeHtml(rec.semester)}</td>
            <td class="text-center">${parseFloat(rec.total_obtained).toFixed(1)} / ${parseFloat(rec.total_max).toFixed(0)}</td>
            <td class="text-center" style="font-weight:700; color:var(--primary);">${parseFloat(rec.gpa).toFixed(2)}</td>
            <td class="text-center"><span class="badge ${getGradeBadge(rec.letter_grade)}">${escapeHtml(rec.letter_grade)}</span></td>
            <td class="text-center"><span class="badge ${rec.status === 'PASSED' ? 'badge-green' : 'badge-red'}">${escapeHtml(rec.status)}</span></td>
            <td class="text-center">
                <div class="table-btn-flex">
                    <button type="button" class="btn-small btn-edit" title="Edit Marksheet" onclick="editRecord(${rec.id})">
                        <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                        <span>Edit</span>
                    </button>
                    <button type="button" class="btn-small btn-view" title="View Full Marksheet" onclick="viewRecordModal(${rec.id})">
                        <svg viewBox="0 0 24 24"><path d="M12 4.5C7 4.5 2.73 7.61 1 12c1.73 4.39 6 7.5 11 7.5s9.27-3.11 11-7.5c-1.73-4.39-6-7.5-11-7.5zM12 17c-2.76 0-5-2.24-5-5s2.24-5 5-5 5 2.24 5 5-2.24 5-5 5zm0-8c-1.66 0-3 1.34-3 3s1.34 3 3 3 3-1.34 3-3-1.34-3-3-3z"/></svg>
                        <span>View</span>
                    </button>
                    <button type="button" class="btn-small btn-del" title="Delete Record" onclick="confirmDeleteRecord(${rec.id}, '${escapeHtml(rec.roll_no)}')">
                        <svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/></svg>
                        <span>Delete</span>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function setupRecordsListeners() {
    const searchInput = document.getElementById('filterSearch');
    const semSelect = document.getElementById('filterSemester');
    const sessSelect = document.getElementById('filterSession');
    const clearBtn = document.getElementById('clearFiltersBtn');

    if (searchInput) searchInput.addEventListener('input', debounce(loadRecordsAndStats, 300));
    if (semSelect) semSelect.addEventListener('change', loadRecordsAndStats);
    if (sessSelect) sessSelect.addEventListener('change', loadRecordsAndStats);

    if (clearBtn) {
        clearBtn.addEventListener('click', () => {
            if (searchInput) searchInput.value = '';
            if (semSelect) semSelect.value = '';
            if (sessSelect) sessSelect.value = '';
            loadRecordsAndStats();
        });
    }

    document.querySelectorAll('.modal-close-trigger').forEach(el => {
        el.addEventListener('click', () => {
            document.querySelectorAll('.modal-backdrop').forEach(m => m.classList.remove('active'));
        });
    });
}

window.editRecord = async function(id) {
    try {
        const res = await fetch(`${API_BASE}/get_students.php?id=${id}`);
        const json = await res.json();

        if (json.success && json.data) {
            const r = json.data;
            currentEditingId = r.id;

            document.querySelector('.tab-btn[data-tab="tabEntry"]').click();

            document.getElementById('entryRollNo').value = r.roll_no;
            document.getElementById('entryFullName').value = r.full_name;
            document.getElementById('entryRegNo').value = r.registration_no || '';
            document.getElementById('entryDepartment').value = r.department || 'Computer Science & Engineering';
            document.getElementById('entrySession').value = r.session;
            document.getElementById('entrySemester').value = r.semester;

            document.getElementById('entryAttendanceMarks').value = r.attendance_marks;
            document.getElementById('entryAttendanceMax').value = r.attendance_max;
            document.getElementById('entryMidtermMarks').value = r.midterm_marks;
            document.getElementById('entryMidtermMax').value = r.midterm_max;

            for (let i = 1; i <= 4; i++) {
                document.getElementById(`sub${i}Code`).value = r[`cse_sub${i}_code`];
                document.getElementById(`sub${i}Name`).value = r[`cse_sub${i}_name`];
                document.getElementById(`sub${i}Credit`).value = r[`cse_sub${i}_credit`];
                document.getElementById(`sub${i}Marks`).value = r[`cse_sub${i}_marks`];
                document.getElementById(`sub${i}Max`).value = r[`cse_sub${i}_max`];
            }

            document.getElementById('formModeBadge').textContent = `Editing (ID: ${r.id})`;
            document.getElementById('formModeBadge').className = 'badge badge-amber';
            document.getElementById('saveMarksBtn').innerHTML = `
                <svg viewBox="0 0 24 24"><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>
                <span>Update Marksheet</span>
            `;

            updateLivePreview();
            window.scrollTo({ top: 120, behavior: 'auto' });
        }
    } catch (e) {
        showToast('Failed to load record for editing', 'error');
    }
};

window.viewRecordModal = async function(id) {
    try {
        const res = await fetch(`${API_BASE}/get_students.php?id=${id}`);
        const json = await res.json();

        if (json.success && json.data) {
            const r = json.data;
            const content = document.getElementById('previewModalBody');
            content.innerHTML = `
                <div style="text-align:center; margin-bottom:1rem;">
                    <h3 style="color:var(--primary-dark); font-size:1.2rem;">${escapeHtml(r.full_name)}</h3>
                    <p style="color:#64748b; font-size:0.84rem;">Roll: <strong>${escapeHtml(r.roll_no)}</strong> &bull; ${escapeHtml(r.semester)} (${escapeHtml(r.session)})</p>
                </div>

                <div class="metrics-flex-row" style="margin-bottom:1rem;">
                    <div class="metric-flex-box">
                        <div class="metric-lbl">Total Marks</div>
                        <div class="metric-num" style="font-size:1.2rem;">${parseFloat(r.total_obtained).toFixed(1)} / ${parseFloat(r.total_max).toFixed(0)}</div>
                    </div>
                    <div class="metric-flex-box">
                        <div class="metric-lbl">Percentage</div>
                        <div class="metric-num" style="font-size:1.2rem;">${parseFloat(r.percentage).toFixed(1)}%</div>
                    </div>
                    <div class="metric-flex-box highlight">
                        <div class="metric-lbl">SGPA</div>
                        <div class="metric-num" style="font-size:1.2rem;">${parseFloat(r.gpa).toFixed(2)}</div>
                    </div>
                    <div class="metric-flex-box">
                        <div class="metric-lbl">Grade</div>
                        <div class="metric-num" style="font-size:1.2rem;"><span class="badge ${getGradeBadge(r.letter_grade)}">${escapeHtml(r.letter_grade)}</span></div>
                    </div>
                </div>

                <h4 style="margin-bottom:0.5rem; font-size:0.9rem;">Course Breakdown:</h4>
                <table class="simple-table" style="font-size:0.82rem;">
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th class="text-center">Credits</th>
                            <th class="text-center">Marks</th>
                            <th class="text-center">Grade</th>
                            <th class="text-center">Point</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Class Attendance</td>
                            <td class="text-center">-</td>
                            <td class="text-center">${parseFloat(r.attendance_marks).toFixed(1)} / ${parseFloat(r.attendance_max).toFixed(0)}</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                        </tr>
                        <tr>
                            <td>Midterm Exam</td>
                            <td class="text-center">-</td>
                            <td class="text-center">${parseFloat(r.midterm_marks).toFixed(1)} / ${parseFloat(r.midterm_max).toFixed(0)}</td>
                            <td class="text-center">-</td>
                            <td class="text-center">-</td>
                        </tr>
                        <tr>
                            <td><strong>${escapeHtml(r.cse_sub1_code)}</strong>: ${escapeHtml(r.cse_sub1_name)}</td>
                            <td class="text-center">${parseFloat(r.cse_sub1_credit).toFixed(1)}</td>
                            <td class="text-center">${parseFloat(r.cse_sub1_marks).toFixed(1)} / ${parseFloat(r.cse_sub1_max).toFixed(0)}</td>
                            <td class="text-center"><span class="badge ${getGradeBadge(r.cse_sub1_grade)}">${escapeHtml(r.cse_sub1_grade)}</span></td>
                            <td class="text-center">${parseFloat(r.cse_sub1_point).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td><strong>${escapeHtml(r.cse_sub2_code)}</strong>: ${escapeHtml(r.cse_sub2_name)}</td>
                            <td class="text-center">${parseFloat(r.cse_sub2_credit).toFixed(1)}</td>
                            <td class="text-center">${parseFloat(r.cse_sub2_marks).toFixed(1)} / ${parseFloat(r.cse_sub2_max).toFixed(0)}</td>
                            <td class="text-center"><span class="badge ${getGradeBadge(r.cse_sub2_grade)}">${escapeHtml(r.cse_sub2_grade)}</span></td>
                            <td class="text-center">${parseFloat(r.cse_sub2_point).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td><strong>${escapeHtml(r.cse_sub3_code)}</strong>: ${escapeHtml(r.cse_sub3_name)}</td>
                            <td class="text-center">${parseFloat(r.cse_sub3_credit).toFixed(1)}</td>
                            <td class="text-center">${parseFloat(r.cse_sub3_marks).toFixed(1)} / ${parseFloat(r.cse_sub3_max).toFixed(0)}</td>
                            <td class="text-center"><span class="badge ${getGradeBadge(r.cse_sub3_grade)}">${escapeHtml(r.cse_sub3_grade)}</span></td>
                            <td class="text-center">${parseFloat(r.cse_sub3_point).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td><strong>${escapeHtml(r.cse_sub4_code)}</strong>: ${escapeHtml(r.cse_sub4_name)}</td>
                            <td class="text-center">${parseFloat(r.cse_sub4_credit).toFixed(1)}</td>
                            <td class="text-center">${parseFloat(r.cse_sub4_marks).toFixed(1)} / ${parseFloat(r.cse_sub4_max).toFixed(0)}</td>
                            <td class="text-center"><span class="badge ${getGradeBadge(r.cse_sub4_grade)}">${escapeHtml(r.cse_sub4_grade)}</span></td>
                            <td class="text-center">${parseFloat(r.cse_sub4_point).toFixed(2)}</td>
                        </tr>
                    </tbody>
                </table>
            `;
            document.getElementById('previewModal').classList.add('active');
        }
    } catch (e) {
        showToast('Error displaying record preview', 'error');
    }
};

window.confirmDeleteRecord = function(id, rollNo) {
    if (confirm(`Delete marksheet for Roll Number: ${rollNo}?`)) {
        deleteRecord(id);
    }
};

async function deleteRecord(id) {
    try {
        const res = await fetch(`${API_BASE}/delete_marks.php?id=${id}`, { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            showToast('Record deleted successfully', 'success');
            loadRecordsAndStats();
        } else {
            showToast(json.message || 'Failed to delete record', 'error');
        }
    } catch (e) {
        showToast('Error communicating with server', 'error');
    }
}

function showToast(message, type = 'info') {
    const container = document.getElementById('toastContainer');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `toast-item toast-${type}`;
    toast.innerHTML = `
        <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
        <span>${escapeHtml(message)}</span>
    `;

    container.appendChild(toast);
    setTimeout(() => toast.remove(), 3000);
}

function getGradeBadge(grade) {
    if (grade === 'A+' || grade === 'A' || grade === 'A-') return 'badge-green';
    if (grade === 'B+' || grade === 'B' || grade === 'B-') return 'badge-blue';
    if (grade === 'C+' || grade === 'C' || grade === 'D') return 'badge-amber';
    return 'badge-red';
}

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
