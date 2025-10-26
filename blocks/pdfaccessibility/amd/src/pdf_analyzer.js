define([], function() {
    return {
        init: function() {
            const getDraftId = () => document.getElementById('id_files')?.value;
            const sesskey = M.cfg.sesskey; // Moodle disponibiliza isto globalmente
            const filemanagerList = document.querySelector('.filemanager .fp-content');

            if (!filemanagerList) {
                alert('filemanager não encontrado!');
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
                fetch('/blocks/pdfaccessibility/ajax/preview.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ draftid, sesskey, courseid })
                })
                .then(res => res.json())
                .then(data => {
                    const div = document.getElementById('analyzer-result');
                    if (!div) {return;}
                    if (data.status !== 'ok' || !data.summary) {
                        div.innerHTML = `<span style="color:red">${data.message || 'Erro ao analisar PDF.'}</span>`;
                        return;
                    }

                    // Tente obter o nome do PDF do local correto
                    let filename = 'PDF';
                    if (data.filename) {
                        filename = data.filename;
                    } else if (data.pdfs && data.pdfs[0] && data.pdfs[0].filename) {
                        filename = data.pdfs[0].filename;
                    }

                    const summary = data.summary;
                    const testConfig = data.testConfig || [];
                    // Function to determine check value (matches PHP pdf_accessibility_config::determine_js_check_value)
                    const determineCheckValue = (testKey, testValue) => {
                        if (testValue === true) return 'Pass';
                        if (testValue === 'PDF not tagged') return 'PDF not tagged';
                        if (testValue === 'Non applicable') return 'Non applicable';
                        if (testValue === false) return 'Fail';
                        // Special cases
                        if (testKey === 'Title' && testValue === 'No Title Found') return 'Fail';
                        if (testKey === 'Title' && testValue !== 'No Title Found') return 'Pass';
                        if (testKey === 'Languages match') return testValue ? 'Pass' : 'Fail';
                        if (testKey === 'PDF only image') return testValue === 'PDF with text' ? 'Pass' : 'Fail';
                        
                        return testValue; // Return as-is for other cases
                    };
                    
                    // Generate checks array using shared logic
                    const checks = testConfig.map(config => {
                        const value = determineCheckValue(config.key, summary[config.key]);
                        return {
                            label: config.label,
                            value: value,
                            pass: value === 'Pass',
                            raw: summary[config.key],
                            link: config.link,
                            linkText: "How to fix?"
                        };
                    });

                    const passed = checks.filter(c => c.pass).length;
                    const nonApplicable = checks.filter(c => c.value === "Non applicable").length;
                    const pdfNotTagged = checks.filter(c => c.value === "PDF not tagged").length;
                    const failed = checks.length - passed - nonApplicable;
                    
                    // Custom sort: Pass -> Fail -> PDF not tagged -> Non applicable
                    checks.sort((a, b) => {
                        // Define priority order
                        const getPriority = (check) => {
                            if (check.pass) return 1; // Pass first
                            if (check.value === "Fail") return 2; // Fail second
                            if (check.value === "PDF not tagged") return 3; // PDF not tagged third
                            if (check.value === "Non applicable") return 4; // Non applicable last
                            return 5; // Any other case
                        };
                        
                        return getPriority(a) - getPriority(b);
                    });


                    let html = `
        <div style="font-family:Arial,sans-serif;max-width:320px;">
            <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; background-color: white; 
            border-radius: 8px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); color: black; margin-bottom: 10px;">
                <i class="fas fa-universal-access" aria-hidden="true"></i>
                <bold style="font-size: 0.95rem; font-weight: bold; margin-bottom: 2px;">${filename}</bold><br>
                <div style="margin-top:10px">
                    <span style="color:#27ae60;font-weight:bold;">${passed}</span>
                    <span style="color:black;">passed</span>
                    <span style="color:#e74c3c;font-weight:bold;margin-left:10px;">${failed}</span>
                    <span style="color:black;">failed</span>
                    
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
                                    margin-bottom: 2px;">Detailed Report</bold><br>
                    ${checks.map((c, i) => {
                        let bg = c.pass ? '#eafaf1' : '#fff4f4';
                        let icon = c.pass
    ? '<i style="color:green;" class="fa fa-check" aria-hidden="true"></i>'
    : '<i style="color:red;" class="fa fa-times" aria-hidden="true"></i>';
                        let color = c.pass ? '#27ae60' : '#e74c3c';
                        let opacity = 1;
                        let extra = '';
                        if (c.value === 'Non applicable') {
                            bg = '#f3f3f3';
                            color = '#000000ff';
                            opacity = 0.6;
                            icon = '<i style="color:#000000;" class="fa fa-minus-circle" aria-hidden="true"></i>';
                        }
                        if (c.value === 'PDF not tagged') {
                            bg = '#fffbe6';
                            color = '#e6b800';
                            icon = '<i style="color:#e6b800;" class="fa fa-exclamation-triangle" aria-hidden="true"></i>';
                            extra = `<div style="margin-top:6px;padding:6px 8px;background:#fff3cd;border-radius:5px;color:#856404;
                            font-size:0.88em;font-weight:bold;">
                                <i class='fa fa-exclamation-circle' style='margin-right:4px;'></i> This PDF is not tagged. 
                                We are unable to check the accessibility of this topic..
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
                <div style="font-weight:bold; font-size: 0.925rem; color: #1e1e1e;">${c.label}</div>
                ${(!c.pass && c.value !== 'PDF not tagged' && c.value !== 'Non applicable') ? `
                    <button type="button"
                        aria-expanded="false"
                        aria-controls="fail-detail-${i}"
                        style="background:none;border:none;cursor:pointer;padding:0 6px;"
                        onclick="
                            var d=document.getElementById('fail-detail-${i}');
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
            ${(!c.pass && c.value !== 'PDF not tagged' && c.value !== 'Non applicable') ? `
            <div id="fail-detail-${i}" style="display:none;margin-top:5px;font-size:0.85em;color:#a94442;">
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
                    div.innerHTML = html;
                });
            };

            const observer = new MutationObserver(() => {
                const draftid = getDraftId();
                const div = document.getElementById('analyzer-result');
                if (div) {
                    div.innerHTML = '<span style="color:#555;">Analyzing PDF accessibility, please wait...</span>';
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