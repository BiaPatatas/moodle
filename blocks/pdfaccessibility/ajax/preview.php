<?php
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../pdf_accessibility_config.php'); // PDF accessibility shared config
require_login();
$input = json_decode(file_get_contents("php://input"), true);
if (!isset($input['sesskey'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesskey missing']);
    exit;
}
$_POST['sesskey'] = $input['sesskey']; // Para o require_sesskey() funcionar
require_sesskey();

file_put_contents(__DIR__.'/debug.txt', 'chegou aqui');

error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');


function save_temp_pdf($file) {
    // Gera um arquivo temporário no diretório temp do Moodle
    global $CFG;
    $tempdir = $CFG->tempdir; 
    $tempfile = tempnam($tempdir, 'pdf_') . '.pdf';
    file_put_contents($tempfile, $file->get_content());
    return $tempfile;
}


$input = json_decode(file_get_contents("php://input"), true);
$draftid = $input['draftid'] ?? null;
$courseid = $input['courseid'] ?? null;

if (!$draftid || !is_numeric($draftid)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid draft ID']);
    exit;
}

if (!$courseid || !is_numeric($courseid) || $courseid <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid course ID: ' . $courseid]);
    exit;
}

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
    echo json_encode(['status' => 'error', 'message' => 'No files found in draft area for this draftid', 'draftid' => $draftid]);
    error_log("esta vazio");
    exit;
}

foreach ($files as $file) {
    if ($file->get_mimetype() === 'application/pdf') {
        // Salva conteúdo PDF temporariamente
        $filepath = save_temp_pdf($file);

        // === SISTEMA DE DEBUG DETALHADO ===
        $debug_dir = __DIR__ . '/../debug/';
        if (!is_dir($debug_dir)) {
            mkdir($debug_dir, 0755, true);
        }
        
        $script_path = __DIR__ . '/../pdf_accessibility.py';
        $python_command = "python3 " . escapeshellarg($script_path) . " " . escapeshellarg($filepath);
        
        // Debug 1: Comando e info do arquivo
        $debug_info = [
            'timestamp' => date('Y-m-d H:i:s'),
            'filename' => $file->get_filename(),
            'filesize' => $file->get_filesize(),
            'filepath' => $filepath,
            'file_exists' => file_exists($filepath),
            'script_path' => $script_path,
            'script_exists' => file_exists($script_path),
            'python_command' => $python_command,
            'cwd' => getcwd()
        ];
        file_put_contents($debug_dir . 'debug_command.txt', json_encode($debug_info, JSON_PRETTY_PRINT));
        
        // Debug 2: Executa script Python e captura output bruto
        $output = shell_exec($python_command . " 2>&1");
        file_put_contents($debug_dir . 'debug_python_output.txt', 
            "=== Python Output Debug ===\n" .
            "Timestamp: " . date('Y-m-d H:i:s') . "\n" .
            "Command: " . $python_command . "\n" .
            "Output Length: " . strlen($output) . "\n" .
            "Raw Output:\n" . $output . "\n" .
            "=== End Output ===\n\n", FILE_APPEND);

        // Remove arquivo temporário após uso
        unlink($filepath);

        // Debug 3: JSON decode e erros
        $result = json_decode($output, true);
        $json_debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'json_decode_success' => $result !== null,
            'json_last_error' => json_last_error(),
            'json_last_error_msg' => json_last_error_msg(),
            'result_type' => gettype($result),
            'result_count' => is_array($result) ? count($result) : 'N/A',
            'result' => $result
        ];
        file_put_contents($debug_dir . 'debug_json.txt', json_encode($json_debug, JSON_PRETTY_PRINT));


        if (!$result) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to analyze PDF']);
            exit;
        }

        // --- Inserir arquivo na tabela, se necessário ---
        global $DB, $USER, $COURSE;
        $filehash = sha1($file->get_content());
        // Verifica se já existe no mesmo curso
        $existing = $DB->get_record('block_pdfaccessibility_pdf_files', [
            'filename' => $file->get_filename(),
            'filehash' => $filehash,
            'courseid' => $courseid
        ]);
        if ($existing) {
            $fileid = $existing->id;
        } else {
            $filedata = new stdClass();
            $filedata->courseid = $courseid; // Use course ID from JavaScript
            $filedata->userid = $USER->id;
            $filedata->filename = $file->get_filename();
            $filedata->filehash = $filehash;
            $filedata->timecreated = time();
            $fileid = $DB->insert_record('block_pdfaccessibility_pdf_files', $filedata, true);
        }

        // --- Inserir resultado do teste ---
        foreach ($result as $testname => $testvalue) {
            // Use shared config to check if test should be excluded
            if (pdf_accessibility_config::should_exclude_test($testname)) {
                continue;
            }

            // Use shared config to determine status
            $status = pdf_accessibility_config::determine_test_status($testname, $testvalue);

            // Atualiza ou insere resultado
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

        // Adiciona o nome do ficheiro e configuração de testes ao JSON de resposta
        echo json_encode([
            'status' => 'ok',
            'summary' => $result,
            'filename' => $file->get_filename(),
            'testConfig' => json_decode(pdf_accessibility_config::get_js_test_config(), true)
        ]);
        exit;  // importante para não continuar o script
    }
}
echo json_encode(['status' => 'error', 'message' => 'No PDF found']);
exit;