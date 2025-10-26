<?php
/**
 * Teste de conexão QualWeb - Docker + CLI
 */

require_once('../../config.php');
require_once(__DIR__ . '/classes/qualweb_evaluator.php');
require_once(__DIR__ . '/classes/qualweb_evaluator_cli.php');
require_once(__DIR__ . '/classes/qualweb_evaluator_backend.php');
require_once(__DIR__ . '/classes/qualweb_factory.php');

require_login();

header('Content-Type: application/json');

try {
    $mode = get_config('block_pdfcounter', 'qualweb_mode') ?: 'docker';
    $enabled = get_config('block_pdfcounter', 'qualweb_enabled');
    
    if (!$enabled) {
        throw new Exception('QualWeb is disabled');
    }
    
    if ($mode === 'docker') {
        // Testar modo Docker original
        $evaluator = new qualweb_evaluator();
        $available = $evaluator->is_service_available();
        
        if ($available) {
            echo json_encode([
                'success' => true,
                'mode' => 'docker',
                'message' => 'Docker API connection successful',
                'api_url' => get_config('block_pdfcounter', 'qualweb_api_url')
            ]);
        } else {
            throw new Exception('Docker API not responding');
        }
        
    } else if ($mode === 'backend') {
        // Testar modo Backend
        $evaluator = new qualweb_evaluator_backend();
        $available = $evaluator->is_service_available();
        
        if ($available) {
            echo json_encode([
                'success' => true,
                'mode' => 'backend',
                'message' => 'Backend API connection successful',
                'api_url' => get_config('block_pdfcounter', 'qualweb_api_url')
            ]);
        } else {
            throw new Exception('Backend API not responding');
        }
        
    } else if ($mode === 'cli') {
        // Testar modo CLI
        $evaluator = new qualweb_evaluator_nodemon();
        $available = $evaluator->is_service_available();
        
        if ($available) {
            echo json_encode([
                'success' => true,
                'mode' => 'cli',
                'message' => 'CLI connection successful',
                'cli_path' => get_config('block_pdfcounter', 'qualweb_cli_path')
            ]);
        } else {
            throw new Exception('QualWeb CLI not found or not working');
        }
        
    } else {
        throw new Exception('Invalid QualWeb mode: ' . $mode);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'mode' => $mode ?? 'unknown'
    ]);
}
?>