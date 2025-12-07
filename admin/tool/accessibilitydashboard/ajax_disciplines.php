<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);


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

header('Content-Type: application/json');
echo json_encode($result);