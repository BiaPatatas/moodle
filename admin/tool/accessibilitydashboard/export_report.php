<?php

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/pdflib.php');
require_once($CFG->libdir . '/adminlib.php');

use tool_accessibilitydashboard\dashboard;

// Verificar se o usuário está logado e tem permissões
require_login();
admin_externalpage_setup('tool_accessibilitydashboard');

// Obter parâmetros de filtro
$department_id = optional_param('department', null, PARAM_INT);
$course_id = optional_param('course', null, PARAM_INT);
$discipline_id = optional_param('discipline', null, PARAM_INT);

// Criar instância do dashboard
$dashboard = new \tool_accessibilitydashboard\dashboard();

// Obter dados
$stats = $dashboard->get_faculty_stats($department_id, $course_id, $discipline_id);
$evolution_data = $dashboard->get_accessibility_evolution($department_id, $course_id, $discipline_id);
$total_pdfs_count = $dashboard->get_total_pdfs_count($department_id, $course_id, $discipline_id);
$problems_found = $dashboard->get_PDFs_problems($department_id, $course_id, $discipline_id);
$filtered_data = $dashboard->get_filtered_data($department_id, $course_id, $discipline_id);
$best_courses = $dashboard->get_best_courses(10, $department_id, $course_id, $discipline_id);
$worst_courses = $dashboard->get_worst_courses(10, $department_id, $course_id, $discipline_id);
$common_errors = $dashboard->get_most_common_errors(10, $department_id, $course_id, $discipline_id);

// Exportar como PDF
export_pdf($stats, $evolution_data, $total_pdfs_count, $problems_found, $filtered_data, 
           $best_courses, $worst_courses, $common_errors, $department_id, $course_id, $discipline_id);

function export_pdf($stats, $evolution_data, $total_pdfs_count, $problems_found, $filtered_data, 
                    $best_courses, $worst_courses, $common_errors, $department_id, $course_id, $discipline_id) {
    
    // Criar PDF
    global $USER, $CFG;
    $pdf = new pdf();
    // Metadata / accessibility improvements
    $pdf->SetTitle('Accessibility Dashboard Report');
    $pdf->SetAuthor(isset($USER) ? fullname($USER) : 'Moodle');
    $pdf->SetSubject('Accessibility Dashboard report');
    $pdf->SetKeywords('accessibility, report, pdf, Moodle');
    $pdf->SetCreator('Moodle Accessibility Dashboard');
    // Ensure viewer displays document title
    if (method_exists($pdf, 'setViewerPreferences')) {
        $pdf->setViewerPreferences(['DisplayDocTitle' => true]);
    }
    // Set language array from the pdf wrapper (already localized)
    if (method_exists($pdf, 'setLanguageArray')) {
        // Try to obtain the language array via a public method if available.
        $lang = null;
        if (method_exists($pdf, 'getLanguageArray')) {
            $lang = $pdf->getLanguageArray();
        } else {
            // $pdf->l may be protected; use reflection to access it safely.
            $reflection = new ReflectionClass($pdf);
            if ($reflection->hasProperty('l')) {
                $prop = $reflection->getProperty('l');
                $prop->setAccessible(true);
                $lang = $prop->getValue($pdf);
            }
        }
        if (!empty($lang) && is_array($lang)) {
            $pdf->setLanguageArray($lang);
        }
    }
    // Use an accessible Unicode font
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetFont('freesans', '', 11);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);
    $pdf->AddPage();

    // Título (semântico) - use HTML so TCPDF emits structure where possible
    $pdf->SetFont('freesans', 'B', 18);
    $pdf->writeHTML('<h1>Accessibility Dashboard Report</h1>', true, false, true, false, '');
    $pdf->Ln(3);
    
    // Data e hora (meta informação legível)
    $pdf->SetFont('freesans', '', 10);
    $pdf->Cell(0, 5, 'Generated on: ' . date('Y-m-d'), 0, 1, 'C');
    $pdf->Ln(6);
    
    // Filtros aplicados
    if ($department_id || $course_id || $discipline_id) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Applied Filters:', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 10);
        
        if ($department_id) $pdf->Cell(0, 5, '• Department ID: ' . $department_id, 0, 1, 'L');
        if ($course_id) $pdf->Cell(0, 5, '• Course ID: ' . $course_id, 0, 1, 'L');
        if ($discipline_id) $pdf->Cell(0, 5, '• Discipline ID: ' . $discipline_id, 0, 1, 'L');
        
        $pdf->Ln(5);
    }
    
    // Estatísticas gerais
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'General Statistics', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 10);
    
    $pdf->Cell(60, 6, 'Courses with PDFs:', 0, 0, 'L');
    $pdf->Cell(0, 6, $stats['courses_with_pdfs'], 0, 1, 'L');
    
    $pdf->Cell(60, 6, 'Total PDFs:', 0, 0, 'L');
    $pdf->Cell(0, 6, $total_pdfs_count, 0, 1, 'L');
    
    $pdf->Cell(60, 6, 'Problems Found:', 0, 0, 'L');
    $pdf->Cell(0, 6, $problems_found, 0, 1, 'L');
    
    $pdf->Cell(60, 6, 'Overall Score:', 0, 0, 'L');
    $pdf->Cell(0, 6, round($stats['accessibility_score'], 1) . '%', 0, 1, 'L');
    
    $pdf->Ln(10);
    
    // Melhores cursos (tabela semântica)
    if (!empty($best_courses)) {
        $pdf->SetFont('freesans', 'B', 12);
        $pdf->writeHTML('<h2>Disciplines with Higher Score</h2>', true, false, true, false, '');

     $table = '<table border="1" cellpadding="4" cellspacing="0" width="100%">'
         . '<thead><tr style="background-color:#e8eef6;font-weight:bold;">'
         . '<th scope="col" align="left">Discipline Name</th>'
         . '<th scope="col" align="left">Course</th>'
         . '<th scope="col" align="center">PDFs</th>'
         . '<th scope="col" align="center">Score (%)</th>'
         . '</tr></thead><tbody>';

        foreach (array_slice($best_courses, 0, 5) as $course) {
            $dname = htmlspecialchars(substr($course->course_name, 0, 60));
            $dept = htmlspecialchars(substr($course->course, 0, 40));
            $pdfs = intval($course->pdfs_count);
            $score = number_format($course->score, 1);
            $table .= "<tr><td>{$dname}</td><td>{$dept}</td><td align=\"center\">{$pdfs}</td><td align=\"center\">{$score}</td></tr>";
        }

        $table .= '</tbody></table>';
        $pdf->SetFont('freesans', '', 9);
        $pdf->writeHTML($table, true, false, true, false, '');
        $pdf->Ln(6);
    }
    
    // Piores cursos
    if (!empty($worst_courses)) {
        $pdf->SetFont('freesans', 'B', 12);
        $pdf->writeHTML('<h2>Disciplines with Lower Score</h2>', true, false, true, false, '');

     $table = '<table border="1" cellpadding="4" cellspacing="0" width="100%">'
         . '<thead><tr style="background-color:#e8eef6;font-weight:bold;">'
         . '<th scope="col" align="left">Discipline Name</th>'
         . '<th scope="col" align="left">Course</th>'
         . '<th scope="col" align="center">PDFs</th>'
         . '<th scope="col" align="center">Score (%)</th>'
         . '</tr></thead><tbody>';

        foreach (array_slice($worst_courses, 0, 5) as $course) {
            $dname = htmlspecialchars(substr($course->course_name, 0, 60));
            $dept = htmlspecialchars(substr($course->course, 0, 40));
            $pdfs = intval($course->pdfs_count);
            $score = number_format($course->score, 1);
            $table .= "<tr><td>{$dname}</td><td>{$dept}</td><td align=\"center\">{$pdfs}</td><td align=\"center\">{$score}</td></tr>";
        }

        $table .= '</tbody></table>';
        $pdf->SetFont('freesans', '', 9);
        $pdf->writeHTML($table, true, false, true, false, '');
        $pdf->Ln(6);
    }
    
    // Nova página para dados detalhados se necessário
    if (!empty($filtered_data) && count($filtered_data) > 20) {
        $pdf->AddPage();
    }
    
    // Dados detalhados (limitado)
    if (!empty($filtered_data)) {
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'Detailed Academic Data', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 8);
        
        // Ordenar os dados detalhados: department, course, discipline (alfabética, case-insensitive)
        if (is_array($filtered_data) && count($filtered_data) > 1) {
            $cmp = function($a, $b) {
                $adept = mb_strtolower(trim((string)($a->department ?? '')));
                $bdept = mb_strtolower(trim((string)($b->department ?? '')));
                $c = strcmp($adept, $bdept);
                if ($c !== 0) {
                    return $c;
                }
                $acourse = mb_strtolower(trim((string)($a->course ?? '')));
                $bcourse = mb_strtolower(trim((string)($b->course ?? '')));
                $c = strcmp($acourse, $bcourse);
                if ($c !== 0) {
                    return $c;
                }
                $adisc = mb_strtolower(trim((string)($a->discipline ?? '')));
                $bdisc = mb_strtolower(trim((string)($b->discipline ?? '')));
                return strcmp($adisc, $bdisc);
            };
            usort($filtered_data, $cmp);
        }

        // Tabela detalhada com cabeçalhos (thead/tbody)
        $pdf->SetFont('freesans', '', 9);
        $table = '<table border="1" cellpadding="4" cellspacing="0" width="100%">'
               . '<thead><tr style="background-color:#e8eef6;font-weight:bold;">'
               . '<th scope="col" align="left">Academic Degree</th>'
               . '<th scope="col" align="left">Course</th>'
               . '<th scope="col" align="left">Discipline</th>'
               . '<th scope="col" align="center">PDFs</th>'
               . '<th scope="col" align="center">Score (%)</th>'
               . '<th scope="col" align="center">Status</th>'
               . '</tr></thead><tbody>';

        foreach (array_slice($filtered_data, 0, 20) as $row) {
            $dept = htmlspecialchars(substr($row->department ?? 'Unknown', 0, 40));
            $course = htmlspecialchars(substr($row->course ?? 'Direct', 0, 40));
            $disc = htmlspecialchars(substr($row->discipline ?? '', 0, 40));
            $pdfs = intval($row->pdfs_count);
            $score = number_format($row->score, 1);
            $status = htmlspecialchars($row->status);
            $table .= "<tr><td>{$dept}</td><td>{$course}</td><td>{$disc}</td><td align=\"center\">{$pdfs}</td><td align=\"center\">{$score}</td><td align=\"center\">{$status}</td></tr>";
        }

        $table .= '</tbody></table>';
        $pdf->writeHTML($table, true, false, true, false, '');
        
     
    }
    
    // Nome do arquivo
    $filename = 'accessibility_report_' . date('Y-m-d_H-i-s') . '.pdf';
    
    // Output do PDF
    $pdf->Output($filename, 'D');
    exit;
}