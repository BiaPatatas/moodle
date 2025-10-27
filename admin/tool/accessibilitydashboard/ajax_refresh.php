<?php
define('AJAX_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/admin/tool/accessibilityDashboard/classes/dashboard.php');

// Verify session and require login
require_login();

// Handle AJAX request
header('Content-Type: application/json');

try {
    // Get filter parameters
    $department_id = optional_param('department', null, PARAM_INT);
    $course_id = optional_param('course', null, PARAM_INT);
    $discipline_id = optional_param('discipline', null, PARAM_INT);
    
    // Create dashboard instance and get updated data
    $dashboard = new \tool_accessibilitydashboard\dashboard();
    
    // Get updated statistics
    $stats = $dashboard->get_faculty_stats($department_id, $course_id, $discipline_id);
    $total_pdfs_count = $dashboard->get_total_pdfs_count($department_id, $course_id, $discipline_id);
    $problems_found = $dashboard->get_PDFs_problems($department_id, $course_id, $discipline_id);
    $evolution_data = $dashboard->get_accessibility_evolution($department_id, $course_id, $discipline_id);
    
    // Return updated data
    echo json_encode([
        'status' => 'success',
        'data' => [
            'courses_with_pdfs' => $stats['courses_with_pdfs'],
            'total_pdfs' => $total_pdfs_count,
            'problems_found' => $problems_found,
            'overall_score' => round($stats['accessibility_score'], 1),
            'evolution_current' => $evolution_data['current_score']
        ]
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>