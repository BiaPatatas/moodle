<?php

use tool_accessibilitydashboard\dashboard;
/**
 * PDF Accessibility Dashboard - Simple Version
 */


require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/admin/tool/accessibilityDashboard/lib.php');

require_login();
admin_externalpage_setup('tool_accessibilitydashboard');

$PAGE->set_url('/admin/tool/accessibilitydashboard/index.php');
$PAGE->set_title(get_string('dashboard_title', 'tool_accessibilitydashboard'));
$PAGE->set_heading(get_string('dashboard_title', 'tool_accessibilitydashboard'));
$PAGE->requires->css('/admin/tool/accessibilitydashboard/index.css');

// --- ROLE LOGIC ---
$isadmin = is_siteadmin();
$systemcontext = context_system::instance();
$usercourses = enrol_get_users_courses($USER->id, true, 'id,category');
$managercourse = null;
$managercategory = null;
$is_manager = false;
if (!$isadmin) {
    foreach ($usercourses as $c) {
        $coursecontext = context_course::instance($c->id);
        if (has_capability('moodle/course:update', $coursecontext, $USER->id) && has_capability('moodle/site:config', $systemcontext) == false) {
            $managercourse = $c->id;
            $managercategory = $c->category;
            $is_manager = true;
            break;
        }
    }
}

// Se não for admin nem manager, bloquear acesso
if (!$isadmin && !$is_manager) {
    echo $OUTPUT->header();
    echo '<div class="alert alert-danger" style="margin:2em;">' .
        get_string('access_restricted', 'tool_accessibilitydashboard') . '</div>';
    echo $OUTPUT->footer();
    exit;
}

// --- DEBUG LOGGING ---
$debug_start_time = microtime(true);
$debug_start_memory = memory_get_usage();
// Prepare debug info array
$debug_info = [];
$debug_info['start_time'] = date('Y-m-d H:i:s');
$debug_info['start_memory'] = $debug_start_memory;
echo $OUTPUT->header();

// Create dashboard instance
$dashboard = new \tool_accessibilitydashboard\dashboard();

// Get filter parameters
if ($isadmin) {
    $department_id = optional_param('department', null, PARAM_INT);
    $course_id = optional_param('course', null, PARAM_INT);
    $discipline_id = optional_param('discipline', null, PARAM_INT);
} else if ($is_manager && $managercourse && $managercategory) {
    $department_id = $managercategory;
    $course_id = $managercourse;
    $discipline_id = optional_param('discipline', null, PARAM_INT);
}
// Add filter info to debug
$debug_info['filters'] = [
     'department_id' => $department_id,
     'course_id' => $course_id,
     'discipline_id' => $discipline_id,
     'page' => $page
];
$page = optional_param('page', 1, PARAM_INT); // Pagination parameter

// Get all data (filtered or global based on parameters)
$stats = $dashboard->get_faculty_stats($department_id, $course_id, $discipline_id);
$evolution_data = $dashboard->get_accessibility_evolution($department_id, $course_id, $discipline_id);
$total_pdfs_count = $dashboard->get_total_pdfs_count($department_id, $course_id, $discipline_id);
$Problems_found = $dashboard->get_PDFs_problems($department_id, $course_id, $discipline_id);
// Add stats to debug
$debug_info['stats'] = $stats;
$debug_info['total_pdfs_count'] = $total_pdfs_count;
$debug_info['Problems_found'] = $Problems_found;
$debug_info['evolution_data'] = $evolution_data;

// Get filter data
$departments = $dashboard->get_departments_for_filter();
$courses = $dashboard->get_courses_for_filter($department_id);
$all_academic_degrees = ($department_id === null || $department_id === '' || !isset($department_id));
$all_courses = ($course_id === null || $course_id === '' || !isset($course_id));
if ($all_academic_degrees) {
    $courses = $dashboard->get_courses_for_filter(null);
    $disciplines = $dashboard->get_disciplines_for_filter(null, null);
} elseif ($all_courses) {
    $courses = $dashboard->get_courses_for_filter($department_id);
    $disciplines = $dashboard->get_disciplines_for_filter(null, $department_id);
} else {
    $courses = $dashboard->get_courses_for_filter($department_id);
    $disciplines = $dashboard->get_disciplines_for_filter($course_id, null);
}

// Get filtered data - show all data by default and when filters are applied
$filtered_data = [];
$show_all_data = false;

// Show data in these cases:
// 1. No GET parameters at all (first page load)
// 2. User explicitly selected "All" for all filters
// 3. Specific filters are applied
if (!isset($_GET['department']) && !isset($_GET['course']) && !isset($_GET['discipline'])) {
    // First page load - show all data
    $show_all_data = true;
    $filtered_data = $dashboard->get_filtered_data(null, null, null);
} elseif (isset($_GET['department']) && $_GET['department'] === '' && 
    isset($_GET['course']) && $_GET['course'] === '' &&
    isset($_GET['discipline']) && $_GET['discipline'] === '') {
    // User explicitly selected "All" for all filters
    $show_all_data = true;
    $filtered_data = $dashboard->get_filtered_data(null, null, null);
} elseif ($department_id || $course_id || $discipline_id) {
    // Show filtered data when specific filters are applied
    $filtered_data = $dashboard->get_filtered_data($department_id, $course_id, $discipline_id);
}

// Add filtered data count to debug
$debug_info['filtered_data_count'] = count($filtered_data);
// Pagination logic
$items_per_page = 10;
$total_items = count($filtered_data);
$total_pages = $total_items > 0 ? ceil($total_items / $items_per_page) : 1;
$page = max(1, min($page, $total_pages)); // Ensure page is within bounds
$offset = ($page - 1) * $items_per_page;
$paginated_data = array_slice($filtered_data, $offset, $items_per_page);

// Get data for bottom cards (filtered)
$best_courses = $dashboard->get_best_courses(4, $department_id, $course_id, $discipline_id);
$worst_courses = $dashboard->get_worst_courses(4, $department_id, $course_id, $discipline_id);
$common_errors = $dashboard->get_most_common_errors(4, $department_id, $course_id, $discipline_id);

// Calculate evolution change (simple difference, not percentage)
// Add best/worst/common errors to debug
$debug_info['best_courses'] = $best_courses;
$debug_info['worst_courses'] = $worst_courses;
$debug_info['common_errors'] = $common_errors;
$evolution_current_score = $evolution_data['current_score'];
$evolution_previous_score = $evolution_data['previous_score'];
$evolution_change = round($evolution_current_score - $evolution_previous_score, 1);
$evolution_positive = $evolution_change >= 0;

// Use filtered overall score from stats
$overall_score = $stats['accessibility_score'];

// Determine color for overall score based on filtered stats
if ($overall_score < 45) {
    $score_color = '#dc3545'; // Red
} elseif ($overall_score <= 70) {
    $score_color = '#ffc107'; // Yellow
} else {
    $score_color = '#28a745'; // Green
}

// --- TABELAS DE BASE DE DADOS ---
$DB = $GLOBALS['DB'];
$dbman = $DB->get_manager();
$debug_info['tables'] = [];
$tables = [
    'block_pdfaccessibility_pdf_files',
    'block_pdfaccessibility_test_results',
    'block_pdfcounter_trends',
    'course',
    'course_categories'
];
foreach ($tables as $table) {
    $tableinfo = [];
    $tableinfo['exists'] = $dbman->table_exists($table) ? 'YES' : 'NO';
    if ($tableinfo['exists'] === 'YES') {
        $tableinfo['total_records'] = $DB->count_records($table);
        $sample = $DB->get_records($table, null, '', '*', 0, 5);
        $tableinfo['sample'] = [];
        foreach ($sample as $row) {
            $tableinfo['sample'][] = $row;
        }
    }
    $debug_info['tables'][$table] = $tableinfo;
}


$debug_end_time = microtime(true);
$debug_end_memory = memory_get_usage();
$debug_info['end_time'] = date('Y-m-d H:i:s');
$debug_info['end_memory'] = $debug_end_memory;
$debug_info['duration_sec'] = round($debug_end_time - $debug_start_time, 4);
$debug_info['memory_used'] = $debug_end_memory - $debug_start_memory;

// Debug logging disabled for production: keep metrics only in memory.
// $debug_file = __DIR__ . '/dashboard_debug.txt';
// file_put_contents($debug_file, "==== DASHBOARD INDEX DEBUG ====" . PHP_EOL, FILE_APPEND);
// file_put_contents($debug_file, print_r($debug_info, true) . PHP_EOL, FILE_APPEND);
// error_log('DEBUG INDEX.PHP block executed');
?>



<div class="dashboard-container">
    <div class="dashboard-main">
        <div class="dashboard-content">
            <div class="dashboard-panel">
                <div class="panel-header">
                    <div class="Header-text">
                        <h3 style="color:white;"><?php echo get_string('dashboard_title', 'tool_accessibilitydashboard'); ?></h3>
                        <p><?php echo get_string('dashboard_subtitle', 'tool_accessibilitydashboard'); ?></p>
                    </div>
                    <div class="Report-button">
                        <button class="button" id="exportButton" onclick="exportReportPDF()">
                            <i class="fas fa-file-pdf" aria-hidden="true"></i><?php echo get_string('export_pdf_report', 'tool_accessibilitydashboard'); ?>
                        </button>
                    </div>
                    
                </div>
                
                <div class="panel-body"><div class="Filters-section">
                        <div class="Filters-panel">
                            <div class="Filters-content">
                                <h4><i class="fas fa-filter"></i> <?php echo get_string('filters', 'tool_accessibilitydashboard'); ?></h4>
                                <form method="GET" action="<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/index.php" class="filter-form">
                                    <div class="filter-row">
                                        <div class="filter-group">
                                            <label for="department"><?php echo get_string('filter_academic_degree', 'tool_accessibilitydashboard'); ?></label>
                                            <select id="department" name="department" onchange="updateCourses()" <?php if (!$isadmin && $managercategory) echo 'disabled'; ?>>
                                                <option value=""><?php echo get_string('all_academic_degrees', 'tool_accessibilitydashboard'); ?></option>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept->id; ?>" <?php echo ($department_id == $dept->id) ? 'selected' : ''; ?>>
                                                        <?php echo s($dept->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (!$isadmin && $managercategory): ?>
                                                <input type="hidden" name="department" value="<?php echo $managercategory; ?>" />
                                            <?php endif; ?>
                                        </div>
                                        <div class="filter-group">
                                            <label for="course"><?php echo get_string('filter_course', 'tool_accessibilitydashboard'); ?></label>
                                            <select id="course" name="course" onchange="updateDisciplines()" <?php if (!$isadmin && $managercourse) echo 'disabled'; ?>>
                                                <option value=""><?php echo get_string('all_courses', 'tool_accessibilitydashboard'); ?></option>
                                                <?php foreach ($courses as $course): ?>
                                                    <option value="<?php echo $course->id; ?>" <?php echo ($course_id == $course->id) ? 'selected' : ''; ?>>
                                                        <?php echo s($course->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (!$isadmin && $managercourse): ?>
                                                <input type="hidden" name="course" value="<?php echo $managercourse; ?>" />
                                            <?php endif; ?>
                                        </div>
                                        <div class="filter-group">
                                            <label for="discipline"><?php echo get_string('filter_discipline', 'tool_accessibilitydashboard'); ?></label>
                                            <select id="discipline" name="discipline">
                                                <option value=""><?php echo get_string('all_disciplines', 'tool_accessibilitydashboard'); ?></option>
                                                <?php foreach ($disciplines as $discipline): ?>
                                                    <option value="<?php echo $discipline->id; ?>" <?php echo ($discipline_id == $discipline->id) ? 'selected' : ''; ?>>
                                                        <?php echo s($discipline->name); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="filter-actions">
                                            <button type="submit" class="btn btn-primary"><?php echo get_string('apply_filters', 'tool_accessibilitydashboard'); ?></button>
                                            <a href="<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/index.php" class="btn btn-secondary"><?php echo get_string('clear_filters', 'tool_accessibilitydashboard'); ?></a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card stat-coursesWithPdfs">
                            <h2 class="stat-number"><?php echo $stats['courses_with_pdfs']; ?></h2>
                            <p class="stat-label"><?php echo get_string('courses_with_pdfs', 'tool_accessibilitydashboard'); ?></p>
                            <div class="stat-icon">
                                <i class="fas fa-graduation-cap"></i>
                            </div>
                        </div>
                        
                        <div class="stat-card stat-total-Pdfs">
                            <h2 class="stat-number"><?php echo $total_pdfs_count; ?></h2>
                            <p class="stat-label"><?php echo get_string('total_pdfs', 'tool_accessibilitydashboard'); ?></p>
                            <div class="stat-icon">
                                <i class="fas fa-file-pdf"></i>
                            </div>
                        </div>

                        <div class="stat-card stat-warning">
                            <h2 class="stat-number"><?php echo $Problems_found; ?></h2>
                            <p class="stat-label"><?php echo get_string('stat_problems_found', 'tool_accessibilitydashboard'); ?></p>
                            <div class="stat-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                        </div>

                        <div class="stat-card stat-overall">
                            <h2 class="stat-number" style="color: <?php echo $score_color; ?>;"><?php echo round($overall_score, 1); ?>%</h2>
                            <p class="stat-label"><?php echo get_string('stat_overall_score', 'tool_accessibilitydashboard'); ?></p>
                            <div class="stat-icon" style="background: linear-gradient(135deg, <?php echo $score_color; ?>, <?php echo $score_color; ?>99);">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                        
                        
                    </div>
                    
                    <div class="Evolution-section">
                        <div class="Evolution-panel">
                            <div class="Evolution-content">
                                <div class="evolution-header">
                                    <h4><i class="fas fa-chart-line"></i> <?php echo get_string('evolution_title', 'tool_accessibilitydashboard'); ?></h4>
                                    <div class="evolution-stats">
                                        <span class="evolution-year"><?php echo date('Y'); ?></span>
                                        <div class="evolution-change <?php echo $evolution_positive ? 'positive' : 'negative'; ?>">
                                            <i class="fas fa-<?php echo $evolution_positive ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                            <?php echo abs($evolution_change); ?>%
                                            <span class="evolution-change-label"><?php echo get_string('evolution_since_last_month', 'tool_accessibilitydashboard'); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="chart-container">
                                    <canvas id="evolutionChart" width="800" height="300"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                    

                    <!-- Data Table Section -->
                    <div class="data-table-section">
                        <div class="table-container">
                            <h4><i class="fas fa-table"></i> <?php echo get_string('datatable_title', 'tool_accessibilitydashboard'); ?>
                            
                            </h4>
                            <?php if (!$show_all_data && !$department_id && !$course_id && !$discipline_id): ?>
                                <div class="no-data">
                                    <i class="fas fa-filter"></i>
                                    <p><?php echo get_string('datatable_select_filters', 'tool_accessibilitydashboard'); ?></p>
                                </div>
                            <?php elseif (empty($filtered_data)): ?>
                                <div class="no-data">
                                    <i class="fas fa-info-circle"></i>
                                    <p><?php echo get_string('datatable_no_data', 'tool_accessibilitydashboard'); ?></p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th><?php echo get_string('column_academic_degree', 'tool_accessibilitydashboard'); ?></th>
                                                <th><?php echo get_string('column_course', 'tool_accessibilitydashboard'); ?></th>
                                                <th><?php echo get_string('column_discipline', 'tool_accessibilitydashboard'); ?></th>
                                                <th><?php echo get_string('column_pdfs', 'tool_accessibilitydashboard'); ?></th>
                                                <th><?php echo get_string('column_score', 'tool_accessibilitydashboard'); ?></th>
                                                <th><?php echo get_string('column_status', 'tool_accessibilitydashboard'); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($paginated_data as $row): ?>
                                                <tr>
                                                    <td><?php echo s($row->department); ?></td>
                                                    <td><?php echo s($row->course); ?></td>
                                                    <td><?php echo s($row->discipline); ?></td>
                                                    <td><?php echo $row->pdfs_count; ?></td>
                                                    <td><?php echo number_format($row->score, 1); ?>%</td>
                                                    <td>
                                                        <span class="status-badge status-<?php echo strtolower($row->status); ?>">
                                                            <?php echo $row->status; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <!-- Pagination Controls -->
                                <?php if ($total_pages > 1): ?>
                                    <div class="pagination-container" style="margin-left: 2%;">
                                        <div class="pagination-info">
                                            <?php $a = (object)[
                                                'from' => (($page - 1) * $items_per_page + 1),
                                                'to' => min($page * $items_per_page, $total_items),
                                                'total' => $total_items
                                            ];
                                            echo get_string('pagination_showing', 'tool_accessibilitydashboard', $a);
                                            ?>
                                        </div>
                                        <div class="pagination-controls" style="margin-right: 2%;">
                                            <?php
                                            // Build base URL for pagination links
                                            $base_url = $CFG->wwwroot . '/admin/tool/accessibilityDashboard/index.php?';
                                            $url_params = [];
                                            if ($department_id) $url_params[] = 'department=' . $department_id;
                                            if ($course_id) $url_params[] = 'course=' . $course_id;
                                            if ($discipline_id) $url_params[] = 'discipline=' . $discipline_id;
                                            $base_url .= implode('&', $url_params);
                                            if (!empty($url_params)) $base_url .= '&';
                                            ?>
                                            
                                            <?php if ($page > 1): ?>
                                                <a href="<?php echo $base_url; ?>page=<?php echo ($page - 1); ?>" class="pagination-btn">
                                                    <i class="fas fa-chevron-left"></i> <?php echo get_string('pagination_previous', 'tool_accessibilitydashboard'); ?>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <span class="pagination-current">
                                                <?php $b = (object)['page' => $page, 'totalpages' => $total_pages];
                                                    echo get_string('pagination_page_of', 'tool_accessibilitydashboard', $b);
                                                ?>
                                            </span>
                                            
                                            <?php if ($page < $total_pages): ?>
                                                <a href="<?php echo $base_url; ?>page=<?php echo ($page + 1); ?>" class="pagination-btn">
                                                    <?php echo get_string('pagination_next', 'tool_accessibilitydashboard'); ?> <i class="fas fa-chevron-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                             
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="CoursesnAndErrors">
                    <div class="stats-ce-grid">
                        <!-- Best Courses Card -->
                        <div class="stat-card-Ce stat-coursesWithBestScore">
                            <div class="card-header">
                                <h4><i class="fas fa-trophy"></i> <?php echo get_string('best_disciplines_title', 'tool_accessibilitydashboard'); ?></h4>
                            </div>
                            <div class="card-content">
                                <?php if (empty($best_courses)): ?>
                                    <div class="no-data-small">
                                        <p><?php echo get_string('best_courses_none', 'tool_accessibilitydashboard'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($best_courses as $course): ?>
                                        <?php 
                                        // Determine color class based on score
                                        if ($course->score < 45) {
                                            $score_class = 'critical'; // Red
                                        } elseif ($course->score <= 70) {
                                            $score_class = 'warning'; // Yellow
                                        } else {
                                            $score_class = 'good'; // Green
                                        }
                                        ?>
                                        <div class="course-item">
                                            <div class="course-info">
                                                <h5><?php echo s($course->course_name); ?></h5>
                                                <p><?php echo s($course->course); ?> | <?php echo $course->pdfs_count; ?> PDFs</p>
                                            </div>
                                            <div class="course-score <?php echo $score_class; ?>">
                                                <?php echo number_format($course->score, 0); ?>%
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Worst Courses Card -->

                                        

                                        <?php
                                        // Debug block removed for production to avoid file logging on every page load.
                                        // Metrics (time/memory) are still computed earlier if needed for future use.
                                        ?>
                        <div class="stat-card-Ce stat-CoursesWorstScore">
                            <div class="card-header">
                                <h4><i class="fas fa-exclamation-triangle"></i> <?php echo get_string('worst_disciplines_title', 'tool_accessibilitydashboard'); ?></h4>
                            </div>
                            <div class="card-content">
                                <?php if (empty($worst_courses)): ?>
                                    <div class="no-data-small">
                                        <p><?php echo get_string('worst_courses_none', 'tool_accessibilitydashboard'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($worst_courses as $course): ?>
                                        <?php 
                                        // Determine color class based on score
                                        if ($course->score < 45) {
                                            $score_class = 'critical'; // Red
                                        } elseif ($course->score <= 70) {
                                            $score_class = 'warning'; // Yellow
                                        } else {
                                            $score_class = 'good'; // Green
                                        }
                                        ?>
                                        <div class="course-item">
                                            <div class="course-info">
                                                <h5><?php echo s($course->course_name); ?></h5>
                                                <p><?php echo s($course->course); ?> | <?php echo $course->pdfs_count; ?> PDFs</p>
                                            </div>
                                            <div class="course-score <?php echo $score_class; ?>">
                                                <?php echo number_format($course->score, 0); ?>%
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Most Failed Tests Card -->
                        <div class="stat-card-Ce stat-FailedTests">
                            <div class="card-header">
                                <h4><i class="fas fa-exclamation-triangle"></i> <?php echo get_string('most_failed_tests_title', 'tool_accessibilitydashboard'); ?></h4>
                            </div>
                            <div class="card-content">
                                <?php if (empty($common_errors)): ?>
                                    <div class="no-data-small">
                                        <p><?php echo get_string('no_failed_tests', 'tool_accessibilitydashboard'); ?></p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($common_errors as $error): ?>
                                        <?php 
                                        // Determine color class based on error percentage (inverted logic - higher error = worse)
                                        if ($error->percentage > 55) {
                                            $error_class = 'critical'; // Red - high error rate
                                        } elseif ($error->percentage >= 30) {
                                            $error_class = 'warning'; // Yellow - medium error rate
                                        } else {
                                            $error_class = 'good'; // Green - low error rate
                                        }
                                        ?>
                                        <div class="error-item">
                                            <div class="error-info">
                                                <h5><?php echo s($error->error_type); ?></h5>
                                                <p>
                                                    <?php $counters = (object)[
                                                        'failed' => $error->failure_count,
                                                        'total' => $error->total_tests
                                                    ];
                                                    echo get_string('failed_pdfs_of_total', 'tool_accessibilitydashboard', $counters);
                                                    ?>
                                                </p>
                                            </div>
                                            <div class="error-percentage <?php echo $error_class; ?>">
                                                <?php echo number_format($error->percentage, 0); ?>%
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Evolution chart with real data from database
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('evolutionChart');
    if (ctx) {
        const chart = new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?php echo json_encode($evolution_data['months']); ?>,
                datasets: [{
                    label: '<?php echo get_string('chart_accessibility_score', 'tool_accessibilitydashboard'); ?>',
                    data: <?php echo json_encode($evolution_data['scores']); ?>,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#007bff',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: '#007bff',
                        borderWidth: 1,
                        cornerRadius: 6,
                        displayColors: false,
                        callbacks: {
                            title: function(context) {
                                // Agora o label já é 'Mar 2026', então basta retornar
                                return context[0].label;
                            },
                            label: function(context) {
                                return '<?php echo get_string('tooltip_accessibility_prefix', 'tool_accessibilitydashboard'); ?> ' + context.parsed.y.toFixed(1) + '%';
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: '#6c757d'
                        }
                    },
                    y: {
                        min: 0,
                        max: 100,
                        grid: {
                            color: '#e9ecef'
                        },
                        ticks: {
                            color: '#6c757d',
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
});

// Filter functionality
function updateCourses() {
    const departmentSelect = document.getElementById('department');
    const courseSelect = document.getElementById('course');
    const disciplineSelect = document.getElementById('discipline');
    const departmentId = departmentSelect.value;

    // Clear current course and discipline options
    courseSelect.innerHTML = '<option value="">All Courses</option>';
    disciplineSelect.innerHTML = '<option value="">All Disciplines</option>';

    let urlCourses;
    let urlDisciplines;
    if (!departmentId) {
        // All Academic Degrees: fetch all courses and all disciplines
        urlCourses = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/ajax_courses_new.php?all=1';
        urlDisciplines = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/ajax_disciplines.php?all=1';
    } else {
        // Fetch courses and disciplines for selected department
        urlCourses = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/ajax_courses_new.php?department=' + departmentId;
        urlDisciplines = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/ajax_disciplines.php?department=' + departmentId;
    }

    // Fetch courses
    fetch(urlCourses)
        .then(response => response.json())
        .then(courses => {
            courses.forEach(course => {
                const option = document.createElement('option');
                option.value = course.id;
                option.textContent = course.name;
                courseSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error fetching courses:', error);
        });

    // Fetch disciplines
    fetch(urlDisciplines)
        .then(response => response.json())
        .then(disciplines => {
            disciplines.forEach(discipline => {
                const option = document.createElement('option');
                option.value = discipline.id;
                option.textContent = discipline.name;
                disciplineSelect.appendChild(option);
            });
        })
        .catch(error => {
            console.error('Error fetching disciplines:', error);
        });
}

function updateDisciplines() {
    const departmentSelect = document.getElementById('department');
    const courseSelect = document.getElementById('course');
    const disciplineSelect = document.getElementById('discipline');
    const departmentId = departmentSelect.value;
    const courseId = courseSelect.value;

    // Clear current discipline options
    disciplineSelect.innerHTML = '<option value="">All Disciplines</option>';

    let urlDisciplines;
    if (!courseId && departmentId) {
        // All Courses selected, but department selected: show all disciplines for department
        urlDisciplines = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/ajax_disciplines.php?department=' + departmentId;
    } else if (!courseId && !departmentId) {
        // All Academic Degrees and All Courses: show all disciplines
        urlDisciplines = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/ajax_disciplines.php?all=1';
    } else if (courseId) {
        // Fetch disciplines for selected course
        urlDisciplines = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/ajax_disciplines.php?course=' + courseId;
    }

    if (urlDisciplines) {
        fetch(urlDisciplines)
            .then(response => response.json())
            .then(disciplines => {
                disciplines.forEach(discipline => {
                    const option = document.createElement('option');
                    option.value = discipline.id;
                    option.textContent = discipline.name;
                    disciplineSelect.appendChild(option);
                });
            })
            .catch(error => {
                console.error('Error fetching disciplines:', error);
            });
    }
}

// Auto-refresh dashboard data every 5 seconds
function refreshDashboardData() {
    // Get current filter values
    const departmentSelect = document.getElementById('department');
    const courseSelect = document.getElementById('course');
    const disciplineSelect = document.getElementById('discipline');
    
    const department_id = departmentSelect ? departmentSelect.value : '';
    const course_id = courseSelect ? courseSelect.value : '';
    const discipline_id = disciplineSelect ? disciplineSelect.value : '';
    
    // Build URL with current filters
    let url = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilityDashboard/ajax_refresh.php?';
    const params = [];
    if (department_id) params.push('department=' + encodeURIComponent(department_id));
    if (course_id) params.push('course=' + encodeURIComponent(course_id));
    if (discipline_id) params.push('discipline=' + encodeURIComponent(discipline_id));
    url += params.join('&');
    
    // Fetch updated data
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update courses with PDFs
                const coursesElement = document.querySelector('.stat-coursesWithPdfs .stat-number');
                if (coursesElement && data.data.courses_with_pdfs !== undefined) {
                    coursesElement.textContent = data.data.courses_with_pdfs;
                }
                
                // Update total PDFs
                const totalPdfsElement = document.querySelector('.stat-total-Pdfs .stat-number');
                if (totalPdfsElement && data.data.total_pdfs !== undefined) {
                    totalPdfsElement.textContent = data.data.total_pdfs;
                }
                
                // Update problems found (PDF Issues)
                const problemsElement = document.querySelector('.stat-warning .stat-number');
                if (problemsElement && data.data.problems_found !== undefined) {
                    problemsElement.textContent = data.data.problems_found;
                }
                
                // Update overall score
                const overallElement = document.querySelector('.stat-overall .stat-number');
                if (overallElement && data.data.overall_score !== undefined) {
                    const newScore = data.data.overall_score;
                    overallElement.textContent = newScore + '%';
                    
                    // Update color based on score
                    let color;
                    if (newScore < 45) {
                        color = '#dc3545'; // Red
                    } else if (newScore <= 70) {
                        color = '#ffc107'; // Yellow
                    } else {
                        color = '#28a745'; // Green
                    }
                    overallElement.style.color = color;
                    
                    // Update icon color too
                    const iconElement = document.querySelector('.stat-overall .stat-icon');
                    if (iconElement) {
                        iconElement.style.background = `linear-gradient(135deg, ${color}, ${color}99)`;
                    }
                }
                
                console.log('Dashboard data refreshed');
            } else {
                console.error('Error refreshing dashboard:', data.message);
            }
        })
        .catch(error => {
            console.error('Error refreshing dashboard:', error);
        });
}

// Start auto-refresh when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Initial refresh after 2 seconds
    setTimeout(refreshDashboardData, 2000);
    
    // Then refresh every 5 seconds
    setInterval(refreshDashboardData, 5000);
    
    console.log('Auto-refresh dashboard started (every 5 seconds)');
});

// Export PDF functionality
function exportReportPDF() {
    // Get current filter values
    const departmentSelect = document.getElementById('department');
    const courseSelect = document.getElementById('course');
    const disciplineSelect = document.getElementById('discipline');
    
    const department_id = departmentSelect ? departmentSelect.value : '';
    const course_id = courseSelect ? courseSelect.value : '';
    const discipline_id = disciplineSelect ? disciplineSelect.value : '';
    
    // Build export URL with current filters
    let exportUrl = '<?php echo $CFG->wwwroot; ?>/admin/tool/accessibilitydashboard/export_report.php?format=pdf';
    
    if (department_id) exportUrl += '&department=' + encodeURIComponent(department_id);
    if (course_id) exportUrl += '&course=' + encodeURIComponent(course_id);
    if (discipline_id) exportUrl += '&discipline=' + encodeURIComponent(discipline_id);
    
    // Show loading indicator
    const exportButton = document.getElementById('exportButton');
    const originalText = exportButton.innerHTML;
    exportButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo get_string('export_generating', 'tool_accessibilitydashboard'); ?>';
    exportButton.disabled = true;
    
    // Create a temporary link and trigger download
    const link = document.createElement('a');
    link.href = exportUrl;
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Reset button after 3 seconds
    setTimeout(function() {
        exportButton.innerHTML = originalText;
        exportButton.disabled = false;
    }, 3000);
    
    console.log('PDF Export initiated:', exportUrl);
}
</script>

<?php echo $OUTPUT->footer(); ?>