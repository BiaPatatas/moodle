<?php
// log_pdf_issues.php - Recebe array pdfIssues do JS e grava em ficheiro de debug
require_once(__DIR__ . '/../../../config.php');
header('Content-Type: application/json');
try {
    $input = json_decode(file_get_contents('php://input'), true);
    $pdfIssues = isset($input['pdfIssues']) ? $input['pdfIssues'] : [];
    $courseid = isset($input['courseid']) ? $input['courseid'] : 'N/A';
    $debug_file = $CFG->dirroot . '/blocks/pdfcounter/debug/pdf_issues_js_debug.log';
    $entry = "\n=== PDF ISSUES JS DEBUG ===\n";
    $entry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $entry .= "CourseID: " . $courseid . "\n";
    $entry .= "PDF Issues: " . print_r($pdfIssues, true) . "\n";
    file_put_contents($debug_file, $entry, FILE_APPEND);
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
