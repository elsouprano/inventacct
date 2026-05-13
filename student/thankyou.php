<?php
session_start();
require_once '../config/db.php';
require_once '../config/app_config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: ../auth/login.php"); exit;
}

// Fetch first name
$stmt = $pdo->prepare("SELECT first_name FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
if (!$user) { header("Location: dashboard.php"); exit; }

// Risk message from session
$show_risk    = !empty($_SESSION['show_risk_message']);
$risk_level   = $_SESSION['risk_level_result'] ?? 'none';
unset($_SESSION['show_risk_message'], $_SESSION['risk_level_result']);

// Also pull from latest submission
if ($show_risk) {
    $rs = $pdo->prepare("SELECT risk_level FROM inventory_submissions WHERE user_id = ? AND is_complete = 1 ORDER BY id DESC LIMIT 1");
    $rs->execute([$_SESSION['user_id']]);
    $sub = $rs->fetch();
    $risk_level = $sub['risk_level'] ?? 'none';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submission Complete - CCT Inventory</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student_mobile.css">
</head>
<body>
<div class="thankyou-wrapper">
    <div class="thankyou-card">
        <div class="checkmark-circle">✓</div>
        <h1>Submission Complete!</h1>
        <p>Thank you, <strong><?php echo htmlspecialchars($user['first_name']); ?></strong>! Your Individual Inventory has been successfully submitted.</p>

        <?php if ($show_risk): ?>
        <div class="risk-message-box">
            <?php if ($risk_level === 'urgent'): ?>
                <strong>We care about your wellbeing.</strong><br>
                Thank you for being open with us. Your wellbeing matters deeply to us. We strongly encourage you to visit the Guidance Office as soon as possible — our counselors are here to support you. You are not alone.
            <?php elseif ($risk_level === 'high'): ?>
                <strong>We're here for you.</strong><br>
                Your responses indicate that you may be experiencing some challenges. Please know that support is available — consider reaching out to the Guidance Office for a confidential conversation.
            <?php elseif ($risk_level === 'moderate'): ?>
                <strong>Take care of yourself.</strong><br>
                You're doing well, but it's always good to check in. Our Guidance Office is open if you ever need someone to talk to.
            <?php else: ?>
                <strong>Great job completing your inventory!</strong><br>
                Your results show you are managing well. Keep taking care of yourself.
            <?php endif; ?>
        </div>
        <div class="thankyou-contact">
            <strong><?php echo GUIDANCE_OFFICE_NAME; ?></strong><br>
            📍 <?php echo GUIDANCE_OFFICE_LOCATION; ?><br>
            ✉️ <?php echo GUIDANCE_OFFICE_EMAIL; ?><br>
            📞 <?php echo GUIDANCE_OFFICE_PHONE; ?>
        </div>
        <?php endif; ?>

        <a href="dashboard.php" class="btn" style="display:block;width:100%;text-align:center;box-sizing:border-box;min-height:48px;line-height:48px;padding:0;margin-top:8px;">
            Go to Dashboard
        </a>
        <p class="countdown-text" id="countdown-text">Redirecting to dashboard in <span id="countdown">10</span> seconds…</p>
    </div>
</div>
<script>
let n = 10;
const el = document.getElementById('countdown');
const timer = setInterval(() => {
    n--;
    if (el) el.textContent = n;
    if (n <= 0) { clearInterval(timer); window.location.href = 'dashboard.php'; }
}, 1000);
// Cancel redirect on any interaction
document.querySelector('.thankyou-card').addEventListener('click', () => {
    clearInterval(timer);
    const ct = document.getElementById('countdown-text');
    if (ct) ct.style.display = 'none';
});
</script>
</body>
</html>
