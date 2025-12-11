<?php
namespace block_pdfcounter\external;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/externallib.php');

use external_function_parameters;
use external_value;
use external_single_structure;
use external_api;
use context_course;
use moodle_exception;


class qualweb_eval extends external_api {
    public static function eval_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'action' => new external_value(PARAM_ALPHA, 'Action', VALUE_DEFAULT, 'start'),
            'module_type' => new external_value(PARAM_ALPHA, 'Module type', VALUE_DEFAULT, 'resource')
        ]);
    }

    public static function eval($courseid, $action = 'start', $module_type = 'resource') {
        global $DB, $CFG, $USER;
                    // DEBUG: Open debug log file for QualWeb
                    $debuglogfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdfcounter_qualweb.txt';
                    $debuglog = @fopen($debuglogfile, 'a');
                    if ($debuglog !== false) {
                        fwrite($debuglog, "\n==== QUALWEB EVAL DEBUG START ====".date('Y-m-d H:i:s')."\n");
                        fwrite($debuglog, "Course ID: {$courseid}\n");
                        fwrite($debuglog, "Action: {$action}\n");
                        fwrite($debuglog, "Module type: {$module_type}\n");
                    }
        require_once($CFG->dirroot . '/blocks/pdfcounter/qualweb_job_model.php');
        $context = context_course::instance($courseid);
        self::validate_context($context);
        if (empty($module_type) || $module_type === 'undefined') {
        $module_type = 'resource';
    }
        if (!is_enrolled($context, $USER)) {
            throw new moodle_exception('not_enrolled', 'block_pdfcounter');
        }
        $job = $DB->get_record('block_pdfcounter_qualweb_jobs', [
            'courseid' => $courseid,
            'userid' => $USER->id
        ]);
        if ($action === 'status') {
            if ($job) {
                return [
                    'status' => $job->status,
                    'result_json' => $job->result_json
                ];
            } else {
                return ['status' => 'none'];
            }
        }
        if (!$job || $job->status === 'error') {
            $job = new \stdClass();
            $job->courseid = $courseid;
            $job->userid = $USER->id;
            $job->status = 'running';
            $job->timecreated = time();
            $job->timemodified = time();
            $job->monitoring_id = null;
            $job->result_json = null;
            $job->id = $DB->insert_record('block_pdfcounter_qualweb_jobs', $job);
        }
        // QualWeb workflow (igual ao AJAX antigo)
        $api_base = 'http://localhost:8081/api';
        $website_name = 'Moodle Course ' . $courseid;
        // $urls = [ $CFG->wwwroot . '/course/view.php?id=' . $courseid ];
        $qualweb_host = 'host.docker.internal'; // ou o IP do host
        $urls = [ 'http://' . $qualweb_host . '/course/view.php?id=' . $courseid ];
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
                return ['status' => 'completed', 'score' => $score, 'failed' => $errors, 'warnings' => $warnings];
            }
        }
        $job->monitoring_id = $monitoring_id;
        $job->result_json = isset($result) ? json_encode($result) : null;
        $job->status = 'error';
        $job->timemodified = time();
        $DB->update_record('block_pdfcounter_qualweb_jobs', $job);
        error_log('QualWeb ERROR: ' . json_encode($result ?? null));
        return ['status' => 'error'];
    }

    public static function eval_returns() {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Status'),
            'result_json' => new external_value(PARAM_RAW, 'Result JSON', VALUE_OPTIONAL),
            'score' => new external_value(PARAM_TEXT, 'Score', VALUE_OPTIONAL),
            'failed' => new external_value(PARAM_TEXT, 'Failed', VALUE_OPTIONAL),
            'warnings' => new external_value(PARAM_TEXT, 'Warnings', VALUE_OPTIONAL)
        ]);
    }
}
