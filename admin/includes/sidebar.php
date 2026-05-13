<?php
// admin/includes/sidebar.php
$current = basename($_SERVER['PHP_SELF']);

function nav_item($href, $icon, $label, $current_page, $match, $disabled = false, $coming_soon = false) {
    $active = (strpos($match, $current_page) !== false) ? 'active' : '';
    $dis_cls = ($disabled || $coming_soon) ? 'disabled' : '';
    $tag = ($disabled || $coming_soon) ? 'span' : 'a';
    $href_attr = ($disabled || $coming_soon) ? '' : "href=\"$href\"";
    $badge = $coming_soon ? '<span class="coming-soon-badge">Soon</span>' : '';
    echo "<$tag $href_attr class=\"nav-item $active $dis_cls\">
        <span class=\"nav-icon\">$icon</span>
        <span class=\"nav-label\">$label</span>
        $badge
        <span class=\"nav-tooltip\">$label</span>
    </$tag>";
}
?>
<aside class="admin-sidebar" id="admin-sidebar">
    <div class="sidebar-logo">
        <div class="logo-circle">CCT</div>
        <div class="logo-text">
            <strong>InventaCCT</strong>
            <span>Guidance &amp; Counseling</span>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-group-label">Main</div>
        <?php nav_item('dashboard.php', '🏠', 'Dashboard', $current, 'dashboard.php'); ?>
        <?php nav_item('dashboard.php#students', '👥', 'Students', $current, ''); ?>
        <?php nav_item('dashboard.php#flagged', '📋', 'Flagged Reviews', $current, ''); ?>
        <?php nav_item('dashboard.php#priority', '⚠️', 'Priority Queue', $current, ''); ?>

        <div class="nav-group-label">Management</div>
        <?php nav_item('manage_periods.php', '📅', 'Inventory Periods', $current, 'manage_periods.php'); ?>
        <?php nav_item('manage_sections.php', '🏫', 'Sections', $current, 'manage_sections.php'); ?>

        <div class="nav-group-label">Reports</div>
        <?php nav_item('#', '📊', 'Analytics', $current, '', true, true); ?>
        <?php nav_item('actions/bulk_action.php', '📁', 'Export All Data', $current, ''); ?>
    </nav>

    <div class="sidebar-bottom">
        <div class="nav-group-label">Account</div>
        <?php nav_item('#', '👤', 'Admin Profile', $current, '', true, true); ?>
        <?php nav_item('../auth/logout.php', '🚪', 'Logout', $current, ''); ?>
    </div>
</aside>

<script>
(function(){
    const sidebar = document.getElementById('admin-sidebar');
    const wrapper = document.querySelector('.admin-wrapper');
    if (!sidebar || !wrapper) return;
    if (localStorage.getItem('sidebar_collapsed') === '1') {
        sidebar.classList.add('collapsed');
        wrapper.classList.add('collapsed');
    }
})();
</script>
