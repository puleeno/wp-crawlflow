<div class="wrap">
    <h1>CrawlFlow - Tạo/Sửa Dự Án</h1>
    <div id="crawlflow-project-editor-root">
        <!-- React app sẽ mount vào đây -->
    </div>
    <script src="<?php echo CRAWLFLOW_PLUGIN_URL . 'assets/js/crawlflow-project-editor.js'; ?>?v=<?php echo CRAWLFLOW_VERSION; ?>"></script>
    <script>
      if (window.React && window.ReactDOM && document.getElementById('crawlflow-project-editor-root')) {
        // Nếu bạn export default App, có thể dùng ReactDOM.render(<App />, ...)
        // Nhưng với webpack, bạn nên import và render trong JS entry point
      }
    </script>
</div>