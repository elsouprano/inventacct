<?php
session_start();
require_once '../config/db.php';
require_once '../config/question_labels.php';

// Session check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$student_id = $_GET['id'] ?? null;
if (!$student_id) {
    header("Location: dashboard.php");
    exit;
}

// Fetch student
$stmt = $pdo->prepare("SELECT u.*, s.submitted_at, s.is_complete, s.id as submission_id, s.risk_level, s.risk_flags, s.risk_reviewed, s.risk_reviewed_by, s.risk_reviewed_at 
    FROM users u 
    LEFT JOIN inventory_submissions s ON u.id = s.user_id 
    WHERE u.id = ? AND u.role = 'student'");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: dashboard.php");
    exit;
}

// Fetch answers
$answers = [];
$scores = [];
if ($student['submission_id']) {
    $stmt_ans = $pdo->prepare("SELECT section, question_key, answer_value FROM inventory_answers WHERE submission_id = ?");
    $stmt_ans->execute([$student['submission_id']]);
    $ans_data = $stmt_ans->fetchAll();
    foreach ($ans_data as $row) {
        $answers[$row['section']][] = $row;
    }
    
    $stmt_scores = $pdo->prepare("SELECT scale, raw_score, interpretation, needs_counseling FROM inventory_scores WHERE submission_id = ?");
    $stmt_scores->execute([$student['submission_id']]);
    $scores = $stmt_scores->fetchAll(PDO::FETCH_ASSOC);
}

$sections = [
    'scores' => 'Scoring Results',
    'learning_style' => 'Learning Style',
    'erq' => 'ERQ',
    'cat' => 'CAT',
    'dass21' => 'DASS-21',
    'ars30' => 'ARS-30',
    'ffmq' => 'FFMQ'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student View - CCT Inventory System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin_layout.css">
    <style>
        .profile-card {
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .profile-item label {
            font-size: 0.8em;
            color: #666;
            display: block;
        }
        .profile-item div {
            font-weight: bold;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .actions-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .btn-sm {
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            border: none;
            text-decoration: none;
            font-size: 0.9em;
            display: inline-block;
        }
        .btn-pdf { background: #5a6268; color: white; }
        .btn-mark { background: var(--accent-color); color: var(--primary-color); font-weight: bold; }
        .btn-delete { background: var(--error-color); color: white; }
        .tabs {
            display: flex;
            gap: 5px;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            font-size: 1em;
            color: #666;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
        }
        .tab.active {
            color: var(--primary-color);
            border-bottom-color: var(--primary-color);
            font-weight: bold;
        }
        .tab-content {
            display: none;
            background: var(--white);
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .tab-content.active {
            display: block;
        }
        .answer-list {
            list-style: none;
            padding: 0;
        }
        .answer-list li {
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            display: block;
        }
        .answer-list li strong {
            color: #555;
            margin-bottom: 5px;
            display: inline-block;
        }
        .answer-list li span {
            display: inline-block;
            margin-left: 10px;
        }
        .score-table {
            width: 100%;
            border-collapse: collapse;
        }
        .score-table th, .score-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .score-table th {
            background: #f8f9fa;
        }
        .badge-normal { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .placeholder {
            color: #888;
            font-style: italic;
        }
    </style>
</head>
<body>
<?php $pageTitle = 'Student Profile'; ?>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include 'includes/admin_header.php'; ?>
        <div class="admin-content">

        <h1>Student Profile</h1>
        
        <div class="actions-bar">
            <a href="actions/export_pdf.php?id=<?php echo $student['id']; ?>" class="btn-sm btn-pdf" target="_blank">Export as PDF</a>
            <form method="POST" action="actions/mark_complete.php" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="user_id" value="<?php echo $student['id']; ?>">
                <button type="submit" class="btn-sm btn-mark"><?php echo $student['is_complete'] ? 'Mark as Pending' : 'Mark Complete'; ?></button>
            </form>
            <form method="POST" action="actions/delete_student.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this student and all their data?');">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="user_id" value="<?php echo $student['id']; ?>">
                <button type="submit" class="btn-sm btn-delete">Delete Student</button>
            </form>
        </div>

        <div class="profile-card">
            <h2><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_initial'] . ' ' . $student['last_name']); ?></h2>
            <div class="profile-grid">
                <div class="profile-item">
                    <label>Student ID</label>
                    <div><?php echo htmlspecialchars($student['student_id'] ?: 'N/A'); ?></div>
                </div>
                <div class="profile-item">
                    <label>Program</label>
                    <div><?php echo htmlspecialchars($student['program'] ?: 'N/A'); ?></div>
                </div>
                <div class="profile-item">
                    <label>Section</label>
                    <div><?php echo htmlspecialchars($student['section'] ?: 'N/A'); ?></div>
                </div>
                <div class="profile-item">
                    <label>Email</label>
                    <div><?php echo htmlspecialchars($student['email']); ?></div>
                </div>
                <div class="profile-item">
                    <label>Contact Number</label>
                    <div>N/A</div>
                </div>
                <div class="profile-item">
                    <label>Is Paying Student</label>
                    <div><?php echo $student['is_paying_student'] ? 'Yes' : 'No'; ?></div>
                </div>
                <div class="profile-item">
                    <label>Address</label>
                    <div><?php echo htmlspecialchars($student['address'] ?: 'N/A'); ?></div>
                </div>
                <div class="profile-item">
                    <label>Submission Status</label>
                    <div>
                        <?php if ($student['is_complete']): ?>
                            <span style="color:#28a745;font-weight:bold;">Submitted on <?php echo date('M d, Y h:i A', strtotime($student['submitted_at'])); ?></span>
                        <?php else: ?>
                            <span style="color:#ffc107;font-weight:bold;">Not Yet Submitted</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="tabs">
            <?php $first = true; foreach($sections as $key => $label): ?>
                <button class="tab <?php echo $first ? 'active' : ''; ?>" onclick="openTab(event, '<?php echo $key; ?>')"><?php echo $label; ?></button>
            <?php $first = false; endforeach; ?>
        </div>

        <?php $first = true; foreach($sections as $key => $label): ?>
            <div id="<?php echo $key; ?>" class="tab-content <?php echo $first ? 'active' : ''; ?>">
                <h3><?php echo $label; ?></h3>
                
                <?php if ($key === 'scores'): ?>
                    <?php if (empty($scores)): ?>
                        <p class="placeholder">No submission yet.</p>
                    <?php else: ?>
                        <div class="risk-assessment" style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px; border: 1px solid var(--border-color);">
                            <h4 style="margin-top: 0; margin-bottom: 15px; color: var(--primary-color);">Mental Health Risk Assessment</h4>
                            
                            <div style="display: flex; gap: 20px; align-items: flex-start; margin-bottom: 20px; flex-wrap: wrap;">
                                <div>
                                    <strong style="display: block; margin-bottom: 5px;">Overall Risk Level:</strong>
                                    <?php 
                                        $r_level = $student['risk_level'] ?? 'none';
                                        $r_color = '#e2e3e5'; $r_text = '#383d41';
                                        if ($r_level === 'urgent') { $r_color = '#dc3545'; $r_text = '#fff'; }
                                        elseif ($r_level === 'high') { $r_color = '#fd7e14'; $r_text = '#fff'; }
                                        elseif ($r_level === 'moderate') { $r_color = '#fff3cd'; $r_text = '#856404'; }
                                        elseif ($r_level === 'low') { $r_color = '#cce5ff'; $r_text = '#004085'; }
                                    ?>
                                    <span style="background: <?php echo $r_color; ?>; color: <?php echo $r_text; ?>; padding: 8px 15px; border-radius: 4px; font-weight: bold; font-size: 1.1em; display: inline-block;">
                                        <?php echo strtoupper($r_level); ?>
                                    </span>
                                </div>
                                
                                <div style="flex: 1; min-width: 250px;">
                                    <strong style="display: block; margin-bottom: 5px;">Risk Flags Detected:</strong>
                                    <?php 
                                    $risk_flags = json_decode($student['risk_flags'] ?: '[]', true);
                                    if (empty($risk_flags)): ?>
                                        <span style="color: #666; font-style: italic;">No specific risk flags detected.</span>
                                    <?php else: ?>
                                        <ul style="margin: 0; padding-left: 20px; color: #dc3545;">
                                            <?php foreach($risk_flags as $f): ?>
                                                <li><?php echo htmlspecialchars($f); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div style="border-top: 1px solid var(--border-color); padding-top: 15px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                <div>
                                    <?php if ($student['risk_reviewed']): ?>
                                        <?php 
                                            $stmt_rev = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                                            $stmt_rev->execute([$student['risk_reviewed_by']]);
                                            $reviewer = $stmt_rev->fetch();
                                            $rev_name = $reviewer ? ($reviewer['first_name'] . ' ' . $reviewer['last_name']) : 'Unknown';
                                        ?>
                                        <span style="color: #28a745; font-weight: bold;">✓ Reviewed by <?php echo htmlspecialchars($rev_name); ?></span> on <?php echo date('M d, Y h:i A', strtotime($student['risk_reviewed_at'])); ?>
                                    <?php else: ?>
                                        <span style="color: #856404; font-weight: bold;">⚠ Requires Review</span>
                                    <?php endif; ?>
                                </div>
                                <form method="POST" action="actions/mark_risk_reviewed.php" style="margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <input type="hidden" name="submission_id" value="<?php echo $student['submission_id']; ?>">
                                    <?php if ($student['risk_reviewed']): ?>
                                        <button type="submit" class="btn btn-outline" style="padding: 5px 15px;">Mark Unreviewed</button>
                                    <?php else: ?>
                                        <button type="submit" class="btn" style="padding: 5px 15px; background: #28a745;">Mark as Reviewed</button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <table class="score-table">
                            <thead>
                                <tr>
                                    <th>Scale</th>
                                    <th>Raw Score</th>
                                    <th>Interpretation</th>
                                    <th>Needs Counseling</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $scale_names = [
                                    'learning_style' => 'Learning Style',
                                    'erq_cr' => 'Cognitive Reappraisal (ERQ)',
                                    'erq_es' => 'Expressive Suppression (ERQ)',
                                    'cat_negative' => 'Negative Affect (CAT)',
                                    'cat_positive' => 'Positive Affect (CAT)',
                                    'dass21_depression' => 'Depression (DASS-21)',
                                    'dass21_anxiety' => 'Anxiety (DASS-21)',
                                    'dass21_stress' => 'Stress (DASS-21)',
                                    'ars30' => 'Academic Resilience (ARS-30)',
                                    'ffmq_observing' => 'Observing (FFMQ)',
                                    'ffmq_describing' => 'Describing (FFMQ)',
                                    'ffmq_awareness' => 'Acting with Awareness (FFMQ)',
                                    'ffmq_nonjudging' => 'Non-judging (FFMQ)',
                                    'ffmq_nonreactivity' => 'Non-reactivity (FFMQ)'
                                ];
                                foreach($scores as $score): 
                                    $interp = strtolower($score['interpretation']);
                                    $badge_class = 'badge-warning'; // Default
                                    if (str_contains($interp, 'normal') || str_contains($interp, 'high') || str_contains($interp, 'well adjusted')) {
                                        $badge_class = 'badge-normal';
                                    } elseif (str_contains($interp, 'severe') || str_contains($interp, 'low') || str_contains($interp, 'poor')) {
                                        $badge_class = 'badge-danger';
                                    }
                                    if ($score['scale'] === 'learning_style') $badge_class = 'badge-normal'; // Always normal for learning style
                                ?>
                                <tr>
                                    <td><strong><?php echo $scale_names[$score['scale']] ?? $score['scale']; ?></strong></td>
                                    <td><?php echo $score['raw_score']; ?></td>
                                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($score['interpretation']); ?></span></td>
                                    <td>
                                        <?php if ($score['needs_counseling']): ?>
                                            <span class="badge badge-danger">Yes</span>
                                        <?php else: ?>
                                            <span class="badge badge-normal">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <?php if (!isset($answers[$key]) || empty($answers[$key])): ?>
                        <p class="placeholder">No submission yet.</p>
                    <?php else: ?>
                        <ul class="answer-list">
                            <?php foreach($answers[$key] as $ans): 
                                $q_key = $ans['question_key'];
                                $q_text = $question_labels[$q_key] ?? $q_key;
                                $ans_text = get_formatted_answer($q_key, $ans['answer_value']);
                            ?>
                                <li>
                                    <strong><?php echo htmlspecialchars($q_text); ?></strong><br>
                                    <span style="color: #333;">&mdash; <?php echo htmlspecialchars($ans_text); ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php $first = false; endforeach; ?>

    </main>

    <script>
    function openTab(evt, sectionName) {
        var i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("tab");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(sectionName).classList.add("active");
        evt.currentTarget.classList.add("active");
    }
    </script>
        </div><!-- admin-content -->
    </div><!-- admin-main -->
</div><!-- admin-wrapper -->
</body>
</html>
