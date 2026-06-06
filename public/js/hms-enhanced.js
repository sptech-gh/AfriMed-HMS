/**
 * HMS Enhanced UI/UX JavaScript
 * Built on top of existing jQuery - Non-breaking enhancements
 * Optimized for Ghana healthcare workflows
 * Version: 1.0
 */

(function($) {
    'use strict';

    // ========================================
    // GLOBAL CONFIGURATION
    // ========================================
    
    var HMS = {
        config: {
            autoSaveInterval: 30000, // 30 seconds
            loadingDelay: 300, // Show loading after 300ms
            alertDuration: 5000 // Auto-hide alerts after 5 seconds
        },
        
        // ========================================
        // FORM AUTO-SAVE
        // ========================================
        
        autoSave: {
            init: function(formId, storageKey) {
                var form = $('#' + formId);
                if (form.length === 0) return;
                
                var self = this;
                var key = storageKey || 'hms_draft_' + formId;
                
                // Auto-save on interval
                setInterval(function() {
                    self.save(form, key);
                }, HMS.config.autoSaveInterval);
                
                // Save on form change
                form.on('change input', function() {
                    self.save(form, key);
                });
                
                // Restore on page load
                self.restore(form, key);
                
                // Clear on successful submit
                form.on('submit', function() {
                    self.clear(key);
                });
            },
            
            save: function(form, key) {
                if (!window.localStorage) return;
                
                var data = form.serialize();
                localStorage.setItem(key, data);
                localStorage.setItem(key + '_timestamp', Date.now());
                
                this.updateIndicator(key);
            },
            
            restore: function(form, key) {
                if (!window.localStorage) return;
                
                var data = localStorage.getItem(key);
                var timestamp = localStorage.getItem(key + '_timestamp');
                
                if (data && timestamp) {
                    var age = Date.now() - parseInt(timestamp);
                    var ageMinutes = Math.floor(age / 60000);
                    
                    if (confirm('Found unsaved data from ' + ageMinutes + ' minutes ago. Restore it?')) {
                        var pairs = data.split('&');
                        pairs.forEach(function(pair) {
                            var parts = pair.split('=');
                            var name = decodeURIComponent(parts[0]);
                            var value = decodeURIComponent(parts[1] || '');
                            
                            var field = form.find('[name="' + name + '"]');
                            if (field.length) {
                                if (field.is(':checkbox') || field.is(':radio')) {
                                    field.prop('checked', value === field.val());
                                } else {
                                    field.val(value);
                                }
                            }
                        });
                        
                        HMS.notification.show('Draft restored successfully', 'success');
                    } else {
                        this.clear(key);
                    }
                }
            },
            
            clear: function(key) {
                if (!window.localStorage) return;
                
                localStorage.removeItem(key);
                localStorage.removeItem(key + '_timestamp');
                this.updateIndicator(key);
            },
            
            updateIndicator: function(key) {
                var timestamp = localStorage.getItem(key + '_timestamp');
                var indicator = $('#autosave-indicator');
                
                if (timestamp) {
                    var date = new Date(parseInt(timestamp));
                    var timeStr = date.toLocaleTimeString();
                    indicator.html('<i class="fa fa-check-circle text-success"></i> Last saved: ' + timeStr);
                } else {
                    indicator.html('');
                }
            }
        },
        
        // ========================================
        // LOADING STATES
        // ========================================
        
        loading: {
            overlay: null,
            
            init: function() {
                if ($('#hms-loading-overlay').length === 0) {
                    $('body').append(
                        '<div id="hms-loading-overlay" class="loading-overlay">' +
                        '<div class="loading-spinner"></div>' +
                        '</div>'
                    );
                }
                this.overlay = $('#hms-loading-overlay');
            },
            
            show: function(message) {
                this.init();
                if (message) {
                    this.overlay.find('.loading-spinner').after('<p style="margin-top: 20px; font-size: 16px; color: #333;">' + message + '</p>');
                }
                this.overlay.addClass('active');
            },
            
            hide: function() {
                if (this.overlay) {
                    this.overlay.removeClass('active');
                    this.overlay.find('p').remove();
                }
            },
            
            button: function(btn, loading) {
                var $btn = $(btn);
                if (loading) {
                    $btn.addClass('btn-loading').prop('disabled', true);
                    $btn.data('original-text', $btn.html());
                } else {
                    $btn.removeClass('btn-loading').prop('disabled', false);
                    if ($btn.data('original-text')) {
                        $btn.html($btn.data('original-text'));
                    }
                }
            }
        },
        
        // ========================================
        // NOTIFICATIONS
        // ========================================
        
        notification: {
            show: function(message, type, duration) {
                type = type || 'info';
                duration = duration || HMS.config.alertDuration;
                
                var alertClass = 'alert-' + type;
                var iconClass = this.getIcon(type);
                
                var alert = $('<div class="alert ' + alertClass + ' alert-dismissible" role="alert">' +
                    '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' +
                    '<span aria-hidden="true">&times;</span>' +
                    '</button>' +
                    '<i class="fa ' + iconClass + '"></i> ' +
                    message +
                    '</div>');
                
                // Find or create notification container
                var container = $('#hms-notification-container');
                if (container.length === 0) {
                    container = $('<div id="hms-notification-container" style="position: fixed; top: 70px; right: 20px; z-index: 9999; max-width: 400px;"></div>');
                    $('body').append(container);
                }
                
                container.append(alert);
                
                // Auto-hide after duration
                setTimeout(function() {
                    alert.fadeOut(300, function() {
                        $(this).remove();
                    });
                }, duration);
            },
            
            getIcon: function(type) {
                var icons = {
                    'success': 'fa-check-circle',
                    'danger': 'fa-exclamation-circle',
                    'warning': 'fa-exclamation-triangle',
                    'info': 'fa-info-circle'
                };
                return icons[type] || 'fa-info-circle';
            }
        },
        
        // ========================================
        // FORM VALIDATION ENHANCEMENT
        // ========================================
        
        validation: {
            init: function() {
                // Real-time validation
                $('form').on('blur', 'input, select, textarea', function() {
                    HMS.validation.validateField($(this));
                });
                
                // Ghana-specific validators
                this.addCustomValidators();
            },
            
            validateField: function(field) {
                var value = field.val();
                var required = field.prop('required');
                var type = field.attr('type');
                var formGroup = field.closest('.form-group');
                
                // Clear previous state
                formGroup.removeClass('has-error has-success');
                formGroup.find('.help-block.error').remove();
                
                // Required check
                if (required && !value) {
                    this.showError(formGroup, field, 'This field is required');
                    return false;
                }
                
                // Type-specific validation
                if (value) {
                    if (type === 'email' && !this.isValidEmail(value)) {
                        this.showError(formGroup, field, 'Please enter a valid email address');
                        return false;
                    }
                    
                    // Ghana phone number
                    if (field.hasClass('ghana-phone') && !this.isValidGhanaPhone(value)) {
                        this.showError(formGroup, field, 'Please enter a valid Ghana phone number (10 digits starting with 0)');
                        return false;
                    }
                    
                    // NHIS number
                    if (field.hasClass('nhis-number') && !this.isValidNHIS(value)) {
                        this.showError(formGroup, field, 'Please enter a valid NHIS number (10 digits)');
                        return false;
                    }
                }
                
                // Show success
                formGroup.addClass('has-success');
                return true;
            },
            
            showError: function(formGroup, field, message) {
                formGroup.addClass('has-error');
                field.after('<span class="help-block error">' + message + '</span>');
            },
            
            isValidEmail: function(email) {
                var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            },
            
            isValidGhanaPhone: function(phone) {
                var re = /^0[0-9]{9}$/;
                return re.test(phone);
            },
            
            isValidNHIS: function(nhis) {
                var re = /^[0-9]{10}$/;
                return re.test(nhis);
            },
            
            addCustomValidators: function() {
                // Add data attributes for validation
                $('input[type="tel"], input.phone').addClass('ghana-phone');
                $('input.nhis, input[name*="nhis"]').addClass('nhis-number');
            }
        },
        
        // ========================================
        // OFFLINE DETECTION
        // ========================================
        
        offline: {
            init: function() {
                window.addEventListener('online', this.onOnline);
                window.addEventListener('offline', this.onOffline);
                
                // Check initial status
                if (!navigator.onLine) {
                    this.onOffline();
                }
            },
            
            onOnline: function() {
                HMS.notification.show('You are back online. Data will sync now.', 'success');
                $('#offline-banner').fadeOut();
                
                // Sync queued data
                HMS.offline.syncQueue();
            },
            
            onOffline: function() {
                HMS.notification.show('You are offline. Changes will be saved locally.', 'warning');
                
                // Show offline banner
                if ($('#offline-banner').length === 0) {
                    $('body').prepend(
                        '<div id="offline-banner" style="background: #f39c12; color: #fff; padding: 10px; text-align: center; font-weight: bold;">' +
                        '<i class="fa fa-exclamation-triangle"></i> You are currently offline. Changes will be saved locally.' +
                        '</div>'
                    );
                }
            },
            
            syncQueue: function() {
                // Sync any queued offline actions
                if (!window.localStorage) return;
                
                var queue = localStorage.getItem('hms_sync_queue');
                if (queue) {
                    try {
                        var items = JSON.parse(queue);
                        // Process queue items
                        // Sync queued items silently
                        // Implementation depends on backend API
                    } catch (e) {
                        console.error('Error syncing queue:', e);
                    }
                }
            }
        },
        
        // ========================================
        // TABLE ENHANCEMENTS
        // ========================================
        
        table: {
            init: function() {
                // Add hover effect to table rows with links
                $('.table tbody tr').each(function() {
                    if ($(this).find('a').length > 0) {
                        $(this).css('cursor', 'pointer');
                    }
                });
                
                // Row click navigation
                $('.table tbody tr').on('click', function(e) {
                    if ($(e.target).is('a, button, input, select')) return;
                    
                    var link = $(this).find('a').first();
                    if (link.length) {
                        window.location.href = link.attr('href');
                    }
                });
                
                // Sortable columns
                this.initSorting();
            },
            
            initSorting: function() {
                $('.table thead th.sortable').css('cursor', 'pointer').on('click', function() {
                    var table = $(this).closest('table');
                    var index = $(this).index();
                    var rows = table.find('tbody tr').toArray();
                    var isAsc = $(this).hasClass('sort-asc');
                    
                    // Clear all sort indicators
                    table.find('thead th').removeClass('sort-asc sort-desc');
                    
                    // Sort rows
                    rows.sort(function(a, b) {
                        var aVal = $(a).find('td').eq(index).text();
                        var bVal = $(b).find('td').eq(index).text();
                        
                        if (isAsc) {
                            return aVal > bVal ? -1 : 1;
                        } else {
                            return aVal < bVal ? -1 : 1;
                        }
                    });
                    
                    // Update table
                    $.each(rows, function(index, row) {
                        table.find('tbody').append(row);
                    });
                    
                    // Toggle sort direction
                    $(this).addClass(isAsc ? 'sort-desc' : 'sort-asc');
                });
            }
        },
        
        // ========================================
        // SEARCH ENHANCEMENTS
        // ========================================
        
        search: {
            init: function() {
                // Debounced search
                var searchInputs = $('input[type="search"], input.search-field');
                searchInputs.on('input', this.debounce(function() {
                    HMS.search.performSearch($(this));
                }, 500));
            },
            
            debounce: function(func, wait) {
                var timeout;
                return function() {
                    var context = this, args = arguments;
                    clearTimeout(timeout);
                    timeout = setTimeout(function() {
                        func.apply(context, args);
                    }, wait);
                };
            },
            
            performSearch: function(input) {
                var value = input.val();
                var target = input.data('search-target');
                
                if (target) {
                    $(target + ' tbody tr').each(function() {
                        var text = $(this).text().toLowerCase();
                        if (text.indexOf(value.toLowerCase()) > -1) {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                }
            }
        },
        
        // ========================================
        // ACCESSIBILITY ENHANCEMENTS
        // ========================================
        
        accessibility: {
            init: function() {
                // Add ARIA labels to form controls without labels
                $('input, select, textarea').each(function() {
                    if (!$(this).attr('aria-label') && !$(this).attr('id')) {
                        var placeholder = $(this).attr('placeholder');
                        if (placeholder) {
                            $(this).attr('aria-label', placeholder);
                        }
                    }
                });
                
                // Keyboard navigation for modals
                $('.modal').on('shown.bs.modal', function() {
                    $(this).find('input, select, textarea, button').first().focus();
                });
                
                // Escape key to close modals
                $(document).on('keydown', function(e) {
                    if (e.key === 'Escape') {
                        $('.modal').modal('hide');
                        if (window.HMS && window.HMS.uiSafety && window.HMS.uiSafety.clearStuckOverlays) {
                            window.HMS.uiSafety.clearStuckOverlays();
                        }
                    }
                });
            }
        },

        uiSafety: {
            clearStuckOverlays: function() {
                try {
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                    $('#hms-loading-overlay').removeClass('active').find('p').remove();
                    $('.loading-overlay').removeClass('active').find('p').remove();
                } catch (e) {}
            }
        },
        
        // ========================================
        // DASHBOARD ENHANCEMENTS
        // ========================================
        
        dashboard: {
            init: function() {
                // Auto-refresh dashboard widgets
                this.autoRefresh();
                
                // Animate stat counters
                this.animateCounters();
            },
            
            autoRefresh: function() {
                // Refresh dashboard every 5 minutes
                setInterval(function() {
                    $('.dashboard-widget[data-refresh-url]').each(function() {
                        var widget = $(this);
                        var url = widget.data('refresh-url');
                        
                        $.get(url, function(data) {
                            widget.find('.dashboard-widget-content').html(data);
                        });
                    });
                }, 300000); // 5 minutes
            },
            
            animateCounters: function() {
                $('.dashboard-stat-value[data-count]').each(function() {
                    var $this = $(this);
                    var target = parseInt($this.data('count'));
                    var current = 0;
                    var increment = target / 50;
                    
                    var timer = setInterval(function() {
                        current += increment;
                        if (current >= target) {
                            current = target;
                            clearInterval(timer);
                        }
                        $this.text(Math.floor(current));
                    }, 20);
                });
            }
        },
        
        // ========================================
        // INITIALIZATION
        // ========================================
        
        init: function() {
            $(document).ready(function() {
                HMS.uiSafety.clearStuckOverlays();

                $(window).on('error', function() {
                    HMS.uiSafety.clearStuckOverlays();
                });

                // Initialize all modules
                HMS.validation.init();
                HMS.offline.init();
                HMS.table.init();
                HMS.search.init();
                HMS.accessibility.init();
                
                // Initialize dashboard if on dashboard page
                if ($('.dashboard-widget').length > 0) {
                    HMS.dashboard.init();
                }
                
                // Add autosave indicator to forms
                $('form').each(function() {
                    var formId = $(this).attr('id');
                    if (formId && !$(this).hasClass('no-autosave')) {
                        $(this).prepend('<div id="autosave-indicator" class="text-muted" style="font-size: 12px; margin-bottom: 10px;"></div>');
                        HMS.autoSave.init(formId);
                    }
                });
                
                // Enhance all buttons with loading state on submit
                $('form').on('submit', function() {
                    var submitBtn = $(this).find('button[type="submit"]');
                    HMS.loading.button(submitBtn, true);
                });
                
                // Auto-hide alerts
                $('.alert').not('.alert-permanent').delay(HMS.config.alertDuration).fadeOut(300);
                
                // HMS Enhanced UI/UX initialized
            });
        }
    };
    
    // Auto-initialize
    HMS.init();
    
    // Expose HMS object globally
    window.HMS = HMS;

    window.HMSClearUiBlockers = function() {
        if (window.HMS && window.HMS.uiSafety && window.HMS.uiSafety.clearStuckOverlays) {
            window.HMS.uiSafety.clearStuckOverlays();
        }
    };
    
})(jQuery);
