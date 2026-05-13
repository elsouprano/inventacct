<?php
session_start();
require_once '../config/db.php';
require_once '../config/app_config.php';

// Session check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Role check
if ($_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php");
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT first_name, program, section FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

// Check submission status
$stmt_sub = $pdo->prepare("SELECT is_complete, submitted_at, validity_status, risk_level FROM inventory_submissions WHERE user_id = ? ORDER BY id DESC LIMIT 1");
$stmt_sub->execute([$_SESSION['user_id']]);
$submission = $stmt_sub->fetch();
$is_submitted = $submission && $submission['is_complete'] == 1;
$submitted_at = $submission['submitted_at'] ?? null;

// Period + Draft state
date_default_timezone_set('Asia/Manila');
$now = new DateTime('now');

$stmt_p = $pdo->prepare("SELECT * FROM inventory_periods WHERE is_active = 1 LIMIT 1");
$stmt_p->execute();
$active_period = $stmt_p->fetch(PDO::FETCH_ASSOC);

$periodStatus = 'none';
$period_open = null;
$period_close = null;

if ($active_period) {
    $period_open = new DateTime($active_period['open_date']);
    $period_close = new DateTime($active_period['close_date']);
    
    if ($now < $period_open) {
        $periodStatus = 'upcoming';
    } elseif ($now >= $period_open && $now <= $period_close) {
        $periodStatus = 'open';
    } else {
        $periodStatus = 'closed';
    }
}

$draft = null;
if (!$is_submitted) {
    $stmt_d = $pdo->prepare("SELECT current_step, last_saved FROM inventory_drafts WHERE user_id = ?");
    $stmt_d->execute([$_SESSION['user_id']]);
    $draft = $stmt_d->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - CCT Inventory System</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student_mobile.css">
</head>
<body>
    <header class="dashboard-header">
        <div class="logo">CCT Inventory</div>
        <a href="../auth/logout.php" class="logout-link">Logout</a>
    </header>

    <main class="dashboard-content">
        <h1>Welcome, <?php echo htmlspecialchars($user['first_name']); ?>!</h1>
        <p class="subtitle">
            <?php echo htmlspecialchars($user['program']); ?> - Year <?php echo htmlspecialchars(explode('-', $user['section'])[0]); ?>, Section <?php echo htmlspecialchars($user['section']); ?>
        </p>

        <div class="card-grid">
            <?php if (isset($_SESSION['show_risk_message']) && $_SESSION['show_risk_message'] && $is_submitted): 
                $risk_level = $submission['risk_level'] ?? 'none';
                unset($_SESSION['show_risk_message']);
            ?>
                <div class="dashboard-card" style="grid-column: 1 / -1; background: #f8f9fa; border-left: 5px solid var(--primary-color);">
                    <?php if ($risk_level === 'urgent'): ?>
                        <p>Thank you for being open with us. Your wellbeing matters deeply to us. We strongly encourage you to visit the Guidance Office as soon as possible — our counselors are here to support you. You are not alone.</p>
                        <div style="margin-top: 15px; font-size: 0.9em; background: rgba(0,0,0,0.05); padding: 15px; border-radius: 4px;">
                            <strong><?php echo GUIDANCE_OFFICE_NAME; ?></strong><br>
                            Location: <?php echo GUIDANCE_OFFICE_LOCATION; ?><br>
                            Email: <?php echo GUIDANCE_OFFICE_EMAIL; ?><br>
                            Phone: <?php echo GUIDANCE_OFFICE_PHONE; ?>
                        </div>
                    <?php elseif ($risk_level === 'high'): ?>
                        <p>Thank you for completing your inventory. We care about your wellbeing and we'd like to connect with you. Please visit the Guidance Office at your earliest convenience. You don't have to go through this alone.</p>
                    <?php elseif ($risk_level === 'moderate'): ?>
                        <p>Thank you for completing your inventory. We noticed you might be going through a tough time. We encourage you to visit the Guidance Office soon — our counselors are ready to listen and help.</p>
                    <?php elseif ($risk_level === 'low'): ?>
                        <p>Thank you for completing your inventory. It looks like you may be experiencing some challenges lately. Remember, the Guidance Office is here for you — feel free to drop by anytime.</p>
                    <?php else: ?>
                        <p>Thank you for completing your inventory! The Guidance Office is always here if you ever need someone to talk to.</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div class="dashboard-card">
                <h3>Individual Inventory</h3>
                <?php if ($submission && $submission['validity_status'] === 'resubmit'): ?>
                    <div style="background: #fff3cd; color: #856404; padding: 10px; border-left: 4px solid #ffc107; margin-bottom: 20px;">
                        <strong>Action Required: Resubmit</strong><br>
                        Your inventory submission has been flagged for review and you have been asked to resubmit. Please complete the inventory again.
                    </div>
                    <a href="inventory.php" class="btn" style="background: #ffc107; color: #333; display: inline-block; width: auto; padding: 10px 20px;">Resubmit Inventory</a>
                    
                <?php elseif ($submission && $submission['validity_status'] === 'rejected'): ?>
                    <div style="background: #f8d7da; color: #721c24; padding: 10px; border-left: 4px solid var(--error-color); margin-bottom: 20px;">
                        <strong>Submission Rejected</strong><br>
                        Your submission has been reviewed and marked as invalid. Please contact the Guidance Office for assistance.
                    </div>
                    
                <?php elseif ($submission && $submission['validity_status'] === 'requires_review'): ?>
                    <div style="background: #e2e3e5; color: #383d41; padding: 10px; border-left: 4px solid #6c757d; margin-bottom: 20px;">
                        <strong>Under Review</strong><br>
                        Your submission is currently under review. You will be notified of any updates.
                    </div>
                    <p style="color: #666; font-size: 0.9em; margin-bottom: 20px;">
                        Date: <?php echo date('F d, Y h:i A', strtotime($submitted_at)); ?>
                    </p>
                    <a href="inventory_view.php" class="btn btn-accent" style="display: inline-block; width: auto; padding: 10px 20px;">View My Submission</a>

                <?php elseif ($is_submitted): ?>
                    <p style="margin-bottom: 10px;">
                        <span style="background: #d4edda; color: #155724; padding: 5px 10px; border-radius: 12px; font-weight: bold; font-size: 0.9em;">Submitted</span>
                    </p>
                    <p style="color: #666; font-size: 0.9em; margin-bottom: 20px;">
                        Date: <?php echo date('F d, Y h:i A', strtotime($submitted_at)); ?>
                    </p>
                    <a href="inventory_view.php" class="btn btn-accent" style="display: inline-block; width: auto; padding: 10px 20px;">View My Submission</a>
                <?php elseif ($periodStatus === 'none'): ?>
                    <p style="color:#888;">No active inventory period. Check back later.</p>
                <?php elseif ($periodStatus === 'upcoming'): ?>
                    <p style="color:#555;">The inventory period has not started yet.</p>
                    <p style="font-size:0.9em;color:#888;">Opens on <strong><?php echo $period_open->format('F d, Y h:i A'); ?></strong></p>
                <?php elseif ($periodStatus === 'closed'): ?>
                    <p style="color:#888;">The submission period has closed. Please contact the Guidance Office if you have concerns.</p>
                <?php elseif ($draft): ?>
                    <?php
                        $diff = time() - strtotime($draft['last_saved']);
                        if ($diff < 60) $saved_str = 'just now';
                        elseif ($diff < 3600) $saved_str = floor($diff/60) . ' minute' . (floor($diff/60)!==1?'s':'') . ' ago';
                        else $saved_str = floor($diff/3600) . ' hour' . (floor($diff/3600)!==1?'s':'') . ' ago';
                    ?>
                    <p style="font-size:0.85em;color:#28a745;margin-bottom:6px;">📂 Draft saved <?php echo $saved_str; ?> &bull; Step <?php echo $draft['current_step']; ?> of 7</p>
                    <p style="font-size:0.85em;color:#856404;margin-bottom:15px;">Closes on <strong><?php echo $period_close->format('F d, Y h:i A'); ?></strong></p>
                    <a href="inventory.php" class="btn" style="display: inline-block; width: auto; padding: 10px 20px;">Continue Inventory</a>
                <?php else: ?>
                    <p style="margin-bottom:6px;">Please complete your Individual Inventory form.</p>
                    <p style="font-size:0.85em;color:#856404;margin-bottom:15px;">Closes on <strong><?php echo $period_close->format('F d, Y h:i A'); ?></strong></p>
                    <a href="inventory.php" class="btn" style="display: inline-block; width: auto; padding: 10px 20px;">Start Inventory</a>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
