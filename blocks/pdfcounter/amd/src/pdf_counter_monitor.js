define(['jquery', 'core/str'], function($, Str) {
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
                            // Todos avaliados, atualiza dashboard uma vez e pára o loop
                            setTimeout(function() {
                                self.refreshDashboard();
                            }, 500);
                        } else {
                            // Avaliou um, atualiza dashboard e chama próximo
                            setTimeout(function() {
                                self.refreshDashboard();
                                setTimeout(evalNext, 1000);
                            }, 500);
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
            // Atualizar mensagem de PDFs pendentes em tempo real
            var pendingCount = 0;

            // Se o backend já enviar o número de PDFs pendentes, usar esse valor
            if (typeof data.pendingCount !== 'undefined' && data.pendingCount !== null) {
                pendingCount = data.pendingCount;
            } else if (data.pdfIssues && data.pdfIssues.length > 0) {
                // Fallback: calcular no frontend a partir dos resultados
                data.pdfIssues.forEach(function(issue) {
                    var totalTests = issue.pass_count + issue.fail_count + issue.not_tagged_count;
                    var doneTests = issue.pass_count + issue.fail_count + issue.not_tagged_count;
                    if (totalTests > 0 && doneTests === issue.not_tagged_count) {
                        pendingCount++;
                    }
                });
            }

            var pendingMsg = document.getElementById('pdf-pending-msg');
            if (pendingMsg) {
                if (pendingCount > 0) {
                    // Obter string localizada a partir do language pack
                    Str.get_string('pendingmsg_analyzing', 'block_pdfcounter', pendingCount)
                        .then(function(msg) {
                            pendingMsg.innerHTML = '⚠️ ' + msg;
                            pendingMsg.style.display = '';
                        })
                        .catch(function() {
                            pendingMsg.innerHTML = '⚠️ ' + pendingCount + ' PDF(s)';
                            pendingMsg.style.display = '';
                        });
                } else {
                    pendingMsg.innerHTML = '';
                    pendingMsg.style.display = 'none';
                }
            }
            // LOG: Enviar pdfIssues para backend debug
            try {
                $.ajax({
                    url: M.cfg.wwwroot + '/blocks/pdfcounter/ajax/log_pdf_issues.php',
                    type: 'POST',
                    dataType: 'json',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        pdfIssues: data.pdfIssues,
                        courseid: data.courseid || (window.M && M.cfg && M.cfg.courseid ? M.cfg.courseid : null)
                    })
                });
            } catch (e) {
                // Ignore log errors
            }

            // ...existing code...
            var overallElement = $('#overall-accessibility-value');
            if (overallElement.length) {
                overallElement.text(data.overallProgress + '%');
                var color = '#000000ff';
                overallElement.css('color', color);
            }

            var progressBar = $('.pdf-counter-progress-bar');
            if (progressBar.length) {
                progressBar.attr('value', data.overallProgress);
                var progressColor = data.overallProgress >= 80 ? '#28a745' : 
                                   (data.overallProgress >= 50 ? '#ffc107' : '#dc3545');
                progressBar.css('--progress-color', progressColor);
            }

            this.updatePdfIssues(data.pdfIssues);
            this.updateHistoricalChart(data.overallProgress);
            var totalElement = $('.total-pdfs-count');
            if (totalElement.length) {
                Str.get_string('totalpdfs', 'block_pdfcounter', data.totalPdfs)
                    .then(function(msg) {
                        totalElement.text(msg);
                    })
                    .catch(function() {
                        // Fallback: mostra só o número se não conseguir ir buscar a string
                        totalElement.text(data.totalPdfs);
                    });
            }
        },

        updatePdfIssues: function(pdfIssues) {
            var issuesList = $('#pdf-issues-list tbody');
            if (!issuesList.length) return;

            if (!pdfIssues || pdfIssues.length === 0) {
                // Mensagem sem issues pode ser localizada via core/str
                Str.get_string('noissues', 'block_pdfcounter')
                    .then(function(msg) {
                        issuesList.html('<tr><td colspan="2" style="text-align: center; color: #28a745; font-style: italic; padding: 15px;">' + msg + '</td></tr>');
                    })
                    .catch(function() {
                        issuesList.html('<tr><td colspan="2" style="text-align: center; color: #28a745; font-style: italic; padding: 15px;">No PDF accessibility issues found.</td></tr>');
                    });
                return;
            }

            // Ordena os PDFs do maior para o menor ratio de falhas
            pdfIssues.sort(function(a, b) {
                var applicableA = (a.pass_count || 0) + (a.fail_count || 0) + (a.not_tagged_count || 0);
                var applicableB = (b.pass_count || 0) + (b.fail_count || 0) + (b.not_tagged_count || 0);
                var failedA = (a.fail_count || 0) + (a.not_tagged_count || 0);
                var failedB = (b.fail_count || 0) + (b.not_tagged_count || 0);
                var ratioA = applicableA > 0 ? failedA / applicableA : 0;
                var ratioB = applicableB > 0 ? failedB / applicableB : 0;
                if (ratioB === ratioA) {
                    return failedB - failedA;
                }
                return ratioB - ratioA;
            });

            var self = this;
            var rowPromises = pdfIssues.map(function(pdf) {
                    var applicableTests = pdf.pass_count + pdf.fail_count + pdf.not_tagged_count;
                    var failedTests = pdf.fail_count + pdf.not_tagged_count;
                    var downloadUrl = M.cfg.wwwroot + '/blocks/pdfcounter/download_report.php?filename=' + encodeURIComponent(pdf.filename) + '&courseid=' + self.courseid;

                    var failInfo = {
                        failed: failedTests,
                        total: applicableTests
                    };

                    return Promise.all([
                        Str.get_string('tests_failed', 'block_pdfcounter', failInfo),
                        Str.get_string('download_report', 'block_pdfcounter')
                    ]).then(function(strings) {
                        var testsFailedStr = strings[0];
                        var downloadLabel = strings[1];

                        var rowHtml = '';
                        rowHtml += '<tr><td colspan="1" style="padding:0;">';
                        rowHtml += '<div class="parent" style="display:grid; grid-template-columns:1fr; grid-template-rows:repeat(3,1fr); grid-column-gap:0; grid-row-gap:0;">';
                        rowHtml += '<div class="div1" style="grid-area:1/1/2/2; align-self:start; font-size:1em;">' + pdf.filename + '</div>';
                        rowHtml += '<div class="div2" style="grid-area:2/1/3/2; align-self:start; text-align:left; font-weight:bold; font-size:1.1em;">' + testsFailedStr + '</div>';
                        rowHtml += '<div class="div3" style="grid-area:3/1/4/2; text-align:left;">';
                        rowHtml += '<a href="' + downloadUrl + '" target="_blank" style="color:#1976d2; text-decoration:underline; font-size:0.95em; display:inline-flex; align-items:center; gap:4px;">';
                        rowHtml += '<i class="fa fa-download" aria-hidden="true" style="color:#1976d2;"></i> ' + downloadLabel;
                        rowHtml += '</a>';
                        rowHtml += '</div>';
                        rowHtml += '</div>';
                        rowHtml += '<hr>';
                        rowHtml += '</td></tr>';
                        return rowHtml;
                    }).catch(function() {
                        // Fallback para inglês caso haja erro a obter strings
                        var fallback = '';
                        fallback += '<tr><td colspan="1" style="padding:0;">';
                        fallback += '<div class="parent" style="display:grid; grid-template-columns:1fr; grid-template-rows:repeat(3,1fr); grid-column-gap:0; grid-row-gap:0;">';
                        fallback += '<div class="div1" style="grid-area:1/1/2/2; align-self:start; font-size:1em;">' + pdf.filename + '</div>';
                        fallback += '<div class="div2" style="grid-area:2/1/3/2; align-self:start; text-align:left; font-weight:bold; font-size:1.1em;">' + failedTests + ' of ' + applicableTests + ' tests failed</div>';
                        fallback += '<div class="div3" style="grid-area:3/1/4/2; text-align:left;">';
                        fallback += '<a href="' + downloadUrl + '" target="_blank" style="color:#1976d2; text-decoration:underline; font-size:0.95em; display:inline-flex; align-items:center; gap:4px;">';
                        fallback += '<i class="fa fa-download" aria-hidden="true" style="color:#1976d2;"></i> Download Report';
                        fallback += '</a>';
                        fallback += '</div>';
                        fallback += '</div>';
                        fallback += '<hr>';
                        fallback += '</td></tr>';
                        return fallback;
                    });
                });

            Promise.all(rowPromises).then(function(rowsHtml) {
                issuesList.html(rowsHtml.join(''));
            });
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