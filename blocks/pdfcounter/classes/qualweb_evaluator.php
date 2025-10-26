<?php
/**
 * QualWeb integration for page accessibility evaluation
 *
 * @package    block_pdfcounter
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Class to handle QualWeb API integration
 */
class qualweb_evaluator {
    
    /** @var string QualWeb API base URL */
    private $api_base_url;
    
    /** @var string API key if required */
    private $api_key;
    
    /**
     * Constructor
     */
    public function __construct() {
        // Configurações do QualWeb - você pode colocar isso em settings
        $this->api_base_url = get_config('block_pdfcounter', 'qualweb_api_url') ?: 'http://localhost:8081/api';
        $this->api_key = get_config('block_pdfcounter', 'qualweb_api_key') ?: '';
    }
    
    /**
     * Evaluate page accessibility using QualWeb
     *
     * @param string $url URL to evaluate
     * @param array $options Additional evaluation options
     * @return array|false Evaluation results or false on failure
     */
    public function evaluate_page($url, $options = []) {
        try {
            // Para uma única página, criamos um monitoring registry temporário
            $monitoring_id = $this->create_monitoring_registry($url, $options);
            
            if (!$monitoring_id) {
                return false;
            }
            
            // Configurar métrica de acessibilidade
            $this->set_accessibility_metric($monitoring_id, 'WCAG21AA');
            
            // Obter webpages do registry
            $webpages = $this->get_monitored_webpages($monitoring_id);
            
            if (empty($webpages)) {
                return false;
            }
            
            // Iniciar avaliação
            $webpage_ids = array_column($webpages, 'id');
            $job_result = $this->start_evaluation($monitoring_id, $webpage_ids);
            
            if (!$job_result) {
                return false;
            }
            
            // Aguardar conclusão (simplificado para demo)
            sleep(5);
            
            // Obter resultados
            $evaluations = $this->get_latest_evaluations($monitoring_id);
            
            if (!empty($evaluations)) {
                return $this->process_api_evaluation_result($evaluations[0]);
            }
            
            return false;
            
        } catch (Exception $e) {
            debugging('QualWeb evaluation failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return false;
        }
    }
    
    /**
     * Evaluate multiple pages in batch
     *
     * @param array $urls Array of URLs to evaluate
     * @return array Results for each URL
     */
    public function evaluate_pages_batch($urls) {
        $results = [];
        
        foreach ($urls as $url) {
            $results[$url] = $this->evaluate_page($url);
        }
        
        return $results;
    }
    
    /**
     * Get evaluation summary for course pages
     *
     * @param int $courseid Course ID
     * @return array Summary data
     */
    public function get_course_page_evaluation($courseid) {
        global $DB, $CFG;
        
        // Obter URLs das páginas do curso
        $course_urls = $this->get_course_page_urls($courseid);
        
        $summary = [
            'total_pages' => count($course_urls),
            'evaluated_pages' => 0,
            'passed_tests' => 0,
            'failed_tests' => 0,
            'warnings' => 0,
            'average_score' => 0,
            'pages' => []
        ];
        
        foreach ($course_urls as $url) {
            $evaluation = $this->evaluate_page($url);
            
            if ($evaluation) {
                $summary['evaluated_pages']++;
                $summary['passed_tests'] += $evaluation['passed'];
                $summary['failed_tests'] += $evaluation['failed'];
                $summary['warnings'] += $evaluation['warnings'];
                $summary['pages'][] = [
                    'url' => $url,
                    'score' => $evaluation['score'],
                    'status' => $evaluation['status'],
                    'issues' => $evaluation['issues_count']
                ];
            }
        }
        
        if ($summary['evaluated_pages'] > 0) {
            $total_tests = $summary['passed_tests'] + $summary['failed_tests'];
            $summary['average_score'] = $total_tests > 0 ? 
                ($summary['passed_tests'] / $total_tests) * 100 : 0;
        }
        
        return $summary;
    }
    
    /**
     * Get URLs of course pages to evaluate
     *
     * @param int $courseid Course ID
     * @return array Array of URLs
     */
    private function get_course_page_urls($courseid) {
        global $DB, $CFG;
        
        $urls = [];
        
        // URL principal do curso
        $urls[] = $CFG->wwwroot . '/course/view.php?id=' . $courseid;
        
        // URLs dos módulos do curso
        $sql = "SELECT cm.id, m.name as modname, cm.instance
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module
                WHERE cm.course = ? AND cm.visible = 1";
        
        $modules = $DB->get_records_sql($sql, [$courseid]);
        
        foreach ($modules as $module) {
            $urls[] = $CFG->wwwroot . '/mod/' . $module->modname . '/view.php?id=' . $module->id;
        }
        
        return $urls;
    }
    
    /**
     * Make API request to QualWeb
     *
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array|false Response data or false on failure
     */
    private function make_api_request($endpoint, $data = []) {
        $url = rtrim($this->api_base_url, '/') . $endpoint;
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        
        // Adicionar API key se configurada
        if (!empty($this->api_key)) {
            $headers[] = 'X-API-Key: ' . $this->api_key;
        }
        
        // Usar cURL diretamente
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        if (!empty($data)) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($response === false || !empty($error)) {
            debugging('QualWeb API request failed: ' . $url . ' - ' . $error, DEBUG_DEVELOPER);
            return false;
        }
        
        if ($http_code !== 200) {
            debugging('QualWeb API HTTP error: ' . $http_code, DEBUG_DEVELOPER);
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            debugging('QualWeb API response decode error: ' . json_last_error_msg(), DEBUG_DEVELOPER);
            return false;
        }
        
        return $decoded;
    }
    
    /**
     * Process evaluation result from QualWeb
     *
     * @param array $raw_result Raw result from QualWeb
     * @return array|false Processed result or false on failure
     */
    private function process_evaluation_result($raw_result) {
        if (!$raw_result) {
            return false;
        }
        
        $result = [
            'url' => $raw_result['url'] ?? '',
            'passed' => 0,
            'failed' => 0,
            'warnings' => 0,
            'score' => 0,
            'status' => 'unknown',
            'issues_count' => 0,
            'issues' => [],
            'timestamp' => time()
        ];
        
        // Processar resultados das regras
        if (isset($raw_result['modules'])) {
            foreach ($raw_result['modules'] as $module) {
                if (isset($module['assertions'])) {
                    foreach ($module['assertions'] as $assertion) {
                        switch ($assertion['verdict']) {
                            case 'passed':
                                $result['passed']++;
                                break;
                            case 'failed':
                                $result['failed']++;
                                $result['issues'][] = [
                                    'code' => $assertion['code'] ?? '',
                                    'description' => $assertion['description'] ?? '',
                                    'severity' => $assertion['severity'] ?? 'medium'
                                ];
                                break;
                            case 'warning':
                                $result['warnings']++;
                                break;
                        }
                    }
                }
            }
        }
        
        // Calcular score e status
        $total_tests = $result['passed'] + $result['failed'];
        if ($total_tests > 0) {
            $result['score'] = ($result['passed'] / $total_tests) * 100;
        }
        
        $result['issues_count'] = count($result['issues']);
        
        // Determinar status baseado no score
        if ($result['score'] >= 80) {
            $result['status'] = 'good';
        } elseif ($result['score'] >= 60) {
            $result['status'] = 'warning';
        } else {
            $result['status'] = 'critical';
        }
        
        return $result;
    }
    
    /**
     * Check if QualWeb service is available
     *
     * @return bool True if available
     */
    public function is_service_available() {
        // Para esta API, vamos tentar listar registries como teste de saúde
        $result = $this->make_api_request('/monitoring/1'); // Teste simples
        return $result !== false;
    }
    
    /**
     * Get QualWeb service status
     *
     * @return array Service status information
     */
    public function get_service_status() {
        $available = $this->is_service_available();
        
        return [
            'available' => $available,
            'version' => 'unknown',
            'uptime' => 0,
            'last_check' => time()
        ];
    }

    /**
     * Create monitoring registry for URL
     *
     * @param string $url URL to monitor
     * @param array $options Additional options
     * @return string|false Registry ID or false on failure
     */
    private function create_monitoring_registry($url, $options = []) {
        global $USER;
        
        $data = [
            'url' => $url,
            'is_mobile' => $options['is_mobile'] ?? false,
            'is_landscape' => $options['is_landscape'] ?? true,
            'display_width' => $options['display_width'] ?? 1920,
            'display_height' => $options['display_height'] ?? 1080,
            'website_name' => $options['website_name'] ?? 'Moodle Course Page',
            'user_id' => $USER->id ?? 1
        ];
        
        $result = $this->make_api_request('/monitoring/crawl', $data);
        
        if ($result && isset($result['monitoring_registry_id'])) {
            return $result['monitoring_registry_id'];
        }
        
        return false;
    }

    /**
     * Set accessibility metric for monitoring registry
     *
     * @param string $monitoring_id Registry ID
     * @param string $metric Accessibility metric (WCAG21AA, etc.)
     * @return bool Success
     */
    private function set_accessibility_metric($monitoring_id, $metric = 'WCAG21AA') {
        $data = [
            'monitoring_registry_id' => $monitoring_id,
            'accessibility_metric' => $metric
        ];
        
        $result = $this->make_api_request('/monitoring/set-accessibility-metric', $data);
        return $result !== false;
    }

    /**
     * Get monitored webpages for registry
     *
     * @param string $monitoring_id Registry ID
     * @return array Array of webpages
     */
    private function get_monitored_webpages($monitoring_id) {
        $result = $this->make_api_request('/monitoring/' . $monitoring_id . '/monitored-webpages');
        
        if ($result && isset($result['monitored_webpages'])) {
            return $result['monitored_webpages'];
        }
        
        return [];
    }

    /**
     * Start evaluation for webpages
     *
     * @param string $monitoring_id Registry ID
     * @param array $webpage_ids Array of webpage IDs
     * @return array|false Job result or false on failure
     */
    private function start_evaluation($monitoring_id, $webpage_ids) {
        $data = [
            'webpage_ids' => $webpage_ids
        ];
        
        $result = $this->make_api_request('/monitoring/' . $monitoring_id . '/evaluate', $data);
        
        if ($result && isset($result['jobId'])) {
            return $result;
        }
        
        return false;
    }

    /**
     * Get latest evaluations for monitoring registry
     *
     * @param string $monitoring_id Registry ID
     * @return array Array of evaluations
     */
    private function get_latest_evaluations($monitoring_id) {
        $result = $this->make_api_request('/monitoring/' . $monitoring_id . '/latest-evaluations');
        
        if ($result && isset($result['evaluations'])) {
            return $result['evaluations'];
        }
        
        return [];
    }

    /**
     * Process evaluation result from QualWeb API
     *
     * @param array $api_result Raw result from API
     * @return array|false Processed result or false on failure
     */
    private function process_api_evaluation_result($api_result) {
        if (!$api_result) {
            return false;
        }
        
        $result = [
            'url' => $api_result['url'] ?? '',
            'passed' => $api_result['passed'] ?? 0,
            'failed' => $api_result['failed'] ?? 0,
            'warnings' => $api_result['warning'] ?? 0,
            'score' => 0,
            'status' => 'unknown',
            'issues_count' => 0,
            'issues' => [],
            'timestamp' => time()
        ];
        
        // Calcular score
        $total_tests = $result['passed'] + $result['failed'];
        if ($total_tests > 0) {
            $result['score'] = ($result['passed'] / $total_tests) * 100;
        }
        
        $result['issues_count'] = $result['failed'];
        
        // Determinar status baseado no score
        if ($result['score'] >= 80) {
            $result['status'] = 'good';
        } elseif ($result['score'] >= 60) {
            $result['status'] = 'warning';
        } else {
            $result['status'] = 'critical';
        }
        
        return $result;
    }
}