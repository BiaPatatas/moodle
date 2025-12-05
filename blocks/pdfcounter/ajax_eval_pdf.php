<?php
// ULTRA-EARLY DEBUG LOGGING
$ultraearlyfile = __DIR__ . '/debug/debug_pdf_ajax.txt';
file_put_contents($ultraearlyfile, "Ultra-early debug: script started " . date('Y-m-d H:i:s') . PHP_EOL, FILE_APPEND);

file_put_contents($ultraearlyfile, "Ultra-early debug: before require_once config.php" . PHP_EOL, FILE_APPEND);
require_once('../../config.php');
file_put_contents($ultraearlyfile, "Ultra-early debug: after require_once config.php" . PHP_EOL, FILE_APPEND);

file_put_contents($ultraearlyfile, "Ultra-early debug: before require_once lib.php" . PHP_EOL, FILE_APPEND);
require_once($CFG->dirroot . '/blocks/pdfcounter/lib.php');
file_put_contents($ultraearlyfile, "Ultra-early debug: after require_once lib.php" . PHP_EOL, FILE_APPEND);

file_put_contents($ultraearlyfile, "Ultra-early debug: before require_once pdf_accessibility_config.php" . PHP_EOL, FILE_APPEND);
require_once($CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility_config.php');
file_put_contents($ultraearlyfile, "Ultra-early debug: after require_once pdf_accessibility_config.php" . PHP_EOL, FILE_APPEND);

file_put_contents($ultraearlyfile, "Ultra-early debug: before require_once filelib.php" . PHP_EOL, FILE_APPEND);
require_once($CFG->dirroot . '/lib/filelib.php');
file_put_contents($ultraearlyfile, "Ultra-early debug: after require_once filelib.php" . PHP_EOL, FILE_APPEND);

file_put_contents($ultraearlyfile, "Ultra-early debug: before require_login" . PHP_EOL, FILE_APPEND);
require_login();
file_put_contents($ultraearlyfile, "Ultra-early debug: after require_login" . PHP_EOL, FILE_APPEND);

// Explicitly set globals as in block_pdfcounter.php
$DB = $GLOBALS['DB'];
$CFG = $GLOBALS['CFG'];
$USER = $GLOBALS['USER'];
file_put_contents($ultraearlyfile, "Ultra-early debug: globals set (DB, CFG, USER)" . PHP_EOL, FILE_APPEND);

$debugfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdf_ajax.txt';
file_put_contents($debugfile, "==== AJAX PDF eval chamado ====".date('Y-m-d H:i:s')."\n", FILE_APPEND);

file_put_contents($debugfile, "Debug: before required_param courseid\n", FILE_APPEND);
try {
    $courseid = required_param('courseid', PARAM_INT);
    file_put_contents($debugfile, "Debug: after required_param courseid, value: $courseid\n", FILE_APPEND);
} catch (Exception $e) {
    file_put_contents($debugfile, "Debug: exception in required_param: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => 'exception', 'message' => $e->getMessage()]);
    exit;
}

file_put_contents($debugfile, "Debug: before header json\n", FILE_APPEND);
header('Content-Type: application/json');
file_put_contents($debugfile, "Debug: after header json\n", FILE_APPEND);

// Permissão: apenas professores/admins
file_put_contents($debugfile, "Debug: before context_course::instance\n", FILE_APPEND);
$context = context_course::instance($courseid);
file_put_contents($debugfile, "Debug: after context_course::instance\n", FILE_APPEND);

file_put_contents($debugfile, "Debug: before has_capability\n", FILE_APPEND);
if (!has_capability('moodle/course:update', $context)) {
    file_put_contents($debugfile, "Debug: sem permissão moodle/course:update\n", FILE_APPEND);
    echo json_encode(['error' => 'no_permission']);
    exit;
}
file_put_contents($debugfile, "Debug: passou permissão\n", FILE_APPEND);

file_put_contents($debugfile, "Debug: before buscar PDFs pendentes\n", FILE_APPEND);
try {
    $pending = block_pdfcounter_get_pending_pdfs($courseid);
    file_put_contents($debugfile, 'Debug: pending array: ' . print_r($pending, true) . "\n", FILE_APPEND);
    file_put_contents($debugfile, 'Debug: pending count: ' . count($pending) . "\n", FILE_APPEND);
    if (empty($pending)) {
        file_put_contents($debugfile, "Debug: nenhum PDF pendente para avaliar.\n", FILE_APPEND);
        echo json_encode(['done' => true, 'message' => 'Todos os PDFs já avaliados.']);
        exit;
    }

    // Avalia o primeiro PDF pendente
    file_put_contents($debugfile, 'Debug: antes de avaliar PDF\n', FILE_APPEND);
    $pdfinfo = $pending[0];
    file_put_contents($debugfile, 'Debug: PDF info: ' . print_r($pdfinfo, true) . "\n", FILE_APPEND);
    $result = block_pdfcounter_evaluate_pdf($pdfinfo, $courseid, $USER->id);
    file_put_contents($debugfile, 'Debug: result: ' . print_r($result, true) . "\n", FILE_APPEND);

    // Retorna status
    file_put_contents($debugfile, "Debug: antes de echo json_encode resultado\n", FILE_APPEND);
    echo json_encode([
        'done' => false,
        'filename' => $pdfinfo['filename'],
        'filehash' => $pdfinfo['filehash'],
        'result' => $result
    ]);
    file_put_contents($debugfile, "Debug: depois de echo json_encode resultado\n", FILE_APPEND);
    exit;
} catch (Exception $e) {
    file_put_contents($debugfile, 'Debug: exception: ' . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['error' => 'exception', 'message' => $e->getMessage()]);
    exit;
}
