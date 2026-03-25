<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/lib.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

header('Content-Type: application/json');

try {
    $department_id = optional_param('department', null, PARAM_INT);
    $all = optional_param('all', null, PARAM_INT);

    $dashboard = new \tool_accessibilitydashboard\dashboard();
    if ($department_id) {
        $courses = $dashboard->get_courses_for_filter($department_id);
    } elseif ($all) {
        $courses = $dashboard->get_courses_for_filter(null);
    } else {
        echo json_encode([]);
        exit;
    }

    $result = [];
    foreach ($courses as $course) {
        $result[] = [
            'id' => $course->id,
            'name' => $course->name
        ];
    }

    echo json_encode($result);
} catch (Exception $e) {
    tool_accessibilitydashboard_log_error('ajax_courses_new.php: exception', [
        'department_id' => $department_id ?? null,
        'all' => $all ?? null,
        'exception' => $e->getMessage(),
    ], 'ajax_courses_new.log');

    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ]);
}