<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../pdf_accessibility_config.php'); // PDF accessibility shared config & logger
require_login();
$rawinput = file_get_contents("php://input");
$input = json_decode($rawinput, true);
if (!isset($input['sesskey'])) {
    pdf_accessibility_log_error('preview.php: sesskey missing', [
        'rawinput' => $rawinput ?? null,
    ], 'preview.log');
    echo json_encode(['status' => 'error', 'message' => 'Sesskey missing']);
    exit;
}
$_POST['sesskey'] = $input['sesskey']; // Para o require_sesskey() funcionar
require_sesskey();

// Debug write to local file disabled for production.
// file_put_contents(__DIR__.'/debug.txt', 'chegou aqui');

// Ensure errors are logged but not sent to the AJAX response, so we always
// return valid JSON to the frontend instead of HTML error pages.
// (Production Moodle normally has display_errors = 0 in php.ini; we keep that.)
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json');


function save_temp_pdf($file) {
    // Gera um arquivo temporário no diretório temp do Moodle
    global $CFG;
    $tempdir = $CFG->tempdir; 
    $tempfile = tempnam($tempdir, 'pdf_') . '.pdf';
    file_put_contents($tempfile, $file->get_content());
    return $tempfile;
}

function is_shell_exec_available() {
    if (!function_exists('shell_exec')) {
        return false;
    }

    $disabled = ini_get('disable_functions');
    if (empty($disabled)) {
        return true;
    }

    $list = array_map('trim', explode(',', strtolower($disabled)));
    return !in_array('shell_exec', $list, true);
}

function is_pdf_candidate($file) {
    $mimetype = (string)$file->get_mimetype();
    if ($mimetype === 'application/pdf') {
        return true;
    }
    return (strtolower(pathinfo((string)$file->get_filename(), PATHINFO_EXTENSION)) === 'pdf');
}

function analyze_pdf_with_python($scriptpath, $filepath) {
    if (!is_shell_exec_available()) {
        return [
            'ok' => false,
            'error' => 'PHP shell_exec is disabled on this server'
        ];
    }

    $iswindows = (DIRECTORY_SEPARATOR === '\\');
    $interpreters = $iswindows ? ['py -3', 'python', 'py'] : ['python3', 'python'];
    $attempts = [];

    foreach ($interpreters as $interpreter) {
        $command = $interpreter . ' ' . escapeshellarg($scriptpath) . ' ' . escapeshellarg($filepath);
        $output = shell_exec($command . ' 2>&1');
        $trimmed = trim((string)$output);
        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return [
                'ok' => true,
                'result' => $decoded,
                'command' => $command
            ];
        }

        $attempts[] = [
            'command' => $command,
            'output' => $trimmed
        ];
    }

    $errordetail = '';
    foreach ($attempts as $attempt) {
        if (!empty($attempt['output'])) {
            $errordetail = substr($attempt['output'], 0, 240);
            break;
        }
    }

    $errormessage = 'Python analyzer failed or returned invalid JSON';
    if ($errordetail !== '') {
        $errormessage .= ' - ' . $errordetail;
    }

    return [
        'ok' => false,
        'error' => $errormessage,
        'attempts' => $attempts
    ];
}

$input = $input ?? json_decode($rawinput, true);
$draftid = $input['draftid'] ?? null;
$courseid = $input['courseid'] ?? null;

if (!$draftid || !is_numeric($draftid)) {
    pdf_accessibility_log_error('preview.php: invalid draft ID', [
        'draftid' => $draftid,
        'courseid' => $courseid,
    ], 'preview.log');
    echo json_encode(['status' => 'error', 'message' => 'Invalid draft ID']);
    exit;
}

if (!$courseid || !is_numeric($courseid) || $courseid <= 0) {
    pdf_accessibility_log_error('preview.php: invalid course ID', [
        'draftid' => $draftid,
        'courseid' => $courseid,
    ], 'preview.log');
    echo json_encode(['status' => 'error', 'message' => 'Invalid course ID: ' . $courseid]);
    exit;
}

try {
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        context_user::instance($USER->id)->id,
        'user',
        'draft',
        $draftid,
        'itemid, filepath, filename',
        false
    );

    // Debug: log found files
    $filenames = [];
    foreach ($files as $file) {
        $filenames[] = $file->get_filename() . ' (' . $file->get_mimetype() . ')';
    }
    if (empty($filenames)) {
        pdf_accessibility_log_error('preview.php: no files in draft area', [
            'draftid' => $draftid,
            'courseid' => $courseid,
        ], 'preview.log');
        echo json_encode(['status' => 'error', 'message' => 'No files found in draft area for this draftid', 'draftid' => $draftid]);
        exit;
    }

    // Novo: processar todos os PDFs e retornar todos juntos
    $pdfs = [];
    $analysiserrors = [];
    global $DB, $USER, $COURSE;
    foreach ($files as $file) {
        if (is_pdf_candidate($file)) {
            $filepath = save_temp_pdf($file);
            $script_path = __DIR__ . '/../pdf_accessibility.py';
            $analysis = analyze_pdf_with_python($script_path, $filepath);
            @unlink($filepath);

            if (!$analysis['ok']) {
                $analysiserrors[] = $file->get_filename() . ': ' . $analysis['error'];
                pdf_accessibility_log_error('preview.php: Python analysis returned invalid JSON', [
                    'filename' => $file->get_filename(),
                    'courseid' => $courseid,
                    'draftid' => $draftid,
                    'error' => $analysis['error'],
                    'attempts' => $analysis['attempts'] ?? [],
                ], 'preview.log');
                continue;
            }

            $result = $analysis['result'];
            $filehash = sha1($file->get_content());
            $existing = $DB->get_record('block_pdfaccessibility_pdf_files', [
                'filename' => $file->get_filename(),
                'filehash' => $filehash,
                'courseid' => $courseid
            ]);
            if ($existing) {
                $fileid = $existing->id;
            } else {
                $filedata = new stdClass();
                $filedata->courseid = $courseid;
                $filedata->userid = $USER->id;
                $filedata->filename = $file->get_filename();
                $filedata->filehash = $filehash;
                $filedata->timecreated = time();
                $fileid = $DB->insert_record('block_pdfaccessibility_pdf_files', $filedata, true);
            }
            foreach ($result as $testname => $testvalue) {
                if (pdf_accessibility_config::should_exclude_test($testname)) {
                    continue;
                }
                $status = pdf_accessibility_config::determine_test_status($testname, $testvalue);
                $existing_test = $DB->get_record('block_pdfaccessibility_test_results', [
                    'fileid' => $fileid,
                    'testname' => $testname
                ]);
                $testdata = new stdClass();
                $testdata->fileid = $fileid;
                $testdata->testname = $testname;
                $testdata->result = $status;
                $testdata->errorpages = '';
                $testdata->timecreated = time();
                if ($existing_test) {
                    $testdata->id = $existing_test->id;
                    $DB->update_record('block_pdfaccessibility_test_results', $testdata);
                } else {
                    $DB->insert_record('block_pdfaccessibility_test_results', $testdata);
                }
            }
            $pdfs[] = [
                'filename' => $file->get_filename(),
                'summary' => $result
            ];
        }
    }
    if (count($pdfs) > 0) {
        echo json_encode([
            'status' => 'ok',
            'pdfs' => $pdfs,
            'testConfig' => json_decode(pdf_accessibility_config::get_js_test_config(), true)
        ]);
        exit;
    }
    if (!empty($analysiserrors)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'No PDF analyzed successfully. ' . $analysiserrors[0]
        ]);
        exit;
    }

    echo json_encode(['status' => 'error', 'message' => 'No PDF found in uploaded files']);
    exit;
} catch (Throwable $e) {
    pdf_accessibility_log_error('preview.php: unexpected exception', [
        'draftid' => $draftid ?? null,
        'courseid' => $courseid ?? null,
        'exception' => $e->getMessage(),
    ], 'preview.log');
    echo json_encode(['status' => 'error', 'message' => 'Unexpected error in preview']);
    exit;
}