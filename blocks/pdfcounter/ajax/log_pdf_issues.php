<?php
// log_pdf_issues.php - Recebe array pdfIssues do JS (debug file logging disabled in production)
require_once(__DIR__ . '/../../../config.php');
header('Content-Type: application/json');
try {
    $input = json_decode(file_get_contents('php://input'), true);
    // Intentionally ignore content and avoid writing any debug files in production.
    echo json_encode(['status' => 'ok']);
} catch (Exception $e) {
    if (function_exists('block_pdfcounter_log_error')) {
        block_pdfcounter_log_error('Exceção em log_pdf_issues.php', [
            'exception' => $e->getMessage(),
        ]);
    }
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
