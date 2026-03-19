<?php
// Language strings for block_pdfcounter - Portuguese (pt)

$string['pluginname'] = 'Painel de Acessibilidade';
$string['pdfresources'] = 'Número de ficheiros PDF';
$string['nocourse'] = 'Não há nenhum curso disponível para mostrar o número de PDFs.';

$string['overall'] = 'Acessibilidade Global';
$string['pendingmsg_analyzing'] = 'A ferramenta ainda está a analisar {$a} PDF(s) nesta página.';
$string['pendingmsg_loading'] = 'A ferramenta está a analisar a acessibilidade dos PDFs deste curso…';
$string['results_title'] = 'Resultados de Acessibilidade dos PDFs';
$string['noissues'] = 'Não foram encontrados problemas de acessibilidade nos PDFs.';
$string['tests_failed'] = '{$a->failed} de {$a->total} testes falharam';
$string['download_report'] = 'Descarregar relatório';
$string['historical_trends'] = 'Evolução histórica';

// Caixa de informação "Saber mais".
$string['learnmore'] = 'Saber mais';
$string['learnmore_close'] = 'Fechar';
$string['learnmore_intro'] = 'O Painel de Acessibilidade apresenta uma visão geral da acessibilidade da disciplina, acompanha a evolução e destaca problemas de acessibilidade em PDFs, com relatórios detalhados para cada ficheiro.';
$string['learnmore_resources'] = 'Recursos:';
$string['learnmore_fcul_guide'] = 'Guia de Acessibilidade da FCUL';
$string['learnmore_fcul_guide_title'] = 'Abrir Guia de Acessibilidade da FCUL';
$string['learnmore_wcag'] = 'Boas práticas para PDFs acessíveis - WCAG 2.2';
$string['learnmore_wcag_title'] = 'Técnicas de PDF para WCAG 2.2';
$string['progress_chart_label'] = 'Progresso (%)';
$string['totalpdfs'] = '{$a} PDFs';

// Integração QualWeb.
$string['qualweb_title'] = 'Acessibilidade do site (QualWeb)';
$string['qualweb_issues_summary'] = 'Passaram {$a->passed}, Avisos {$a->warnings}, Falharam {$a->failed}';

// Definições.
$string['settings_qualweb_header'] = 'Integração com QualWeb';
$string['settings_qualweb_api_baseurl'] = 'URL base da API QualWeb';
$string['settings_qualweb_api_baseurl_desc'] = 'URL base da API REST de monitorização de acessibilidade QualWeb (por exemplo, http://localhost:8081/api).';
$string['settings_qualweb_apikey'] = 'Chave da API QualWeb';
$string['settings_qualweb_apikey_desc'] = 'Chave de API opcional a enviar no cabeçalho X-API-Key ao chamar a API QualWeb.';
$string['settings_qualweb_monitoring_id'] = 'ID de registo de monitorização';
$string['settings_qualweb_monitoring_id_desc'] = 'ID do registo de monitorização no QualWeb cujo score global e estatísticas de problemas devem ser apresentados no bloco.';
