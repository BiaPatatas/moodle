<?php
/**
 * QualWeb Evaluator - Adaptado para qualweb/backend
 */

defined('MOODLE_INTERNAL') || die();

class qualweb_evaluator_backend {
    
    private $api_base_url;
    
    public function __construct() {
        $this->api_base_url = get_config('block_pdfcounter', 'qualweb_api_url') ?: 'http://localhost:8081';
    }
    
    /**
     * Verificar se o serviço está disponível
     */
    public function is_service_available() {
        // Para o qualweb/backend, vamos tentar uma chamada simples de teste
        $url = rtrim($this->api_base_url, '/') . '/app/url';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => 'https://example.com']));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Debug info
        debugging('QualWeb Backend - URL: ' . $url, DEBUG_DEVELOPER);
        debugging('QualWeb Backend - HTTP Code: ' . $http_code, DEBUG_DEVELOPER);
        debugging('QualWeb Backend - Error: ' . $error, DEBUG_DEVELOPER);
        debugging('QualWeb Backend - Response length: ' . strlen($response), DEBUG_DEVELOPER);
        
        // Se conseguiu conectar e recebeu alguma resposta (mesmo que erro), serviço está rodando
        if (empty($error) && $http_code > 0) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Avaliar uma página
     */
    public function evaluate_page($url, $options = []) {
        debugging('QualWeb Backend - Starting evaluation for: ' . $url, DEBUG_DEVELOPER);
        
        // Sempre tentar avaliação real agora
        return $this->real_evaluate_page($url, $options);
    }
    
    /**
     * Avaliação real (separada para debug)
     */
    private function real_evaluate_page($url, $options = []) {
        $api_url = rtrim($this->api_base_url, '/') . '/app/url';
        
        $data = [
            'url' => $url,
            'options' => $options
        ];
        
        debugging('QualWeb Backend - API URL: ' . $api_url, DEBUG_DEVELOPER);
        debugging('QualWeb Backend - Payload: ' . json_encode($data), DEBUG_DEVELOPER);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutos para avaliação
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        debugging('QualWeb Backend - HTTP Code: ' . $http_code, DEBUG_DEVELOPER);
        debugging('QualWeb Backend - Error: ' . ($error ?: 'None'), DEBUG_DEVELOPER);
        debugging('QualWeb Backend - Response length: ' . strlen($response), DEBUG_DEVELOPER);
        debugging('QualWeb Backend - Content type: ' . ($info['content_type'] ?? 'Unknown'), DEBUG_DEVELOPER);
        
        if (!empty($error)) {
            throw new Exception('Connection error: ' . $error);
        }
        
        if ($http_code !== 200) {
            debugging('QualWeb Backend - Full response: ' . substr($response, 0, 500), DEBUG_DEVELOPER);
            throw new Exception('HTTP error: ' . $http_code . ' - ' . $response);
        }
        
        debugging('QualWeb Backend - Raw response (first 500 chars): ' . substr($response, 0, 500), DEBUG_DEVELOPER);
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('QualWeb Backend - JSON error: ' . json_last_error_msg(), DEBUG_DEVELOPER);
            debugging('QualWeb Backend - Raw response for JSON debug: ' . $response, DEBUG_DEVELOPER);
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }
        
        debugging('QualWeb Backend - Parsed result keys: ' . implode(', ', array_keys($result)), DEBUG_DEVELOPER);
        
        return $this->process_qualweb_result($result);
    }
    
    /**
     * Processar resultado do QualWeb
     */
    private function process_qualweb_result($raw_result) {
        debugging('QualWeb Backend - Processing result with keys: ' . implode(', ', array_keys($raw_result)), DEBUG_DEVELOPER);
        
        // Adaptar o resultado para o formato esperado
        $processed = [
            'url' => $raw_result['url'] ?? '',
            'metadata' => [
                'passed' => 0,
                'warning' => 0,
                'failed' => 0,
                'score' => 0
            ]
        ];
        
        // Verificar diferentes formatos possíveis
        
        // Formato 1: Resultado direto com metadata
        if (isset($raw_result['metadata'])) {
            debugging('QualWeb Backend - Found direct metadata', DEBUG_DEVELOPER);
            $processed['metadata']['passed'] = $raw_result['metadata']['passed'] ?? 0;
            $processed['metadata']['warning'] = $raw_result['metadata']['warning'] ?? 0;
            $processed['metadata']['failed'] = $raw_result['metadata']['failed'] ?? 0;
        }
        
        // Formato 2: Se tiver módulos de testes (formato comum do QualWeb)
        elseif (isset($raw_result['modules'])) {
            debugging('QualWeb Backend - Found modules format', DEBUG_DEVELOPER);
            foreach ($raw_result['modules'] as $module_name => $module) {
                debugging('QualWeb Backend - Processing module: ' . $module_name, DEBUG_DEVELOPER);
                if (isset($module['metadata'])) {
                    $processed['metadata']['passed'] += $module['metadata']['passed'] ?? 0;
                    $processed['metadata']['warning'] += $module['metadata']['warning'] ?? 0;
                    $processed['metadata']['failed'] += $module['metadata']['failed'] ?? 0;
                }
            }
        }
        
        // Formato 3: Estrutura de resultados (assertions)
        elseif (isset($raw_result['assertions'])) {
            debugging('QualWeb Backend - Found assertions format', DEBUG_DEVELOPER);
            foreach ($raw_result['assertions'] as $assertion) {
                $verdict = $assertion['verdict'] ?? $assertion['result'] ?? '';
                switch (strtolower($verdict)) {
                    case 'passed':
                    case 'pass':
                        $processed['metadata']['passed']++;
                        break;
                    case 'warning':
                    case 'warn':
                        $processed['metadata']['warning']++;
                        break;
                    case 'failed':
                    case 'fail':
                        $processed['metadata']['failed']++;
                        break;
                }
            }
        }
        
        // Formato 4: Resultado simples com totais
        elseif (isset($raw_result['passed']) || isset($raw_result['warning']) || isset($raw_result['failed'])) {
            debugging('QualWeb Backend - Found simple totals format', DEBUG_DEVELOPER);
            $processed['metadata']['passed'] = $raw_result['passed'] ?? 0;
            $processed['metadata']['warning'] = $raw_result['warning'] ?? 0;
            $processed['metadata']['failed'] = $raw_result['failed'] ?? 0;
        }
        
        // Se não encontrou nada, debug completo
        else {
            debugging('QualWeb Backend - Unknown format, full structure: ' . print_r($raw_result, true), DEBUG_DEVELOPER);
        }
        
        // Calcular score
        $total = $processed['metadata']['passed'] + 
                $processed['metadata']['warning'] + 
                $processed['metadata']['failed'];
        
        if ($total > 0) {
            $processed['metadata']['score'] = round(($processed['metadata']['passed'] / $total) * 100, 1);
        }
        
        debugging('QualWeb Backend - Final processed result: ' . print_r($processed, true), DEBUG_DEVELOPER);
        
        return $processed;
    }
    
    /**
     * Avaliar múltiplas páginas de um curso
     */
    public function evaluate_course_pages($courseid, $pages = []) {
        global $CFG;
        
        if (empty($pages)) {
            $pages = $this->get_course_pages($courseid);
        }
        
        $results = [];
        $errors = [];
        
        foreach ($pages as $page) {
            try {
                $result = $this->evaluate_page($page['url']);
                $results[] = $result;
                
                // Cache o resultado
                $this->cache_result($courseid, $page['url'], $result);
                
            } catch (Exception $e) {
                $errors[] = [
                    'url' => $page['url'],
                    'error' => $e->getMessage()
                ];
            }
            
            // Pausa entre avaliações
            sleep(2);
        }
        
        return [
            'results' => $results,
            'errors' => $errors,
            'summary' => $this->calculate_summary($results)
        ];
    }
    
    /**
     * Obter páginas do curso
     */
    private function get_course_pages($courseid) {
        global $DB, $CFG;
        
        $pages = [];
        
        // Página principal do curso
        $pages[] = [
            'url' => $CFG->wwwroot . '/course/view.php?id=' . $courseid,
            'title' => 'Course Main Page'
        ];
        
        // Algumas páginas do curso
        $modules = $DB->get_records_sql("
            SELECT cm.id, m.name as modname, cm.instance
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE cm.course = ? AND cm.visible = 1
            AND m.name IN ('page', 'resource', 'quiz', 'forum', 'book')
            LIMIT 5
        ", [$courseid]);
        
        foreach ($modules as $module) {
            $pages[] = [
                'url' => $CFG->wwwroot . '/mod/' . $module->modname . '/view.php?id=' . $module->id,
                'title' => ucfirst($module->modname) . ' ' . $module->id
            ];
        }
        
        return $pages;
    }
    
    /**
     * Cache do resultado
     */
    private function cache_result($courseid, $url, $result) {
        global $DB;
        
        try {
            $record = new stdClass();
            $record->courseid = $courseid;
            $record->url = $url;
            $record->results = json_encode($result);
            $record->timecreated = time();
            
            $existing = $DB->get_record('block_pdfcounter_qualweb', [
                'courseid' => $courseid,
                'url' => $url
            ]);
            
            if ($existing) {
                $record->id = $existing->id;
                $record->timemodified = time();
                $DB->update_record('block_pdfcounter_qualweb', $record);
            } else {
                $DB->insert_record('block_pdfcounter_qualweb', $record);
            }
        } catch (Exception $e) {
            debugging('Cache error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Calcular resumo
     */
    private function calculate_summary($results) {
        if (empty($results)) {
            return [
                'total_pages' => 0,
                'average_score' => 0,
                'total_passed' => 0,
                'total_warnings' => 0,
                'total_failed' => 0
            ];
        }
        
        $total_score = 0;
        $total_passed = 0;
        $total_warnings = 0;
        $total_failed = 0;
        
        foreach ($results as $result) {
            if (isset($result['metadata'])) {
                $meta = $result['metadata'];
                $total_score += $meta['score'] ?? 0;
                $total_passed += $meta['passed'] ?? 0;
                $total_warnings += $meta['warning'] ?? 0;
                $total_failed += $meta['failed'] ?? 0;
            }
        }
        
        $count = count($results);
        
        return [
            'total_pages' => $count,
            'average_score' => $count > 0 ? round($total_score / $count, 2) : 0,
            'total_passed' => $total_passed,
            'total_warnings' => $total_warnings,
            'total_failed' => $total_failed
        ];
    }
}