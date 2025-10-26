<?php
/**
 * QualWeb Evaluator - Versão SEM Docker
 * Alternativa para servidores que não suportam Docker
 */

class qualweb_evaluator_nodemon {
    
    private $qualweb_cli_path;
    private $temp_dir;
    
    public function __construct() {
        global $CFG;
        
        // Caminho para o CLI do QualWeb (se instalado via npm)
        $this->qualweb_cli_path = get_config('block_pdfcounter', 'qualweb_cli_path') ?: '/usr/local/bin/qw';
        $this->temp_dir = $CFG->tempdir . '/qualweb';
        
        // Criar diretório temporário
        if (!is_dir($this->temp_dir)) {
            mkdir($this->temp_dir, 0755, true);
        }
    }
    
    /**
     * Verificar se QualWeb CLI está disponível
     */
    public function is_service_available() {
        // Verificar se o comando qw existe
        $output = [];
        $return_code = 0;
        exec($this->qualweb_cli_path . ' --version 2>&1', $output, $return_code);
        
        return $return_code === 0;
    }
    
    /**
     * Avaliar uma página usando QualWeb CLI
     */
    public function evaluate_page($url, $options = []) {
        if (!$this->is_service_available()) {
            throw new Exception('QualWeb CLI not available');
        }
        
        // Arquivo temporário para resultados
        $result_file = $this->temp_dir . '/result_' . time() . '.json';
        
        // Construir comando
        $cmd = $this->qualweb_cli_path . ' --url "' . escapeshellarg($url) . '"';
        $cmd .= ' --save-name "' . basename($result_file, '.json') . '"';
        $cmd .= ' --save-dir "' . dirname($result_file) . '"';
        $cmd .= ' --module wcag-techniques';
        
        // Executar comando
        $output = [];
        $return_code = 0;
        exec($cmd . ' 2>&1', $output, $return_code);
        
        if ($return_code !== 0) {
            throw new Exception('QualWeb evaluation failed: ' . implode('\n', $output));
        }
        
        // Ler resultados
        if (!file_exists($result_file)) {
            throw new Exception('QualWeb result file not found');
        }
        
        $json_data = file_get_contents($result_file);
        $results = json_decode($json_data, true);
        
        // Limpar arquivo temporário
        unlink($result_file);
        
        return $this->process_results($results);
    }
    
    /**
     * Processar resultados do QualWeb CLI
     */
    private function process_results($raw_results) {
        if (!isset($raw_results['modules'])) {
            throw new Exception('Invalid QualWeb results format');
        }
        
        $wcag = $raw_results['modules']['wcag-techniques'] ?? [];
        
        $processed = [
            'url' => $raw_results['url'] ?? '',
            'metadata' => [
                'passed' => 0,
                'warning' => 0,
                'failed' => 0,
                'score' => 0
            ],
            'modules' => [
                'wcag-techniques' => [
                    'type' => 'wcag-techniques',
                    'metadata' => [
                        'passed' => count($wcag['assertions']['passed'] ?? []),
                        'warning' => count($wcag['assertions']['warning'] ?? []),
                        'failed' => count($wcag['assertions']['failed'] ?? [])
                    ]
                ]
            ]
        ];
        
        // Calcular score
        $total = $processed['modules']['wcag-techniques']['metadata']['passed'] +
                $processed['modules']['wcag-techniques']['metadata']['warning'] +
                $processed['modules']['wcag-techniques']['metadata']['failed'];
        
        if ($total > 0) {
            $score = ($processed['modules']['wcag-techniques']['metadata']['passed'] / $total) * 100;
            $processed['metadata']['score'] = round($score, 2);
        }
        
        $processed['metadata'] = $processed['modules']['wcag-techniques']['metadata'];
        
        return $processed;
    }
    
    /**
     * Avaliar múltiplas páginas
     */
    public function evaluate_course_pages($courseid, $pages = []) {
        global $COURSE, $CFG;
        
        if (empty($pages)) {
            // Pegar páginas do curso automaticamente
            $pages = $this->get_course_pages($courseid);
        }
        
        $results = [];
        $errors = [];
        
        foreach ($pages as $page) {
            try {
                $url = $page['url'];
                $result = $this->evaluate_page($url);
                $results[] = $result;
                
                // Salvar no cache
                $this->cache_evaluation_result($courseid, $url, $result);
                
            } catch (Exception $e) {
                $errors[] = [
                    'url' => $page['url'],
                    'error' => $e->getMessage()
                ];
            }
            
            // Pequena pausa entre avaliações
            sleep(1);
        }
        
        return [
            'results' => $results,
            'errors' => $errors,
            'summary' => $this->calculate_summary($results)
        ];
    }
    
    /**
     * Obter páginas do curso para avaliar
     */
    private function get_course_pages($courseid) {
        global $DB, $CFG;
        
        $pages = [];
        
        // Página principal do curso
        $pages[] = [
            'url' => $CFG->wwwroot . '/course/view.php?id=' . $courseid,
            'title' => 'Course Main Page'
        ];
        
        // Módulos do curso
        $modules = $DB->get_records_sql("
            SELECT cm.id, cm.module, m.name as modname, 
                   COALESCE(r.name, f.name, p.name, q.name) as title
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            LEFT JOIN {resource} r ON r.id = cm.instance AND m.name = 'resource'
            LEFT JOIN {folder} f ON f.id = cm.instance AND m.name = 'folder'
            LEFT JOIN {page} p ON p.id = cm.instance AND m.name = 'page'
            LEFT JOIN {quiz} q ON q.id = cm.instance AND m.name = 'quiz'
            WHERE cm.course = ? AND cm.visible = 1
            LIMIT 10
        ", [$courseid]);
        
        foreach ($modules as $module) {
            $pages[] = [
                'url' => $CFG->wwwroot . '/mod/' . $module->modname . '/view.php?id=' . $module->id,
                'title' => $module->title ?: 'Module ' . $module->id
            ];
        }
        
        return $pages;
    }
    
    /**
     * Cache dos resultados
     */
    private function cache_evaluation_result($courseid, $url, $result) {
        global $DB;
        
        $record = new stdClass();
        $record->courseid = $courseid;
        $record->url = $url;
        $record->results = json_encode($result);
        $record->timecreated = time();
        
        // Verificar se já existe
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
    }
    
    /**
     * Calcular resumo dos resultados
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
            $meta = $result['metadata'];
            $total_score += $meta['score'];
            $total_passed += $meta['passed'];
            $total_warnings += $meta['warning'];
            $total_failed += $meta['failed'];
        }
        
        return [
            'total_pages' => count($results),
            'average_score' => round($total_score / count($results), 2),
            'total_passed' => $total_passed,
            'total_warnings' => $total_warnings,
            'total_failed' => $total_failed
        ];
    }
}