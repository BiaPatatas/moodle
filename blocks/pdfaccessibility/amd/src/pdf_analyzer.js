define(['core/str'], function(Str) {
    // Default labels (English); will be overridden by language strings when available.
    var lang = {
        status_pass: 'Pass',
        status_fail: 'Fail',
        status_nonapplicable: 'Non applicable',
        status_not_tagged: 'PDF not tagged',
        tests_passed_label: 'passed',
        tests_failed_label: 'failed',
        report: 'Detailed Report',
        not_tagged_help: 'This PDF is not tagged. We are unable to check the accessibility of this content.'
    };

    Str.get_strings([
        {key: 'status_pass', component: 'block_pdfaccessibility'},
        {key: 'status_fail', component: 'block_pdfaccessibility'},
        {key: 'status_nonapplicable', component: 'block_pdfaccessibility'},
        {key: 'status_not_tagged', component: 'block_pdfaccessibility'},
        {key: 'tests_passed_label', component: 'block_pdfaccessibility'},
        {key: 'tests_failed_label', component: 'block_pdfaccessibility'},
        {key: 'report', component: 'block_pdfaccessibility'},
        {key: 'not_tagged_help', component: 'block_pdfaccessibility'}
    ]).then(function(results) {
        lang.status_pass = results[0];
        lang.status_fail = results[1];
        lang.status_nonapplicable = results[2];
        lang.status_not_tagged = results[3];
        lang.tests_passed_label = results[4];
        lang.tests_failed_label = results[5];
        lang.report = results[6];
        lang.not_tagged_help = results[7];
    }).catch(function() {
        // Keep defaults on error.
    });

    return {
        init: function() {
            const getDraftId = () => document.getElementById('id_files')?.value;
            const sesskey = M.cfg.sesskey; // Moodle disponibiliza isto globalmente
            const filemanagerList = document.querySelector('.filemanager .fp-content');

            if (!filemanagerList) {
                // Mensagem apenas de debug na consola, pode ficar em inglês
                console.warn('filemanager not found');
                return;
            }

            // Function to get current course ID
            const getCurrentCourseId = function() {
                // Try URL parameter first
                var urlParams = new URLSearchParams(window.location.search);
                var courseIdFromUrl = urlParams.get('id');
                if (courseIdFromUrl && parseInt(courseIdFromUrl) > 0) {
                    return parseInt(courseIdFromUrl);
                }

                // Try body data attributes
                var bodyElement = document.body;
                if (bodyElement) {
                    var courseIdFromBody = bodyElement.getAttribute('data-courseid') ||
                                          bodyElement.getAttribute('data-course-id');
                    if (courseIdFromBody && parseInt(courseIdFromBody) > 0) {
                        return parseInt(courseIdFromBody);
                    }
                }

                // Try Moodle config
                if (typeof M !== 'undefined' && M.cfg && M.cfg.courseId && parseInt(M.cfg.courseId) > 0) {
                    return parseInt(M.cfg.courseId);
                }

                // Fallback - try to find course ID in page elements
                var courseLink = document.querySelector('a[href*="course/view.php?id="]');
                if (courseLink) {
                    var match = courseLink.href.match(/[?&]id=(\d+)/);
                    if (match && parseInt(match[1]) > 0) {
                        return parseInt(match[1]);
                    }
                }

                return null;
            };

            const fetchPdfInfo = (draftid) => {
                const courseid = getCurrentCourseId();
                const url = M.cfg.wwwroot + '/blocks/pdfaccessibility/ajax/preview.php';
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({draftid, sesskey, courseid})
                })
                .then(res => res.json())
                .then(data => {
                    const div = document.getElementById('analyzer-result');
                    if (!div) {
                        return null;
                    }
                    if (data.status !== 'ok' || !data.pdfs || !Array.isArray(data.pdfs) || data.pdfs.length === 0) {
                        Str.get_string('error_analyzing', 'block_pdfaccessibility')
                            .then(function(msg) {
                                div.innerHTML = `<span style="color:red">${data.message || msg}</span>`;
                            })
                            .catch(function() {
                                div.innerHTML = `<span style="color:red">${data.message || 'Error analyzing PDF.'}</span>`;
                            });
                        return null;
                    }

                    const testConfig = data.testConfig || [];
                    // Determine internal status code (pass/fail/nonapplicable/not_tagged) from raw value.
                    const determineStatusCode = (testKey, testValue) => {
                        if (testValue === true) {
                            return 'pass';
                        }
                        if (testValue === 'PDF not tagged') {
                            return 'not_tagged';
                        }
                        if (testValue === 'Non applicable') {
                            return 'nonapplicable';
                        }
                        if (testValue === false) {
                            return 'fail';
                        }
                        // Special cases
                        if (testKey === 'Title' && testValue === 'No Title Found') {
                            return 'fail';
                        }
                        if (testKey === 'Title' && testValue !== 'No Title Found') {
                            return 'pass';
                        }
                        if (testKey === 'Languages match') {
                            return testValue ? 'pass' : 'fail';
                        }
                        // Novo teste de OCR: "PDF OCR status"
                        if (testKey === 'PDF OCR status') {
                            if (testValue === 'PDF with text') {
                                return 'pass';
                            }
                            if (testValue === 'Scanned PDF without OCR') {
                                return 'fail';
                            }
                            if (testValue === 'Only Images') {
                                return 'nonapplicable';
                            }
                        }
                        return 'other'; // Fallback for other cases
                    };

                    let html = '';
                    data.pdfs.forEach((pdf, idx) => {
                        const filename = pdf.filename || `PDF ${idx + 1}`;
                        const summary = pdf.summary;
                        if (!summary) {
 return;
}
                        // Garante alinhamento correto entre key, label, descrição e valor, e oculta testes indesejados
                        const excludedKeys = [
                            'Language declared',
                            'Language detected',
                            // Campos agregados do relatório Python que não são testes individuais
                            'Passed',
                            'Failed',
                            'Non applicable',
                            // Campos de detalhe de links, usados apenas para informação
                            'Links Error Pages',
                            'Links Error Detail',
                        ];
                        const checks = Object.keys(summary)
                            .filter(key => !excludedKeys.includes(key))
                            .map((key) => {
                                const config = testConfig.find(cfg => cfg.key === key) || {};
                                const status = determineStatusCode(key, summary[key]);
                                var valueLabel;
                                if (status === 'pass') {
                                    valueLabel = lang.status_pass;
                                } else if (status === 'fail') {
                                    valueLabel = lang.status_fail;
                                } else if (status === 'nonapplicable') {
                                    valueLabel = lang.status_nonapplicable;
                                } else if (status === 'not_tagged') {
                                    valueLabel = lang.status_not_tagged;
                                } else {
                                    valueLabel = summary[key];
                                }
                                return {
                                    key: key,
                                    label: config.label || key,
                                    status: status,
                                    value: valueLabel,
                                    pass: status === 'pass',
                                    raw: summary[key],
                                    link: config.link || '',
                                    linkText: config.linkText || 'How to fix?',
                                    description: config.description || ''
                                };
                            });
                        const passed = checks.filter(c => c.status === 'pass').length;
                        const nonApplicable = checks.filter(c => c.status === 'nonapplicable').length;
                        const failed = checks.filter(c => c.status === 'fail').length;
                        // Custom sort: Pass -> Fail -> PDF not tagged -> Non applicable
                        checks.sort((a, b) => {
                            // Define priority order
                            const getPriority = (check) => {
                                if (check.status === 'pass') {
                                    return 1;
                                } // Pass first
                                if (check.status === 'fail') {
                                    return 2;
                                } // Fail second
                                if (check.status === 'not_tagged') {
                                    return 3;
                                } // PDF not tagged third
                                if (check.status === 'nonapplicable') {
                                    return 4;
                                } // Non applicable last
                                return 5; // Any other case
                            };
                            return getPriority(a) - getPriority(b);
                        });
                        html += `
        <div style="font-family:Arial,sans-serif;max-width:320px;">
            <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; background-color: white; 
            border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); color: black; margin-bottom: 10px;">
                <i class="fas fa-universal-access" aria-hidden="true"></i>
                <bold style="font-size: 0.95rem; font-weight: bold; margin-bottom: 2px;">${filename}</bold><br>
                <div style="margin-top:10px">
                    <span style="color:#27ae60;font-weight:bold;">${passed}</span>
                    <span style="color:black;">${lang.tests_passed_label}</span>
                    <span style="color:#e74c3c;font-weight:bold;margin-left:10px;">${failed}</span>
                    <span style="color:black;">${lang.tests_failed_label}</span>
                </div>
            </div>
            <div>
            <div style="background: #f8f9fa;
                        border-radius: 8px;
                        padding: 10px;
                        background-color: white;
                        border-radius: 8px;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                        color: black;
                        margin-bottom: 10px;">
                             
                    <bold style="   font-size: 0.90rem;
                                    font-weight: bold;
                                    margin-bottom: 2px;">${lang.report}</bold><br>
                    ${checks.map((c, i) => {
                        let bg = c.pass ? '#eafaf1' : '#fff4f4';
                        let icon = c.pass
    ? '<i style="color:green;" class="fa fa-check" aria-hidden="true"></i>'
    : '<i style="color:red;" class="fa fa-times" aria-hidden="true"></i>';
                        let color = c.pass ? '#27ae60' : '#e74c3c';
                        let opacity = 1;
                        let extra = '';
                        // Info icon and description
                        let infoId = `desc_${c.key.replace(/\s+/g, '_')}_${i}_${idx}`;
                        let infoIcon = `<span class=\"pdf-info-icon\" style=\"cursor:pointer; color:#1976d2; margin-left:6px;\" data-info-id=\"${infoId}\"><i class='fa fa-info-circle' style="color: #252525ff;"></i></span>`;
                        let infoDesc = '';
                        if (c.description) {
                            infoDesc = `<div id=\"${infoId}\" class=\"pdf-info-desc\" style=\"display:none; background:#f8f9fa; border:1px solid #e3e3e3; border-radius:6px; margin:6px 0 8px 0; padding:8px; font-size:0.92em; color:#333;\">${c.description}</div>`;
                        }
                        if (c.status === 'nonapplicable') {
                            bg = '#f3f3f3';
                            color = '#000000ff';
                            opacity = 0.6;
                            icon = '<i style="color:#000000;" class="fa fa-minus-circle" aria-hidden="true"></i>';
                        }
                        if (c.status === 'not_tagged') {
                            bg = '#fffbe6';
                            color = '#e6b800';
                            icon = '<i style="color:#e6b800;" class="fa fa-exclamation-triangle" aria-hidden="true"></i>';
                            extra = `<div style="margin-top:6px;padding:6px 8px;background:#fff3cd;border-radius:5px;color:#856404;
                            font-size:0.88em;font-weight:bold;">
                                <i class='fa fa-exclamation-circle' style='margin-right:4px;'></i> ${lang.not_tagged_help}
                            </div>`;
                        }
                        return `
    <div style="display:flex;align-items:flex-start;margin-top:8px;margin-bottom:10px;
        background:${bg};
        border-radius:6px;padding:6px 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);opacity:${opacity};">
        <span style="font-size:1.2em;margin-right:8px;margin-top:2px;color:${color};">
            ${icon}
        </span>
        <div style="flex:1;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="font-weight:bold; font-size: 0.925rem; color: #1e1e1e;">${c.label} ${infoIcon}</div>
                ${(!c.pass && c.status !== 'not_tagged' && c.status !== 'nonapplicable') ? `
                    <button type="button"
                        aria-expanded="false"
                        aria-controls="fail-detail-${i}-${idx}"
                        style="background:none;border:none;cursor:pointer;padding:0 6px; color: black;"
                        onclick="
                            var d=document.getElementById('fail-detail-${i}-${idx}');
                            var a=this.querySelector('.arrow');
                            var expanded=this.getAttribute('aria-expanded')==='true';
                            d.style.display=expanded?'none':'block';
                            this.setAttribute('aria-expanded',!expanded);
                            a.style.transform=expanded?'rotate(0deg)':'rotate(90deg)';
                        ">
                        <span class="arrow" style="display:inline-block;transition:transform 0.2s;
                        vertical-align:middle;">&#9662;</span>
                    </button>
                ` : ''}
            </div>
            <div style="font-size: 0.9rem; color: #282828; margin-left: 1%; margin-top: 1px;">${c.value}</div>
            ${infoDesc}
            ${(!c.pass && c.status !== 'not_tagged' && c.status !== 'nonapplicable') ? `
            <div id="fail-detail-${i}-${idx}" style="display:none;margin-top:5px;font-size:0.85em;color:#a94442;">
                <a href="${c.link}" target="_blank" rel="noopener">${c.linkText || c.link}</a>
            </div>
        ` : ''}
            ${extra}
        </div>
    </div>
                        `;
                    }).join('')}
                        </div>
                    </div>
                </div>
                        `;
                    });
                    div.innerHTML = html;
                                        // Event delegation para info icons (garante funcionamento mesmo após re-render)
                                        div.querySelectorAll('.pdf-info-icon').forEach(function(icon) {
                                            icon.addEventListener('click', function(e) {
                                                console.log('Info icon clicked', this, this.getAttribute('data-info-id'));
                                                e.stopPropagation();
                                                const id = this.getAttribute('data-info-id');
                                                if (id) {
                                                    const desc = document.getElementById(id);
                                                    if (desc) {
                                                        desc.style.display = (desc.style.display === 'block') ? 'none' : 'block';
                                                    } else {
                                                        console.warn('Descrição não encontrada para', id);
                                                    }
                                                }
                                            });
                                            // Remove inline onclick se existir (por segurança)
                                            icon.removeAttribute('onclick');
                                        });
                    return true;
                })
                .catch(error => {
                    const div = document.getElementById('analyzer-result');
                    if (div) {
                        const details = (error && (error.stack || error.message || error.toString())) || '';
                        Str.get_string('network_error', 'block_pdfaccessibility')
                            .then(function(msg) {
                                div.innerHTML = `<span style="color:red">${msg}<br><small>${details}</small></span>`;
                            })
                            .catch(function() {
                                div.innerHTML = `<span style="color:red">Network or server error while analyzing PDF.<br><small>${details}</small></span>`;
                            });
                    }
                    console.error('Erro ao analisar PDF:', error);
                    return null;
                });
            };

            const observer = new MutationObserver(() => {
                const draftid = getDraftId();
                const div = document.getElementById('analyzer-result');
                if (div) {
                    Str.get_string('analyzing', 'block_pdfaccessibility')
                        .then(function(msg) {
                            div.innerHTML = `<span style="color:#555;">${msg}</span>`;
                        })
                        .catch(function() {
                            div.innerHTML = '<span style="color:#555;">Analyzing PDF accessibility, please wait...</span>';
                        });
                }
                if (draftid) {
                    fetchPdfInfo(draftid);
                }
            });

            observer.observe(filemanagerList, {
                childList: true,
                subtree: true
            });
        }
    };
});