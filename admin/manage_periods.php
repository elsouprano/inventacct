<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle deactivate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deactivate') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['period_error'] = 'Invalid CSRF token.';
    } else {
        $period_id = (int)($_POST['period_id'] ?? 0);
        $pdo->prepare("UPDATE inventory_periods SET is_active = 0 WHERE id = ?")->execute([$period_id]);
        $_SESSION['period_success'] = 'Period deactivated. The inventory is now closed for all students.';
    }
    header("Location: manage_periods.php");
    exit;
}

// Fetch active period
$active_period = $pdo->query("SELECT p.*, u.first_name, u.last_name FROM inventory_periods p LEFT JOIN users u ON p.created_by = u.id WHERE p.is_active = 1 LIMIT 1")->fetch();

// Fetch period history
$history = $pdo->query("SELECT p.*, u.first_name, u.last_name, eu.first_name as ext_first, eu.last_name as ext_last FROM inventory_periods p LEFT JOIN users u ON p.created_by = u.id LEFT JOIN users eu ON p.extended_by = eu.id WHERE p.is_active = 0 ORDER BY p.created_at DESC")->fetchAll();

$now = time();
$error   = $_SESSION['period_error']   ?? null; unset($_SESSION['period_error']);
$success = $_SESSION['period_success'] ?? null; unset($_SESSION['period_success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Inventory Periods - Admin</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin_layout.css">
    <style>
        .period-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.07);
            padding: 25px;
            margin-bottom: 30px;
            border-left: 5px solid var(--primary-color);
        }
        .period-card.no-period {
            border-left-color: #aaa;
            color: #666;
            font-style: italic;
        }
        .period-meta { display: flex; gap: 30px; flex-wrap: wrap; margin: 15px 0; }
        .period-meta div { font-size: 0.9em; }
        .period-meta label { color: #888; font-size: 0.8em; display: block; }
        .badge { padding: 5px 12px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .badge-open     { background: #d4edda; color: #155724; }
        .badge-closed   { background: #f8d7da; color: #721c24; }
        .badge-upcoming { background: #cce5ff; color: #004085; }
        .form-card {
            background: var(--white);
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 25px;
            margin-bottom: 30px;
        }
        .form-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .form-row .form-group { flex: 1; min-width: 200px; }
        .form-group label { display: block; margin-bottom: 5px; font-size: 0.9em; font-weight: bold; color: #555; }
        .form-group input[type="text"],
        .form-group input[type="datetime-local"] {
            width: 100%; padding: 9px; border: 1px solid var(--border-color); border-radius: 4px; font-size: 0.95em;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 11px 14px; border-bottom: 1px solid var(--border-color); text-align: left; }
        th { background: #f8f9fa; color: var(--primary-color); font-size: 0.9em; }
        .extend-form { display: inline-flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-top: 12px; }
        .extend-form input { padding: 6px; border: 1px solid var(--border-color); border-radius: 4px; }
        .btn-sm { padding: 6px 14px; border-radius: 4px; cursor: pointer; border: none; font-size: 0.9em; text-decoration: none; display: inline-block; }
        .btn-deactivate { background: #dc3545; color: white; }
        .btn-extend     { background: var(--primary-color); color: white; }
    </style>
</head>
<body>
<?php $pageTitle = 'Manage Periods'; ?>
<div class="admin-wrapper">
    <?php include 'includes/sidebar.php'; ?>
    <div class="admin-main">
        <?php include 'includes/admin_header.php'; ?>
        <div class="admin-content">

        <h1>Manage Inventory Periods</h1>

        <?php if ($error): ?>
            <div class="error-message" style="margin-bottom: 20px;"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div style="background:#d4edda;color:#155724;padding:12px 16px;border-radius:4px;margin-bottom:20px;"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- Active Period Card -->
        <h2 style="margin-bottom: 15px;">Active Period</h2>
        <?php if ($active_period): 
            $is_open     = $now >= strtotime($active_period['open_date'])  && $now <= strtotime($active_period['close_date']);
            $is_upcoming = $now <  strtotime($active_period['open_date']);
            $days_left   = max(0, (int)ceil((strtotime($active_period['close_date']) - $now) / 86400));
        ?>
        <div class="period-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
                <h3 style="margin:0;"><?php echo htmlspecialchars($active_period['label']); ?></h3>
                <?php if ($is_upcoming): ?>
                    <span class="badge badge-upcoming">Upcoming</span>
                <?php elseif ($is_open): ?>
                    <span class="badge badge-open">Open &bull; <?php echo $days_left; ?> day<?php echo $days_left !== 1 ? 's' : ''; ?> remaining</span>
                <?php else: ?>
                    <span class="badge badge-closed">Closed</span>
                <?php endif; ?>
            </div>
            <div class="period-meta">
                <div><label>Opens</label><?php echo date('M d, Y h:i A', strtotime($active_period['open_date'])); ?></div>
                <div><label>Closes</label><?php echo date('M d, Y h:i A', strtotime($active_period['close_date'])); ?></div>
                <div><label>Created by</label><?php echo htmlspecialchars($active_period['first_name'] . ' ' . $active_period['last_name']); ?></div>
                <?php if ($active_period['extended_at']): ?>
                    <div><label>Extended at</label><?php echo date('M d, Y h:i A', strtotime($active_period['extended_at'])); ?></div>
                <?php endif; ?>
            </div>

            <!-- Extend form -->
            <form method="POST" action="actions/extend_period.php" class="extend-form">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="period_id" value="<?php echo $active_period['id']; ?>">
                <label style="font-size:0.9em;font-weight:bold;color:#555;">Extend close date:</label>
                <input type="datetime-local" name="new_close_date" required min="<?php echo date('Y-m-d\TH:i', strtotime($active_period['close_date']) + 60); ?>">
                <button type="submit" class="btn-sm btn-extend">Extend</button>
            </form>

            <!-- Deactivate -->
            <form method="POST" action="" style="margin-top:15px;" onsubmit="return confirm('This will close the inventory for all students. Are you sure?');">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="period_id" value="<?php echo $active_period['id']; ?>">
                <button type="submit" class="btn-sm btn-deactivate">Deactivate Period</button>
            </form>
        </div>
        <?php else: ?>
        <div class="period-card no-period">
            No active period. Create one below.
        </div>
        <?php endif; ?>

        <!-- Create New Period -->
        <h2 style="margin-bottom: 15px;">Create New Period</h2>
        <div class="form-card">
            <?php if ($active_period): ?>
                <p style="color:#856404;background:#fff3cd;padding:10px 14px;border-radius:4px;margin:0;">
                    ⚠ An active period already exists. Deactivate it before creating a new one.
                </p>
            <?php else: ?>
            <form method="POST" action="actions/create_period.php">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <div class="form-group" style="margin-bottom:15px;">
                    <label for="label">Period Label</label>
                    <input type="text" id="label" name="label" placeholder="e.g. 1st Semester 2025-2026" required>
                </div>
                <div class="form-row" style="margin-bottom:20px;">
                    <div class="form-group">
                        <label for="open_date">Open Date &amp; Time</label>
                        <input type="datetime-local" id="open_date" name="open_date" required>
                    </div>
                    <div class="form-group">
                        <label for="close_date">Close Date &amp; Time</label>
                        <input type="datetime-local" id="close_date" name="close_date" required>
                    </div>
                </div>
                <button type="submit" class="btn" style="width:auto;padding:10px 25px;">Create Period</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Period History -->
        <h2 style="margin-bottom: 15px;">Period History</h2>
        <div style="background:var(--white);border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05);overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Label</th>
                        <th>Open Date</th>
                        <th>Close Date</th>
                        <th>Created By</th>
                        <th>Extended?</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                        <tr><td colspan="6" style="text-align:center;color:#888;padding:20px;">No past periods yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($history as $h): 
                            $completed = time() > strtotime($h['close_date']);
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($h['label']); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($h['open_date'])); ?></td>
                            <td><?php echo date('M d, Y h:i A', strtotime($h['close_date'])); ?></td>
                            <td><?php echo htmlspecialchars($h['first_name'] . ' ' . $h['last_name']); ?></td>
                            <td><?php echo $h['extended_at'] ? '✓ Yes' : '—'; ?></td>
                            <td>
                                <?php if ($completed): ?>
                                    <span class="badge badge-closed">Completed</span>
                                <?php else: ?>
                                    <span class="badge" style="background:#e2e3e5;color:#383d41;">Deactivated</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div><!-- admin-content -->
    </div><!-- admin-main -->
</div><!-- admin-wrapper -->
</body>
</html>
