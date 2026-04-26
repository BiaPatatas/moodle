<?php
/**
 * PDF Accessibility Shared Configuration and Logic
 * This file contains all shared configuration and helper functions
 * for PDF accessibility testing across both blocks
 */

defined('MOODLE_INTERNAL') || die();

class pdf_accessibility_config {
    
    /**
     * Test names that should be excluded from processing
     */
    const EXCLUDED_TESTS = [
        'Language declared',
        'Language detected',
        // Campos agregados/sintéticos do relatório Python
        'Passed',
        'Failed',
        'Non applicable',
        // Campos de detalhe usados apenas para informação de links
        'Links Error Pages',
        'Links Error Detail',
    ];
    
    /**
     * Test configuration mapping - centralizes all test information.
     *
     * The human-readable texts (label, description, "How to fix?") are
     * provided via Moodle language strings so they can be translated.
     * This config only stores a stable key and the help link.
     */
    const TEST_CONFIG = [
        'Title' => [
            'key' => 'title',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.xdsz0ysx738j'
        ],
        'Languages match' => [
            'key' => 'languagesmatch',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.d9ynqih0xdej'
        ],
        // Nome antigo do teste de OCR (para compatibilidade com resultados antigos)
        'PDF only image' => [
            'key' => 'pdfonlyimage',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.c077nfc3meb3'
        ],
        // Novo nome do teste de OCR (Python atual devolve "PDF OCR status")
        // Reutiliza as mesmas strings de idioma/ajuda de "pdfonlyimage".
        'PDF OCR status' => [
            'key' => 'pdfonlyimage',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.c077nfc3meb3'
        ],
        'Links Valid' => [
            'key' => 'linksvalid',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.o9p8yrk0s0ni'
        ],
        'Figures with alt text' => [
            'key' => 'figuresalt',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.9alufce1hsoc'
        ],
        'Lists marked as Lists' => [
            'key' => 'lists',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.6jc5gvwrm9z'
        ],
        'Table With Headers' => [
            'key' => 'tableheaders',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.ik2uvandglez'
        ]
    ];

    /**
     * Get the base key used for language strings for a given test.
     *
     * @param string $testkey Raw test name coming from the Python script.
     * @return string|null Base key (e.g. 'title') or null if not defined.
     */
    protected static function get_test_base_key($testkey) {
        if (!isset(self::TEST_CONFIG[$testkey]['key'])) {
            return null;
        }
        return self::TEST_CONFIG[$testkey]['key'];
    }

    /**
     * Get the localized label for a test.
     *
     * @param string $testkey
     * @return string
     */
    public static function get_test_label($testkey) {
        $base = self::get_test_base_key($testkey);
        if ($base === null) {
            return $testkey;
        }
        return get_string('test_' . $base . '_label', 'block_pdfaccessibility');
    }

    /**
     * Get the localized description for a test (used in info icon and JS).
     *
     * @param string $testkey
     * @return string
     */
    public static function get_test_description($testkey) {
        $base = self::get_test_base_key($testkey);
        if ($base === null) {
            return '';
        }
        return get_string('test_' . $base . '_desc', 'block_pdfaccessibility');
    }

    /**
     * Get the localized "How to fix?" text used for all tests.
     *
     * @return string
     */
    public static function get_test_link_text() {
        return get_string('test_howtofix', 'block_pdfaccessibility');
    }

    /**
     * Get the help link URL for a given test.
     *
     * The default comes from TEST_CONFIG['link'], but if a language
     * string "test_{$base}_url" exists it overrides that value so
     * each site/language can point to its own Moodle resource.
     *
     * @param string $testkey Raw test name from Python.
     * @return string URL to open when clicking "How to fix?".
     */
    public static function get_test_link($testkey) {
        $base = self::get_test_base_key($testkey);
        // Fallback to config link if no base key.
        if ($base === null) {
            return isset(self::TEST_CONFIG[$testkey]['link']) ? self::TEST_CONFIG[$testkey]['link'] : '';
        }

        $identifier = 'test_' . $base . '_url';
        $stringmanager = get_string_manager();
        if ($stringmanager->string_exists($identifier, 'block_pdfaccessibility')) {
            return get_string($identifier, 'block_pdfaccessibility');
        }

        return isset(self::TEST_CONFIG[$testkey]['link']) ? self::TEST_CONFIG[$testkey]['link'] : '';
    }

    /**
     * Gera HTML do info icon + descrição expansível para um teste
     * @param string $testkey
     * @return string
     */
    public static function get_info_icon_html($testkey) {
        if (!isset(self::TEST_CONFIG[$testkey])) return '';
        $description = self::get_test_description($testkey);
        if ($description === '') return '';
        $desc = htmlspecialchars($description);
        $id = 'desc_' . md5($testkey . uniqid());
        $icon = '<span class="pdf-info-icon" style="cursor:pointer; color:#1976d2; margin-left:6px;" onclick="var d=document.getElementById(\''.$id.'\'); if(d.style.display==\'none\'){d.style.display=\'block\';}else{d.style.display=\'none\';}"><i class="fa fa-info-circle"></i></span>';
        $descdiv = '<div id="'.$id.'" class="pdf-info-desc" style="display:none; background:#f8f9fa; border:1px solid #e3e3e3; border-radius:6px; margin:6px 0 8px 0; padding:8px; font-size:0.92em; color:#333;">'.$desc.'</div>';
        return $icon . $descdiv;
    }
    
    /**
     * Determine the status of a test based on its name and value
     * 
     * @param string $testname The name of the test
     * @param mixed $testvalue The value returned by the Python script
     * @return string One of: 'pass', 'fail', 'non applicable', 'pdf not tagged'
     */
    public static function determine_test_status($testname, $testvalue) {
        // Tratamento específico para o teste de OCR
        if ($testname === 'PDF OCR status') {
            if ($testvalue === 'Only Images') {
                // Documento apenas com imagens/fotografias – teste não aplicável
                return 'non applicable';
            }
            if ($testvalue === 'Scanned PDF without OCR') {
                // Documento digitalizado sem OCR – falha
                return 'fail';
            }
            if ($testvalue === 'PDF with text') {
                // PDF com camada de texto/OCR – sucesso
                return 'pass';
            }
        }

        // Handle special string values first
        if ($testvalue === 'Non applicable') {
            return 'non applicable';
        }

        if ($testvalue === 'PDF not tagged') {
            return 'pdf not tagged';
        }

        // Special case for Title test
        if ($testname === 'Title' && $testvalue === 'No Title Found') {
            return 'fail';
        }
        
        // Handle boolean and string boolean values
        if ($testvalue === false || $testvalue === 'false' || empty($testvalue)) {
            return 'fail';
        }
        
        if ($testvalue === true || $testvalue === 'true' || !empty($testvalue)) {
            return 'pass';
        }
        
        // Default for unexpected cases
        return 'fail';
    }
    
    /**
     * Check if a test should be excluded from processing
     * 
     * @param string $testname The name of the test
     * @return bool True if the test should be excluded
     */
    public static function should_exclude_test($testname) {
        return in_array($testname, self::EXCLUDED_TESTS);
    }
    
    /**
     * Calculate applicable tests count (excludes 'non applicable')
     * 
     * @param int $pass_count Number of passed tests
     * @param int $fail_count Number of failed tests
     * @param int $not_tagged_count Number of 'pdf not tagged' tests
     * @return int Total applicable tests
     */
    public static function calculate_applicable_tests($pass_count, $fail_count, $not_tagged_count) {
        return $pass_count + $fail_count + $not_tagged_count;
    }
    
    /**
     * Calculate failed tests count (includes 'pdf not tagged' as failures)
     * 
     * @param int $fail_count Number of failed tests
     * @param int $not_tagged_count Number of 'pdf not tagged' tests
     * @return int Total failed tests
     */
    public static function calculate_failed_tests($fail_count, $not_tagged_count) {
        return $fail_count + $not_tagged_count;
    }
    
    /**
     * Process PDF analysis results and store them in database
     * 
     * @param object $DB Moodle database object
     * @param array $result Analysis results from Python script
     * @param int $pdfid PDF record ID
     * @return bool Success status
     */
    public static function process_and_store_results($DB, $result, $pdfid) {
        foreach ($result as $testname => $testvalue) {
            if (self::should_exclude_test($testname)) {
                continue;
            }
            
            $status = self::determine_test_status($testname, $testvalue);
            
            $testdata = new stdClass();
            $testdata->fileid = $pdfid;
            $testdata->testname = $testname;
            $testdata->result = $status;
            $testdata->errorpages = '';
            $testdata->timecreated = time();
            
            $DB->insert_record('block_pdfaccessibility_test_results', $testdata);
        }
        
        return true;
    }
    
    /**
     * Get test counts for a PDF
     * 
     * @param object $DB Moodle database object
     * @param int $pdfid PDF record ID
     * @return array Array with counts: pass_count, fail_count, nonapplicable_count, not_tagged_count
     */
    public static function get_test_counts($DB, $pdfid) {
        $testresults = $DB->get_records('block_pdfaccessibility_test_results', ['fileid' => $pdfid]);
        
        $counts = [
            'pass_count' => 0,
            'fail_count' => 0,
            'nonapplicable_count' => 0,
            'not_tagged_count' => 0
        ];
        
        foreach ($testresults as $test) {
            switch ($test->result) {
                case 'pass':
                    $counts['pass_count']++;
                    break;
                case 'fail':
                    $counts['fail_count']++;
                    break;
                case 'non applicable':
                    $counts['nonapplicable_count']++;
                    break;
                case 'pdf not tagged':
                    $counts['not_tagged_count']++;
                    break;
            }
        }
        
        return $counts;
    }
    
    /**
     * Calculate progress percentage for a PDF
     * 
     * @param array $counts Test counts from get_test_counts()
     * @return float Progress percentage (0-100)
     */
    public static function calculate_progress($counts) {
        $applicable_tests = self::calculate_applicable_tests(
            $counts['pass_count'],
            $counts['fail_count'], 
            $counts['not_tagged_count']
        );
        
        if ($applicable_tests === 0) {
            return 0;
        }
        
        return ($counts['pass_count'] / $applicable_tests) * 100;
    }
    
    /**
     * Get progress color based on percentage
     * 
     * @param float $progress Progress percentage
     * @return string CSS color code
     */
    public static function get_progress_color($progress) {
        if ($progress < 45) {
            return "#dc3545"; // Red
        } elseif ($progress <= 70) {
            return "#ffc107"; // Yellow
        } else {
            return "#28a745"; // Green
        }
    }
    
    /**
     * Generate JavaScript object with test configuration
     * This can be used to output configuration to JavaScript
     * 
     * @return string JavaScript object literal
     */
    public static function get_js_test_config() {
        $config = [];
        foreach (self::TEST_CONFIG as $key => $test) {
            $label = self::get_test_label($key);
            $description = self::get_test_description($key);
            $linktext = self::get_test_link_text();
            $link = self::get_test_link($key);

            $config[] = [
                'key' => $key,
                'label' => $label,
                'link' => $link,
                'description' => $description,
                'linkText' => $linktext
            ];
        }
        return json_encode($config);
    }
    
    /**
     * Determine check value for JavaScript (mirrors JavaScript logic)
     * 
     * @param string $testkey Test key from Python output
     * @param mixed $testvalue Test value from Python output
     * @return string Display value for frontend
     */
    public static function determine_js_check_value($testkey, $testvalue) {
        if ($testvalue === true) return 'Pass';
        if ($testvalue === 'PDF not tagged') return 'PDF not tagged';
        if ($testvalue === 'Non applicable') return 'Non applicable';
        if ($testvalue === false) return 'Fail';
        
        // Special cases
        if ($testkey === 'Title' && $testvalue === 'No Title Found') return 'Fail';
        if ($testkey === 'Title' && $testvalue !== 'No Title Found') return 'Pass';
        if ($testkey === 'Languages match') return $testvalue ? 'Pass' : 'Fail';
        if ($testkey === 'PDF OCR status') {
            if ($testvalue === 'PDF with text') return 'Pass';
            if ($testvalue === 'Scanned PDF without OCR') return 'Fail';
            if ($testvalue === 'Only Images') return 'Non applicable';
        }
        
        return $testvalue; // Return as-is for other cases
    }
}

/**
 * Regista mensagens de erro/debug do PDF Accessibility em ficheiros dentro
 * de blocks/pdfaccessibility/debug. Usado pelos vários pontos de logging
 * (preview.php, block_pdfaccessibility, etc.).
 *
 * @param string $message Mensagem principal de log.
 * @param array $data Dados adicionais serializados em JSON.
 * @param string $filename Nome do ficheiro de log dentro da pasta debug.
 */
function pdf_accessibility_log_error(string $message, array $data = [], string $filename = 'pdfaccessibility_debug.log'): void {
    global $CFG;

    if (empty($CFG) || empty($CFG->dirroot)) {
        // Fallback para não falhar em contextos sem $CFG apropriado.
        error_log('PdfAccessibility DEBUG (fallback) - ' . $message . ' ' . json_encode($data));
        return;
    }

    // In many production setups, Moodle code folders are read-only.
    // Try plugin debug folder first, then writable runtime folders.
    $candidatedirs = [
        $CFG->dirroot . '/blocks/pdfaccessibility/debug',
        (!empty($CFG->dataroot) ? $CFG->dataroot . '/temp/block_pdfaccessibility/debug' : null),
        sys_get_temp_dir() . '/block_pdfaccessibility_debug',
    ];

    $debugdir = null;
    foreach ($candidatedirs as $dir) {
        if (empty($dir)) {
            continue;
        }
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        if (is_dir($dir) && is_writable($dir)) {
            $debugdir = $dir;
            break;
        }
    }

    if ($debugdir === null) {
        error_log('PdfAccessibility DEBUG (no writable log dir) - ' . $message . ' ' . json_encode($data));
        return;
    }

    // Prevent path traversal in custom filenames.
    $safefilename = basename($filename);
    $logfile = $debugdir . '/' . $safefilename;
    $entry = '[' . date('Y-m-d H:i:s') . "] " . $message;
    if (!empty($data)) {
        $entry .= ' ' . json_encode($data);
    }
    $entry .= PHP_EOL;

    $written = @file_put_contents($logfile, $entry, FILE_APPEND);
    if ($written === false) {
        error_log('PdfAccessibility DEBUG (file_put_contents failed) - ' . $message . ' ' . json_encode($data));
    }
}