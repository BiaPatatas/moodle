<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$department_id = optional_param('department', null, PARAM_INT);

if (!$department_id) {
    echo json_encode([]);
    exit;
}

$dashboard = new \tool_accessibilitydashboard\dashboard();
$courses = $dashboard->get_courses_for_filter($department_id);

$result = [];
foreach ($courses as $course) {
    $result[] = [
        'id' => $course->id,
        'name' => $course->name
    ];
}

header('Content-Type: application/json');
echo json_encode($result);