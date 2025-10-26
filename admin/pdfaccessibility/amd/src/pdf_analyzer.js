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

            const fetchPdfInfo = (draftid) => {
                fetch('/blocks/pdfaccessibility/ajax/preview.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ draftid, sesskey })
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
                    const checks = [
                            {
                                label: 'Document Title Check',
                                value: summary['Title'] !== 'No Title Found' ? 'Pass' : 'Fail',
                                pass: summary['Title'] !== 'No Title Found',
                                link: "https://www.w3.org/TR/WCAG20-TECHS/pdf#PDF18",
                                linkText: "How to fix?",
                            },
                            {
                                label: 'Language Consistency Check',
                                value: summary['Languages match'] ? 'Pass' : 'Fail',
                                pass: summary['Languages match'],
                                link: "https://www.w3.org/TR/WCAG20-TECHS/pdf#PDF16",
                                linkText: "How to fix?",
                            },
                            {
                                label: 'OCR Application Check',
                                value: summary['PDF only image'] === 'PDF with text' ? 'Pass' : 'Fail',
                                pass: summary['PDF only image'] === 'PDF with text',
                                link: "https://www.w3.org/TR/WCAG20-TECHS/pdf#PDF7",
                                linkText: "How to fix?",
                            },
                            {
                                label: 'Link Validity Check',
                                value: summary['Links Valid'] ? 'Pass' : 'Fail',
                                pass: summary['Links Valid'],
                                link: "https://www.w3.org/TR/WCAG20-TECHS/pdf#PDF11",
                                linkText: "How to fix?",
                            },
                            {
                                label: 'Image Alt Text Check',
                                value: summary['Figures with alt text'] ? 'Pass' : 'Fail',
                                pass: summary['Figures with alt text'],
                                link: "https://www.w3.org/TR/WCAG20-TECHS/pdf#PDF1",
                                linkText: "How to fix?",
                            },
                            {
                                label: 'List Tagging Check',
                                value: summary['Lists marked as Lists'] ? 'Pass' : 'Fail',
                                pass: summary['Lists marked as Lists'],
                                link: "https://www.w3.org/TR/WCAG20-TECHS/pdf#PDF21",
                                linkText: "How to fix?",
                            },
                            {
                                label: 'Table Header Check',
                                value: summary['Table With Headers'] ? 'Pass' : 'Fail',
                                pass: summary['Table With Headers'],
                                link: "https://www.w3.org/TR/WCAG20-TECHS/pdf#PDF6",
                                linkText: "How to fix?",
                            }
                    ];

                    const passed = checks.filter(c => c.pass).length;
                    const failed = checks.length - passed;
                    checks.sort((a, b) => (a.pass === b.pass) ? 0 : a.pass ? -1 : 1);


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
                    ${checks.map((c, i) => `
    <div style="display:flex;align-items:flex-start;margin-top:8px;margin-bottom:10px;
        background:${c.pass ? '#eafaf1' : '#fff4f4'};
        border-radius:6px;padding:6px 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
        <span style="font-size:1.2em;margin-right:8px;margin-top:2px;color:${c.pass ? '#27ae60' : '#e74c3c'};">
            ${c.pass
                ? '<i style="color:green;" class="fa fa-check" aria-hidden="true"></i>'
                : '<i style="color:red;" class="fa fa-times" aria-hidden="true"></i>'
            }
        </span>
        <div style="flex:1;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="font-weight:bold; font-size: 0.925rem; color: #1e1e1e;">${c.label}</div>
                ${!c.pass ? `
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
            ${!c.pass ? `
            <div id="fail-detail-${i}" style="display:none;margin-top:5px;font-size:0.85em;color:#a94442;">
                <a href="${c.link}" target="_blank" rel="noopener">${c.linkText || c.link}</a>
            </div>
        ` : ''}
        </div>
    </div>
`).join('')}
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