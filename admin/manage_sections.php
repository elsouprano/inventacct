<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php"); exit;
}
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch all programs with section counts
$programs = $pdo->query("
    SELECT p.*, COUNT(s.id) as section_count
    FROM programs p
    LEFT JOIN sections s ON s.program_id = p.id
    WHERE p.is_active = 1
    GROUP BY p.id
    ORDER BY p.code ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sections - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin_layout.css">
    <style>
        .two-panel { display: flex; gap: 20px; align-items: flex-start; }
        .panel-left  { width: 260px; flex-shrink: 0; }
        .panel-right { flex: 1; }
        .program-list { background: var(--white); border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); overflow: hidden; }
        .program-item {
            padding: 13px 16px; border-bottom: 1px solid var(--border-color);
            cursor: pointer; display: flex; justify-content: space-between;
            align-items: center; transition: background 0.15s;
        }
        .program-item:last-child { border-bottom: none; }
        .program-item:hover { background: #f0f4f8; }
        .program-item.active { background: var(--primary-color); color: white; }
        .program-item.active .sec-count { background: rgba(255,255,255,0.25); color: white; }
        .sec-count { font-size: 0.75em; background: #e9ecef; color: #555; padding: 2px 8px; border-radius: 10px; }
        .panel-card { background: var(--white); border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); padding: 20px; margin-bottom: 20px; }
        .section-table { width: 100%; border-collapse: collapse; }
        .section-table th, .section-table td { padding: 10px 12px; border-bottom: 1px solid var(--border-color); text-align: left; }
        .section-table th { background: #f8f9fa; color: var(--primary-color); font-size: 0.88em; }
        .badge { padding: 3px 9px; border-radius: 10px; font-size: 0.78em; font-weight: bold; }
        .badge-active   { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
        .btn-toggle { padding: 4px 12px; border-radius: 4px; cursor: pointer; border: none; font-size: 0.82em; }
        .btn-deactivate { background: #ffc107; color: #333; }
        .btn-activate   { background: #28a745; color: white; }
        .add-form { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-top: 5px; }
        .add-form .form-group { flex: 1; min-width: 120px; }
        .add-form label { display: block; font-size: 0.85em; font-weight: bold; color: #555; margin-bottom: 4px; }
        .add-form input, .add-form select {
            width: 100%; padding: 7px 9px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.9em;
        }
        .bulk-row { display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap; margin-top: 10px; }
        .bulk-row label { font-size: 0.85em; font-weight: bold; color: #555; display: block; margin-bottom: 4px; }
        .bulk-row input[type=number], .bulk-row select { padding: 6px 8px; border: 1px solid var(--border-color); border-radius: 4px; width: 70px; }
        #right-placeholder { color: #aaa; font-style: italic; padding: 40px 20px; text-align: center; }
        .toast {
            position: fixed; bottom: 24px; right: 24px;
            background: #28a745; color: white; padding: 12px 20px;
            border-radius: 6px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            font-size: 0.95em; opacity: 0; transition: opacity 0.3s; z-index: 9999;
        }
        .toast.error { background: #dc3545; }
        .toast.show  { opacity: 1; }
    </style>
</head>
<body>
<?php $pageTitle = 'Manage Sections'; ?>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include 'includes/admin_header.php'; ?>
        <div class="admin-content">

        <h1>Manage Sections</h1>

        <div class="two-panel">
            <!-- Left: Program List -->
            <div class="panel-left">
                <h3 style="margin-bottom:10px;font-size:1em;color:#555;">Programs</h3>
                <div class="program-list">
                    <?php foreach ($programs as $prog): ?>
                    <div class="program-item" data-program-id="<?php echo $prog['id']; ?>" data-program-name="<?php echo htmlspecialchars($prog['code']); ?>" onclick="selectProgram(this)">
                        <span style="font-weight:bold;"><?php echo htmlspecialchars($prog['code']); ?></span>
                        <span class="sec-count"><?php echo $prog['section_count']; ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Right: Sections Panel -->
            <div class="panel-right">
                <div id="right-placeholder">← Select a program to manage its sections.</div>
                <div id="right-content" style="display:none;">
                    <div class="panel-card">
                        <h3 id="panel-title" style="margin-top:0;margin-bottom:15px;"></h3>
                        <div style="overflow-x:auto;">
                            <table class="section-table">
                                <thead>
                                    <tr>
                                        <th>Section</th>
                                        <th>Year</th>
                                        <th>Students</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="sections-tbody">
                                    <tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px;">Loading...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Add single section -->
                    <div class="panel-card">
                        <h4 style="margin-top:0;margin-bottom:12px;">Add Section</h4>
                        <div class="add-form">
                            <div class="form-group">
                                <label>Section Code (X-Y)</label>
                                <input type="text" id="new-section-code" placeholder="e.g. 5-1" pattern="\d+-\d+">
                            </div>
                            <button class="btn" style="padding:7px 18px;width:auto;" onclick="addSection()">Add</button>
                        </div>
                        <div id="add-msg" style="font-size:0.85em;margin-top:8px;"></div>
                    </div>

                    <!-- Bulk add -->
                    <div class="panel-card">
                        <h4 style="margin-top:0;margin-bottom:12px;">Bulk Add Sections</h4>
                        <p style="font-size:0.85em;color:#666;margin-bottom:10px;">Add multiple sections for a year level at once.</p>
                        <div class="bulk-row">
                            <div>
                                <label>Year Level</label>
                                <select id="bulk-year">
                                    <option value="1">1</option>
                                    <option value="2">2</option>
                                    <option value="3">3</option>
                                    <option value="4">4</option>
                                </select>
                            </div>
                            <div style="display:flex;align-items:flex-end;gap:6px;">
                                <div>
                                    <label>From #</label>
                                    <input type="number" id="bulk-from" value="1" min="1">
                                </div>
                                <div style="padding-bottom:7px;color:#888;">to</div>
                                <div>
                                    <label>To #</label>
                                    <input type="number" id="bulk-to" value="4" min="1" max="30">
                                </div>
                            </div>
                            <button class="btn" style="padding:7px 18px;width:auto;" onclick="bulkAdd()">Add All</button>
                        </div>
                        <div id="bulk-msg" style="font-size:0.85em;margin-top:8px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="toast" id="toast"></div>

    <script>
    const CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
    let currentProgramId   = null;
    let currentProgramName = null;

    function showToast(msg, isError = false) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast' + (isError ? ' error' : '') + ' show';
        setTimeout(() => t.classList.remove('show'), 3000);
    }

    function selectProgram(el) {
        document.querySelectorAll('.program-item').forEach(p => p.classList.remove('active'));
        el.classList.add('active');
        currentProgramId   = el.dataset.programId;
        currentProgramName = el.dataset.programName;
        document.getElementById('right-placeholder').style.display = 'none';
        document.getElementById('right-content').style.display = 'block';
        document.getElementById('panel-title').textContent = currentProgramName + ' Sections';
        loadSections();
    }

    function loadSections() {
        if (!currentProgramId) return;
        fetch('actions/get_program_sections.php?program_id=' + currentProgramId)
            .then(r => r.json())
            .then(data => renderSections(data))
            .catch(() => showToast('Failed to load sections.', true));
    }

    function renderSections(sections) {
        const tbody = document.getElementById('sections-tbody');
        if (!sections.length) {
            tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;color:#aaa;padding:20px;">No sections yet. Add one below.</td></tr>';
            return;
        }
        tbody.innerHTML = sections.map(s => `
            <tr id="sec-row-${s.id}">
                <td><strong>${s.section_code}</strong></td>
                <td>${s.year_level}</td>
                <td>${s.student_count}</td>
                <td><span class="badge badge-${s.is_active == 1 ? 'active' : 'inactive'}">${s.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                <td>
                    <button class="btn-toggle ${s.is_active == 1 ? 'btn-deactivate' : 'btn-activate'}"
                        onclick="toggleSection(${s.id}, ${s.is_active})">
                        ${s.is_active == 1 ? 'Deactivate' : 'Activate'}
                    </button>
                </td>
            </tr>
        `).join('');
    }

    function toggleSection(sectionId, currentStatus) {
        if (currentStatus == 1 && !confirm('Deactivate this section? It will be hidden from registration.')) return;
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('section_id', sectionId);
        fetch('actions/toggle_section.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showToast(data.is_active ? 'Section activated.' : 'Section deactivated.');
                    loadSections();
                } else {
                    showToast(data.error || 'Error.', true);
                }
            })
            .catch(() => showToast('Network error.', true));
    }

    function addSection() {
        const code = document.getElementById('new-section-code').value.trim();
        const msg  = document.getElementById('add-msg');
        if (!code.match(/^\d+-\d+$/)) { msg.textContent = '⚠ Format must be X-Y (e.g. 3-1).'; msg.style.color = '#dc3545'; return; }
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('program_id', currentProgramId);
        fd.append('section_code', code);
        fetch('actions/add_section.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msg.textContent = '✓ Section added.'; msg.style.color = '#28a745';
                    document.getElementById('new-section-code').value = '';
                    loadSections();
                    // Update count badge in left panel
                    updateProgramCount();
                } else {
                    msg.textContent = '⚠ ' + (data.error || 'Error.'); msg.style.color = '#dc3545';
                }
            })
            .catch(() => { msg.textContent = 'Network error.'; msg.style.color = '#dc3545'; });
    }

    function bulkAdd() {
        const year = document.getElementById('bulk-year').value;
        const from = parseInt(document.getElementById('bulk-from').value);
        const to   = parseInt(document.getElementById('bulk-to').value);
        const msg  = document.getElementById('bulk-msg');
        if (to < from) { msg.textContent = '⚠ "To" must be >= "From".'; msg.style.color = '#dc3545'; return; }
        if ((to - from + 1) > 30) { msg.textContent = '⚠ Max 30 sections at a time.'; msg.style.color = '#dc3545'; return; }
        const fd = new FormData();
        fd.append('csrf_token', CSRF);
        fd.append('program_id', currentProgramId);
        fd.append('year_level', year);
        fd.append('from', from);
        fd.append('to', to);
        fetch('actions/bulk_add_sections.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msg.textContent = `✓ Added ${data.added}, skipped ${data.skipped} (already existed).`;
                    msg.style.color = '#28a745';
                    loadSections();
                    updateProgramCount();
                } else {
                    msg.textContent = '⚠ ' + (data.error || 'Error.'); msg.style.color = '#dc3545';
                }
            })
            .catch(() => { msg.textContent = 'Network error.'; msg.style.color = '#dc3545'; });
    }

    function updateProgramCount() {
        // Refresh section count badge for the active program row
        fetch('actions/get_program_sections.php?program_id=' + currentProgramId)
            .then(r => r.json())
            .then(data => {
                const el = document.querySelector(`.program-item.active .sec-count`);
                if (el) el.textContent = data.length;
            });
    }

    // Auto-select first program on load
    window.addEventListener('DOMContentLoaded', () => {
        const first = document.querySelector('.program-item');
        if (first) first.click();
    });
    </script>
        </div><!-- admin-content -->
    </div><!-- admin-main -->
</div><!-- admin-wrapper -->
</body>
</html>
