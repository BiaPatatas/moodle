<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

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

header('Content-Type: application/json');
echo json_encode($result);