<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>CrawlFlow - Quản lý dự án</h1>
    <p>
        <a href="<?php echo admin_url('admin.php?page=crawlflow-project-editor'); ?>" class="button button-primary">Tạo dự án mới</a>
    </p>
    <div id="crawlflow-dashboard-list">
        <!-- Danh sách dự án sẽ được render ở đây (có thể dùng JS/AJAX sau) -->
        <p>Đang tải danh sách dự án...</p>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Initialize dashboard
    CrawlFlowDashboard.init();
});
</script>