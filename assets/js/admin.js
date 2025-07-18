/**
 * CrawlFlow Admin JavaScript
 */
var CrawlFlowDashboard = {

    /**
     * Initialize dashboard
     */
    init: function() {
        this.bindEvents();
        this.loadStatus();
        this.startStatusPolling();
    },

    /**
     * Bind event handlers
     */
    bindEvents: function() {
        var self = this;

        // Start crawl button
        $('#start-crawl').on('click', function() {
            self.startCrawl();
        });

        // Stop crawl button
        $('#stop-crawl').on('click', function() {
            self.stopCrawl();
        });

        // Refresh status button
        $('#refresh-status').on('click', function() {
            self.loadStatus();
        });

        // Save configuration
        $('#save-config').on('click', function() {
            self.saveConfiguration();
        });
    },

    /**
     * Start crawl process
     */
    startCrawl: function() {
        var self = this;
        var $startBtn = $('#start-crawl');
        var $stopBtn = $('#stop-crawl');

        // Show loading state
        $startBtn.prop('disabled', true).html('<span class="crawlflow-loading"></span> ' + crawlflow_ajax.strings.crawling);

        $.ajax({
            url: crawlflow_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'crawlflow_start_crawl',
                nonce: crawlflow_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    self.showNotification('Crawl started successfully!', 'success');
                    $startBtn.prop('disabled', true);
                    $stopBtn.prop('disabled', false);
                    self.updateStatus('running');
                } else {
                    self.showNotification('Error starting crawl: ' + response.data, 'error');
                    $startBtn.prop('disabled', false).html(crawlflow_ajax.strings.start_crawl);
                }
            },
            error: function() {
                self.showNotification('Network error occurred', 'error');
                $startBtn.prop('disabled', false).html(crawlflow_ajax.strings.start_crawl);
            }
        });
    },

    /**
     * Stop crawl process
     */
    stopCrawl: function() {
        var self = this;
        var $startBtn = $('#start-crawl');
        var $stopBtn = $('#stop-crawl');

        // Show loading state
        $stopBtn.prop('disabled', true).html('<span class="crawlflow-loading"></span> Stopping...');

        $.ajax({
            url: crawlflow_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'crawlflow_stop_crawl',
                nonce: crawlflow_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    self.showNotification('Crawl stopped successfully!', 'success');
                    $startBtn.prop('disabled', false).html(crawlflow_ajax.strings.start_crawl);
                    $stopBtn.prop('disabled', true).html(crawlflow_ajax.strings.stop_crawl);
                    self.updateStatus('stopped');
                } else {
                    self.showNotification('Error stopping crawl: ' + response.data, 'error');
                    $stopBtn.prop('disabled', false).html(crawlflow_ajax.strings.stop_crawl);
                }
            },
            error: function() {
                self.showNotification('Network error occurred', 'error');
                $stopBtn.prop('disabled', false).html(crawlflow_ajax.strings.stop_crawl);
            }
        });
    },

    /**
     * Load current status
     */
    loadStatus: function() {
        var self = this;

        $.ajax({
            url: crawlflow_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'crawlflow_get_status',
                nonce: crawlflow_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    self.updateStatusDisplay(response.data);
                } else {
                    console.error('Error loading status:', response.data);
                }
            },
            error: function() {
                console.error('Network error loading status');
            }
        });
    },

    /**
     * Update status display
     */
    updateStatusDisplay: function(data) {
        // Update status numbers
        $('#total-urls').text(data.total_urls || 0);
        $('#processed-urls').text(data.processed_urls || 0);
        $('#feed-items').text(data.feed_items || 0);

        // Update status indicator
        var status = data.status || 'stopped';
        this.updateStatus(status);

        // Update activity log
        if (data.recent_activity) {
            this.updateActivityLog(data.recent_activity);
        }
    },

    /**
     * Update status indicator
     */
    updateStatus: function(status) {
        var $statusIndicator = $('#crawl-status');
        var $startBtn = $('#start-crawl');
        var $stopBtn = $('#stop-crawl');

        $statusIndicator.removeClass('running stopped paused').addClass(status);

        switch (status) {
            case 'running':
                $statusIndicator.text('Running');
                $startBtn.prop('disabled', true);
                $stopBtn.prop('disabled', false);
                break;
            case 'stopped':
                $statusIndicator.text('Stopped');
                $startBtn.prop('disabled', false);
                $stopBtn.prop('disabled', true);
                break;
            case 'paused':
                $statusIndicator.text('Paused');
                $startBtn.prop('disabled', false);
                $stopBtn.prop('disabled', false);
                break;
        }
    },

    /**
     * Update activity log
     */
    updateActivityLog: function(activities) {
        var $log = $('#activity-log');
        var html = '';

        if (activities && activities.length > 0) {
            activities.forEach(function(activity) {
                var className = activity.type || 'info';
                html += '<p class="' + className + '">[' + activity.timestamp + '] ' + activity.message + '</p>';
            });
        } else {
            html = '<p>No recent activity</p>';
        }

        $log.html(html);
        $log.scrollTop($log[0].scrollHeight);
    },

    /**
     * Start status polling
     */
    startStatusPolling: function() {
        var self = this;

        // Poll status every 5 seconds
        setInterval(function() {
            self.loadStatus();
        }, 5000);
    },

    /**
     * Save configuration
     */
    saveConfiguration: function() {
        var self = this;
        var config = {
            crawlflow_enabled: $('#crawlflow_enabled').is(':checked'),
            crawlflow_debug_mode: $('#crawlflow_debug_mode').is(':checked'),
            crawlflow_max_concurrent: $('#crawlflow_max_concurrent').val(),
            crawlflow_request_delay: $('#crawlflow_request_delay').val(),
            crawlflow_user_agent: $('#crawlflow_user_agent').val(),
            crawlflow_timeout: $('#crawlflow_timeout').val(),
            crawlflow_retry_attempts: $('#crawlflow_retry_attempts').val()
        };

        $.ajax({
            url: crawlflow_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'crawlflow_save_config',
                nonce: crawlflow_ajax.nonce,
                config: config
            },
            success: function(response) {
                if (response.success) {
                    self.showNotification('Configuration saved successfully!', 'success');
                } else {
                    self.showNotification('Error saving configuration: ' + response.data, 'error');
                }
            },
            error: function() {
                self.showNotification('Network error occurred', 'error');
            }
        });
    },

    /**
     * Show notification
     */
    showNotification: function(message, type) {
        var $notification = $('<div class="crawlflow-notification ' + type + '">' + message + '</div>');

        // Remove existing notifications
        $('.crawlflow-notification').remove();

        // Add new notification
        $('.wrap h1').after($notification);

        // Auto-remove after 5 seconds
        setTimeout(function() {
            $notification.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    },

    /**
     * Format timestamp
     */
    formatTimestamp: function(timestamp) {
        var date = new Date(timestamp);
        return date.toLocaleString();
    },

    /**
     * Format file size
     */
    formatFileSize: function(bytes) {
        if (bytes === 0) return '0 Bytes';
        var k = 1024;
        var sizes = ['Bytes', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
};

/**
 * Utility functions
 */
var CrawlFlowUtils = {

    /**
     * Debounce function
     */
    debounce: function(func, wait, immediate) {
        var timeout;
        return function() {
            var context = this, args = arguments;
            var later = function() {
                timeout = null;
                if (!immediate) func.apply(context, args);
            };
            var callNow = immediate && !timeout;
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
            if (callNow) func.apply(context, args);
        };
    },

    /**
     * Throttle function
     */
    throttle: function(func, limit) {
        var inThrottle;
        return function() {
            var args = arguments;
            var context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(function() {
                    inThrottle = false;
                }, limit);
            }
        };
    },

    /**
     * Validate URL
     */
    isValidUrl: function(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    },

    /**
     * Sanitize HTML
     */
    sanitizeHtml: function(html) {
        return $('<div>').html(html).text();
    }
};

// Initialize when document is ready
jQuery(document).ready(function($) {
    CrawlFlowDashboard.init();
});