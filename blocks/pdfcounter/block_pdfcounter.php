<?php
require_once(__DIR__ . '/../../config.php'); // Loads Moodle core and classes
require_once($CFG->dirroot . '/lib/weblib.php'); // Required for moodle_url class
require_once($CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility_config.php'); // PDF accessibility shared config

defined('MOODLE_INTERNAL') || die();

class block_pdfcounter extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_pdfcounter');
    }

    public function get_content() {
        global $COURSE, $DB, $CFG, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();

        // Check if we're in a course
        if (empty($COURSE) || $COURSE->id == SITEID) {
            $this->content->text = get_string('nocourse', 'block_pdfcounter');
            return $this->content;
        }

        $current_month = date('Y-m');
        // Buscar todos os PDFs do curso pelo courseid (usando a tabela do plugin)
        $allpdfs = $DB->get_records('block_pdfaccessibility_pdf_files', ['courseid' => $COURSE->id]);

        // Calcular progresso geral (overall accessibility) com base em TODOS os PDFs do curso
        $total_percent = 0;
        $total_pdfs = 0;
        $pdf_issues = [];
        foreach ($allpdfs as $pdfrecord) {
            $pdfid = $pdfrecord->id;
            $counts = pdf_accessibility_config::get_test_counts($DB, $pdfid);
            $display_name = !empty($pdfrecord->filename) ? $pdfrecord->filename : 'PDF';
            $pdf_issues[] = [
                'filename' => $pdfrecord->filename,
                'display_name' => $display_name,
                'fileid' => $pdfid,
                'fail_count' => $counts['fail_count'],
                'pass_count' => $counts['pass_count'],
                'nonapplicable_count' => $counts['nonapplicable_count'],
                'not_tagged_count' => $counts['not_tagged_count']
            ];
            // Calcular progresso para cada PDF
            $applicable_tests = pdf_accessibility_config::calculate_applicable_tests(
                $counts['pass_count'],
                $counts['fail_count'],
                $counts['not_tagged_count']
            );
            if ($applicable_tests > 0) {
                $percent = pdf_accessibility_config::calculate_progress($counts);
                $total_percent += $percent;
                $total_pdfs++;
            }
        }
        $progress_value = $total_pdfs > 0 ? round($total_percent / $total_pdfs) : 0;
        $progress_color = pdf_accessibility_config::get_progress_color($progress_value);

        // Atualiza o valor na tabela de tendências para o mês atual, se necessário
        $trend_exists = $DB->record_exists('block_pdfcounter_trends', [
            'courseid' => $COURSE->id,
            'month' => $current_month
        ]);
        if (!$trend_exists) {
            $trend = new stdClass();
            $trend->courseid = $COURSE->id;
            $trend->month = $current_month;
            $trend->progress_value = $progress_value;
            $trend->timecreated = time();
            $DB->insert_record('block_pdfcounter_trends', $trend);
        }

        $trends = $DB->get_records('block_pdfcounter_trends', ['courseid' => $COURSE->id], 'month ASC');

        // // Atualiza o valor na tabela de tendências para o mês atual
        // if ($trend_exists) {
        //     $DB->set_field('block_pdfcounter_trends', 'progress_value', $progress_value, [
        //         'courseid' => $COURSE->id,
        //         'month' => $current_month
        //     ]);
        // }

        //-------------------------------------Overall Accessibility HTML (sempre no topo)---------------------------
        $overall_html = '
        <style>
            .p-3{
            background-color: #f8f8f8;
        }
        </style>
         <div style="font-family:Arial,sans-serif;max-width:320px; ">
        <a href="#" class="learn-more-link" style="color: #0F6CBF; text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 10px;">
            <i class="fa fa-info-circle" aria-hidden="true"></i> Read More
        </a>
        <div class="learn-more-content" style="display:none; background: #f8f9fa; padding: 12px; border-radius: 6px; margin-bottom: 10px;">
            <p style="margin: 0 0 12px 0; font-size: 0.85rem; line-height: 1.4;">The Accessibility Dashboard offers an overview of course accessibility, tracks progress, and highlights PDF accessibility issues, with detailed reports for each file.</p>
            <div style="margin-top: 10px;">
                <p style="margin: 8px 0 4px 0; font-size: 0.8rem; font-weight: bold;">Resources:</p>
                <ul style="margin: 0; padding-left: 20px; font-size: 0.8rem;">
                    <li style="margin: 4px 0;">
                        <a href="/blocks/pdfcounter/docs/guia_doc_acessivel_fcul.pdf" target="_blank" 
                           style="color: #0F6CBF; text-decoration: underline;"
                           title="Open FCUL Accessibility Guide">
                            <i class="fa fa-external-link" style="font-size: 0.7rem;"></i> FCUL Accessibility Guide
                        </a>
                    </li>
                    <li style="margin: 4px 0;">
                        <a href="https://www.w3.org/WAI/WCAG22/Techniques/#pdf" target="_blank" 
                           style="color: #0F6CBF; text-decoration: underline;"
                           title="WCAG 2.2 PDF Techniques">
                            <i class="fa fa-external-link" style="font-size: 0.7rem;"></i> Accessible PDF Best Practices - WCAG 2.2
                        </a>
                    </li>
                </ul>
            </div>
        </div>
         <script>
            document.addEventListener("DOMContentLoaded", function() {
                const learnMoreLink = document.querySelector(".learn-more-link");
                const learnMoreContent = document.querySelector(".learn-more-content");
                
                if (learnMoreLink && learnMoreContent) {
                    learnMoreLink.addEventListener("click", function(e) {
                        e.preventDefault();
                        if (learnMoreContent.style.display === "none" || learnMoreContent.style.display === "") {
                            learnMoreContent.style.display = "block";
                            this.innerHTML = "<i class=\"fa fa-times\" aria-hidden=\"true\"></i> Close";
                        } else {
                            learnMoreContent.style.display = "none";
                            this.innerHTML = "<i class=\"fa fa-info-circle\" aria-hidden=\"true\"></i> Read More";
                        }
                    });
                }
            });
            </script>
                
                <div style="background: #f8f9fa;
                            border-radius: 8px;
                            padding: 15px;
                            background-color: white;
                            border-radius: 8px;
                            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                            color: black;
                            margin-bottom: 10px; margin-top: 10px;"> 
                          
                    <bold style="   font-size: 0.90rem;
                                    font-weight: bold;
                                    margin-bottom: 2px;">Overall Accessibility</bold><br>
                    <div style="margin-top:10px; display: flex;justify-content: center; align-items: center;">
                        <span id="overall-accessibility-value" class="overall-value" style="font-size:20px;">' . $progress_value . '%</span>
                        <progress class="progress pdf-counter-progress-bar" value="' . $progress_value . '" max="100" style="--progress-color: ' . $progress_color . ';     margin-left: 8%;"></progress>
                    </div>
                </div>
<style>
.pdfcounter-block {
    padding: 15px;
    background-color: white;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: box-shadow 0.3s ease;
    margin-bottom:10px;
}
.pdfcounter-block:hover {
    box-shadow: 0 3px 8px rgba(0,0,0,0.15);
}
.pdfcounter-content {
    display: flex;
    align-items: center;
    margin-bottom: 12px;
}
.overall-content {
    align-items: center;
    margin-bottom: 12px;
}
.pdfcounter-icon {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 50%;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: 15px;
    color: #495057;
}
.pdfcounter-info {
    display: flex;
    flex-direction: column;
}
.pdfcounter-label {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 2px;
}
.pdfcounter-value {
    font-size: 1.5rem;
    font-weight: 600;
    color: #212529;
}
.pdfcounter-description {
    padding-top: 10px;
    border-top: 1px solid #f1f3f5;
    font-size: 0.75rem;
    color: #868e96;
}
.overall-accessibility {
    display: flex;
    flex-direction: column;
}
.overall-label {
    font-size: 0.90rem;
    margin-bottom: 2px;
    font-weight: bold;
}
.overall-value {
    font-size: 1.5rem;
    color: #212529;
}
.progress {
    width: 100%;
    height: 20px;
    border-radius: 10px;
    overflow: hidden;
    appearance: none;
}
.progress::-webkit-progress-bar {
    background-color: #ddd;
    border-radius: 10px;
}
.progress::-webkit-progress-value {
    background-color: var(--progress-color) !important;
}
</style>
';

        // Adiciona o HTML do topo
        $this->content->text = $overall_html;

        //-------------------------------------PDFs Issues---------------------------------------
        $pdf_issues_html = '<div style="font-family:Arial,sans-serif;max-width:320px;">
            <div style="background: #f8f9fa; border-radius: 8px; padding: 15px; background-color: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); color: black; margin-bottom: 10px;">
                <span style="font-size: 0.90rem; font-weight: bold; margin-bottom: 2px;">PDFs Issues</span><br>
                <div style="margin-top:10px; max-height: 200px; overflow-y: auto;">';
                
        foreach ($pdf_issues as $issue) {
            $download_url = new moodle_url('/blocks/pdfcounter/download_report.php', [
                'filename' => urlencode($issue['filename']),
                'courseid' => $COURSE->id
            ]);
            $applicable_tests = pdf_accessibility_config::calculate_applicable_tests(
                $issue['pass_count'],
                $issue['fail_count'],
                $issue['not_tagged_count']
            );
            $failed_tests = pdf_accessibility_config::calculate_failed_tests(
                $issue['fail_count'],
                $issue['not_tagged_count']
            );
            
            $pdf_issues_html .= '
                <div style="border-bottom: 1px solid #e9ecef; padding: 8px 0; margin-bottom: 8px; word-wrap: break-word;">
                    <div style="font-size: 0.85rem; font-weight: 500; color: #2e3032ff; margin-bottom: 4px; 
                               overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" 
                         title="' . htmlspecialchars($issue['display_name']) . '">
                        <i class="fa-solid fa-file-pdf"></i> ' . htmlspecialchars($issue['display_name']) . '.pdf ' .'
                    </div>
                    <div style="font-size: 0.8rem; color: #000000ff; margin-bottom: 6px;">
                        <strong>' . $failed_tests . ' of ' . $applicable_tests . ' tests failed</strong>
                    </div>
                    <div style="text-align: right;">
                        <a href="' . $download_url->out() . '" target="_blank" 
                           style="font-size: 0.75rem; color: #0F6CBF; text-decoration: none;">
                            <i class="fa fa-download" aria-hidden="true"></i> Download Report
                        </a>
                    </div>
                </div>';
        }
        
        $pdf_issues_html .= '</div>
            </div>
        </div>';

        $this->content->text .= $pdf_issues_html;

        //--------------------------------------------Progress Value (Historical Trends)-------------------------
        $meses = [];
        $progress_values = [];
        foreach ($trends as $trend) {
            $meses[] = $trend->month;
            $progress_values[] = $trend->progress_value;
        }

        $this->content->text .= '
        <div style="font-family:Arial,sans-serif;max-width:320px;">
            <div style="background: #f8f9fa;
                        border-radius: 8px;
                        padding: 15px;
                        background-color: white;
                        border-radius: 8px;
                        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                        color: black;
                        margin-bottom: 10px;">
                <span style="font-size: 0.90rem; font-weight: bold; margin-bottom: 2px;">Historical Trends</span><br>
                <div style="margin-top:10px">
                    <canvas id="progressChart" width="200" height="150"></canvas>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            const meses = ' . json_encode($meses) . ';
            let progressValues = ' . json_encode($progress_values) . ';

            const ctx = document.getElementById("progressChart").getContext("2d");
            window.progressChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels: meses,
                    datasets: [{
                        label: "Progress (%)",
                        data: progressValues,
                        borderColor: "blue",
                        backgroundColor: "rgba(0,0,255,0.1)",
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: false,
                    plugins: {
                        legend: {
                            labels: {
                                boxWidth: 0
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100
                        }
                    }
                }
            });
        </script>
        ';

        // Add real-time monitoring JavaScript
        global $PAGE;
        $PAGE->requires->js_call_amd('block_pdfcounter/pdf_counter_monitor', 'init', [$COURSE->id]);

        $this->content->footer = '';

        return $this->content;
    }

    /**
     * Count PDF files in a course.
     *
     * @param int $courseid The course ID
     * @return int Number of PDF files
     */
    private function count_pdf_files($courseid) {
        global $DB;

        $sql = "
        SELECT 
            cm.id AS cmid,
            f.filename,
            f.filepath,
            f.mimetype,
            m.name AS modulename
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = 70
        JOIN {files} f ON f.contextid = ctx.id
        WHERE 
            cm.course = :courseid
            AND cm.deletioninprogress = 0
            AND cm.visible = 1
            AND f.component IN ('mod_resource', 'mod_folder')
            AND f.filearea = 'content'
            AND f.filename != '.'
            AND f.mimetype = 'application/pdf'
        ORDER BY cm.id, f.filepath, f.filename
        ";

        $params = array('courseid' => $courseid);
        $resources = $DB->get_records_sql($sql, $params);

        $pdfcount = 0;
        foreach ($resources as $resource) {
            if ($resource->mimetype == 'application/pdf' ||
                substr($resource->filename, -4) === '.pdf') {
                $pdfcount++;
            }
        }

        return $pdfcount;
    }

    /**
     * This block can be added to any page.
     *
     * @return array
     */
    public function applicable_formats() {
        // Permitir o bloco em todos os tipos de páginas
        return array(
            'course-view' => true,
            'site' => true,
            'mod' => true,
            'my' => true,
            'all' => true
        );
    }

    /**
     * Allow multiple instances of this block.
     *
     * @return boolean
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Block has configuration.
     *
     * @return boolean
     */
    public function has_config() {
        return false;
    }
}




