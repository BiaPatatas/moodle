<?php
/**
 * English language strings for PDF Accessibility Admin Tool
 */

$string['pluginname'] = 'Accessibility Dashboard';
$string['dashboard_title'] = 'Accessibility Dashboard';

// Stats
$string['courses_with_pdfs'] = 'Courses with PDFs';
$string['total_pdfs'] = 'Total PDFs';
$string['accessibility_score'] = 'Accessibility Score';
$string['recent_activity'] = 'Recent Activity';

// Filters
$string['filters'] = 'Filters';
$string['all_departments'] = 'All Departments';
$string['all_courses'] = 'All Programs';
$string['last_year'] = 'Last Year';
$string['apply_filters'] = 'Apply Filters';

// Departments
$string['departments_performance'] = 'Performance by Department';
$string['department'] = 'Department';
$string['courses'] = 'Programs';
$string['pdfs'] = 'PDFs';
$string['avg_score'] = 'Average Score';
$string['actions'] = 'Actions';
$string['view_details'] = 'View Details';

// Common Errors
$string['common_errors'] = 'Most Common Errors';
$string['error_type'] = 'Error Type';
$string['occurrences'] = 'Occurrences';
$string['percentage'] = 'Percentage';
$string['guidance'] = 'Guidance';

// Objectives
$string['accessibility_objectives'] = 'Accessibility Objectives';
$string['no_objectives'] = 'No objectives defined yet.';
$string['add_objective'] = 'Add Objective';
$string['edit_objective'] = 'Edit Objective';
$string['delete_objective'] = 'Delete Objective';
$string['deadline'] = 'Deadline';
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';

// Reports
$string['export_report'] = 'Export Report';

// Extra strings for UI in index.php
$string['access_restricted'] = 'Access restricted: only administrators or course managers can view this dashboard.';
$string['dashboard_subtitle'] = 'Monitoring accessibility of the institution';
$string['export_pdf_report'] = 'Export PDF report';

$string['filter_academic_degree'] = 'Academic Degree:';
$string['filter_course'] = 'Program:';
$string['filter_discipline'] = 'Course:';
$string['all_academic_degrees'] = 'All Academic Degrees';
$string['all_disciplines'] = 'All Courses';
$string['clear_filters'] = 'Clear Filters';

$string['stat_problems_found'] = 'Problems Found';
$string['stat_overall_score'] = 'Overall Score';

$string['evolution_title'] = 'Accessibility Evolution';
$string['evolution_since_last_month'] = 'since last month';

$string['datatable_title'] = 'Academic Data';
$string['datatable_select_filters'] = 'Please select filters to view program data.';
$string['datatable_no_data'] = 'No data found for the selected filters.';

$string['column_academic_degree'] = 'Academic Degree';
$string['column_course'] = 'Program';
$string['column_discipline'] = 'Course';
$string['column_pdfs'] = 'PDFs';
$string['column_score'] = 'Score';
$string['column_status'] = 'Status';

$string['pagination_showing'] = 'Showing {$a->from} - {$a->to} of {$a->total} results';
$string['pagination_previous'] = 'Previous';
$string['pagination_next'] = 'Next';
$string['pagination_page_of'] = 'Page {$a->page} of {$a->totalpages}';

$string['best_disciplines_title'] = 'Courses with Higher Score';
$string['best_courses_none'] = 'No programs with accessibility scores found.';

$string['worst_disciplines_title'] = 'Courses with Lower Score';
$string['worst_courses_none'] = 'No programs with poor accessibility scores found.';

$string['most_failed_tests_title'] = 'Most Failed Tests';
$string['no_failed_tests'] = 'No failed tests found.';
$string['failed_pdfs_of_total'] = '{$a->failed} of {$a->total} PDFs failed';

$string['chart_accessibility_score'] = 'Accessibility Score (%)';
$string['tooltip_accessibility_prefix'] = 'Accessibility:';

$string['export_generating'] = 'Generating PDF...';

// Capabilities
$string['pdfaccessibility:viewdashboard'] = 'View PDF accessibility dashboard';