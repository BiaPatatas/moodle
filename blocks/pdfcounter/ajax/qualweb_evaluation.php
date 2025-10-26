<?php
/**
 * AJAX endpoint for QualWeb page evaluation
 *
 * @package    block_pdfcounter
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../../config.php');
require_once('../classes/qualweb_factory.php');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'evaluate', PARAM_ALPHA);

require_login();

$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

header('Content-Type: application/json');

try {
    $evaluator = qualweb_factory::create_evaluator();
    
    // Check if QualWeb is enabled
    $qualweb_enabled = get_config('block_pdfcounter', 'qualweb_enabled');
    
    if (!$qualweb_enabled) {
        echo json_encode([
            'success' => false,
            'error' => 'QualWeb is disabled'
        ]);
        exit;
    }
    
    switch ($action) {
        case 'evaluate':
            // Evaluate course pages
            $results = $evaluator->evaluate_course_pages($courseid);
            
            // Store results in database for caching
            store_evaluation_results($courseid, $results);
            
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;
            
        case 'status':
            // Get service status
            $status = $evaluator->get_service_status();
            
            echo json_encode([
                'success' => true,
                'data' => $status
            ]);
            break;
            
        case 'results':
            // Get cached results
            $results = get_cached_evaluation_results($courseid);
            
            echo json_encode([
                'success' => true,
                'data' => $results
            ]);
            break;
            
        default:
            echo json_encode([
                'success' => false,
                'error' => 'Invalid action'
            ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Store evaluation results in database
 *
 * @param int $courseid Course ID
 * @param array $results Evaluation results
 */
function store_evaluation_results($courseid, $results) {
    global $DB;
    
    // Delete old results
    $DB->delete_records('block_pdfcounter_qualweb', ['courseid' => $courseid]);
    
    // Adaptar dados do factory para o formato esperado
    $summary = $results['summary'] ?? [];
    $evaluation_results = $results['results'] ?? [];
    
    // Store new results
    $record = new stdClass();
    $record->courseid = $courseid;
    $record->total_pages = $summary['total_pages'] ?? count($evaluation_results);
    $record->evaluated_pages = $summary['total_pages'] ?? count($evaluation_results);
    $record->passed_tests = $summary['total_passed'] ?? 0;
    $record->failed_tests = $summary['total_failed'] ?? 0;
    $record->warnings = $summary['total_warnings'] ?? 0;
    $record->average_score = $summary['average_score'] ?? 0;
    $record->results_data = json_encode($evaluation_results);
    $record->timecreated = time();
    $record->timemodified = time();
    
    $DB->insert_record('block_pdfcounter_qualweb', $record);
}

/**
 * Get cached evaluation results
 *
 * @param int $courseid Course ID
 * @return array|null Cached results or null if not found
 */
function get_cached_evaluation_results($courseid) {
    global $DB;
    
    $record = $DB->get_record('block_pdfcounter_qualweb', ['courseid' => $courseid]);
    
    if (!$record) {
        return null;
    }
    
    return [
        'total_pages' => $record->total_pages,
        'evaluated_pages' => $record->evaluated_pages,
        'passed_tests' => $record->passed_tests,
        'failed_tests' => $record->failed_tests,
        'warnings' => $record->warnings,
        'average_score' => $record->average_score,
        'pages' => json_decode($record->results_data, true),
        'last_evaluation' => $record->timemodified
    ];
}
?>