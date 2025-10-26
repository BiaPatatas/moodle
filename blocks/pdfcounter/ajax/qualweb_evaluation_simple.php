<?php
/**
 * AJAX endpoint para QualWeb - Versão Simplificada
 */

define('AJAX_SCRIPT', true);
require_once('../../../config.php');
require_once('../classes/qualweb_factory.php');

header('Content-Type: application/json');

$courseid = required_param('courseid', PARAM_INT);
$action = optional_param('action', 'evaluate', PARAM_ALPHA);

require_login();

try {
    // Verificar se QualWeb está habilitado
    $qualweb_enabled = get_config('block_pdfcounter', 'qualweb_enabled');
    
    if (!$qualweb_enabled) {
        echo json_encode([
            'success' => false,
            'error' => 'QualWeb is disabled. Please enable it in settings.'
        ]);
        exit;
    }
    
    // Verificar se o serviço está disponível
    if (!qualweb_factory::is_available()) {
        echo json_encode([
            'success' => false,
            'error' => 'QualWeb service is not available. Please check Docker container.'
        ]);
        exit;
    }
    
    if ($action === 'evaluate') {
        // Criar evaluator
        $evaluator = qualweb_factory::create_evaluator();
        
        // Avaliar páginas do curso
        $results = qualweb_factory::evaluate_course_pages($courseid);
        
        // Salvar no cache/banco
        save_results_to_database($courseid, $results);
        
        echo json_encode([
            'success' => true,
            'message' => 'Evaluation completed successfully',
            'data' => $results['summary'] ?? []
        ]);
        
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid action'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Evaluation failed: ' . $e->getMessage()
    ]);
}

/**
 * Salvar resultados no banco
 */
function save_results_to_database($courseid, $results) {
    global $DB;
    
    try {
        // Remover resultados antigos
        $DB->delete_records('block_pdfcounter_qualweb', ['courseid' => $courseid]);
        
        $summary = $results['summary'] ?? [];
        $pages_data = $results['results'] ?? [];
        
        // Criar novo registro
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->total_pages = $summary['total_pages'] ?? 0;
        $record->evaluated_pages = $summary['total_pages'] ?? 0;
        $record->passed_tests = $summary['total_passed'] ?? 0;
        $record->failed_tests = $summary['total_failed'] ?? 0;
        $record->warnings = $summary['total_warnings'] ?? 0;
        $record->average_score = $summary['average_score'] ?? 0;
        $record->results_data = json_encode($pages_data);
        $record->timecreated = time();
        $record->timemodified = time();
        
        $DB->insert_record('block_pdfcounter_qualweb', $record);
        
    } catch (Exception $e) {
        debugging('Error saving QualWeb results: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}
?>