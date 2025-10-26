<?php
require_once(__DIR__ . '/../../../config.php');
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

if (!$draftid || !is_numeric($draftid)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid draft ID']);
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

        // Executa script Python para analisar o PDF
        $output = shell_exec("python3 " . escapeshellarg(__DIR__ . '/../pdf_accessibility.py') . " " . escapeshellarg($filepath));

        // Remove arquivo temporário após uso
        unlink($filepath);

        $result = json_decode($output, true);

        if (!$result) {
            echo json_encode(['status' => 'error', 'message' => 'Failed to analyze PDF']);
            exit;
        }

        // Adiciona o nome do ficheiro ao JSON de resposta
        echo json_encode([
            'status' => 'ok',
            'summary' => $result,
            'filename' => $file->get_filename()
        ]);
        exit;  // importante para não continuar o script
    }
}
echo json_encode(['status' => 'error', 'message' => 'No PDF found']);
exit;