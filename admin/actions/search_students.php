<?php
session_start();
require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// --- Input sanitization ---
$q                = trim($_GET['q'] ?? '');
$program          = trim($_GET['program'] ?? '');
$year_level       = trim($_GET['year_level'] ?? '');
$section          = trim($_GET['section'] ?? '');
$submission_status = trim($_GET['submission_status'] ?? '');
$risk_level       = trim($_GET['risk_level'] ?? '');
$validity_status  = trim($_GET['validity_status'] ?? '');
$sort_col         = trim($_GET['sort_col'] ?? 'last_name');
$sort_dir         = strtoupper(trim($_GET['sort_dir'] ?? 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
$page             = max(1, (int)($_GET['page'] ?? 1));
$per_page         = in_array((int)($_GET['per_page'] ?? 25), [25, 50, 100]) ? (int)$_GET['per_page'] : 25;

// Allowed sort columns map (safe whitelist)
$sort_map = [
    'student_id'  => 'u.student_id',
    'last_name'   => 'u.last_name',
    'program'     => 'u.program',
    'section'     => 'u.section',
    'is_submitted'=> 'is_submitted',
    'risk_level'  => 'risk_level',
    'time_taken'  => 'time_elapsed_seconds',
];
$order_by = $sort_map[$sort_col] ?? 'u.last_name';

// --- Grand total (always total students regardless of filters) ---
$grand_total = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();

// --- Build WHERE + params ---
$where  = ["u.role = 'student'"];
$params = [];

// Search
if ($q !== '') {
    // If table is large (>1000), limit search to student_id and last_name only
    if ($grand_total > 1000) {
        $where[]  = "(u.student_id LIKE ? OR u.last_name LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
    } else {
        $where[]  = "(u.student_id LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
        $params[] = "%$q%";
    }
}

// Filters
if ($program !== '') {
    $where[]  = "u.program = ?";
    $params[] = $program;
}
if ($year_level !== '') {
    $where[]  = "u.section LIKE ?";
    $params[] = $year_level . '-%';
}
if ($section !== '') {
    $where[]  = "u.section = ?";
    $params[] = $section;
}
if ($submission_status === 'submitted') {
    $where[] = "s.is_complete = 1";
} elseif ($submission_status === 'pending') {
    $where[] = "(s.id IS NULL OR s.is_complete = 0)";
}
if ($risk_level !== '') {
    $where[]  = "s.risk_level = ?";
    $params[] = $risk_level;
}
if ($validity_status !== '') {
    $where[]  = "s.validity_status = ?";
    $params[] = $validity_status;
}

$where_sql = implode(' AND ', $where);

// --- Count total matching ---
$count_sql = "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN inventory_submissions s ON s.user_id = u.id AND s.id = (
        SELECT MAX(id) FROM inventory_submissions WHERE user_id = u.id
    )
    WHERE $where_sql
";
$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();
$pages = $total > 0 ? (int)ceil($total / $per_page) : 1;
$page  = min($page, $pages);
$offset = ($page - 1) * $per_page;

// --- Fetch students ---
$sql = "
    SELECT 
        u.id, u.student_id, u.first_name, u.middle_initial, u.last_name,
        u.program, u.section, u.email,
        s.id as submission_id,
        s.is_complete as is_submitted,
        s.risk_level,
        s.validity_status,
        s.time_elapsed_seconds,
        s.submitted_at
    FROM users u
    LEFT JOIN inventory_submissions s ON s.user_id = u.id AND s.id = (
        SELECT MAX(id) FROM inventory_submissions WHERE user_id = u.id
    )
    WHERE $where_sql
    ORDER BY $order_by $sort_dir
    LIMIT ? OFFSET ?
";
$params_paged = array_merge($params, [$per_page, $offset]);
$stmt = $pdo->prepare($sql);
$stmt->execute($params_paged);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format rows
$students = [];
foreach ($rows as $r) {
    $time_sec = $r['time_elapsed_seconds'];
    $time_fmt = $time_sec !== null
        ? floor($time_sec / 60) . 'm ' . ($time_sec % 60) . 's'
        : null;
    $students[] = [
        'id'              => (int)$r['id'],
        'student_id'      => $r['student_id'] ?? '',
        'full_name'       => trim($r['first_name'] . ' ' . ($r['middle_initial'] ? $r['middle_initial'] . '. ' : '') . $r['last_name']),
        'program'         => $r['program'] ?? '',
        'section'         => $r['section'] ?? '',
        'email'           => $r['email'] ?? '',
        'is_submitted'    => (bool)$r['is_submitted'],
        'risk_level'      => $r['risk_level'] ?? 'none',
        'validity_status' => $r['validity_status'] ?? '',
        'time_elapsed'    => $time_sec !== null ? (int)$time_sec : null,
        'time_fmt'        => $time_fmt,
        'submitted_at'    => $r['submitted_at'] ? date('M d, Y', strtotime($r['submitted_at'])) : null,
        'submission_id'   => $r['submission_id'] ? (int)$r['submission_id'] : null,
    ];
}

echo json_encode([
    'students'    => $students,
    'total'       => $total,
    'grand_total' => $grand_total,
    'pages'       => $pages,
    'page'        => $page,
    'per_page'    => $per_page,
]);
