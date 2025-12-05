define(['jquery'], function($) {
    var PdfCounterMonitor = {
        courseid: null,
        refreshInterval: 3000, // 3 seconds for faster updates
        intervalId: null,
        initialized: false,

        init: function(courseid) {
            // Get course ID from multiple sources, prioritizing the most reliable
            this.courseid = this.getCurrentCourseId(courseid);
            
            if (!this.courseid || this.courseid <= 0) {
                return;
            }
            
            this.startMonitoring();
            this.setupDOMObserver();
            this.initialized = true;
        },

        getCurrentCourseId: function(providedCourseId) {
            // Priority order for getting course ID:
            // 1. Provided course ID parameter
            // 2. From URL parameters
            // 3. From page body data attributes
            // 4. From M.cfg if available
            
            if (providedCourseId && providedCourseId > 0) {
                return providedCourseId;
            }
            
            // Try to get from URL
            var urlParams = new URLSearchParams(window.location.search);
            var courseIdFromUrl = urlParams.get('id');
            if (courseIdFromUrl && parseInt(courseIdFromUrl) > 0) {
                return parseInt(courseIdFromUrl);
            }
            
            // Try to get from body data attributes
            var bodyElement = document.querySelector('body');
            if (bodyElement) {
                var courseIdFromBody = bodyElement.getAttribute('data-courseid') || 
                                     bodyElement.getAttribute('data-course-id');
                if (courseIdFromBody && parseInt(courseIdFromBody) > 0) {
                    return parseInt(courseIdFromBody);
                }
            }
            
            // Try to get from page context
            var pageWrapper = document.querySelector('#page-wrapper');
            if (pageWrapper) {
                var courseIdFromWrapper = pageWrapper.getAttribute('data-courseid');
                if (courseIdFromWrapper && parseInt(courseIdFromWrapper) > 0) {
                    return parseInt(courseIdFromWrapper);
                }
            }
            
            // Try from course header
            var courseHeader = document.querySelector('.course-content');
            if (courseHeader) {
                var courseIdFromHeader = courseHeader.getAttribute('data-courseid');
                if (courseIdFromHeader && parseInt(courseIdFromHeader) > 0) {
                    return parseInt(courseIdFromHeader);
                }
            }
            
            // Last resort: M.cfg
            if (M.cfg && M.cfg.courseid && M.cfg.courseid > 0) {
                return M.cfg.courseid;
            }
            
            // Fallback: try to extract from current URL path
            var pathMatch = window.location.pathname.match(/\/course\/view\.php/);
            if (pathMatch && courseIdFromUrl) {
                return parseInt(courseIdFromUrl);
            }
            
            return null;
        },

        startMonitoring: function() {
            var self = this;
            // Start the background evaluation loop
            self.evaluatePendingPdfs();
        },

        stopMonitoring: function() {
            // No interval needed for background evaluation
        },

        // Avalia PDFs pendentes em loop via AJAX
        evaluatePendingPdfs: function() {
            var self = this;
            // Chama ajax_eval_pdf.php até não restar pendente
            function evalNext() {
                $.ajax({
                    url: M.cfg.wwwroot + '/blocks/pdfcounter/ajax_eval_pdf.php',
                    type: 'POST',
                    dataType: 'json',
                    contentType: 'application/x-www-form-urlencoded',
                    data: $.param({
                        courseid: self.courseid,
                        sesskey: M.cfg.sesskey
                    }),
                    success: function(response) {
                        if (response.done) {
                            // Todos avaliados, agora atualiza dashboard
                            self.refreshDashboard();
                            // Repete após intervalo
                            setTimeout(self.evaluatePendingPdfs.bind(self), self.refreshInterval);
                        } else {
                            // Avaliou um, atualiza dashboard e chama próximo
                            self.refreshDashboard();
                            setTimeout(evalNext, 1000);
                        }
                    },
                    error: function() {
                        // Em caso de erro, tenta novamente depois
                        setTimeout(evalNext, self.refreshInterval);
                    }
                });
            }
            evalNext();
        },

        // Atualiza dashboard (só leitura)
        refreshDashboard: function() {
            var self = this;
            $.ajax({
                url: M.cfg.wwwroot + '/blocks/pdfcounter/ajax/update_dashboard.php',
                type: 'POST',
                dataType: 'json',
                contentType: 'application/json',
                data: JSON.stringify({
                    courseid: self.courseid,
                    sesskey: M.cfg.sesskey
                }),
                success: function(response) {
                    if (response.status === 'ok') {
                        self.updateDashboardUI(response);
                    }
                }
            });
        },

        updateDashboardUI: function(data) {
            // Update overall accessibility value
            var overallElement = $('#overall-accessibility-value');
            if (overallElement.length) {
                overallElement.text(data.overallProgress + '%');
                
                // Apply color based on progress (keep black as user requested)
                var color = '#000000ff';
                overallElement.css('color', color);
            }

            // Update progress bar (HTML5 progress element)
            var progressBar = $('.pdf-counter-progress-bar');
            if (progressBar.length) {
                // Update the value attribute
                progressBar.attr('value', data.overallProgress);
                
                // Update the CSS custom property for color
                var progressColor = data.overallProgress >= 80 ? '#28a745' : 
                                   (data.overallProgress >= 50 ? '#ffc107' : '#dc3545');
                progressBar.css('--progress-color', progressColor);
            }

            // Update PDF issues list
            this.updatePdfIssues(data.pdfIssues);
            
            // Update historical trends chart
            this.updateHistoricalChart(data.overallProgress);
            
            // Update total count if element exists
            var totalElement = $('.total-pdfs-count');
            if (totalElement.length) {
                totalElement.text(data.totalPdfs + ' PDFs');
            }
        },

        updatePdfIssues: function(pdfIssues) {
            var issuesList = $('#pdf-issues-list tbody');
            if (!issuesList.length) return;

            if (!pdfIssues || pdfIssues.length === 0) {
                issuesList.html('<tr><td colspan="2" style="text-align: center; color: #28a745; font-style: italic; padding: 15px;">No PDF accessibility issues found.</td></tr>');
                return;
            }

            var html = '';
            var self = this; // Store reference to this
            pdfIssues.forEach(function(pdf) {
                var applicableTests = pdf.pass_count + pdf.fail_count + pdf.not_tagged_count;
                var failedTests = pdf.fail_count + pdf.not_tagged_count;
                
                // Generate download URL (using course ID from the monitor)
                var downloadUrl = M.cfg.wwwroot + '/blocks/pdfcounter/download_report.php?filename=' + 
                                encodeURIComponent(pdf.filename) + '&courseid=' + self.courseid;

                html += '<tr>';
                html += '  <td style="align-content: flex-start;">' + pdf.filename + '</td>';
                html += '  <td style="text-align:right; font-weight: bold; width:100%;">' + failedTests + ' of ' + applicableTests + ' tests failed</td>';
                html += '</tr>';
                html += '<tr>';
                html += '  <td></td>';
                html += '  <td style="text-align: right;"><a href="' + downloadUrl + '" target="_blank" style="margin-left:8px; font-size:0.9em;"><i class="fa fa-download" aria-hidden="true"></i> Download Report</a></td>';
                html += '</tr>';
            });

            issuesList.html(html);
        },

        updateHistoricalChart: function(currentProgress) {
            // Check if Chart.js is available and chart exists
            if (typeof Chart !== 'undefined' && window.progressChart) {
                // Get current month
                var currentMonth = new Date().toISOString().slice(0, 7); // Format: YYYY-MM
                
                // Get current data
                var chart = window.progressChart;
                var labels = chart.data.labels;
                var data = chart.data.datasets[0].data;
                
                // Update or add current month data
                var monthIndex = labels.indexOf(currentMonth);
                if (monthIndex !== -1) {
                    // Update existing month
                    data[monthIndex] = currentProgress;
                } else {
                    // Add new month
                    labels.push(currentMonth);
                    data.push(currentProgress);
                    
                    // Keep only last 6 months
                    if (labels.length > 6) {
                        labels.shift();
                        data.shift();
                    }
                }
                
                // Update the chart
                chart.update();
            }
        },

        setupDOMObserver: function() {
            var self = this;
            
            // Watch for changes in course content area
            var targetNode = document.querySelector('#region-main');
            if (!targetNode) return;

            var observer = new MutationObserver(function(mutations) {
                var shouldRefresh = false;
                
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'childList') {
                        // Check if any activity elements were added or removed
                        mutation.addedNodes.forEach(function(node) {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                if (node.classList.contains('activity') || 
                                    node.querySelector('.activity') ||
                                    node.classList.contains('resource') ||
                                    node.querySelector('.resource') ||
                                    node.classList.contains('activityinstance') ||
                                    node.querySelector('.activityinstance')) {
                                    shouldRefresh = true;
                                }
                            }
                        });
                        
                        mutation.removedNodes.forEach(function(node) {
                            if (node.nodeType === Node.ELEMENT_NODE) {
                                if (node.classList.contains('activity') || 
                                    node.querySelector('.activity') ||
                                    node.classList.contains('resource') ||
                                    node.querySelector('.resource') ||
                                    node.classList.contains('activityinstance') ||
                                    node.querySelector('.activityinstance')) {
                                    shouldRefresh = true;
                                }
                            }
                        });
                    }
                });

                if (shouldRefresh) {
                    // Add a delay to allow DB operations to complete
                    setTimeout(function() {
                        self.refreshDashboard();
                    }, 2000);
                }
            });

            observer.observe(targetNode, {
                childList: true,
                subtree: true
            });
        },

        destroy: function() {
            this.stopMonitoring();
            this.initialized = false;
        }
    };

    return {
        init: function(courseid) {
            PdfCounterMonitor.init(courseid);
        },
        
        destroy: function() {
            PdfCounterMonitor.destroy();
        }
    };
});