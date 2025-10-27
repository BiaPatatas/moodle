<?php
require_once(__DIR__ . '/../../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$course_id = optional_param('course', null, PARAM_INT);

if (!$course_id) {
    echo json_encode([]);
    exit;
}

$dashboard = new \tool_accessibilitydashboard\dashboard();
$disciplines = $dashboard->get_disciplines_for_filter($course_id);

$result = [];
foreach ($disciplines as $discipline) {
    $result[] = [
        'id' => $discipline->id,
        'name' => $discipline->name
    ];
}

header('Content-Type: application/json');
echo json_encode($result);