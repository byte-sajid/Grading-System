/**
 * University Marks Grading System - Student Portal Client Script
 *
 * Simple Flexbox UI logic (No Animations, No Emoji, Clean SVG/Text)
 */

'use strict';

const API_BASE = window.location.pathname.includes('/frontend/') 
    ? '../backend/api' 
    : 'backend/api';

document.addEventListener('DOMContentLoaded', () => {
    initPortal();
});

function initPortal() {
    loadDropdowns();
    setupEventListeners();
}

/**
 * Loads dynamic sessions and semesters dropdowns
 */
async function loadDropdowns() {
    const sessionSelect = document.getElementById('searchSession');
    const semesterSelect = document.getElementById('searchSemester');

    try {
        const response = await fetch(`${API_BASE}/get_dropdowns.php`);
        const json = await response.json();

        if (json.success && json.data) {
            if (sessionSelect && Array.isArray(json.data.sessions)) {
                sessionSelect.innerHTML = '<option value="">-- Select Session --</option>';
                json.data.sessions.forEach(sess => {
                    const opt = document.createElement('option');
                    opt.value = sess;
                    opt.textContent = sess;
                    if (sess === '2021-2022') opt.selected = true;
                    sessionSelect.appendChild(opt);
                });
            }

            if (semesterSelect && Array.isArray(json.data.semesters)) {
                semesterSelect.innerHTML = '<option value="">-- Select Semester --</option>';
                json.data.semesters.forEach(sem => {
                    const opt = document.createElement('option');
                    opt.value = sem;
                    opt.textContent = sem;
                    if (sem === '5th Semester') opt.selected = true;
                    semesterSelect.appendChild(opt);
                });
            }
        }
    } catch (error) {
        console.warn('Using HTML fallback dropdown options');
    }
}

/**
 * Sets up event listeners
 */
function setupEventListeners() {
    const form = document.getElementById('searchForm');
    const resetBtn = document.getElementById('resetSearchBtn');

    if (form) {
        form.addEventListener('submit', handleSearchSubmit);
    }

    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            document.getElementById('resultContainer').style.display = 'none';
            document.getElementById('searchRoll').focus();
        });
    }

    // Quick test preset chips
    document.querySelectorAll('.sample-chip').forEach(btn => {
        btn.addEventListener('click', () => {
            const roll = btn.getAttribute('data-roll');
            const sem = btn.getAttribute('data-sem');
            const sess = btn.getAttribute('data-sess');

            if (roll) document.getElementById('searchRoll').value = roll;
            if (sem) document.getElementById('searchSemester').value = sem;
            if (sess) document.getElementById('searchSession').value = sess;

            if (form) {
                form.dispatchEvent(new Event('submit'));
            }
        });
    });
}

/**
 * Handles student search submission
 */
async function handleSearchSubmit(e) {
    e.preventDefault();

    const rollNo = document.getElementById('searchRoll').value.trim();
    const semester = document.getElementById('searchSemester').value.trim();
    const session = document.getElementById('searchSession').value.trim();

    const submitBtn = document.getElementById('searchBtn');
    const resultContainer = document.getElementById('resultContainer');

    if (!rollNo || !semester || !session) {
        showAlert('Please provide Roll Number, Semester, and Academic Session.', 'danger');
        return;
    }

    clearAlert();
    submitBtn.disabled = true;
    submitBtn.innerHTML = `
        <svg style="width:16px;height:16px;fill:currentColor;" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
        <span>Searching...</span>
    `;

    try {
        const url = `${API_BASE}/search_result.php?roll_no=${encodeURIComponent(rollNo)}&semester=${encodeURIComponent(semester)}&session=${encodeURIComponent(session)}`;
        const response = await fetch(url);
        const data = await response.json();

        if (data.success && data.data) {
            renderMarksheet(data.data);
            resultContainer.style.display = 'block';
            window.scrollTo({ top: resultContainer.offsetTop - 20, behavior: 'auto' });
        } else {
            resultContainer.style.display = 'none';
            showAlert(data.message || 'No result found matching your query.', 'danger');
        }
    } catch (err) {
        resultContainer.style.display = 'none';
        showAlert('Failed to connect to university grading database. Please verify backend server and MySQL connection.', 'danger');
    } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = `
            <svg style="width:16px;height:16px;fill:currentColor;" viewBox="0 0 24 24"><path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>
            <span>Search Result</span>
        `;
    }
}

/**
 * Renders the marksheet DOM
 */
function renderMarksheet(data) {
    const student = data.student || {};
    const summary = data.summary || {};
    const continuous = data.continuous_assessment || [];
    const subjects = data.subjects || [];
    const history = data.academic_history || [];

    // Student Bio
    document.getElementById('resFullName').textContent = student.full_name || '-';
    document.getElementById('resRollNo').textContent = student.roll_no || '-';
    document.getElementById('resRegNo').textContent = student.registration_no || 'N/A';
    document.getElementById('resDepartment').textContent = student.department || 'Computer Science & Engineering';
    document.getElementById('resSession').textContent = student.session || '-';
    document.getElementById('resSemester').textContent = student.semester || '-';
    document.getElementById('resIssueDate').textContent = student.issue_date || '-';

    // Summary Metrics
    document.getElementById('resTotalMarks').textContent = `${summary.total_obtained.toFixed(2)} / ${summary.total_max.toFixed(2)}`;
    document.getElementById('resPercentage').textContent = `${summary.percentage.toFixed(2)}%`;
    document.getElementById('resSGPA').textContent = summary.sgpa.toFixed(2);
    document.getElementById('resCGPA').textContent = summary.cgpa.toFixed(2);

    const statusBadge = document.getElementById('resStatusBadge');
    if (summary.status === 'PASSED') {
        statusBadge.className = 'badge badge-green';
        statusBadge.textContent = 'PASSED';
    } else {
        statusBadge.className = 'badge badge-red';
        statusBadge.textContent = 'FAILED / REFERRED';
    }

    const remarksEl = document.getElementById('resRemarks');
    if (remarksEl) {
        remarksEl.textContent = `${summary.remarks} (Grade: ${summary.letter_grade})`;
    }

    // Continuous Assessment (Attendance & Midterm)
    const caTbody = document.getElementById('continuousAssessmentBody');
    caTbody.innerHTML = '';
    continuous.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>${escapeHtml(item.title)}</strong></td>
            <td class="text-center">${item.max.toFixed(2)}</td>
            <td class="text-center" style="font-weight:700; color:var(--primary);">${item.obtained.toFixed(2)}</td>
            <td class="text-center">${item.percentage.toFixed(1)}%</td>
            <td class="text-center"><span class="badge badge-green">Satisfactory</span></td>
        `;
        caTbody.appendChild(tr);
    });

    // CSE 4 Subjects
    const subTbody = document.getElementById('subjectsTableBody');
    subTbody.innerHTML = '';
    subjects.forEach((sub, idx) => {
        const gradeBadgeClass = getGradeBadge(sub.grade);
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-center" style="font-weight:700;">${idx + 1}</td>
            <td><strong>${escapeHtml(sub.code)}</strong></td>
            <td><strong>${escapeHtml(sub.name)}</strong></td>
            <td class="text-center">${sub.credit.toFixed(2)}</td>
            <td class="text-center">${sub.max.toFixed(2)}</td>
            <td class="text-center" style="font-weight:700;">${sub.obtained.toFixed(2)}</td>
            <td class="text-center"><span class="badge ${gradeBadgeClass}">${escapeHtml(sub.grade)}</span></td>
            <td class="text-center" style="font-weight:700;">${sub.point.toFixed(2)}</td>
        `;
        subTbody.appendChild(tr);
    });

    // Multi-Semester History
    const historySection = document.getElementById('academicHistorySection');
    const historyTbody = document.getElementById('academicHistoryBody');
    if (history.length > 1) {
        historyTbody.innerHTML = '';
        history.forEach(rec => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${escapeHtml(rec.semester)}</strong></td>
                <td>${escapeHtml(rec.session)}</td>
                <td class="text-center">${parseFloat(rec.total_obtained).toFixed(2)} / ${parseFloat(rec.total_max).toFixed(2)}</td>
                <td class="text-center" style="font-weight:700; color:var(--primary);">${parseFloat(rec.gpa).toFixed(2)}</td>
                <td class="text-center"><span class="badge ${getGradeBadge(rec.letter_grade)}">${escapeHtml(rec.letter_grade)}</span></td>
                <td class="text-center"><span class="badge ${rec.status === 'PASSED' ? 'badge-green' : 'badge-red'}">${escapeHtml(rec.status)}</span></td>
            `;
            historyTbody.appendChild(tr);
        });
        historySection.style.display = 'block';
    } else {
        historySection.style.display = 'none';
    }
}

function getGradeBadge(grade) {
    if (grade === 'A+' || grade === 'A' || grade === 'A-') return 'badge-green';
    if (grade === 'B+' || grade === 'B' || grade === 'B-') return 'badge-blue';
    if (grade === 'C+' || grade === 'C' || grade === 'D') return 'badge-amber';
    return 'badge-red';
}

function showAlert(message, type = 'danger') {
    const alertBox = document.getElementById('searchAlert');
    if (!alertBox) return;

    alertBox.className = `alert alert-${type}`;
    alertBox.innerHTML = `
        <svg viewBox="0 0 24 24"><path d="M1 21h22L12 2 1 21zm12-3h-2v-2h2v2zm0-4h-2v-4h2v4z"/></svg>
        <div>${escapeHtml(message)}</div>
    `;
    alertBox.style.display = 'flex';
}

function clearAlert() {
    const alertBox = document.getElementById('searchAlert');
    if (alertBox) {
        alertBox.style.display = 'none';
        alertBox.innerHTML = '';
    }
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
