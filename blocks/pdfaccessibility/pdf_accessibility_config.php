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
    const EXCLUDED_TESTS = ['Language declared', 'Language detected', 'Passed', 'Failed'];
    
    /**
     * Test configuration mapping - centralizes all test information
     * (Inclui label, link, description, linkText)
     */
    const TEST_CONFIG = [
        'Title' => [
            'label' => 'Document Title Check',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.xdsz0ysx738j',
            'description' => 'Checks if the PDF has a real title set in its properties.',
            'linkText' => 'How to fix?'
        ],
        'Languages match' => [
            'label' => 'Language Consistency Check', 
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.d9ynqih0xdej',
            'description' => 'Ensures the document\'s language setting matches the actual content.',
            'linkText' => 'How to fix?'
        ],
        'PDF only image' => [
            'label' => 'OCR Application Check',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.c077nfc3meb3',
            'description' => 'Checks if the PDF is just a scanned image of text.',
            'linkText' => 'How to fix?'
        ],
        'Links Valid' => [
            'label' => 'Link Validity Check',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.o9p8yrk0s0ni',
            'description' => 'Verifies that all hyperlinks are functional and correctly tagged.',
            'linkText' => 'How to fix?'
        ],
        'Figures with alt text' => [
            'label' => 'Image Alt Text Check',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.9alufce1hsoc',
            'description' => 'Checks if images have "Alternative Text" descriptions.',
            'linkText' => 'How to fix?'
        ],
        'Lists marked as Lists' => [
            'label' => 'List Tagging Check',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.6jc5gvwrm9z',
            'description' => 'Ensures that visual lists are correctly tagged in the code.',
            'linkText' => 'How to fix?'
        ],
        'Table With Headers' => [
            'label' => 'Table Header Check',
            'link' => 'https://docs.google.com/document/d/1I3O_K_A7Uja_Zkm1x16pzUT8wCXbO_UjSMdz0kVMLQw/edit?tab=t.0#heading=h.ik2uvandglez',
            'description' => 'Verifies that data tables have defined headers.',
            'linkText' => 'How to fix?'
        ]
    ];

    /**
     * Gera HTML do info icon + descrição expansível para um teste
     * @param string $testkey
     * @return string
     */
    public static function get_info_icon_html($testkey) {
        if (!isset(self::TEST_CONFIG[$testkey])) return '';
        $desc = htmlspecialchars(self::TEST_CONFIG[$testkey]['description']);
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
            $config[] = [
                'key' => $key,
                'label' => $test['label'],
                'link' => $test['link'],
                'description' => isset($test['description']) ? $test['description'] : ''
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
        if ($testkey === 'PDF only image') return $testvalue === 'PDF with text' ? 'Pass' : 'Fail';
        
        return $testvalue; // Return as-is for other cases
    }
}