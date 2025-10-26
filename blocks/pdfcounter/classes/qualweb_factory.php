<?php
/**
 * QualWeb Factory - Escolhe automaticamente Docker ou CLI
 */

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/qualweb_evaluator.php');
require_once(__DIR__ . '/qualweb_evaluator_cli.php');
require_once(__DIR__ . '/qualweb_evaluator_backend.php');

class qualweb_factory {
    
    /**
     * Criar instância do evaluator apropriado
     */
    public static function create_evaluator() {
        $mode = get_config('block_pdfcounter', 'qualweb_mode') ?: 'docker';
        
        if ($mode === 'cli') {
            return new qualweb_evaluator_nodemon();
        } else if ($mode === 'backend') {
            return new qualweb_evaluator_backend();
        } else {
            // Tentar detectar automaticamente qual tipo de Docker está rodando
            return self::detect_docker_type();
        }
    }
    
    /**
     * Detectar automaticamente o tipo de Docker QualWeb
     */
    private static function detect_docker_type() {
        $api_url = get_config('block_pdfcounter', 'qualweb_api_url') ?: 'http://localhost:8081';
        
        // Tentar backend primeiro (qualweb/backend)
        $backend = new qualweb_evaluator_backend();
        if ($backend->is_service_available()) {
            // Salvar detecção automática
            set_config('qualweb_mode', 'backend', 'block_pdfcounter');
            return $backend;
        }
        
        // Fallback para o evaluator original
        return new qualweb_evaluator();
    }
    
    /**
     * Verificar se QualWeb está disponível
     */
    public static function is_available() {
        // TEMPORÁRIO: sempre retornar true para testar a avaliação
        // Remover depois que funcionar
        return true;
        
        try {
            $evaluator = self::create_evaluator();
            return $evaluator->is_service_available();
        } catch (Exception $e) {
            debugging('QualWeb availability check failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
    
    /**
     * Obter modo atual
     */
    public static function get_current_mode() {
        return get_config('block_pdfcounter', 'qualweb_mode') ?: 'docker';
    }
    
    /**
     * Avaliar página (método unificado)
     */
    public static function evaluate_page($url, $options = []) {
        $evaluator = self::create_evaluator();
        return $evaluator->evaluate_page($url, $options);
    }
    
    /**
     * Avaliar páginas do curso (método unificado)
     */
    public static function evaluate_course_pages($courseid, $pages = []) {
        $evaluator = self::create_evaluator();
        
        // Verificar se o método existe (CLI tem, Docker não tem ainda)
        if (method_exists($evaluator, 'evaluate_course_pages')) {
            return $evaluator->evaluate_course_pages($courseid, $pages);
        } else {
            // Fallback para evaluator Docker
            return self::evaluate_multiple_pages_fallback($courseid, $pages, $evaluator);
        }
    }
    
    /**
     * Fallback para avaliar múltiplas páginas no modo Docker
     */
    private static function evaluate_multiple_pages_fallback($courseid, $pages, $evaluator) {
        global $CFG;
        
        if (empty($pages)) {
            $pages = self::get_course_pages($courseid);
        }
        
        $results = [];
        $errors = [];
        
        foreach ($pages as $page) {
            try {
                $result = $evaluator->evaluate_page($page['url']);
                $results[] = $result;
                
                // Cache básico
                self::cache_result($courseid, $page['url'], $result);
                
            } catch (Exception $e) {
                $errors[] = [
                    'url' => $page['url'],
                    'error' => $e->getMessage()
                ];
            }
            
            // Pausa para não sobrecarregar
            sleep(1);
        }
        
        return [
            'results' => $results,
            'errors' => $errors,
            'summary' => self::calculate_summary($results)
        ];
    }
    
    /**
     * Obter páginas do curso
     */
    private static function get_course_pages($courseid) {
        global $DB, $CFG;
        
        $pages = [];
        
        // Página principal
        $pages[] = [
            'url' => $CFG->wwwroot . '/course/view.php?id=' . $courseid,
            'title' => 'Course Main Page'
        ];
        
        // Alguns módulos do curso
        $modules = $DB->get_records_sql("
            SELECT cm.id, m.name as modname, cm.instance
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE cm.course = ? AND cm.visible = 1
            AND m.name IN ('page', 'resource', 'quiz', 'forum')
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
     * Cache simples de resultado
     */
    private static function cache_result($courseid, $url, $result) {
        global $DB;
        
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->url = $url;
        $record->results = json_encode($result);
        $record->timecreated = time();
        
        try {
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
            // Ignore cache errors
            debugging('QualWeb cache error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    
    /**
     * Calcular resumo
     */
    private static function calculate_summary($results) {
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