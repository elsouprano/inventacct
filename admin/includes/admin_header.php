<?php
// admin/includes/admin_header.php
// Expects $pageTitle and $user to be set by the including page
$adminName = isset($user['last_name']) ? htmlspecialchars($user['last_name']) : 'Admin';
?>
<header class="admin-header" id="admin-header">
    <button class="hamburger-btn" onclick="toggleSidebar()" title="Toggle Sidebar" aria-label="Toggle Sidebar">&#9776;</button>
    <div class="header-title"><?php echo htmlspecialchars($pageTitle ?? 'Admin'); ?></div>
    <div class="header-right">
        <span>👤 <?php echo $adminName; ?></span>
        <a href="../auth/logout.php">Logout</a>
    </div>
</header>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('admin-sidebar');
    const wrapper = document.querySelector('.admin-wrapper');
    const collapsed = sidebar.classList.toggle('collapsed');
    wrapper.classList.toggle('collapsed', collapsed);
    localStorage.setItem('sidebar_collapsed', collapsed ? '1' : '0');
}
</script>
