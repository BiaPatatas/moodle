<?php
// Debug utility for PDF Accessibility Dashboard
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/admin/tool/accessibilitydashboard/classes/dashboard.php');

$start = microtime(true);
$start_mem = memory_get_usage();

$DB = $GLOBALS['DB'];
$dbman = $DB->get_manager();

$debugfile = __DIR__ . '/dashboard_debug.txt';
$log = "# PDF Accessibility Dashboard Debug Log\n# Generated: " . date('Y-m-d H:i:s') . "\n\n";
$log .= "Start time: $start\nStart memory: $start_mem\n\n";

function debug_table($table, &$log) {
    global $DB;
    $log .= "==== Table: $table ====\n";
    if ($DB->get_manager()->table_exists($table)) {
        $count = $DB->count_records($table);
        $log .= "Exists: YES\nTotal records: $count\n";
        $sample = $DB->get_records($table, null, '', '*', 0, 5);
        $log .= "Sample records (up to 5):\n";
        foreach ($sample as $row) {
            $log .= print_r($row, true) . "\n";
        }
    } else {
        $log .= "Exists: NO\n";
    }
    $log .= "\n";
}

// List of relevant tables
$tables = [
    'block_pdfaccessibility_pdf_files',
    'block_pdfaccessibility_test_results',
    'block_pdfcounter_trends',
    'course',
    'course_categories'
];

foreach ($tables as $table) {
    debug_table($table, $log);
}

$log .= "==== Dashboard Filters ====\n";
$department_id = optional_param('department', null, PARAM_INT);
$course_id = optional_param('course', null, PARAM_INT);
$discipline_id = optional_param('discipline', null, PARAM_INT);
$log .= "Department: $department_id\nCourse: $course_id\nDiscipline: $discipline_id\n\n";

// Try to instantiate dashboard and get stats
try {
    $dashboard = new \tool_accessibilitydashboard\dashboard();
    $stats_start = microtime(true);
    $stats = $dashboard->get_faculty_stats($department_id, $course_id, $discipline_id);
    $stats_end = microtime(true);
    $log .= "==== Dashboard Stats ====\n";
    $log .= print_r($stats, true) . "\n";
    $log .= "Stats duration: " . round($stats_end - $stats_start, 3) . "s\n";
    $log .= "Stats memory usage: " . memory_get_usage() . "\n";
} catch (Exception $e) {
    $log .= "Dashboard error: " . $e->getMessage() . "\n";
}

// Show PHP info for environment debugging
if (isset($_GET['phpinfo'])) {
    ob_start();
    phpinfo();
    $log .= "==== PHP Info ====\n" . ob_get_clean() . "\n";
}

$end = microtime(true);
$end_mem = memory_get_usage();
$log .= "\nEnd time: $end\nEnd memory: $end_mem\n";
$log .= "Total duration: " . round($end - $start, 3) . "s\n";
$log .= "Peak memory usage: " . memory_get_peak_usage() . "\n";


// --- TESTES DE DIAGNÓSTICO ---
error_log('DEBUG DASHBOARD.PHP block executado');
echo '<!-- DEBUG DASHBOARD.PHP EXECUTADO -->';
// Teste de escrita simples
$testfile = __DIR__ . '/test_dashboard_write.txt';
file_put_contents($testfile, 'test: ' . date('Y-m-d H:i:s') . "\n", FILE_APPEND);

// Escrever debug normal
file_put_contents($debugfile, $log, LOCK_EX);
echo "Debug info written to dashboard_debug.txt";
