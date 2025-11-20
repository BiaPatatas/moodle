<?php
// download_report.php - Gera e faz download do relatório HTML de acessibilidade de um PDF
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/moodlelib.php');

$filename = required_param('filename', PARAM_RAW);
$courseid = required_param('courseid', PARAM_INT);

// Validate course ID
if ($courseid <= 0) {
    throw new moodle_exception('invalidcourseid', 'error', '', 'Invalid course ID');
}

// Check if course exists
if (!$DB->record_exists('course', array('id' => $courseid))) {
    throw new moodle_exception('coursenotfound', 'error', '', 'Course not found');
}

// Require login and check permissions
require_login($courseid);
$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

// Buscar o contenthash do ficheiro

// Buscar o registro do PDF na tabela de acessibilidade para este curso
$pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', [
    'filename' => $filename,
    'courseid' => $courseid
]);
if (!$pdfrecord) {
    throw new moodle_exception('reportnotfound', 'error', '', 'Relatório não encontrado para este curso');
}

// Buscar os resultados dos testes desse PDF
$testresults = $DB->get_records('block_pdfaccessibility_test_results', ['fileid' => $pdfrecord->id]);

// Gerar HTML do relatório
$html = '<!DOCTYPE html><html lang="pt"><head><meta charset="UTF-8"><title>Accessibility Report PDF</title>';
$html .= '<style>body{font-family:Arial,sans-serif;background:#f8f9fa;padding:20px;}';
$html .= 'table{width:100%;border-collapse:collapse;margin-top:20px;}th,td{border:1px solid #ccc;padding:8px;}th{background:#eee;}</style></head><body>';
$html .= '<h2>Accessibility Report PDF</h2>';
$html .= '<p><strong>File:</strong> ' . htmlspecialchars($filename) . '</p>';
$html .= '<table><thead><tr><th>Test</th><th>Result</th><th>Description</th><th>How to fix?</th></tr></thead><tbody>';

// Carrega a configuração centralizada
require_once($CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility_config.php');
$checks_info = pdf_accessibility_config::TEST_CONFIG;

foreach ($testresults as $test) {
    if (in_array($test->testname, ['Language declared', 'Language detected', 'Passed', 'Failed'])) continue;
    $label = isset($checks_info[$test->testname]['label']) ? $checks_info[$test->testname]['label'] : $test->testname;
    $link = isset($checks_info[$test->testname]['link']) ? $checks_info[$test->testname]['link'] : '';
    $desc = isset($checks_info[$test->testname]['description']) ? $checks_info[$test->testname]['description'] : '';
    
    // Determinar o status com base no resultado real
    if ($test->result === 'pass') {
        $status = '<span style="color:green;font-weight:bold;">Pass</span>';
    } elseif ($test->result === 'fail') {
        $status = '<span style="color:red;font-weight:bold;">Fail</span>';
    } elseif ($test->result === 'non applicable') {
        $status = '<span style="color:gray;font-style:italic;">Non applicable</span>';
    } elseif ($test->result === 'pdf not tagged') {
        $status = '<span style="color:orange;font-weight:bold;">PDF not tagged</span>';
    } else {
        $status = '<span style="color:purple;">' . htmlspecialchars($test->result) . '</span>';
    }
    
    $html .= '<tr><td>' . htmlspecialchars($label) . '</td><td>'  . $status . '</td><td>'  . $desc . '</td><td><a href="' . htmlspecialchars($link) . '" target="_blank">' . htmlspecialchars($link) . '</a></td></tr>';
}

$html .= '</tbody></table>';
$html .= '</body></html>';

// Enviar como download
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="report_' . basename($filename, '.pdf') . '.html"');
echo $html;
exit;
