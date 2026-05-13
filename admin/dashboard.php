<?php
session_start();
require_once '../config/db.php';

// Session check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Role check
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch user data
$stmt = $pdo->prepare("SELECT last_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Programs for filter dropdown
$db_programs = $pdo->query("SELECT id, code FROM programs WHERE is_active = 1 ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);

// URL filter state (for pre-populating filters on load)
$f_q       = trim($_GET['q'] ?? '');
$f_program = trim($_GET['program'] ?? '');
$f_year    = trim($_GET['year_level'] ?? '');
$f_section = trim($_GET['section'] ?? '');
$f_status  = trim($_GET['submission_status'] ?? '');
$f_risk    = trim($_GET['risk_level'] ?? '');
$f_valid   = trim($_GET['validity_status'] ?? '');
$f_sort    = trim($_GET['sort_col'] ?? 'last_name');
$f_dir     = trim($_GET['sort_dir'] ?? 'ASC');
$f_page    = max(1, (int)($_GET['page'] ?? 1));
$f_pp      = in_array((int)($_GET['per_page'] ?? 25), [25,50,100]) ? (int)$_GET['per_page'] : 25;

// Stats Queries
$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$total_submitted = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM inventory_submissions WHERE is_complete = 1")->fetchColumn();
$pending = max(0, $total_students - $total_submitted);

$needs_counseling_count = $pdo->query("SELECT COUNT(DISTINCT s.user_id) FROM inventory_submissions s JOIN inventory_scores sc ON s.id = sc.submission_id WHERE sc.needs_counseling = 1")->fetchColumn();

$requires_review_count = $pdo->query("SELECT COUNT(*) FROM inventory_submissions WHERE validity_status = 'requires_review'")->fetchColumn();
$rejected_count = $pdo->query("SELECT COUNT(*) FROM inventory_submissions WHERE validity_status = 'rejected'")->fetchColumn();

$stmt_flagged = $pdo->query("
    SELECT s.id as submission_id, u.student_id, u.first_name, u.last_name, u.program, s.time_elapsed_seconds, s.validity_flags
    FROM inventory_submissions s
    JOIN users u ON s.user_id = u.id
    WHERE s.validity_status = 'requires_review'
    ORDER BY s.submitted_at DESC
");
$flagged_submissions = $stmt_flagged->fetchAll();

$urgent_high_count = $pdo->query("SELECT COUNT(*) FROM inventory_submissions WHERE risk_level IN ('urgent', 'high') AND is_complete = 1")->fetchColumn();
$moderate_count = $pdo->query("SELECT COUNT(*) FROM inventory_submissions WHERE risk_level = 'moderate' AND is_complete = 1")->fetchColumn();

$stmt_priority = $pdo->query("
    SELECT s.id as submission_id, u.id as user_id, u.student_id, u.first_name, u.last_name, u.program, 
           s.risk_level, s.submitted_at,
           (SELECT CONCAT(raw_score, ' (', interpretation, ')') FROM inventory_scores WHERE submission_id = s.id AND scale = 'dass21_depression') as dass_d,
           (SELECT CONCAT(raw_score, ' (', interpretation, ')') FROM inventory_scores WHERE submission_id = s.id AND scale = 'dass21_anxiety') as dass_a,
           (SELECT CONCAT(raw_score, ' (', interpretation, ')') FROM inventory_scores WHERE submission_id = s.id AND scale = 'dass21_stress') as dass_s
    FROM inventory_submissions s
    JOIN users u ON s.user_id = u.id
    WHERE s.risk_level IN ('urgent', 'high') AND s.is_complete = 1
    ORDER BY CASE WHEN s.risk_level = 'urgent' THEN 1 ELSE 2 END, s.submitted_at DESC
");
$priority_submissions = $stmt_priority->fetchAll();

$stmt_breakdown = $pdo->query("SELECT u.program, COUNT(s.id) as count FROM users u JOIN inventory_submissions s ON u.id = s.user_id WHERE u.role = 'student' AND s.is_complete = 1 GROUP BY u.program");
$breakdown = $stmt_breakdown->fetchAll();

// Active period
$active_period = $pdo->query("SELECT * FROM inventory_periods WHERE is_active = 1 LIMIT 1")->fetch();
$period_now = time();
$period_days_left = $active_period ? max(0, (int)ceil((strtotime($active_period['close_date']) - $period_now) / 86400)) : 0;
$period_is_open   = $active_period && $period_now >= strtotime($active_period['open_date']) && $period_now <= strtotime($active_period['close_date']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - CCT Inventory System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin_layout.css">
    <style>
        .filter-bar {
            background: var(--white);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-bar select {
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 4px;
        }
        .filter-bar button {
            padding: 8px 15px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .filter-bar a.reset {
            color: var(--error-color);
            text-decoration: none;
            font-size: 0.9em;
        }
        .table-container {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }
        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        th {
            background: #f8f9fa;
            color: var(--primary-color);
        }
        .badge {
            padding: 5px 10px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .badge-submitted { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-risk-none { background: #e2e3e5; color: #383d41; }
        .badge-risk-low { background: #cce5ff; color: #004085; }
        .badge-risk-moderate { background: #fff3cd; color: #856404; }
        .badge-risk-high { background: #fd7e14; color: #fff; }
        .badge-risk-urgent { background: #dc3545; color: #fff; }
        .actions {
            display: flex;
            gap: 10px;
        }
        .actions form { margin: 0; }
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8em;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            display: inline-block;
        }
        .btn-view { background: var(--primary-color); color: white; }
        .btn-mark { background: var(--accent-color); color: var(--primary-color); font-weight: bold;}
        .btn-delete { background: var(--error-color); color: white; }
        .breakdown-list { list-style: none; padding: 0; font-size: 0.9em; text-align: left; margin-top: 10px; }
        .breakdown-list li { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 5px 0; }
    </style>
</head>
<body>
<?php $pageTitle = 'Dashboard'; ?>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include 'includes/admin_header.php'; ?>
        <div class="admin-content">

        <h1>Welcome, Admin <?php echo htmlspecialchars($user['last_name']); ?>!</h1>
        <p class="subtitle">Administrator Dashboard</p>

        <?php if (!empty($priority_submissions)): ?>
        <h2 style="margin-top: 20px; margin-bottom: 20px; color: var(--error-color);">⚠ Priority — Requires Immediate Attention</h2>
        <div style="background: var(--white); border-radius: 8px; box-shadow: 0 2px 8px rgba(220,53,69,0.2); overflow-x: auto; margin-bottom: 30px; border: 2px solid var(--error-color);">
            <table class="student-table" style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #fff5f5; border-bottom: 2px solid #ffcaca; text-align: left;">
                        <th style="padding: 15px;">Student ID</th>
                        <th style="padding: 15px;">Name</th>
                        <th style="padding: 15px;">Program</th>
                        <th style="padding: 15px;">Risk Level</th>
                        <th style="padding: 15px;">DASS-21 Summary</th>
                        <th style="padding: 15px;">Time Submitted</th>
                        <th style="padding: 15px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($priority_submissions as $ps): 
                        $badge_class = $ps['risk_level'] === 'urgent' ? 'badge-risk-urgent' : 'badge-risk-high';
                        $dass_summary = "D: {$ps['dass_d']} | A: {$ps['dass_a']} | S: {$ps['dass_s']}";
                    ?>
                    <tr style="border-bottom: 1px solid #ffcaca;">
                        <td style="padding: 15px;"><?php echo htmlspecialchars($ps['student_id']); ?></td>
                        <td style="padding: 15px; font-weight: bold;"><?php echo htmlspecialchars($ps['first_name'] . ' ' . $ps['last_name']); ?></td>
                        <td style="padding: 15px;"><?php echo htmlspecialchars($ps['program']); ?></td>
                        <td style="padding: 15px;"><span class="badge <?php echo $badge_class; ?>"><?php echo strtoupper($ps['risk_level']); ?></span></td>
                        <td style="padding: 15px; font-size: 0.85em;"><?php echo htmlspecialchars($dass_summary); ?></td>
                        <td style="padding: 15px;"><?php echo date('M d, Y h:i A', strtotime($ps['submitted_at'])); ?></td>
                        <td style="padding: 15px;">
                            <a href="student_view.php?id=<?php echo $ps['user_id']; ?>" class="btn-sm" style="background: var(--error-color); color: white; text-decoration: none;">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <div class="card-grid" style="margin-bottom: 30px;">
            <div class="dashboard-card">
                <h3>Total Students</h3>
                <div class="stat"><?php echo $total_students; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Submitted</h3>
                <div class="stat"><?php echo $total_submitted; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>Pending</h3>
                <div class="stat"><?php echo $pending; ?></div>
            </div>
            <div class="dashboard-card" style="border-top-color: var(--error-color);">
                <h3 style="color: var(--error-color);">High/Urgent Risk</h3>
                <div class="stat"><?php echo $urgent_high_count; ?></div>
            </div>
            <div class="dashboard-card" style="border-top-color: #ffc107;">
                <h3 style="color: #ffc107;">Moderate Risk</h3>
                <div class="stat"><?php echo $moderate_count; ?></div>
            </div>
            <div class="dashboard-card" style="border-top-color: var(--error-color);">
                <h3 style="color: var(--error-color);">Needs Counseling</h3>
                <div class="stat"><?php echo $needs_counseling_count; ?></div>
            </div>
            <div class="dashboard-card" style="border-top-color: #ffc107;">
                <h3 style="color: #ffc107;">Requires Review</h3>
                <div class="stat"><?php echo $requires_review_count; ?></div>
            </div>
            <div class="dashboard-card" style="border-top-color: var(--error-color);">
                <h3 style="color: var(--error-color);">Rejected</h3>
                <div class="stat"><?php echo $rejected_count; ?></div>
            </div>
            <div class="dashboard-card">
                <h3>By Program</h3>
                <ul class="breakdown-list">
                    <?php if (empty($breakdown)): ?>
                        <li>No submissions yet</li>
                    <?php else: ?>
                        <?php foreach($breakdown as $row): ?>
                            <li><span><?php echo htmlspecialchars($row['program'] ?: 'Unknown'); ?></span> <strong><?php echo $row['count']; ?></strong></li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="dashboard-card" style="border-top-color: <?php echo $period_is_open ? '#28a745' : ($active_period ? '#ffc107' : '#aaa'); ?>;">
                <h3 style="color: <?php echo $period_is_open ? '#155724' : ($active_period ? '#856404' : '#888'); ?>;">Active Period</h3>
                <?php if ($active_period): ?>
                    <div style="font-size:0.85em;font-weight:bold;margin-bottom:5px;"><?php echo htmlspecialchars($active_period['label']); ?></div>
                    <div style="font-size:0.8em;color:#666;margin-bottom:4px;">Closes: <?php echo date('M d, Y', strtotime($active_period['close_date'])); ?></div>
                    <?php if ($period_is_open): ?>
                        <div style="font-size:0.8em;color:#28a745;"><?php echo $period_days_left; ?> day<?php echo $period_days_left !== 1 ? 's' : ''; ?> remaining</div>
                    <?php else: ?>
                        <div style="font-size:0.8em;color:#856404;">Upcoming / Closed</div>
                    <?php endif; ?>
                    <a href="manage_periods.php" style="font-size:0.8em;color:var(--primary-color);text-decoration:none;display:block;margin-top:8px;">Manage &rarr;</a>
                <?php else: ?>
                    <div style="color:#888;font-size:0.9em;">No active period.</div>
                    <a href="manage_periods.php" style="font-size:0.8em;color:var(--primary-color);text-decoration:none;display:block;margin-top:8px;">Create one &rarr;</a>
                <?php endif; ?>
            </div>
        </div>

        <h2 style="margin-top: 40px; margin-bottom: 20px; color: #ffc107;">Flagged Submissions</h2>
        <div style="background: var(--white); border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); overflow-x: auto; margin-bottom: 40px;">
            <table class="student-table" style="width: 100%; border-collapse: collapse; min-width: 800px;">
                <thead>
                    <tr style="background: #f8f9fa; border-bottom: 2px solid var(--border-color); text-align: left;">
                        <th style="padding: 15px;">Student ID</th>
                        <th style="padding: 15px;">Name</th>
                        <th style="padding: 15px;">Program</th>
                        <th style="padding: 15px;">Time Taken</th>
                        <th style="padding: 15px;">Flags</th>
                        <th style="padding: 15px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($flagged_submissions)): ?>
                        <tr><td colspan="6" style="padding: 15px; text-align: center; color: #888;">No flagged submissions.</td></tr>
                    <?php else: ?>
                        <?php foreach ($flagged_submissions as $fs): 
                            $time_formatted = floor($fs['time_elapsed_seconds'] / 60) . 'm ' . ($fs['time_elapsed_seconds'] % 60) . 's';
                            $flags = json_decode($fs['validity_flags'] ?: '[]', true);
                        ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 15px;"><?php echo htmlspecialchars($fs['student_id']); ?></td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($fs['first_name'] . ' ' . $fs['last_name']); ?></td>
                            <td style="padding: 15px;"><?php echo htmlspecialchars($fs['program']); ?></td>
                            <td style="padding: 15px; <?php echo $fs['time_elapsed_seconds'] < 300 ? 'color: var(--error-color); font-weight: bold;' : ''; ?>">
                                <?php echo $time_formatted; ?>
                            </td>
                            <td style="padding: 15px;">
                                <?php foreach($flags as $f): ?>
                                    <span class="badge badge-pending" style="display: block; margin-bottom: 5px; font-size: 0.8em;"><?php echo htmlspecialchars($f); ?></span>
                                <?php endforeach; ?>
                            </td>
                            <td style="padding: 15px;">
                                <a href="review_submission.php?id=<?php echo $fs['submission_id']; ?>" class="btn-sm" style="background: #ffc107; color: #333; text-decoration: none;">Review</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <h2 style="margin-bottom:16px;">All Students</h2>

        <!-- Search -->
        <div style="background:var(--white);padding:16px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.05);margin-bottom:12px;">
            <div style="position:relative;margin-bottom:12px;">
                <input type="text" id="search-input" placeholder="Search by Student ID, Name, or Email..." value="<?php echo htmlspecialchars($f_q); ?>"
                    style="width:100%;padding:10px 40px 10px 14px;border:1px solid var(--border-color);border-radius:6px;font-size:1em;box-sizing:border-box;">
                <span id="search-clear" onclick="clearSearch()" style="position:absolute;right:36px;top:50%;transform:translateY(-50%);cursor:pointer;color:#aaa;display:none;">✕</span>
                <span id="search-spin" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);display:none;font-size:1.1em;">⏳</span>
            </div>
            <!-- Filters -->
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <select id="f-program" onchange="loadSectionFilter(this.value)">
                    <option value="">All Programs</option>
                    <?php foreach($db_programs as $p): ?>
                    <option value="<?php echo htmlspecialchars($p['code']); ?>" data-id="<?php echo $p['id']; ?>" <?php if($f_program===$p['code']) echo 'selected'; ?>><?php echo htmlspecialchars($p['code']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="f-year">
                    <option value="">All Years</option>
                    <?php foreach([1,2,3,4] as $y): ?><option value="<?php echo $y; ?>" <?php if($f_year==$y) echo 'selected'; ?>>Year <?php echo $y; ?></option><?php endforeach; ?>
                </select>
                <select id="f-section"><option value="">All Sections</option></select>
                <select id="f-status">
                    <option value="">All Statuses</option>
                    <option value="submitted" <?php if($f_status==='submitted') echo 'selected'; ?>>Submitted</option>
                    <option value="pending" <?php if($f_status==='pending') echo 'selected'; ?>>Pending</option>
                </select>
                <select id="f-risk">
                    <option value="">All Risk</option>
                    <?php foreach(['none','low','moderate','high','urgent'] as $r): ?><option value="<?php echo $r; ?>" <?php if($f_risk===$r) echo 'selected'; ?>><?php echo ucfirst($r); ?></option><?php endforeach; ?>
                </select>
                <select id="f-validity">
                    <option value="">All Validity</option>
                    <?php foreach(['valid'=>'Valid','requires_review'=>'Requires Review','rejected'=>'Rejected','resubmit'=>'Resubmit'] as $v=>$vl): ?><option value="<?php echo $v; ?>" <?php if($f_valid===$v) echo 'selected'; ?>><?php echo $vl; ?></option><?php endforeach; ?>
                </select>
                <button class="btn" style="padding:8px 16px;width:auto;" onclick="doSearch()">Search</button>
                <button style="padding:8px 14px;border:1px solid #ccc;border-radius:4px;background:#fff;cursor:pointer;" onclick="clearAll()">Clear All</button>
                <select id="f-pp" onchange="doSearch()" style="margin-left:auto;">
                    <?php foreach([25,50,100] as $pp): ?><option value="<?php echo $pp; ?>" <?php if($f_pp==$pp) echo 'selected'; ?>>Show <?php echo $pp; ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Bulk actions + results count + export -->
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px;">
            <div style="display:flex;align-items:center;gap:8px;">
                <select id="bulk-action" style="padding:6px 10px;border:1px solid var(--border-color);border-radius:4px;">
                    <option value="">Bulk Action</option>
                    <option value="mark_complete">Mark Complete</option>
                    <option value="export_csv">Export CSV</option>
                    <option value="delete">Delete</option>
                </select>
                <button onclick="doBulk()" style="padding:6px 14px;border:none;border-radius:4px;background:var(--primary-color);color:#fff;cursor:pointer;">Apply</button>
                <span id="sel-count" style="font-size:0.85em;color:#666;"></span>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span id="results-count" style="font-size:0.88em;color:#666;"></span>
                <button onclick="exportAll()" style="padding:6px 14px;border:1px solid var(--border-color);border-radius:4px;background:#fff;cursor:pointer;font-size:0.88em;">⬇ Export Current View</button>
            </div>
        </div>

        <!-- Table -->
        <div style="background:var(--white);border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,.05);overflow-x:auto;">
            <table id="student-table" style="width:100%;border-collapse:collapse;min-width:900px;">
                <thead>
                    <tr style="background:#f8f9fa;">
                        <th style="padding:12px 8px;width:36px;"><input type="checkbox" id="chk-all" onclick="toggleAll(this)"></th>
                        <?php
                        $cols = ['student_id'=>'Student ID','last_name'=>'Full Name','program'=>'Program','section'=>'Section','is_submitted'=>'Status','risk_level'=>'Risk','validity'=>'Validity','time_taken'=>'Time','actions'=>'Actions'];
                        foreach($cols as $ck=>$cl):
                            $sortable = $ck !== 'actions' && $ck !== 'validity';
                            $icon = ($f_sort===$ck) ? ($f_dir==='ASC'?'▲':'▼') : '';
                        ?>
                        <th style="padding:12px 10px;text-align:left;color:var(--primary-color);<?php echo $sortable?'cursor:pointer;user-select:none;':'' ?>;white-space:nowrap;"
                            <?php if($sortable): ?>onclick="sortBy('<?php echo $ck; ?>')"<?php endif; ?>>
                            <?php echo $cl; ?> <?php echo $icon; ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody id="student-tbody">
                    <tr><td colspan="10" style="padding:30px;text-align:center;"><div class="skel-row"></div><div class="skel-row"></div><div class="skel-row"></div></td></tr>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div id="pagination" style="display:flex;justify-content:center;gap:4px;margin-top:16px;flex-wrap:wrap;"></div>

        <style>
        #student-table th{border-bottom:2px solid var(--border-color);}
        #student-table td{padding:11px 10px;border-bottom:1px solid #f0f0f0;vertical-align:middle;}
        #student-table tr:hover td{background:#f5f7fa;}
        .row-urgent td:first-child{border-left:3px solid #dc3545;}
        .row-high td:first-child{border-left:3px solid #fd7e14;}
        .row-review td:first-child{border-left:3px solid #ffc107;}
        .row-highlight{background:#fff9d6 !important;}
        .skel-row{height:18px;background:linear-gradient(90deg,#eee 25%,#f5f5f5 50%,#eee 75%);background-size:200% 100%;animation:skel 1.2s infinite;border-radius:4px;margin:10px 0;}
        @keyframes skel{0%{background-position:200% 0}100%{background-position:-200% 0}}
        .pg-btn{padding:5px 10px;border:1px solid var(--border-color);border-radius:4px;cursor:pointer;background:#fff;min-width:32px;}
        .pg-btn.active{background:var(--primary-color);color:#fff;border-color:var(--primary-color);}
        .pg-btn:disabled{opacity:.4;cursor:default;}
        .badge-valid{background:#d4edda;color:#155724;}
        .badge-requires_review{background:#fff3cd;color:#856404;}
        .badge-rejected{background:#f8d7da;color:#721c24;}
        .badge-resubmit{background:#cce5ff;color:#004085;}
        select{padding:7px 8px;border:1px solid var(--border-color);border-radius:4px;font-size:0.9em;}
        </style>

        <script>
        const CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        let currentPage = <?php echo $f_page; ?>;
        let sortCol = <?php echo json_encode($f_sort); ?>;
        let sortDir = <?php echo json_encode($f_dir); ?>;
        let totalPages = 1;
        let debounceT;
        let selectedIds = new Set();

        function getFilters(){
            return {
                q: document.getElementById('search-input').value.trim(),
                program: document.getElementById('f-program').value,
                year_level: document.getElementById('f-year').value,
                section: document.getElementById('f-section').value,
                submission_status: document.getElementById('f-status').value,
                risk_level: document.getElementById('f-risk').value,
                validity_status: document.getElementById('f-validity').value,
                sort_col: sortCol, sort_dir: sortDir,
                page: currentPage,
                per_page: document.getElementById('f-pp').value
            };
        }

        function buildQS(p){ return new URLSearchParams(p).toString(); }

        function doSearch(resetPage){
            if(resetPage) currentPage = 1;
            clearTimeout(debounceT);
            debounceT = setTimeout(fetchStudents, 50);
        }

        function fetchStudents(){
            const f = getFilters();
            const qs = buildQS(f);
            // Update URL
            history.replaceState(null,'','dashboard.php?' + qs + '#students');
            document.getElementById('search-spin').style.display = 'inline';
            document.getElementById('student-tbody').innerHTML = '<tr><td colspan="10" style="padding:30px;text-align:center;"><div class="skel-row"></div><div class="skel-row"></div><div class="skel-row"></div></td></tr>';

            fetch('actions/search_students.php?' + qs)
                .then(r=>r.json())
                .then(data=>{
                    document.getElementById('search-spin').style.display='none';
                    renderRows(data.students);
                    renderPagination(data.page, data.pages);
                    totalPages = data.pages;
                    const rc = document.getElementById('results-count');
                    if(data.total === data.grand_total){
                        rc.textContent = `Showing ${data.students.length} of ${data.total} students`;
                    } else {
                        rc.textContent = `Showing ${data.students.length} of ${data.total} (filtered from ${data.grand_total} total)`;
                    }
                })
                .catch(()=>{
                    document.getElementById('search-spin').style.display='none';
                    document.getElementById('student-tbody').innerHTML='<tr><td colspan="10" style="text-align:center;color:#dc3545;padding:20px;">Failed to load students. Please refresh.</td></tr>';
                });
        }

        function renderRows(students){
            const tb = document.getElementById('student-tbody');
            if(!students.length){
                tb.innerHTML='<tr><td colspan="10" style="text-align:center;padding:30px;color:#888;">No students found matching your search. <a href="#" onclick="clearAll();return false;">Clear filters</a></td></tr>';
                return;
            }
            let html='';
            students.forEach(s=>{
                const rowCls = s.risk_level==='urgent'?'row-urgent':s.risk_level==='high'?'row-high':s.validity_status==='requires_review'?'row-review':'';
                const timeCls = s.time_elapsed!==null && s.time_elapsed<300 ? 'color:#dc3545;font-weight:bold;' : '';
                const timeStr = s.time_fmt ? (s.time_elapsed<300?'⚠ ':'')+s.time_fmt : '—';
                const chk = selectedIds.has(s.id)?'checked':'';
                html+=`<tr class="${rowCls}" id="row-${s.id}">
                    <td><input type="checkbox" class="row-chk" value="${s.id}" ${chk} onchange="toggleSel(${s.id},this.checked)"></td>
                    <td><strong>${esc(s.student_id)}</strong></td>
                    <td>${esc(s.full_name)}</td>
                    <td>${esc(s.program)}</td>
                    <td>${esc(s.section)}</td>
                    <td><span class="badge ${s.is_submitted?'badge-submitted':'badge-pending'}">${s.is_submitted?'Submitted':'Pending'}</span></td>
                    <td><span class="badge badge-risk-${s.risk_level||'none'}">${cap(s.risk_level||'none')}</span></td>
                    <td>${s.validity_status?'<span class="badge badge-'+s.validity_status+'">'+cap(s.validity_status.replace('_',' '))+'</span>':'—'}</td>
                    <td style="${timeCls}">${timeStr}</td>
                    <td>
                        <div style="display:flex;gap:5px;">
                            <a href="student_view.php?id=${s.id}" title="View" style="padding:4px 8px;background:var(--primary-color);color:#fff;border-radius:4px;text-decoration:none;font-size:0.9em;">👁</a>
                            <button title="Mark Complete" onclick="markOne(${s.id})" style="padding:4px 8px;background:#ffc107;border:none;border-radius:4px;cursor:pointer;font-size:0.9em;">✓</button>
                            <button title="Delete" onclick="deleteOne(${s.id})" style="padding:4px 8px;background:#dc3545;color:#fff;border:none;border-radius:4px;cursor:pointer;font-size:0.9em;">🗑</button>
                        </div>
                    </td>
                </tr>`;
            });
            tb.innerHTML=html;
            // Highlight exact student_id match
            const q = document.getElementById('search-input').value.trim();
            if(q && students.length===1 && students[0].student_id===q){
                const row=document.getElementById('row-'+students[0].id);
                if(row){row.classList.add('row-highlight');row.scrollIntoView({behavior:'smooth',block:'center'});}
            }
            updateSelCount();
        }

        function renderPagination(cur, total){
            const pg = document.getElementById('pagination');
            if(total<=1){pg.innerHTML='';return;}
            let html='';
            const btn=(label,p,dis,act)=>`<button class="pg-btn${act?' active':''}" ${dis?'disabled':''} onclick="goPage(${p})">${label}</button>`;
            html+=btn('«',1,cur===1,false);
            html+=btn('‹',cur-1,cur===1,false);
            let s=Math.max(1,cur-2), e=Math.min(total,s+4);
            s=Math.max(1,e-4);
            if(s>1) html+='<span style="padding:5px 4px;">…</span>';
            for(let i=s;i<=e;i++) html+=btn(i,i,false,i===cur);
            if(e<total) html+='<span style="padding:5px 4px;">…</span>';
            html+=btn('›',cur+1,cur===total,false);
            html+=btn('»',total,cur===total,false);
            pg.innerHTML=html;
        }

        function goPage(p){ currentPage=p; fetchStudents(); }
        function sortBy(col){ if(sortCol===col) sortDir=sortDir==='ASC'?'DESC':'ASC'; else{sortCol=col;sortDir='ASC';} currentPage=1; fetchStudents(); }

        function esc(s){ const d=document.createElement('div');d.textContent=s||'';return d.innerHTML; }
        function cap(s){ return s?s.charAt(0).toUpperCase()+s.slice(1):s; }

        function toggleSel(id,checked){ if(checked) selectedIds.add(id); else selectedIds.delete(id); updateSelCount(); }
        function toggleAll(cb){ document.querySelectorAll('.row-chk').forEach(c=>{c.checked=cb.checked;toggleSel(parseInt(c.value),cb.checked);}); updateSelCount(); }
        function updateSelCount(){ document.getElementById('sel-count').textContent = selectedIds.size>0?selectedIds.size+' selected':''; }

        function clearSearch(){ document.getElementById('search-input').value=''; document.getElementById('search-clear').style.display='none'; doSearch(true); }
        function clearAll(){
            document.getElementById('search-input').value='';
            ['f-program','f-year','f-section','f-status','f-risk','f-validity'].forEach(id=>document.getElementById(id).value='');
            document.getElementById('f-section').innerHTML='<option value="">All Sections</option>';
            sortCol='last_name'; sortDir='ASC'; currentPage=1; doSearch();
        }

        function loadSectionFilter(programCode){
            const sel = document.getElementById('f-section');
            sel.innerHTML='<option value="">All Sections</option>';
            if(!programCode) return;
            const opt = document.querySelector('#f-program option[value="'+programCode+'"]');
            const pid = opt ? opt.dataset.id : '';
            if(!pid) return;
            fetch('../student/actions/get_sections.php?program_id='+pid)
                .then(r=>r.json())
                .then(secs=>{ secs.forEach(s=>{const o=document.createElement('option');o.value=s.section_code;o.textContent=s.section_code;sel.appendChild(o);}); });
        }

        function markOne(uid){
            const fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('action','mark_complete'); fd.append('user_ids[]',uid);
            fetch('actions/bulk_action.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) fetchStudents(); });
        }
        function deleteOne(uid){
            if(!confirm('Delete this student and all their data?')) return;
            const fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('action','delete'); fd.append('user_ids[]',uid);
            fetch('actions/bulk_action.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{ if(d.success) fetchStudents(); });
        }

        function doBulk(){
            const action=document.getElementById('bulk-action').value;
            if(!action||selectedIds.size===0){alert('Select an action and at least one student.');return;}
            if(action==='delete'&&!confirm(`Delete ${selectedIds.size} student(s) permanently?`)) return;
            if(action==='export_csv'){exportSelected();return;}
            const fd=new FormData(); fd.append('csrf_token',CSRF); fd.append('action',action);
            selectedIds.forEach(id=>fd.append('user_ids[]',id));
            fetch('actions/bulk_action.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
                if(d.success){selectedIds.clear();updateSelCount();fetchStudents();}
                else alert(d.error||'Error.');
            });
        }

        function exportSelected(){
            const fd=new FormData(); fd.append('action','export_csv'); fd.append('csrf_token',CSRF);
            selectedIds.forEach(id=>fd.append('user_ids[]',id));
            const form=document.createElement('form'); form.method='POST'; form.action='actions/bulk_action.php';
            form.innerHTML=`<input name="action" value="export_csv"><input name="csrf_token" value="${CSRF}">`;
            selectedIds.forEach(id=>{ const i=document.createElement('input');i.name='user_ids[]';i.value=id;form.appendChild(i); });
            document.body.appendChild(form); form.submit(); document.body.removeChild(form);
        }

        function exportAll(){
            const f=getFilters(); delete f.page; delete f.per_page;
            const form=document.createElement('form'); form.method='POST'; form.action='actions/bulk_action.php';
            form.innerHTML=`<input name="action" value="export_csv"><input name="csrf_token" value="${CSRF}">`;
            document.body.appendChild(form); form.submit(); document.body.removeChild(form);
        }

        // Search input listeners
        document.getElementById('search-input').addEventListener('input', function(){
            document.getElementById('search-clear').style.display = this.value ? 'inline' : 'none';
            clearTimeout(debounceT); debounceT = setTimeout(()=>{ currentPage=1; fetchStudents(); }, 300);
        });

        // Init: load sections if program pre-selected
        if(document.getElementById('f-program').value) loadSectionFilter(document.getElementById('f-program').value);
        // Pre-select saved section
        const initSec = <?php echo json_encode($f_section); ?>;
        if(initSec){
            const si=document.getElementById('f-section');
            // wait for section load then select
            setTimeout(()=>{ for(let o of si.options) if(o.value===initSec){o.selected=true;break;} },600);
        }

        // Initial load
        fetchStudents();
        </script>
        </div><!-- admin-content -->
    </div><!-- admin-main -->
</div><!-- admin-wrapper -->
</body>
</html>
