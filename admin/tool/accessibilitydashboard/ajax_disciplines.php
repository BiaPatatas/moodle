<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

header('Content-Type: application/json');

try {
    $course_id = optional_param('course', null, PARAM_INT);
    $department_id = optional_param('department', null, PARAM_INT);
    $all = optional_param('all', null, PARAM_INT);

    $dashboard = new \tool_accessibilitydashboard\dashboard();
    if ($course_id) {
        $disciplines = $dashboard->get_disciplines_for_filter($course_id, null);
    } elseif ($department_id) {
        $disciplines = $dashboard->get_disciplines_for_filter(null, $department_id);
    } elseif ($all) {
        $disciplines = $dashboard->get_disciplines_for_filter(null, null);
    } else {
        echo json_encode([]);
        exit;
    }

    $result = [];
    foreach ($disciplines as $discipline) {
        $result[] = [
            'id' => $discipline->id,
            'name' => $discipline->name
        ];
    }

    echo json_encode($result);
} catch (Exception $e) {
    tool_accessibilitydashboard_log_error('ajax_disciplines.php: exception', [
        'course_id' => $course_id ?? null,
        'department_id' => $department_id ?? null,
        'all' => $all ?? null,
        'exception' => $e->getMessage(),
    ], 'ajax_disciplines.log');

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}