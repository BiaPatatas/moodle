<?php
// AJAX endpoint for QualWeb async evaluation
require_once(__DIR__ . '/../../config.php');
require_login();
global $DB, $CFG, $USER;
header('Content-Type: application/json');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'start', PARAM_ALPHA);

if (!is_enrolled(context_course::instance($courseid), $USER)) {
    echo json_encode(['error' => 'not_enrolled']);
    exit;
}

// Job lookup
$job = $DB->get_record('block_pdfcounter_qualweb_jobs', [
    'courseid' => $courseid,
    'userid' => $USER->id
]);

if ($action === 'status') {
    if ($job) {
        echo json_encode([
            'status' => $job->status,
            'result_json' => $job->result_json
        ]);
    } else {
        echo json_encode(['status' => 'none']);
    }
    exit;
}

if (!$job || $job->status === 'error') {
    // Create new job
    $job = new stdClass();
    $job->courseid = $courseid;
    $job->userid = $USER->id;
    $job->status = 'running';
    $job->timecreated = time();
    $job->timemodified = time();
    $job->monitoring_id = null;
    $job->result_json = null;
    $job->id = $DB->insert_record('block_pdfcounter_qualweb_jobs', $job);
}

// Run QualWeb workflow (same as cron, but for this user/course)
$api_base = 'http://localhost:8081/api';
$website_name = 'Moodle Course ' . $courseid;
$urls = [ $CFG->wwwroot . '/course/view.php?id=' . $courseid ];
$sqlpages = "SELECT cm.id as cmid FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module JOIN {page} p ON (m.name = 'page' AND p.id = cm.instance) WHERE cm.course = :courseid AND cm.deletioninprogress = 0 AND cm.visible = 1 AND m.name = 'page'";
$pages = $DB->get_records_sql($sqlpages, array('courseid' => $courseid));
foreach ($pages as $page) {
    $urls[] = $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid;
}
$sqlurls = "SELECT cm.id as cmid FROM {course_modules} cm JOIN {modules} m ON m.id = cm.module JOIN {url} u ON (m.name = 'url' AND u.id = cm.instance) WHERE cm.course = :courseid AND cm.deletioninprogress = 0 AND cm.visible = 1 AND m.name = 'url'";
$urlmods = $DB->get_records_sql($sqlurls, array('courseid' => $courseid));
foreach ($urlmods as $urlmod) {
    $urls[] = $CFG->wwwroot . '/mod/url/view.php?id=' . $urlmod->cmid;
}

// 1. Criar monitoring registry
$payload = [
    'url' => $urls[0],
    'is_mobile' => false,
    'is_landscape' => true,
    'display_width' => 1920,
    'display_height' => 1080,
    'website_name' => $website_name,
    'user_id' => $USER->id
];
$ch = curl_init($api_base . '/monitoring/crawl');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$monitoring_id = null;
$result = null;
if ($httpcode == 200 && $response) {
    $result = json_decode($response, true);
    $monitoring_id = $result['monitoring_registry_id'] ?? null;
}

if ($monitoring_id) {
    // 2. Adicionar todas as URLs ao monitoring registry
    $payload = [
        'urls' => $urls,
        'needs_authentication' => false
    ];
    $ch = curl_init($api_base . '/monitoring/' . $monitoring_id . '/add-webpages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    curl_close($ch);

    // 3. Buscar IDs das páginas adicionadas
    $ch = curl_init($api_base . '/monitoring/' . $monitoring_id . '/monitored-webpages');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $webpage_ids = [];
    if ($httpcode == 200 && $response) {
        $result = json_decode($response, true);
        if (!empty($result['monitored_webpages'])) {
            foreach ($result['monitored_webpages'] as $wp) {
                if (!empty($wp['id'])) $webpage_ids[] = $wp['id'];
            }
        }
    }

    if (!empty($webpage_ids)) {
        // 4. Iniciar avaliação
        $payload = [ 'webpage_ids' => $webpage_ids ];
        $ch = curl_init($api_base . '/monitoring/' . $monitoring_id . '/evaluate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $jobId = null;
        if ($httpcode == 200 && $response) {
            $result = json_decode($response, true);
            $jobId = $result['jobId'] ?? null;
        }

        // 5. Poll for completion (up to 20s)
        $status = 'queued';
        $tries = 0;
        while ($jobId && $tries < 20 && $status !== 'completed') {
            sleep(1);
            $ch = curl_init($api_base . '/monitoring/job-progress/' . $jobId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpcode == 200 && $response) {
                $data = json_decode($response, true);
                if (!empty($data['status'])) $status = $data['status'];
            }
            $tries++;
        }

        // 6. Buscar score final e issues
        $score = 'N/A';
        $ch = curl_init($api_base . '/monitoring/' . $monitoring_id . '/score');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $raw_json = $response;
        $errors = 'N/A';
        $warnings = 'N/A';
        if ($httpcode == 200 && $response) {
            $result = json_decode($response, true);
            if (isset($result['score'])) $score = $result['score'];
        }
        // Buscar estatísticas de issues agregadas
        $ch = curl_init($api_base . '/monitoring/' . $monitoring_id . '/issues-stats');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $stats_response = curl_exec($ch);
        $stats_httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($stats_httpcode == 200 && $stats_response) {
            $stats = json_decode($stats_response, true);
            if (isset($stats['failed'])) $errors = $stats['failed'];
            if (isset($stats['warnings'])) $warnings = $stats['warnings'];
        }
        // Save result JSON
        $job->monitoring_id = $monitoring_id;
        $job->result_json = json_encode([
            'score' => $score,
            'failed' => $errors,
            'warnings' => $warnings,
            'raw' => $raw_json
        ]);
        $job->status = 'completed';
        $job->timemodified = time();
        $DB->update_record('block_pdfcounter_qualweb_jobs', $job);
        echo json_encode(['status' => 'completed', 'score' => $score, 'failed' => $errors, 'warnings' => $warnings]);
        exit;
    }
}
// If any step failed
$job->monitoring_id = $monitoring_id;
$job->result_json = isset($result) ? json_encode($result) : null;
$job->status = 'error';
$job->timemodified = time();
$DB->update_record('block_pdfcounter_qualweb_jobs', $job);
echo json_encode(['status' => 'error']);
exit;
