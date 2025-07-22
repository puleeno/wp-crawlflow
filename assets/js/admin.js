/**
 * CrawlFlow Admin JavaScript
 */

(function($) {
    'use strict';

    // CrawlFlow Admin namespace
    window.CrawlFlowAdmin = window.CrawlFlowAdmin || {};

    // Initialize admin functionality
    CrawlFlowAdmin.init = function() {
        this.initDashboard();
        this.initProjectCompose();
        this.initSettings();
        this.initLogs();
    };

    // Dashboard functionality
    CrawlFlowAdmin.initDashboard = function() {
        if (!$('.crawlflow-overview-cards').length) {
            return;
        }

        // Auto-refresh dashboard stats
        this.setupAutoRefresh();

        // Setup refresh button
        $('.crawlflow-refresh-stats').on('click', function(e) {
            e.preventDefault();
            CrawlFlowAdmin.refreshDashboardStats();
        });
    };



    // Project compose functionality
    CrawlFlowAdmin.initProjectCompose = function() {
        if (!$('#project-compose-form').length) {
            return;
        }

        // Setup form validation
        this.setupProjectComposeValidation();

        // Setup auto-save functionality
        this.setupProjectComposeAutoSave();

        // Setup form field change handlers
        this.setupProjectComposeFieldHandlers();
    };

    // Settings functionality
    CrawlFlowAdmin.initSettings = function() {
        if (!$('.crawlflow-settings-form').length) {
            return;
        }

        // Setup settings form
        this.setupSettingsForm();
    };

    // Logs functionality
    CrawlFlowAdmin.initLogs = function() {
        if (!$('.crawlflow-logs-controls').length) {
            return;
        }

        // Setup log filtering
        this.setupLogFiltering();

        // Setup log level filtering
        $('select[name="level"]').on('change', function() {
            CrawlFlowAdmin.filterLogsByLevel($(this).val());
        });
    };

    // Auto-refresh dashboard stats
    CrawlFlowAdmin.setupAutoRefresh = function() {
        // Refresh stats every 30 seconds
        setInterval(function() {
            CrawlFlowAdmin.refreshDashboardStats();
        }, 30000);
    };

    // Refresh dashboard stats via AJAX
    CrawlFlowAdmin.refreshDashboardStats = function() {
        $.ajax({
            url: crawlflowAdmin.ajaxUrl,
            type: 'POST',
            data: {
                action: 'crawlflow_refresh_dashboard',
                nonce: crawlflowAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    CrawlFlowAdmin.updateDashboardStats(response.data);
                }
            },
            error: function() {
                console.error('Failed to refresh dashboard stats');
            }
        });
    };

    // Update dashboard stats
    CrawlFlowAdmin.updateDashboardStats = function(data) {
        // Update project stats
        $('.stat-value[data-stat="total_projects"]').text(data.total_projects || 0);
        $('.stat-value[data-stat="active_projects"]').text(data.active_projects || 0);

        // Update URL stats
        $('.stat-value[data-stat="total_urls_processed"]').text(data.total_urls_processed || 0);
        $('.stat-value[data-stat="total_urls_pending"]').text(data.total_urls_pending || 0);
        $('.stat-value[data-stat="total_urls_failed"]').text(data.total_urls_failed || 0);
        $('.stat-value[data-stat="total_urls_skipped"]').text(data.total_urls_skipped || 0);

        // Update system stats
        $('.stat-value[data-stat="total_logs"]').text(data.total_logs || 0);
    };



    // Setup project compose validation
    CrawlFlowAdmin.setupProjectComposeValidation = function() {
        $('#project-compose-form').on('submit', function(e) {
            var projectName = $('#project_name').val().trim();
            var toothType = $('#tooth_type').val();
            var baseUrl = $('#base_url').val().trim();

            // Clear previous errors
            $('.error-message').remove();
            $('input, select, textarea').removeClass('error');

            var hasErrors = false;

            if (!projectName) {
                CrawlFlowAdmin.showFieldError('#project_name', 'Please enter a project name');
                hasErrors = true;
            }

            if (!toothType) {
                CrawlFlowAdmin.showFieldError('#tooth_type', 'Please select a tooth type');
                hasErrors = true;
            }

            if (!baseUrl) {
                CrawlFlowAdmin.showFieldError('#base_url', 'Please enter a base URL');
                hasErrors = true;
            } else {
                // Validate URL format
                try {
                    new URL(baseUrl);
                } catch (e) {
                    CrawlFlowAdmin.showFieldError('#base_url', 'Please enter a valid URL');
                    hasErrors = true;
                }
            }

            if (hasErrors) {
                e.preventDefault();
                return false;
            }
        });
    };

    // Show field error
    CrawlFlowAdmin.showFieldError = function(fieldSelector, message) {
        $(fieldSelector).addClass('error');
        $(fieldSelector).after('<div class="error-message">' + message + '</div>');
    };

    // Setup project compose auto-save
    CrawlFlowAdmin.setupProjectComposeAutoSave = function() {
        var autoSaveTimer;
        var autoSaveIndicator = $('<div class="crawlflow-auto-save-indicator">Auto-saved</div>');
        $('body').append(autoSaveIndicator);

        $('input, textarea, select').on('change', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                CrawlFlowAdmin.autoSaveProject();
            }, 2000);
        });
    };

    // Auto-save project
    CrawlFlowAdmin.autoSaveProject = function() {
        var formData = $('#project-compose-form').serialize();
        formData += '&action=crawlflow_auto_save_project&nonce=' + crawlflowAdmin.nonce;

        $.ajax({
            url: crawlflowAdmin.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    CrawlFlowAdmin.showAutoSaveIndicator();
                }
            },
            error: function() {
                console.error('Auto-save failed');
            }
        });
    };

    // Show auto-save indicator
    CrawlFlowAdmin.showAutoSaveIndicator = function() {
        var indicator = $('.crawlflow-auto-save-indicator');
        indicator.addClass('show');
        setTimeout(function() {
            indicator.removeClass('show');
        }, 2000);
    };

    // Setup project compose field handlers
    CrawlFlowAdmin.setupProjectComposeFieldHandlers = function() {
        // URL validation on blur
        $('#base_url').on('blur', function() {
            var url = $(this).val().trim();
            if (url) {
                try {
                    new URL(url);
                    $(this).removeClass('error');
                    $(this).next('.error-message').remove();
                } catch (e) {
                    CrawlFlowAdmin.showFieldError('#base_url', 'Please enter a valid URL');
                }
            }
        });

        // Clear errors on focus
        $('input, textarea, select').on('focus', function() {
            $(this).removeClass('error');
            $(this).next('.error-message').remove();
        });
    };



    // Setup settings form
    CrawlFlowAdmin.setupSettingsForm = function() {
        $('.crawlflow-settings-form').on('submit', function(e) {
            // Add any settings form validation here
            return true;
        });
    };

    // Setup log filtering
    CrawlFlowAdmin.setupLogFiltering = function() {
        $('.crawlflow-logs-filter').on('submit', function(e) {
            // Form will submit normally
            return true;
        });
    };

    // Filter logs by level
    CrawlFlowAdmin.filterLogsByLevel = function(level) {
        if (!level) {
            $('.log-row').show();
            return;
        }

        $('.log-row').hide();
        $('.log-row[data-level="' + level + '"]').show();
    };

    // Utility functions
    CrawlFlowAdmin.showNotification = function(message, type) {
        type = type || 'info';

        var notification = $('<div class="crawlflow-notification crawlflow-notification-' + type + '">' + message + '</div>');
        $('body').append(notification);

        setTimeout(function() {
            notification.fadeOut(function() {
                $(this).remove();
            });
        }, 3000);
    };

    CrawlFlowAdmin.confirmAction = function(message, callback) {
        if (confirm(message)) {
            callback();
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        CrawlFlowAdmin.init();
    });

    // Global functions for project management
    window.deleteProject = function(projectId) {
        if (confirm('Are you sure you want to delete this project?')) {
            $.ajax({
                url: crawlflowAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'crawlflow_delete_project',
                    project_id: projectId,
                    nonce: crawlflowAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert('Failed to delete project');
                    }
                },
                error: function() {
                    alert('Failed to delete project');
                }
            });
        }
    };

    window.runMigration = function() {
        if (confirm('Are you sure you want to run the migration?')) {
            var form = $('<form method="post" action="' + crawlflowAdmin.adminUrl + 'admin-post.php">');
            form.append('<input type="hidden" name="action" value="crawlflow_run_migration">');
            form.append('<input type="hidden" name="nonce" value="' + crawlflowAdmin.nonce + '">');
            $('body').append(form);
            form.submit();
        }
    };

    window.exportData = function(type) {
        var form = $('<form method="post" action="' + crawlflowAdmin.adminUrl + 'admin-post.php">');
        form.append('<input type="hidden" name="action" value="crawlflow_export_data">');
        form.append('<input type="hidden" name="export_type" value="' + type + '">');
        form.append('<input type="hidden" name="nonce" value="' + crawlflowAdmin.nonce + '">');
        $('body').append(form);
        form.submit();
    };

})(jQuery);