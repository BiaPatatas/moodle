// qualweb_async.js - Handles async QualWeb evaluation via AJAX
import Ajax from 'core/ajax';

export const init = (courseid) => {
    const qualwebDiv = document.getElementById('qualweb-result-async');
    if (!qualwebDiv) return;

    function updateStatus(msg, details = '') {
        qualwebDiv.innerHTML = `<div style="background:#e3f2fd; border-radius:8px; padding:10px; margin-bottom:10px;">${msg}${details}</div>`;
    }

    function pollStatus() {
        Ajax.call([
            {
                methodname: 'block_pdfcounter_qualweb_eval',
                args: { courseid: courseid, action: 'status' }
            }
        ])[0].then(data => {
            if (data.status === 'completed') {
                let res = '';
                if (data.result_json) {
                    try {
                        const result = JSON.parse(data.result_json);
                        res = `<br>Score: ${result.score}<br>Errors: ${result.failed}<br>Warnings: ${result.warnings}`;
                        res += `<details style='margin-top:5px;'><summary style='cursor:pointer;'>Ver JSON bruto do QualWeb</summary><pre style='font-size:0.8em; max-height:200px; overflow:auto; background:#f4f4f4; border-radius:4px; padding:6px;'>${result.raw}</pre></details>`;
                    } catch(e) {}
                }
                updateStatus('✅ Avaliação QualWeb concluída.', res);
            } else if (data.status === 'running' || data.status === 'pending') {
                updateStatus('⏳ Avaliação QualWeb em andamento...');
                setTimeout(pollStatus, 3000);
            } else if (data.status === 'error') {
                updateStatus('❌ Erro na avaliação QualWeb. Tente novamente.');
            } else {
                // Start evaluation
                updateStatus('⏳ Iniciando avaliação QualWeb...');
                Ajax.call([
                    {
                        methodname: 'block_pdfcounter_qualweb_eval',
                        args: { courseid: courseid }
                    }
                ])[0].then(() => setTimeout(pollStatus, 3000));
            }
        }).catch(() => updateStatus('❌ Erro de comunicação com o servidor.'));
    }

    pollStatus();
};
