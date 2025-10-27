<?php
/**
 * Dashboard class for PDF Accessibility Admin Tool
 */

namespace tool_accessibilitydashboard;

defined('MOODLE_INTERNAL') || die();

class dashboard {

    /**
     * Get faculty-wide accessibility statistics
     */
    public function get_faculty_stats($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Clean up plugin records that belong to courses which are no longer visible.
        // This ensures the dashboard does not show PDFs for hidden/removed courses.
        

        // Check if tables exist
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_pdf_files')) {
            return [
                'courses_with_pdfs' => 0,
                'total_visible_pdfs' => 0,
                'overall_score' => 0,
                'departments_count' => $DB->count_records('course_categories'),
                'accessible_pdfs' => 0,
                'needs_improvement' => 0,
                'recent_pdfs' => 0,
                'total_tests' => 0,
                'passed_tests' => 0,
                'accessibility_score' => 0
            ];
        }

        // Build filter conditions
        $params = [];
        $where_conditions = ['pf.courseid > 1', 'c.visible = 1'];
        
        if ($discipline_id) {
            $where_conditions[] = 'c.id = ?';
            $params[] = $discipline_id;
        } elseif ($course_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($department_id) {
            $where_conditions[] = 'cc2.id = ?';
            $params[] = $department_id;
        }

        // Add joins for department filtering
        $joins = "FROM {block_pdfaccessibility_pdf_files} pf
                  JOIN {course} c ON c.id = pf.courseid";
        
        if ($department_id && !$course_id && !$discipline_id) {
            $joins .= " JOIN {course_categories} cc ON cc.id = c.category
                       LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent";
        }

        $where_clause = implode(' AND ', $where_conditions);

        // Total courses with PDFs (filtered)
        $courses_with_pdfs = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT pf.courseid) 
             $joins
             WHERE $where_clause", $params
        );

        // Total PDFs analyzed (filtered)
        $total_pdfs = $DB->count_records_sql(
            "SELECT COUNT(pf.id) 
             $joins
             WHERE $where_clause", $params
        );

        // Overall accessibility score (filtered)
        $accessibility_score = $this->calculate_overall_score($department_id, $course_id, $discipline_id);

        // Recent activity (last 30 days, visible courses only)
        $recent_pdfs = $DB->count_records_sql(
            "SELECT COUNT(pf.id) 
             FROM {block_pdfaccessibility_pdf_files} pf
             JOIN {course} c ON c.id = pf.courseid
             WHERE pf.timecreated > ? AND pf.courseid > 1 AND c.visible = 1",
            [time() - (30 * 24 * 60 * 60)]
        );

        // Additional stats
        $total_tests = $DB->count_records_sql(
            "SELECT COUNT(tr.id) 
             FROM {block_pdfaccessibility_test_results} tr
             JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
             JOIN {course} c ON c.id = pf.courseid
             WHERE pf.courseid > 1 AND c.visible = 1"
        );

        $passed_tests = $DB->count_records_sql(
            "SELECT COUNT(tr.id) 
             FROM {block_pdfaccessibility_test_results} tr
             JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
             JOIN {course} c ON c.id = pf.courseid
             WHERE pf.courseid > 1 AND c.visible = 1 AND tr.result = 'pass'"
        );

        return [
            'courses_with_pdfs' => $courses_with_pdfs,
            'total_pdfs' => $total_pdfs,
            'accessibility_score' => $accessibility_score,
            'recent_pdfs' => $recent_pdfs,
            'total_tests' => $total_tests,
            'passed_tests' => $passed_tests,
            'failed_tests' => $total_tests - $passed_tests
        ];
    }

    /**
     * Calculate overall accessibility percentage (filtered or global)
     */
    private function calculate_overall_score($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Check if tables exist
        $dbman = $DB->get_manager();
        
        // If trends table exists, use it for current scores (more accurate) - ONLY CURRENT MONTH
        if ($dbman->table_exists('block_pdfcounter_trends')) {
            // Get current month in YYYY-MM format
            $current_month = date('Y-m');
            
            // Get the most recent score from trends table - CURRENT MONTH ONLY
            if ($discipline_id) {
                // Specific discipline - get its exact score
                $result = $DB->get_record_sql(
                    "SELECT AVG(progress_value) as avg_score
                     FROM {block_pdfcounter_trends} t
                     JOIN {course} c ON c.id = t.courseid
                     WHERE c.visible = 1 AND c.id = ? AND t.month = ?", [$discipline_id, $current_month]
                );
            } elseif ($course_id && $department_id) {
                // Department + Course selected - average of ALL disciplines in that course
                $result = $DB->get_record_sql(
                    "SELECT AVG(progress_value) as avg_score
                     FROM {block_pdfcounter_trends} t
                     JOIN {course} c ON c.id = t.courseid
                     WHERE c.visible = 1 AND c.category = ? AND t.month = ?", [$course_id, $current_month]
                );
            } elseif ($course_id) {
                // Only course selected - average of ALL disciplines in that course
                $result = $DB->get_record_sql(
                    "SELECT AVG(progress_value) as avg_score
                     FROM {block_pdfcounter_trends} t
                     JOIN {course} c ON c.id = t.courseid
                     WHERE c.visible = 1 AND c.category = ? AND t.month = ?", [$course_id, $current_month]
                );
            } elseif ($department_id) {
                // Only department selected - average of ALL disciplines in ALL courses of that department
                $result = $DB->get_record_sql(
                    "SELECT AVG(progress_value) as avg_score
                     FROM {block_pdfcounter_trends} t
                     JOIN {course} c ON c.id = t.courseid
                     JOIN {course_categories} cc ON cc.id = c.category
                     LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                     WHERE c.visible = 1 AND cc2.id = ? AND t.month = ?", [$department_id, $current_month]
                );
            } else {
                // No filters - global average
                $result = $DB->get_record_sql(
                    "SELECT AVG(progress_value) as avg_score
                     FROM {block_pdfcounter_trends} t
                     JOIN {course} c ON c.id = t.courseid
                     WHERE c.visible = 1 AND t.courseid > 1 AND t.month = ?", [$current_month]
                );
            }
            
            return $result ? round($result->avg_score, 1) : 0;
        }

        // Fall back to test results calculation
        if (!$dbman->table_exists('block_pdfaccessibility_test_results') || 
            !$dbman->table_exists('block_pdfaccessibility_pdf_files')) {
            return 0;
        }

        // Build filter conditions and queries
        $params = [];
        $where_conditions = ['pf.courseid > 1', 'c.visible = 1'];
        
        // Simple approach: build the WHERE clause based on filters
        if ($discipline_id) {
            $where_conditions[] = 'c.id = ?';
            $params[] = $discipline_id;
            
            $total_tests = $DB->count_records_sql(
                "SELECT COUNT(tr.id) 
                 FROM {block_pdfaccessibility_test_results} tr
                 JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                 JOIN {course} c ON c.id = pf.courseid
                 WHERE " . implode(' AND ', $where_conditions), $params
            );
            
        } elseif ($course_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
            
            $total_tests = $DB->count_records_sql(
                "SELECT COUNT(tr.id) 
                 FROM {block_pdfaccessibility_test_results} tr
                 JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                 JOIN {course} c ON c.id = pf.courseid
                 WHERE " . implode(' AND ', $where_conditions), $params
            );
            
        } elseif ($department_id) {
            $total_tests = $DB->count_records_sql(
                "SELECT COUNT(tr.id) 
                 FROM {block_pdfaccessibility_test_results} tr
                 JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                 JOIN {course} c ON c.id = pf.courseid
                 JOIN {course_categories} cc ON cc.id = c.category
                 LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                 WHERE pf.courseid > 1 AND c.visible = 1 AND cc2.id = ?", [$department_id]
            );
            
        } else {
            // Global - no filters
            $total_tests = $DB->count_records_sql(
                "SELECT COUNT(tr.id) 
                 FROM {block_pdfaccessibility_test_results} tr
                 JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                 JOIN {course} c ON c.id = pf.courseid
                 WHERE " . implode(' AND ', $where_conditions), $params
            );
        }

        if ($total_tests == 0) return 0;

        // Now count passed tests with same logic
        if ($discipline_id) {
            $passed_tests = $DB->count_records_sql(
                "SELECT COUNT(tr.id) 
                 FROM {block_pdfaccessibility_test_results} tr
                 JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                 JOIN {course} c ON c.id = pf.courseid
                 WHERE pf.courseid > 1 AND c.visible = 1 AND c.id = ? AND tr.result = 'pass'", [$discipline_id]
            );
            
        } elseif ($course_id) {
            $passed_tests = $DB->count_records_sql(
                "SELECT COUNT(tr.id) 
                 FROM {block_pdfaccessibility_test_results} tr
                 JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                 JOIN {course} c ON c.id = pf.courseid
                 WHERE pf.courseid > 1 AND c.visible = 1 AND c.category = ? AND tr.result = 'pass'", [$course_id]
            );
            
        } elseif ($department_id) {
            $passed_tests = $DB->count_records_sql(
                "SELECT COUNT(tr.id) 
                 FROM {block_pdfaccessibility_test_results} tr
                 JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                 JOIN {course} c ON c.id = pf.courseid
                 JOIN {course_categories} cc ON cc.id = c.category
                 LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                 WHERE pf.courseid > 1 AND c.visible = 1 AND cc2.id = ? AND tr.result = 'pass'", [$department_id]
            );
            
        } else {
            // Global - no filters
            $passed_tests = $DB->count_records_sql(
                "SELECT COUNT(tr.id) 
                 FROM {block_pdfaccessibility_test_results} tr
                 JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                 JOIN {course} c ON c.id = pf.courseid
                 WHERE pf.courseid > 1 AND c.visible = 1 AND tr.result = 'pass'", []
            );
        }
        
        return round(($passed_tests / $total_tests) * 100, 1);
    }

    /**
     * Get statistics by department/category (only visible courses)
     */
    public function get_departments_stats() {
        global $DB;

        // Check if tables exist
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_pdf_files')) {
            return [];
        }

        $sql = "SELECT 
                    cc.name as department_name,
                    cc.id as category_id,
                    COUNT(DISTINCT c.id) as courses_count,
                    COUNT(DISTINCT pf.id) as pdfs_count,
                    COUNT(DISTINCT pf.userid) as teachers_count,
                    CASE 
                        WHEN COUNT(tr.id) > 0 
                        THEN ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1)
                        ELSE 0.0 
                    END as avg_score,
                    COUNT(CASE WHEN tr.result = 'fail' THEN 1 END) as total_errors,
                    MAX(pf.timecreated) as last_activity
                FROM {course_categories} cc
                LEFT JOIN {course} c ON c.category = cc.id AND c.id > 1 AND c.visible = 1
                LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                WHERE cc.depth > 0
                GROUP BY cc.id, cc.name
                HAVING pdfs_count > 0
                ORDER BY avg_score DESC, pdfs_count DESC";

        $results = $DB->get_records_sql($sql);
        
        // Add additional calculations
        foreach ($results as $dept) {
            $dept->status = $this->get_department_status($dept->avg_score);
            $dept->last_activity_formatted = $dept->last_activity ? date('d/m/Y', $dept->last_activity) : 'N/A';
        }

        return $results;
    }

    /**
     * Get department status based on score
     */
    private function get_department_status($score) {
        if ($score >= 80) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 50) return 'Needs Improvement';
        return 'Critical';
    }

    /**
     * Get most common accessibility errors
     */
    public function get_common_errors() {
        global $DB;

        // Check if tables exist
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_test_results') || 
            !$dbman->table_exists('block_pdfaccessibility_pdf_files')) {
            // Return default error types if tables don't exist
            return [
                (object)['error_type' => 'Título Ausente', 'count' => 0, 'percentage' => 0],
                (object)['error_type' => 'Sem Texto Alternativo', 'count' => 0, 'percentage' => 0],
                (object)['error_type' => 'Links Inválidos', 'count' => 0, 'percentage' => 0],
                (object)['error_type' => 'Sem Cabeçalhos', 'count' => 0, 'percentage' => 0],
                (object)['error_type' => 'Problemas de Idioma', 'count' => 0, 'percentage' => 0]
            ];
        }

        $sql = "SELECT 
                    tr.testname as error_type,
                    COUNT(*) as count,
                    ROUND((COUNT(*) * 100.0 / (
                        SELECT COUNT(*) 
                        FROM {block_pdfaccessibility_test_results} tr2
                        JOIN {block_pdfaccessibility_pdf_files} pf2 ON pf2.id = tr2.fileid
                        JOIN {course} c2 ON c2.id = pf2.courseid
                        WHERE c2.visible = 1 AND c2.id > 1 AND tr2.result = 'fail'
                    )), 1) as percentage
                FROM {block_pdfaccessibility_test_results} tr
                JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                JOIN {course} c ON c.id = pf.courseid
                WHERE c.visible = 1 AND c.id > 1 AND tr.result = 'fail'
                GROUP BY tr.testname
                ORDER BY count DESC
                LIMIT 10";

        $errors = $DB->get_records_sql($sql);
        
        // If no errors found, return default types
        if (empty($errors)) {
            return [
                (object)['error_type' => 'Título Ausente', 'count' => 0, 'percentage' => 0],
                (object)['error_type' => 'Sem Texto Alternativo', 'count' => 0, 'percentage' => 0],
                (object)['error_type' => 'Links Inválidos', 'count' => 0, 'percentage' => 0],
                (object)['error_type' => 'Sem Cabeçalhos', 'count' => 0, 'percentage' => 0],
                (object)['error_type' => 'Problemas de Idioma', 'count' => 0, 'percentage' => 0]
            ];
        }
        
        return array_values($errors);
    }

    /**
     * Get accessibility objectives
     */
    public function get_objectives() {
        global $DB;

        if (!$DB->get_manager()->table_exists('tool_pdfaccessibility_objectives')) {
            return [];
        }

        return $DB->get_records('tool_pdfaccessibility_objectives', null, 'target_date ASC');
    }

    /**
     * Get accessibility trends over time (filtered or global)
     */
    public function get_accessibility_trends($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Check if tables exist
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_pdf_files')) {
            return [];
        }

        // Get data for last 12 months
        $twelve_months_ago = time() - (12 * 30 * 24 * 60 * 60);
        
        // Build filter conditions
        $params = [$twelve_months_ago];
        $where_conditions = ['pf.courseid > 1', 'c.visible = 1', 'pf.timecreated > ?'];
        
        if ($discipline_id) {
            $where_conditions[] = 'c.id = ?';
            $params[] = $discipline_id;
        } elseif ($course_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($department_id) {
            $where_conditions[] = 'cc2.id = ?';
            $params[] = $department_id;
        }

        // Add joins for department filtering
        $joins = "FROM {block_pdfaccessibility_pdf_files} pf
                  JOIN {course} c ON c.id = pf.courseid";
        
        if ($department_id && !$course_id && !$discipline_id) {
            $joins .= " JOIN {course_categories} cc ON cc.id = c.category
                       LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent";
        }

        $joins .= " LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id";
        $where_clause = implode(' AND ', $where_conditions);
        
        // Use different SQL syntax for better compatibility (filtered or global)
        $sql = "SELECT 
                    CONCAT(YEAR(FROM_UNIXTIME(pf.timecreated)), '-', 
                           LPAD(MONTH(FROM_UNIXTIME(pf.timecreated)), 2, '0')) as month,
                    COUNT(DISTINCT pf.id) as total_pdfs,
                    ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score,
                    YEAR(FROM_UNIXTIME(pf.timecreated)) as year,
                    MONTH(FROM_UNIXTIME(pf.timecreated)) as month_num
                $joins
                WHERE $where_clause
                GROUP BY YEAR(FROM_UNIXTIME(pf.timecreated)), MONTH(FROM_UNIXTIME(pf.timecreated))
                ORDER BY year ASC, month_num ASC";
        
        $results = $DB->get_records_sql($sql, $params);
        
        // Fill in missing months with zero values
        $trends = [];
        $current_time = $twelve_months_ago;
        $end_time = time();
        
        while ($current_time <= $end_time) {
            $month_key = date('Y-m', $current_time);
            $month_name = date('M Y', $current_time);
            
            $found = false;
            foreach ($results as $result) {
                if ($result->month === $month_key) {
                    $trends[] = [
                        'month' => $month_name,
                        'month_key' => $month_key,
                        'total_pdfs' => (int)$result->total_pdfs,
                        'avg_score' => (float)($result->avg_score ?? 0)
                    ];
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $trends[] = [
                    'month' => $month_name,
                    'month_key' => $month_key,
                    'total_pdfs' => 0,
                    'avg_score' => 0
                ];
            }
            
            $current_time = strtotime('+1 month', $current_time);
        }
        
        return $trends;
    }

    /**
     * Get courses with filtering
     */
    public function get_courses_filtered($department_id = null, $period_days = null) {
        global $DB;

        $params = [];
        $where_conditions = ['c.id > 1'];

        if ($department_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $department_id;
        }

        if ($period_days && $period_days > 0) {
            $where_conditions[] = 'pf.timecreated > ?';
            $params[] = time() - ($period_days * 24 * 60 * 60);
        }

        $sql = "SELECT 
                    c.id,
                    c.fullname as course_name,
                    cc.name as department_name,
                    COUNT(DISTINCT pf.id) as pdfs_count,
                    ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                WHERE " . implode(' AND ', $where_conditions) . "
                GROUP BY c.id, c.fullname, cc.name
                HAVING pdfs_count > 0
                ORDER BY avg_score DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Create or update an objective
     */
    public function save_objective($data) {
        global $DB, $USER;

        // Ensure table exists
        $this->ensure_objectives_table();

        $objective = new \stdClass();
        $objective->title = $data['title'];
        $objective->description = $data['description'] ?? '';
        $objective->target_percentage = $data['target_percentage'];
        $objective->target_date = strtotime($data['target_date']);
        $objective->timemodified = time();

        if (!empty($data['id'])) {
            // Update existing
            $objective->id = $data['id'];
            return $DB->update_record('tool_pdfaccessibility_objectives', $objective);
        } else {
            // Create new
            $objective->timecreated = time();
            $objective->userid = $USER->id;
            return $DB->insert_record('tool_pdfaccessibility_objectives', $objective);
        }
    }

    /**
     * Delete an objective
     */
    public function delete_objective($id) {
        global $DB;

        return $DB->delete_records('tool_pdfaccessibility_objectives', ['id' => $id]);
    }

    /**
     * Ensure objectives table exists
     */
    private function ensure_objectives_table() {
        global $DB;
        
        $dbman = $DB->get_manager();
        $table_name = 'tool_pdfaccessibility_objectives';
        
        if (!$dbman->table_exists($table_name)) {
            $table = new \xmldb_table($table_name);
            
            // Add fields
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('target_percentage', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null);
            $table->add_field('target_date', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            
            // Add keys
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            
            $dbman->create_table($table);
        }
    }

    /**
     * Calculate objective progress
     */
    public function calculate_objective_progress($objective) {
        // Get current overall score
        $current_score = $this->calculate_overall_score();
        
        // Calculate progress towards target
        if ($objective->target_percentage <= $current_score) {
            return 100; // Target achieved
        }
        
        // Calculate progress percentage
        return round(($current_score / $objective->target_percentage) * 100, 1);
    }

    /**
     * Get top performing courses
     */
    public function get_top_courses($limit = 5) {
        global $DB;

        // Check if tables exist
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_pdf_files') || 
            !$dbman->table_exists('block_pdfaccessibility_test_results')) {
            return [];
        }

        $sql = "SELECT 
                    c.id,
                    c.fullname as course_name,
                    cc.name as department_name,
                    COUNT(DISTINCT pf.id) as pdfs_count,
                    ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                WHERE c.visible = 1 AND c.id > 1
                GROUP BY c.id, c.fullname, cc.name
                HAVING pdfs_count >= 3
                ORDER BY avg_score DESC, pdfs_count DESC";

        return $DB->get_records_sql($sql, [], 0, $limit);
    }

    /**
     * Get courses that need attention
     */
    public function get_attention_courses($limit = 5) {
        global $DB;

        // Check if tables exist
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_pdf_files') || 
            !$dbman->table_exists('block_pdfaccessibility_test_results')) {
            return [];
        }

        $sql = "SELECT 
                    c.id,
                    c.fullname as course_name,
                    cc.name as department_name,
                    COUNT(DISTINCT pf.id) as pdfs_count,
                    ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score,
                    COUNT(CASE WHEN tr.result = 'fail' THEN 1 END) as total_errors
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                WHERE c.visible = 1 AND c.id > 1
                GROUP BY c.id, c.fullname, cc.name
                HAVING avg_score < 50 AND pdfs_count > 0
                ORDER BY avg_score ASC, total_errors DESC";

        return $DB->get_records_sql($sql, [], 0, $limit);
    }

    /**
     * Get recent activity summary
     */
    public function get_recent_activity($days = 7) {
        global $DB;

        $since = time() - ($days * 24 * 60 * 60);

        $activity = [];

        // Recent PDFs added
        $recent_pdfs = $DB->get_records_sql(
            "SELECT pf.*, c.fullname as course_name, u.firstname, u.lastname
             FROM {block_pdfaccessibility_pdf_files} pf
             JOIN {course} c ON c.id = pf.courseid
             JOIN {user} u ON u.id = pf.userid
             WHERE pf.timecreated > ? AND c.visible = 1
             ORDER BY pf.timecreated DESC
             LIMIT 10",
            [$since]
        );

        foreach ($recent_pdfs as $pdf) {
            $activity[] = [
                'type' => 'pdf_added',
                'time' => $pdf->timecreated,
                'description' => "PDF '{$pdf->filename}' adicionado ao curso '{$pdf->course_name}' por {$pdf->firstname} {$pdf->lastname}",
                'icon' => 'fa-file-pdf',
                'color' => 'info'
            ];
        }

        // Sort by time
        usort($activity, function($a, $b) {
            return $b['time'] - $a['time'];
        });

        return array_slice($activity, 0, 10);
    }

    /**
     * Get improvement suggestions
     */
    public function get_improvement_suggestions() {
        global $DB;

        $suggestions = [];

        // Check for departments with low scores
        $low_performing_depts = $DB->get_records_sql(
            "SELECT cc.name as department_name, 
                    ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score
             FROM {course_categories} cc
             JOIN {course} c ON c.category = cc.id AND c.visible = 1
             JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
             LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
             WHERE cc.depth > 0
             GROUP BY cc.id, cc.name
             HAVING avg_score < 60 AND COUNT(pf.id) > 5
             ORDER BY avg_score ASC
             LIMIT 3"
        );

        foreach ($low_performing_depts as $dept) {
            $suggestions[] = [
                'type' => 'department_training',
                'priority' => 'high',
                'title' => "Formação em Acessibilidade - {$dept->department_name}",
                'description' => "Departamento com pontuação baixa ({$dept->avg_score}%). Recomenda-se formação específica.",
                'action' => 'create_training'
            ];
        }

        // Check for common errors that can be easily fixed
        $common_fixable_errors = $DB->get_records_sql(
            "SELECT testname, COUNT(*) as error_count
             FROM {block_pdfaccessibility_test_results} tr
             JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
             JOIN {course} c ON c.id = pf.courseid
             WHERE tr.result = 'fail' AND c.visible = 1
             AND testname IN ('Title', 'PDF only image')
             GROUP BY testname
             ORDER BY error_count DESC
             LIMIT 2"
        );

        foreach ($common_fixable_errors as $error) {
            $error_name = $error->testname === 'Title' ? 'Títulos em falta' : 'PDFs apenas com imagens';
            $suggestions[] = [
                'type' => 'quick_fix',
                'priority' => 'medium',
                'title' => "Correção Rápida: {$error_name}",
                'description' => "{$error->error_count} PDFs com este problema facilmente corrigível.",
                'action' => 'bulk_fix'
            ];
        }

        return $suggestions;
    }

    /**
     * Get total PDFs count (filtered or global)
     */
    public function get_total_pdfs_count($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Ensure we prune invisible course PDFs before counting.
        

        // Check if table exists
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_pdf_files')) {
            return 0;
        }

        // Build filter conditions
        $params = [];
        $where_conditions = ['pf.courseid > 1', 'c.visible = 1'];
        
        if ($discipline_id) {
            $where_conditions[] = 'c.id = ?';
            $params[] = $discipline_id;
        } elseif ($course_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($department_id) {
            $where_conditions[] = 'cc2.id = ?';
            $params[] = $department_id;
        }

        // Add joins for department filtering
        $joins = "FROM {block_pdfaccessibility_pdf_files} pf
                  JOIN {course} c ON c.id = pf.courseid";
        
        if ($department_id && !$course_id && !$discipline_id) {
            $joins .= " JOIN {course_categories} cc ON cc.id = c.category
                       LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent";
        }

        $where_clause = implode(' AND ', $where_conditions);
        
        // Count PDFs (filtered or global)
        $total_pdfs = $DB->count_records_sql("SELECT COUNT(pf.id) $joins WHERE $where_clause", $params);

        return $total_pdfs;
    }

     /**
     * Get total PDF problems (filtered or global)
     */

    public function get_PDFs_problems($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Check if table exists
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_test_results')) {
            return 0;
        }

        // Use a simpler approach: calculate based on current course state
        // Get courses that match the filter
        $courses_to_check = [];
        
        if ($discipline_id) {
            // Specific discipline
            if ($DB->record_exists('course', ['id' => $discipline_id, 'visible' => 1])) {
                $courses_to_check[] = $discipline_id;
            }
        } elseif ($course_id && $department_id) {
            // All disciplines in a specific course
            $courses_to_check = $DB->get_fieldset_select('course', 'id', 'category = ? AND visible = 1 AND id > 1', [$course_id]);
        } elseif ($course_id) {
            // All disciplines in a course category
            $courses_to_check = $DB->get_fieldset_select('course', 'id', 'category = ? AND visible = 1 AND id > 1', [$course_id]);
        } elseif ($department_id) {
            // All disciplines in all courses of a department
            $sql = "SELECT c.id 
                    FROM {course} c
                    JOIN {course_categories} cc ON cc.id = c.category
                    LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                    WHERE c.visible = 1 AND c.id > 1 AND cc2.id = ?";
            $courses_to_check = $DB->get_fieldset_sql($sql, [$department_id]);
        } else {
            // Global - all visible courses
            $courses_to_check = $DB->get_fieldset_select('course', 'id', 'visible = 1 AND id > 1');
        }

        if (empty($courses_to_check)) {
            return 0;
        }

        // Count problems for these courses using current PDF accessibility data
        $total_problems = 0;
        foreach ($courses_to_check as $courseid) {
            // Get current PDFs in this course (similar to pdfcounter logic)
            $sql = "SELECT DISTINCT f.contenthash
                    FROM {course_modules} cm
                    JOIN {modules} m ON m.id = cm.module
                    JOIN {resource} r ON r.id = cm.instance
                    JOIN {context} ctx ON ctx.instanceid = cm.id
                    JOIN {files} f ON f.contextid = ctx.id
                    WHERE cm.course = ? AND cm.deletioninprogress = 0 AND cm.visible = 1
                    AND m.name = 'resource' AND f.component = 'mod_resource'
                    AND f.filearea = 'content' AND f.filename != '.'
                    AND LOWER(f.filename) LIKE '%.pdf'";
            
            $current_pdfs = $DB->get_fieldset_sql($sql, [$courseid]);
            
            if (!empty($current_pdfs)) {
                // Count problems for these current PDFs only
                list($in_sql, $in_params) = $DB->get_in_or_equal($current_pdfs, SQL_PARAMS_NAMED);
                $problems_sql = "SELECT COUNT(tr.id)
                               FROM {block_pdfaccessibility_test_results} tr
                               JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                               WHERE pf.courseid = :courseid
                               AND pf.filehash $in_sql
                               AND tr.result IN ('fail', 'pdf not tagged')";
                
                $params = array_merge(['courseid' => $courseid], $in_params);
                $course_problems = $DB->count_records_sql($problems_sql, $params);
                $total_problems += $course_problems;
            }
        }

        return $total_problems;
    }

    /**
     * Get accessibility evolution data by month (filtered or global)
     * Uses mdl_block_pdfcounter_trends table for monthly accessibility data
     */
    public function get_accessibility_evolution($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Use trends table if it exists, otherwise fall back to test results
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('block_pdfcounter_trends')) {
            return $this->get_evolution_from_trends_table($department_id, $course_id, $discipline_id);
        } else {
            return $this->get_evolution_from_test_results($department_id, $course_id, $discipline_id);
        }
    }

    /**
     * Get evolution data from trends table (with filtering)
     */
    private function get_evolution_from_trends_table($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Calculate academic year start (September)
        $current_year = date('Y');
        $current_month = date('n'); // 1-12
        
        // If we're before September, use previous year's September
        if ($current_month < 9) {
            $academic_start = strtotime("September 1, " . ($current_year - 1));
        } else {
            $academic_start = strtotime("September 1, $current_year");
        }

        // Build SQL query based on filter type
        if ($discipline_id) {
            // Specific discipline - show only its evolution
            $sql = "SELECT 
                        t.month,
                        AVG(t.progress_value) as avg_score
                    FROM {block_pdfcounter_trends} t
                    JOIN {course} c ON c.id = t.courseid
                    WHERE t.timecreated >= ? AND c.visible = 1 AND c.id = ?
                    GROUP BY t.month
                    ORDER BY t.month ASC";
            $results = $DB->get_records_sql($sql, [$academic_start, $discipline_id]);
            
        } elseif ($course_id && $department_id) {
            // Department + Course - average evolution of ALL disciplines in that course
            $sql = "SELECT 
                        t.month,
                        AVG(t.progress_value) as avg_score
                    FROM {block_pdfcounter_trends} t
                    JOIN {course} c ON c.id = t.courseid
                    WHERE t.timecreated >= ? AND c.visible = 1 AND c.category = ?
                    GROUP BY t.month
                    ORDER BY t.month ASC";
            $results = $DB->get_records_sql($sql, [$academic_start, $course_id]);
            
        } elseif ($course_id) {
            // Only course - average evolution of ALL disciplines in that course
            $sql = "SELECT 
                        t.month,
                        AVG(t.progress_value) as avg_score
                    FROM {block_pdfcounter_trends} t
                    JOIN {course} c ON c.id = t.courseid
                    WHERE t.timecreated >= ? AND c.visible = 1 AND c.category = ?
                    GROUP BY t.month
                    ORDER BY t.month ASC";
            $results = $DB->get_records_sql($sql, [$academic_start, $course_id]);
            
        } elseif ($department_id) {
            // Only department - average evolution of ALL disciplines in ALL courses of that department
            $sql = "SELECT 
                        t.month,
                        AVG(t.progress_value) as avg_score
                    FROM {block_pdfcounter_trends} t
                    JOIN {course} c ON c.id = t.courseid
                    JOIN {course_categories} cc ON cc.id = c.category
                    LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                    WHERE t.timecreated >= ? AND c.visible = 1 AND cc2.id = ?
                    GROUP BY t.month
                    ORDER BY t.month ASC";
            $results = $DB->get_records_sql($sql, [$academic_start, $department_id]);
            
        } else {
            // Global - average of all disciplines
            $sql = "SELECT 
                        t.month,
                        AVG(t.progress_value) as avg_score
                    FROM {block_pdfcounter_trends} t
                    JOIN {course} c ON c.id = t.courseid
                    WHERE t.timecreated >= ? AND c.visible = 1 AND t.courseid > 1
                    GROUP BY t.month
                    ORDER BY t.month ASC";
            $results = $DB->get_records_sql($sql, [$academic_start]);
        }

        // Process results to fill missing months and format for chart
        $months = [];
        $scores = [];

        // Generate months from September to current month
        $start_date = $academic_start;
        $current_time = time();
        $temp_date = $start_date;
        
        while ($temp_date <= $current_time) {
            $month_key = date('Y-m', $temp_date);
            $month_label = date('M', $temp_date);
            
            $months[] = $month_label;
            
            // Check if we have data for this month
            $found = false;
            foreach ($results as $result) {
                if ($result->month === $month_key) {
                    $scores[] = round($result->avg_score, 1);
                    $found = true;
                    break;
                }
            }
            
            // If no data for this month, use previous month's score or 0
            if (!$found) {
                $scores[] = count($scores) > 0 ? end($scores) : 0;
            }
            
            $temp_date = strtotime('+1 month', $temp_date);
        }

        return [
            'months' => $months,
            'scores' => $scores,
            'current_score' => count($scores) > 0 ? end($scores) : 0,
            'previous_score' => count($scores) > 1 ? $scores[count($scores) - 2] : 0
        ];
    }

    /**
     * Get evolution data from real test results when trends table doesn't exist
     */
    private function get_evolution_from_test_results($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Check if test results table exists
        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_test_results') || 
            !$dbman->table_exists('block_pdfaccessibility_pdf_files')) {
            return $this->get_dummy_evolution_data($department_id, $course_id, $discipline_id);
        }

        // Get data for last 12 months from PDF creation dates
        $twelve_months_ago = time() - (12 * 30 * 24 * 60 * 60);
        
        // Build SQL query based on filter type
        if ($discipline_id) {
            $sql = "SELECT 
                        CONCAT(YEAR(FROM_UNIXTIME(pf.timecreated)), '-', 
                               LPAD(MONTH(FROM_UNIXTIME(pf.timecreated)), 2, '0')) as month,
                        COUNT(DISTINCT pf.id) as total_pdfs,
                        ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score,
                        YEAR(FROM_UNIXTIME(pf.timecreated)) as year,
                        MONTH(FROM_UNIXTIME(pf.timecreated)) as month_num
                    FROM {block_pdfaccessibility_pdf_files} pf
                    JOIN {course} c ON c.id = pf.courseid
                    LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                    WHERE pf.courseid > 1 AND c.visible = 1 AND pf.timecreated > ? AND c.id = ?
                    GROUP BY YEAR(FROM_UNIXTIME(pf.timecreated)), MONTH(FROM_UNIXTIME(pf.timecreated))
                    ORDER BY year ASC, month_num ASC";
            $results = $DB->get_records_sql($sql, [$twelve_months_ago, $discipline_id]);
            
        } elseif ($course_id) {
            $sql = "SELECT 
                        CONCAT(YEAR(FROM_UNIXTIME(pf.timecreated)), '-', 
                               LPAD(MONTH(FROM_UNIXTIME(pf.timecreated)), 2, '0')) as month,
                        COUNT(DISTINCT pf.id) as total_pdfs,
                        ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score,
                        YEAR(FROM_UNIXTIME(pf.timecreated)) as year,
                        MONTH(FROM_UNIXTIME(pf.timecreated)) as month_num
                    FROM {block_pdfaccessibility_pdf_files} pf
                    JOIN {course} c ON c.id = pf.courseid
                    LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                    WHERE pf.courseid > 1 AND c.visible = 1 AND pf.timecreated > ? AND c.category = ?
                    GROUP BY YEAR(FROM_UNIXTIME(pf.timecreated)), MONTH(FROM_UNIXTIME(pf.timecreated))
                    ORDER BY year ASC, month_num ASC";
            $results = $DB->get_records_sql($sql, [$twelve_months_ago, $course_id]);
            
        } elseif ($department_id) {
            $sql = "SELECT 
                        CONCAT(YEAR(FROM_UNIXTIME(pf.timecreated)), '-', 
                               LPAD(MONTH(FROM_UNIXTIME(pf.timecreated)), 2, '0')) as month,
                        COUNT(DISTINCT pf.id) as total_pdfs,
                        ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score,
                        YEAR(FROM_UNIXTIME(pf.timecreated)) as year,
                        MONTH(FROM_UNIXTIME(pf.timecreated)) as month_num
                    FROM {block_pdfaccessibility_pdf_files} pf
                    JOIN {course} c ON c.id = pf.courseid
                    JOIN {course_categories} cc ON cc.id = c.category
                    LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                    LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                    WHERE pf.courseid > 1 AND c.visible = 1 AND pf.timecreated > ? AND cc2.id = ?
                    GROUP BY YEAR(FROM_UNIXTIME(pf.timecreated)), MONTH(FROM_UNIXTIME(pf.timecreated))
                    ORDER BY year ASC, month_num ASC";
            $results = $DB->get_records_sql($sql, [$twelve_months_ago, $department_id]);
            
        } else {
            // Global - no filters
            $sql = "SELECT 
                        CONCAT(YEAR(FROM_UNIXTIME(pf.timecreated)), '-', 
                               LPAD(MONTH(FROM_UNIXTIME(pf.timecreated)), 2, '0')) as month,
                        COUNT(DISTINCT pf.id) as total_pdfs,
                        ROUND(AVG(CASE WHEN tr.result = 'pass' THEN 100.0 ELSE 0.0 END), 1) as avg_score,
                        YEAR(FROM_UNIXTIME(pf.timecreated)) as year,
                        MONTH(FROM_UNIXTIME(pf.timecreated)) as month_num
                    FROM {block_pdfaccessibility_pdf_files} pf
                    JOIN {course} c ON c.id = pf.courseid
                    LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                    WHERE pf.courseid > 1 AND c.visible = 1 AND pf.timecreated > ?
                    GROUP BY YEAR(FROM_UNIXTIME(pf.timecreated)), MONTH(FROM_UNIXTIME(pf.timecreated))
                    ORDER BY year ASC, month_num ASC";
            $results = $DB->get_records_sql($sql, [$twelve_months_ago]);
        }

        // Fill in missing months and format for chart
        $months = [];
        $scores = [];
        $start_time = $twelve_months_ago;
        $current_time = time();
        $temp_time = $start_time;

        while ($temp_time <= $current_time) {
            $month_key = date('Y-m', $temp_time);
            $month_label = date('M', $temp_time);
            
            $months[] = $month_label;
            
            // Check if we have data for this month
            $found = false;
            foreach ($results as $result) {
                if ($result->month === $month_key) {
                    $scores[] = (float)$result->avg_score;
                    $found = true;
                    break;
                }
            }
            
            // If no data for this month, use previous month's score or 0
            if (!$found) {
                $scores[] = count($scores) > 0 ? end($scores) : 0;
            }
            
            $temp_time = strtotime('+1 month', $temp_time);
        }

        return [
            'months' => $months,
            'scores' => $scores,
            'current_score' => count($scores) > 0 ? end($scores) : 0,
            'previous_score' => count($scores) > 1 ? $scores[count($scores) - 2] : 0
        ];
    }

    /**
     * Get dummy evolution data when database tables don't exist
     */
    private function get_dummy_evolution_data($department_id = null, $course_id = null, $discipline_id = null) {
        $months = [];
        $scores = [];
        
        // Calculate academic year start (September)
        $current_year = date('Y');
        $current_month = date('n'); // 1-12
        
        // If we're before September, use previous year's September
        if ($current_month < 9) {
            $academic_start = strtotime("September 1, " . ($current_year - 1));
        } else {
            $academic_start = strtotime("September 1, $current_year");
        }
        
        // Generate realistic progression data from September
        $temp_date = $academic_start;
        $current_time = time();
        $month_count = 0;
        
        while ($temp_date <= $current_time) {
            $month_label = date('M', $temp_date);
            $months[] = $month_label;
            
            // Progressive improvement starting from September
            $base_score = 20 + ($month_count * 3) + rand(-2, 3);
            $scores[] = min(100, max(0, $base_score)); // Keep between 0-100
            
            $temp_date = strtotime('+1 month', $temp_date);
            $month_count++;
        }

        return [
            'months' => $months,
            'scores' => $scores,
            'current_score' => end($scores),
            'previous_score' => count($scores) > 1 ? $scores[count($scores) - 2] : 0
        ];
    }

    /**
     * Get all departments (top-level categories) for filter dropdown
     */
    public function get_departments_for_filter() {
        global $DB;

        $sql = "SELECT CONCAT('dept_', cc.id) as unique_id, cc.id, cc.name
                FROM {course_categories} cc
                WHERE EXISTS (
                    SELECT 1 FROM {course_categories} cc2
                    JOIN {course} c ON c.category = cc2.id
                    JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                    WHERE cc2.parent = cc.id AND c.visible = 1 AND c.id > 1
                )
                AND cc.depth = 1
                ORDER BY cc.name ASC";

        $results = $DB->get_records_sql($sql);
        
        $departments = [];
        foreach ($results as $result) {
            $departments[] = (object)[
                'id' => $result->id,
                'name' => $result->name
            ];
        }
        
        return $departments;
    }

    /**
     * Get courses (sub-categories) within a department
     */
    public function get_courses_for_filter($department_id = null) {
        global $DB;

        $params = [];
        $where_dept = '';
        
        if ($department_id) {
            $where_dept = 'AND cc.parent = ?';
            $params[] = $department_id;
        }

        $sql = "SELECT CONCAT('course_', cc.id) as unique_id, cc.id, cc.name
                FROM {course_categories} cc
                WHERE EXISTS (
                    SELECT 1 FROM {course} c
                    JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                    WHERE c.category = cc.id AND c.visible = 1 AND c.id > 1
                )
                AND cc.depth = 2 $where_dept
                ORDER BY cc.name ASC";

        $results = $DB->get_records_sql($sql, $params);
        
        $courses = [];
        foreach ($results as $result) {
            $courses[] = (object)[
                'id' => $result->id,
                'name' => $result->name
            ];
        }
        
        return $courses;
    }

    /**
     * Get disciplines (actual courses) within a course category
     */
    public function get_disciplines_for_filter($course_id = null) {
        global $DB;

        $params = [];
        $where_course = '';
        
        if ($course_id) {
            $where_course = 'AND c.category = ?';
            $params[] = $course_id;
        }

        $sql = "SELECT CONCAT('disc_', c.id) as unique_id, c.id, c.fullname as name, c.category
                FROM {course} c
                WHERE EXISTS (
                    SELECT 1 FROM {block_pdfaccessibility_pdf_files} pf 
                    WHERE pf.courseid = c.id
                )
                AND c.visible = 1 AND c.id > 1 $where_course
                ORDER BY c.fullname ASC";

        $results = $DB->get_records_sql($sql, $params);
        
        $disciplines = [];
        foreach ($results as $result) {
            $disciplines[] = (object)[
                'id' => $result->id,
                'name' => $result->name,
                'category' => $result->category
            ];
        }
        
        return $disciplines;
    }

    /**
     * Get filtered data for the table
     */
    public function get_filtered_data($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Check if trends table exists, use it for more accurate scores
        $dbman = $DB->get_manager();
        if ($dbman->table_exists('block_pdfcounter_trends')) {
            return $this->get_filtered_data_from_trends($department_id, $course_id, $discipline_id);
        }

        // Fall back to test results method
        $params = [];
        $where_conditions = ['c.visible = 1', 'c.id > 1'];

        if ($discipline_id) {
            // Filter by specific discipline
            $where_conditions[] = 'c.id = ?';
            $params[] = $discipline_id;
        } elseif ($course_id && $department_id) {
            // Department + Course = all disciplines in that course
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($course_id) {
            // Only course = all disciplines in that course
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($department_id) {
            // Only department = all disciplines in all courses of that department
            $where_conditions[] = 'cc2.id = ?';
            $params[] = $department_id;
        }

        $sql = "SELECT 
                    CONCAT('row_', c.id) as unique_id,
                    COALESCE(cc2.name, cc.name) as department,
                    cc.name as course,
                    c.fullname as discipline,
                    COUNT(DISTINCT pf.id) as pdfs_count,
                    CASE 
                        WHEN COUNT(tr.id) > 0 
                        THEN ROUND((COUNT(CASE WHEN tr.result = 'pass' THEN 1 END) * 100.0) / 
                             COUNT(CASE WHEN tr.result IN ('pass', 'fail', 'pdf not tagged') THEN 1 END), 1)
                        ELSE 0.0 
                    END as score,
                    CASE 
                        WHEN ROUND((COUNT(CASE WHEN tr.result = 'pass' THEN 1 END) * 100.0) / 
                             NULLIF(COUNT(CASE WHEN tr.result IN ('pass', 'fail', 'pdf not tagged') THEN 1 END), 0), 1) >= 70 
                        THEN 'Good'
                        WHEN ROUND((COUNT(CASE WHEN tr.result = 'pass' THEN 1 END) * 100.0) / 
                             NULLIF(COUNT(CASE WHEN tr.result IN ('pass', 'fail', 'pdf not tagged') THEN 1 END), 0), 1) >= 45 
                        THEN 'Warning'
                        ELSE 'Critical' 
                    END as status
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                LEFT JOIN {block_pdfaccessibility_test_results} tr ON tr.fileid = pf.id
                WHERE " . implode(' AND ', $where_conditions) . "
                GROUP BY cc.id, cc.name, c.id, c.fullname, cc2.id, cc2.name
                ORDER BY score DESC, pdfs_count DESC";

        $results = $DB->get_records_sql($sql, $params);

        // Convert to simple array
        $data = [];
        foreach ($results as $result) {
            $data[] = (object)[
                'department' => $result->department,
                'course' => $result->course,
                'discipline' => $result->discipline,
                'pdfs_count' => $result->pdfs_count,
                'score' => $result->score,
                'status' => $result->status
            ];
        }

        return $data;
    }

    /**
     * Get filtered data using trends table (more accurate scores)
     */
    private function get_filtered_data_from_trends($department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Get current month in YYYY-MM format for filtering trends
        $current_month = date('Y-m');

        // Base query using trends table for accurate scores - CURRENT MONTH ONLY
        if ($discipline_id) {
            // Specific discipline
            $sql = "SELECT 
                        CONCAT('row_', c.id) as unique_id,
                        COALESCE(cc2.name, cc.name) as department,
                        cc.name as course,
                        c.fullname as discipline,
                        COUNT(DISTINCT pf.id) as pdfs_count,
                        COALESCE(AVG(t.progress_value), 0) as score
                    FROM {course} c
                    JOIN {course_categories} cc ON cc.id = c.category
                    LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                    LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                    LEFT JOIN {block_pdfcounter_trends} t ON t.courseid = c.id AND t.month = ?
                    WHERE c.visible = 1 AND c.id = ?
                    GROUP BY c.id, c.fullname, cc.id, cc.name, cc2.id, cc2.name";
            $params = [$current_month, $discipline_id];
            
        } elseif ($course_id && $department_id) {
            // Department + Course = all disciplines in that course
            $sql = "SELECT 
                        CONCAT('row_', c.id) as unique_id,
                        COALESCE(cc2.name, cc.name) as department,
                        cc.name as course,
                        c.fullname as discipline,
                        COUNT(DISTINCT pf.id) as pdfs_count,
                        COALESCE(AVG(t.progress_value), 0) as score
                    FROM {course} c
                    JOIN {course_categories} cc ON cc.id = c.category
                    LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                    LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                    LEFT JOIN {block_pdfcounter_trends} t ON t.courseid = c.id AND t.month = ?
                    WHERE c.visible = 1 AND c.id > 1 AND c.category = ?
                    GROUP BY c.id, c.fullname, cc.id, cc.name, cc2.id, cc2.name";
            $params = [$current_month, $course_id];
            
        } elseif ($course_id) {
            // Only course = all disciplines in that course
            $sql = "SELECT 
                        CONCAT('row_', c.id) as unique_id,
                        COALESCE(cc2.name, cc.name) as department,
                        cc.name as course,
                        c.fullname as discipline,
                        COUNT(DISTINCT pf.id) as pdfs_count,
                        COALESCE(AVG(t.progress_value), 0) as score
                    FROM {course} c
                    JOIN {course_categories} cc ON cc.id = c.category
                    LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                    LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                    LEFT JOIN {block_pdfcounter_trends} t ON t.courseid = c.id AND t.month = ?
                    WHERE c.visible = 1 AND c.id > 1 AND c.category = ?
                    GROUP BY c.id, c.fullname, cc.id, cc.name, cc2.id, cc2.name";
            $params = [$current_month, $course_id];
            
        } elseif ($department_id) {
            // Only department = all disciplines in all courses of that department
            $sql = "SELECT 
                        CONCAT('row_', c.id) as unique_id,
                        COALESCE(cc2.name, cc.name) as department,
                        cc.name as course,
                        c.fullname as discipline,
                        COUNT(DISTINCT pf.id) as pdfs_count,
                        COALESCE(AVG(t.progress_value), 0) as score
                    FROM {course} c
                    JOIN {course_categories} cc ON cc.id = c.category
                    LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                    LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                    LEFT JOIN {block_pdfcounter_trends} t ON t.courseid = c.id AND t.month = ?
                    WHERE c.visible = 1 AND c.id > 1 AND cc2.id = ?
                    GROUP BY c.id, c.fullname, cc.id, cc.name, cc2.id, cc2.name";
            $params = [$current_month, $department_id];
            
        } else {
            // Global - all disciplines
            $sql = "SELECT 
                        CONCAT('row_', c.id) as unique_id,
                        COALESCE(cc2.name, cc.name) as department,
                        CASE 
                            WHEN cc2.name IS NOT NULL THEN cc.name
                            ELSE 'Direct'
                        END as course,
                        c.fullname as discipline,
                        COUNT(DISTINCT pf.id) as pdfs_count,
                        COALESCE(AVG(t.progress_value), 0) as score
                    FROM {course} c
                    JOIN {course_categories} cc ON cc.id = c.category
                    LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                    LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                    LEFT JOIN {block_pdfcounter_trends} t ON t.courseid = c.id AND t.month = ?
                    WHERE c.visible = 1 AND c.id > 1
                    GROUP BY c.id, c.fullname, cc.id, cc.name, cc2.id, cc2.name";
            $params = [$current_month];
        }

        $results = $DB->get_records_sql($sql, $params);

        // Convert to simple array with status calculation based on trends score
        $data = [];
        foreach ($results as $result) {
            // Only include courses that have PDFs
            if ($result->pdfs_count > 0) {
                $score = round($result->score, 1);
                
                // Calculate status based on score
                if ($score >= 70) {
                    $status = 'Good';
                } elseif ($score >= 45) {
                    $status = 'Warning';
                } else {
                    $status = 'Critical';
                }
                
                $data[] = (object)[
                    'department' => $result->department,
                    'course' => $result->course,
                    'discipline' => $result->discipline,
                    'pdfs_count' => $result->pdfs_count,
                    'score' => $score,
                    'status' => $status
                ];
            }
        }

        return $data;
    }

    /**
     * Debug method to check courses per department
     */
    public function debug_courses_by_department($department_id) {
        global $DB;

        $sql = "SELECT c.id, c.fullname, c.category, cc.name as category_name
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                WHERE c.category = ? AND c.visible = 1 AND c.id > 1
                ORDER BY c.fullname";

        return $DB->get_records_sql($sql, [$department_id]);
    }

    /**
     * Get courses with best accessibility scores
     */
    public function get_best_courses($limit = 4, $department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Get current month for trends filtering
        $current_month = date('Y-m');
        
        // Build WHERE conditions and params based on filters
        $where_conditions = ['c.visible = 1', 'c.id > 1'];
        $params = [];
        
        // Add current month parameter first (for the JOIN)
        $params[] = $current_month;
        
        if ($discipline_id) {
            $where_conditions[] = 'c.id = ?';
            $params[] = $discipline_id;
        } elseif ($course_id && $department_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($course_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($department_id) {
            $where_conditions[] = 'cc2.id = ?';
            $params[] = $department_id;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = "SELECT 
                    CONCAT('best_', c.id) as unique_id,
                    c.fullname as course_name,
                    COALESCE(cc2.name, cc.name) as department,
                    COUNT(DISTINCT pf.id) as pdfs_count,
                    COALESCE(AVG(t.progress_value), 0) as score
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                LEFT JOIN {block_pdfcounter_trends} t ON t.courseid = c.id AND t.month = ?
                WHERE $where_clause
                GROUP BY c.id, c.fullname, cc.name, cc2.name
                HAVING score > 0
                ORDER BY score DESC, pdfs_count DESC
                LIMIT " . intval($limit);

        $results = $DB->get_records_sql($sql, $params);
        
        $courses = [];
        foreach ($results as $result) {
            $courses[] = (object)[
                'course_name' => $result->course_name,
                'department' => $result->department,
                'pdfs_count' => $result->pdfs_count,
                'score' => $result->score
            ];
        }
        
        return $courses;
    }

    /**
     * Get courses with worst accessibility scores
     */
    public function get_worst_courses($limit = 4, $department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Get current month for trends filtering
        $current_month = date('Y-m');
        
        // Build WHERE conditions and params based on filters
        $where_conditions = ['c.visible = 1', 'c.id > 1'];
        $params = [];
        
        // Add current month parameter first (for the JOIN)
        $params[] = $current_month;
        
        if ($discipline_id) {
            $where_conditions[] = 'c.id = ?';
            $params[] = $discipline_id;
        } elseif ($course_id && $department_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($course_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($department_id) {
            $where_conditions[] = 'cc2.id = ?';
            $params[] = $department_id;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = "SELECT 
                    CONCAT('worst_', c.id) as unique_id,
                    c.fullname as course_name,
                    COALESCE(cc2.name, cc.name) as department,
                    COUNT(DISTINCT pf.id) as pdfs_count,
                    COALESCE(AVG(t.progress_value), 0) as score
                FROM {course} c
                JOIN {course_categories} cc ON cc.id = c.category
                LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                LEFT JOIN {block_pdfaccessibility_pdf_files} pf ON pf.courseid = c.id
                LEFT JOIN {block_pdfcounter_trends} t ON t.courseid = c.id AND t.month = ?
                WHERE $where_clause
                GROUP BY c.id, c.fullname, cc.name, cc2.name
                HAVING score > 0
                ORDER BY score ASC, pdfs_count DESC
                LIMIT " . intval($limit);

        $results = $DB->get_records_sql($sql, $params);
        
        $courses = [];
        foreach ($results as $result) {
            $courses[] = (object)[
                'course_name' => $result->course_name,
                'department' => $result->department,
                'pdfs_count' => $result->pdfs_count,
                'score' => $result->score
            ];
        }
        
        return $courses;
    }

    /**
     * Get most common accessibility errors/failed tests
     */
    public function get_most_common_errors($limit = 4, $department_id = null, $course_id = null, $discipline_id = null) {
        global $DB;

        // Build WHERE conditions based on filters
        $where_conditions = ['c.visible = 1', 'c.id > 1'];
        $params = [];
        
        if ($discipline_id) {
            $where_conditions[] = 'c.id = ?';
            $params[] = $discipline_id;
        } elseif ($course_id && $department_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($course_id) {
            $where_conditions[] = 'c.category = ?';
            $params[] = $course_id;
        } elseif ($department_id) {
            $where_conditions[] = 'cc2.id = ?';
            $params[] = $department_id;
        }

        $where_clause = implode(' AND ', $where_conditions);

        $sql = "SELECT 
                    CONCAT('error_', ROW_NUMBER() OVER (ORDER BY COUNT(CASE WHEN tr.result IN ('fail', 'pdf not tagged') THEN 1 END) DESC)) as unique_id,
                    tr.testname as error_type,
                    COUNT(CASE WHEN tr.result IN ('fail', 'pdf not tagged') THEN 1 END) as failure_count,
                    COUNT(*) as total_tests,
                    ROUND((COUNT(CASE WHEN tr.result IN ('fail', 'pdf not tagged') THEN 1 END) * 100.0) / COUNT(*), 1) as percentage
                FROM {block_pdfaccessibility_test_results} tr
                JOIN {block_pdfaccessibility_pdf_files} pf ON pf.id = tr.fileid
                JOIN {course} c ON c.id = pf.courseid
                JOIN {course_categories} cc ON cc.id = c.category
                LEFT JOIN {course_categories} cc2 ON cc2.id = cc.parent
                WHERE $where_clause
                GROUP BY tr.testname
                HAVING failure_count > 0
                ORDER BY failure_count DESC, percentage DESC
                LIMIT " . intval($limit);

        $results = $DB->get_records_sql($sql, $params);
        
        $errors = [];
        foreach ($results as $result) {
            $errors[] = (object)[
                'error_type' => $result->error_type,
                'failure_count' => $result->failure_count,
                'total_tests' => $result->total_tests,
                'percentage' => $result->percentage
            ];
        }
        
        return $errors;
    }

    /**
     * Remove plugin pdf records that belong to courses which are not visible or missing.
     * This keeps the dashboard in sync: if a course is hidden/removed, its PDFs are
     * no longer relevant and their plugin rows should be cleaned up.
     */
    private function prune_invisible_course_pdfs() {
        global $DB;

        $dbman = $DB->get_manager();
        if (!$dbman->table_exists('block_pdfaccessibility_pdf_files')) {
            return;
        }

        // Find pdf records where the course is missing or not visible
        $sql = "SELECT pf.id FROM {block_pdfaccessibility_pdf_files} pf LEFT JOIN {course} c ON c.id = pf.courseid WHERE c.id IS NULL OR c.visible = 0";
        $ids = $DB->get_fieldset_sql($sql);
        if (empty($ids)) {
            return;
        }

        try {
            list($in_sql, $params) = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            // Delete associated test results
            $DB->delete_records_select('block_pdfaccessibility_test_results', 'fileid ' . $in_sql, $params);
            // Delete the pdf file records
            $DB->delete_records_select('block_pdfaccessibility_pdf_files', 'id ' . $in_sql, $params);
            debugging('prune_invisible_course_pdfs: removed orphaned pdf ids: ' . implode(',', $ids), DEBUG_DEVELOPER);
        } catch (\dml_exception $e) {
            debugging('prune_invisible_course_pdfs: DB error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}