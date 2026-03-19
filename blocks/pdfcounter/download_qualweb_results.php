<?php
// blocks/pdfcounter/download_qualweb_results.php
require_once(__DIR__ . '/../../config.php');
require_login();

$monitoring_id = required_param('monitoring_id', PARAM_TEXT);
$api_base = 'http://localhost:8081/api';

// Buscar assertions detalhadas por teste
$assertions_json = @file_get_contents("$api_base/monitoring/$monitoring_id/latest-assertions-by-test");
if (!$assertions_json) {
    die('Erro ao buscar assertions detalhadas do QualWeb.');
}
$assertions = json_decode($assertions_json, true);
if (empty($assertions['assertions'])) {
    die('Nenhuma assertion encontrada para este monitoring_id.');
}

$results = [];
foreach ($assertions['assertions'] as $assertion) {
    $row = [
        'Rule' => $assertion['assertion_rule'] ?? '',
        'Test' => $assertion['assertion_name'] ?? '',
        'Passed' => $assertion['passed'] ?? 0,
        'Warning' => $assertion['warning'] ?? 0,
        'Failed' => $assertion['failed'] ?? 0,
        'NPB' => $assertion['NPB'] ?? 0,
        'BPB' => $assertion['BPB'] ?? 0,
        'BP' => $assertion['BP'] ?? 0,
        'Barrier' => $assertion['barrier'] ?? '',
        'A3 exp' => $assertion['A3 exp'] ?? '',
        'A3 Score' => $assertion['A3 Score'] ?? '',
    ];
    $results[] = $row;
}

// Gerar CSV
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="qualweb_results.csv"');
$output = fopen('php://output', 'w');
fputcsv($output, array_keys($results[0]));
foreach ($results as $row) {
    fputcsv($output, $row);
}
fclose($output);
exit;
