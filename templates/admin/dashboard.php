<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1>CrawlFlow - Quản lý dự án</h1>
    <?php
    // Tổng số dự án
    $totalProjects = 5;

    // Số dự án đang hoạt động
    $activeProjects = 2;

    // Tổng số feed item đã xử lý
    $totalFeedItemsProcessed = 1200;

    // Tổng số feed item còn lại
    $totalFeedItemsPending = 300;

    // Số feed item bị bỏ qua
    $totalFeedItemsSkipped = 15;

    // Số feed item thất bại
    $totalFeedItemsFailed = 7;

    // Tổng số log
    $totalLogs = 150;

    ?>
    <div class="crawlflow-system-overview">
        <h2>Tổng quan hệ thống CrawlFlow</h2>
        <div class="overview-cards">
            <div class="overview-card">
                <h3>Tổng số dự án</h3>
                <div><?php echo $totalProjects; ?></div>
            </div>
            <div class="overview-card">
                <h3>Dự án đang hoạt động</h3>
                <div><?php echo $activeProjects; ?></div>
            </div>
            <div class="overview-card">
                <h3>Feed item đã xử lý</h3>
                <div><?php echo $totalFeedItemsProcessed; ?></div>
            </div>
            <div class="overview-card">
                <h3>Feed item còn lại</h3>
                <div><?php echo $totalFeedItemsPending; ?></div>
            </div>
            <div class="overview-card">
                <h3>Feed item bị bỏ qua</h3>
                <div><?php echo $totalFeedItemsSkipped; ?></div>
            </div>
            <div class="overview-card">
                <h3>Feed item thất bại</h3>
                <div><?php echo $totalFeedItemsFailed; ?></div>
            </div>
            <div class="overview-card">
                <h3>Tổng số logs</h3>
                <div><?php echo $totalLogs; ?></div>
            </div>
        </div>
    </div>
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