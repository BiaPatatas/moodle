<?php
// log_pdf_issues.php - Recebe array pdfIssues do JS
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/blocks/pdfcounter/lib.php');

header('Content-Type: application/json');
try {
    $input = json_decode(file_get_contents('php://input'), true);
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    block_pdfcounter_debug_log('Exceção em log_pdf_issues.php', [
        'exception' => $e->getMessage(),
    ], 'log_pdf_issues.log');
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
