<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility_config.php');

// === DEBUG SYSTEM UPDATE DASHBOARD ===
$debug_dir = $CFG->dirroot . '/blocks/pdfcounter/debug/';
if (!is_dir($debug_dir)) {
    mkdir($debug_dir, 0755, true);
}
$debug_log = $debug_dir . 'update_dashboard_debug.log';
$debug_entry = "\n=== UPDATE DASHBOARD DEBUG ===\n";
$debug_entry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
$debug_entry .= "Request Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
$debug_entry .= "User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A') . "\n";
file_put_contents($debug_log, $debug_entry, FILE_APPEND);

// Verify session and require login
require_login();

// Handle AJAX request
header('Content-Type: application/json');

try {
    $input = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($input['sesskey']) || !confirm_sesskey($input['sesskey'])) {
        throw new Exception('Invalid session key');
    }
    
    $courseid = $input['courseid'];
    if (!$courseid || $courseid <= 0) {
        throw new Exception('Invalid Course ID: ' . $courseid);
    }
    
    // Verify course exists
    if (!$DB->record_exists('course', array('id' => $courseid))) {
        throw new Exception('Course not found: ' . $courseid);
    }
    
    // Buscar todos os PDFs do curso pelo courseid (usando a tabela do plugin)
    $allpdfs = $DB->get_records('block_pdfaccessibility_pdf_files', ['courseid' => $courseid]);

    $pdf_issues = [];
    $total_percent = 0;
    $total_pdfs = 0;
    foreach ($allpdfs as $pdfrecord) {
        $pdfid = $pdfrecord->id;
        $counts = pdf_accessibility_config::get_test_counts($DB, $pdfid);
        $display_name = !empty($pdfrecord->filename) ? $pdfrecord->filename : 'PDF';
        $pdf_issue = [
            'filename' => $pdfrecord->filename,
            'fileid' => $pdfid,
            'fail_count' => $counts['fail_count'],
            'pass_count' => $counts['pass_count'],
            'nonapplicable_count' => $counts['nonapplicable_count'],
            'not_tagged_count' => $counts['not_tagged_count']
        ];
        // Calcular progresso para cada PDF
        $applicable_tests = pdf_accessibility_config::calculate_applicable_tests(
            $counts['pass_count'],
            $counts['fail_count'],
            $counts['not_tagged_count']
        );
        if ($applicable_tests > 0) {
            $percent = pdf_accessibility_config::calculate_progress($counts);
            $total_percent += $percent;
            $total_pdfs++;
        }
        $pdf_issues[] = $pdf_issue;
    }

        // LOG: Gravar array pdf_issues em ficheiro de debug
        $debug_issues_file = $CFG->dirroot . '/blocks/pdfcounter/debug/pdf_issues_debug.log';
        $debug_issues_entry = "\n=== PDF ISSUES DEBUG ===\n";
        $debug_issues_entry .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $debug_issues_entry .= "CourseID: " . $courseid . "\n";
        $debug_issues_entry .= "PDF Issues: " . print_r($pdf_issues, true) . "\n";
        file_put_contents($debug_issues_file, $debug_issues_entry, FILE_APPEND);

    // Calcular progresso geral
    $overall_progress = $total_pdfs > 0 ? round($total_percent / $total_pdfs) : 0;
    $progress_color = pdf_accessibility_config::get_progress_color($overall_progress);

    // Update trends table for current month
    $current_month = date('Y-m');
    $trend_exists = $DB->record_exists('block_pdfcounter_trends', [
        'courseid' => $courseid,
        'month' => $current_month
    ]);

    if ($trend_exists) {
        $DB->set_field('block_pdfcounter_trends', 'progress_value', $overall_progress, [
            'courseid' => $courseid,
            'month' => $current_month
        ]);
    } else {
        $trend = new stdClass();
        $trend->courseid = $courseid;
        $trend->month = $current_month;
        $trend->progress_value = $overall_progress;
        $trend->timecreated = time();
        $DB->insert_record('block_pdfcounter_trends', $trend);
    }

    // Return updated data
    echo json_encode([
        'status' => 'ok',
        'overallProgress' => $overall_progress,
        'progressColor' => $progress_color,
        'pdfIssues' => $pdf_issues,
        'totalPdfs' => $total_pdfs
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>