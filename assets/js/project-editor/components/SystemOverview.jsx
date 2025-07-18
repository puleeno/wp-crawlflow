import React, { useEffect, useState } from 'react';

export default function SystemOverview() {
  const [overview, setOverview] = useState({ loading: true });

  useEffect(() => {
    // Gọi API lấy tổng quan hệ thống (có thể cần chỉnh lại endpoint cho phù hợp backend)
    fetch(window.crawlflowSystemOverviewApi || '/wp-json/crawlflow/v1/system-overview')
      .then(res => res.json())
      .then(data => setOverview(data))
      .catch(() => setOverview({ error: true }));
  }, []);

  if (overview.loading) return <div>Đang tải tổng quan hệ thống...</div>;
  if (overview.error) return <div>Lỗi khi tải tổng quan hệ thống!</div>;

  return (
    <div className="crawlflow-system-overview">
      <h2>Tổng quan hệ thống CrawlFlow</h2>
      <div className="overview-cards">
        <div className="overview-card">
          <h3>Tổng số dự án</h3>
          <div>{overview.totalProjects || 0}</div>
        </div>
        <div className="overview-card">
          <h3>Tổng số feed</h3>
          <div>{overview.totalFeeds || 0}</div>
        </div>
        <div className="overview-card">
          <h3>Tổng số URL đã crawl</h3>
          <div>{overview.totalUrls || 0}</div>
        </div>
        <div className="overview-card">
          <h3>Tổng số feed item</h3>
          <div>{overview.totalFeedItems || 0}</div>
        </div>
        <div className="overview-card">
          <h3>Trạng thái hệ thống</h3>
          <div>{overview.status || 'Đang chờ'}</div>
        </div>
      </div>
      {/* Có thể bổ sung thêm log, thống kê, ... */}
    </div>
  );
}