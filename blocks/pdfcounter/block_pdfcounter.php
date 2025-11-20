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

        // DEBUG: Open debug log file (create if not exists, check write permissions)
        $debuglogfile = $CFG->dirroot . '/blocks/pdfcounter/debug/debug_pdfcounter.txt';
        $debuglog = @fopen($debuglogfile, 'a');
        if ($debuglog === false) {
            // Fallback: try to create the file
            @touch($debuglogfile);
            @chmod($debuglogfile, 0666);
            $debuglog = @fopen($debuglogfile, 'a');
        }
        if ($debuglog !== false) {
            fwrite($debuglog, "\n==== PDFCOUNTER DEBUG START ====\n");
            fwrite($debuglog, "Course ID: {$COURSE->id}\n");
        } else {
            // Fallback: log to syslog if file cannot be opened
            error_log("PDFCOUNTER: Não foi possível abrir debug_pdfcounter.txt para escrita em $debuglogfile");
        }

        // Buscar todos os PDFs visíveis no curso (mod_resource e mod_folder)
        $sql = "SELECT f.filename, f.contenthash, cm.id as cmid,
            CASE 
                WHEN m.name = 'resource' THEN r.name
                WHEN m.name = 'folder' THEN fo.name
                ELSE ''
            END as resource_name,
            m.name as modulename
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            LEFT JOIN {resource} r ON (m.name = 'resource' AND r.id = cm.instance)
            LEFT JOIN {folder} fo ON (m.name = 'folder' AND fo.id = cm.instance)
            JOIN {context} ctx ON ctx.instanceid = cm.id
            JOIN {files} f ON f.contextid = ctx.id
            WHERE cm.course = :courseid
            AND cm.deletioninprogress = 0
            AND cm.visible = 1
            AND m.name IN ('resource', 'folder')
            AND f.component IN ('mod_resource', 'mod_folder')
            AND f.filearea = 'content'
            AND f.filename != '.'";
        $files = $DB->get_records_sql($sql, array('courseid' => $COURSE->id));

        // Avaliar e guardar PDFs visíveis que ainda não estão na base de dados
        foreach ($files as $file) {
            if (substr($file->filename, -4) === '.pdf') {
                if ($debuglog !== false) fwrite($debuglog, "[mod_resource/mod_folder] Found PDF: {$file->filename} | hash: {$file->contenthash}\n");
                $filehash = $file->contenthash;
                $pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', [
                    'filehash' => $filehash,
                    'courseid' => $COURSE->id
                ]);
                if (!$pdfrecord) {
                    $filepath = $CFG->dataroot . '/filedir/' . substr($filehash, 0, 2) . '/' . substr($filehash, 2, 2) . '/' . $filehash;
                    if (!file_exists($filepath)) {
                        if ($debuglog !== false) fwrite($debuglog, "[mod_resource/mod_folder] File not found: $filepath\n");
                        continue;
                    }
                    $python_commands = ['python3', 'python', 'py', 'python.exe'];
                    $script = $CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility.py';
                    $result = null;
                    foreach ($python_commands as $python) {
                        $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($filepath);
                        if ($debuglog !== false) fwrite($debuglog, "[mod_resource/mod_folder] Running: $command\n");
                        $output = shell_exec($command . ' 2>&1');
                        if ($debuglog !== false) fwrite($debuglog, "[mod_resource/mod_folder] Output: $output\n");
                        $decoded = json_decode($output, true);
                        if ($decoded && is_array($decoded)) {
                            $result = $decoded;
                            break;
                        }
                    }
                    if (!$result || !is_array($result)) {
                        if ($debuglog !== false) fwrite($debuglog, "[mod_resource/mod_folder] Python analysis failed for $filepath\n");
                        continue;
                    }
                    $pdfrecord = new stdClass();
                    $pdfrecord->courseid = $COURSE->id;
                    $pdfrecord->userid = $USER->id;
                    $pdfrecord->filehash = $filehash;
                    // Remove duplo .pdf do nome
                    $filename = $file->filename;
                    if (substr_count($filename, '.pdf') > 1) {
                        $filename = preg_replace('/(\.pdf)+$/', '.pdf', $filename);
                    }
                    $pdfrecord->filename = $filename;
                    $pdfrecord->timecreated = time();
                    $pdfid = $DB->insert_record('block_pdfaccessibility_pdf_files', $pdfrecord, true);
                    pdf_accessibility_config::process_and_store_results($DB, $result, $pdfid);
                    if ($debuglog !== false) fwrite($debuglog, "[mod_resource/mod_folder] PDF evaluated and stored: {$filename}\n");
                }
            }
        }

// NOVO: Buscar PDFs em links de páginas mod_page
$sqlpages = "SELECT cm.id as cmid, p.content, ctx.id as contextid
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    JOIN {page} p ON (m.name = 'page' AND p.id = cm.instance)
    JOIN {context} ctx ON ctx.instanceid = cm.id
    WHERE cm.course = :courseid
    AND cm.deletioninprogress = 0
    AND cm.visible = 1
    AND m.name = 'page'";
$pages = $DB->get_records_sql($sqlpages, array('courseid' => $COURSE->id));
$pagelinks = [];
foreach ($pages as $page) {
    fwrite($debuglog, "[mod_page] Checking page cmid={$page->cmid}, contextid={$page->contextid}\n");
    // Extrair links <a href="...pdf"> do conteúdo da página
    if (preg_match_all('/<a[^>]+href=\"([^\"]+\.pdf)\"[^>]*>/i', $page->content, $matches)) {
        foreach ($matches[1] as $pdfurl) {
            fwrite($debuglog, "[mod_page] Found PDF link: $pdfurl\n");
            $pagelinks[] = [
                'url' => $pdfurl,
                'contextid' => $page->contextid,
                'cmid' => $page->cmid
            ];
        }
    }
    // Também extrai links pluginfile.php que terminam em .pdf
    if (preg_match_all('/<a[^>]+href=\"([^\"]*pluginfile\.php[^\"]+\.pdf)\"[^>]*>/i', $page->content, $matches2)) {
        foreach ($matches2[1] as $pdfurl) {
            fwrite($debuglog, "[mod_page] Found pluginfile.php PDF link: $pdfurl\n");
            $pagelinks[] = [
                'url' => $pdfurl,
                'contextid' => $page->contextid,
                'cmid' => $page->cmid
            ];
        }
    }
}

// NOVO: Buscar PDFs em links de mod_url
$sqlurls = "SELECT cm.id as cmid, u.externalurl, ctx.id as contextid
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    JOIN {url} u ON (m.name = 'url' AND u.id = cm.instance)
    JOIN {context} ctx ON ctx.instanceid = cm.id
    WHERE cm.course = :courseid
    AND cm.deletioninprogress = 0
    AND cm.visible = 1
    AND m.name = 'url'";
$urls = $DB->get_records_sql($sqlurls, array('courseid' => $COURSE->id));
$urllinks = [];
foreach ($urls as $url) {
    fwrite($debuglog, "[mod_url] Checking url cmid={$url->cmid}, contextid={$url->contextid}, externalurl={$url->externalurl}\n");
    // Só considera links que terminam em .pdf
    if (preg_match('/\.pdf($|\?)/i', $url->externalurl)) {
        fwrite($debuglog, "[mod_url] Found PDF link: {$url->externalurl}\n");
        $urllinks[] = [
            'url' => $url->externalurl,
            'contextid' => $url->contextid,
            'cmid' => $url->cmid
        ];
    }
}

// Avaliar e guardar PDFs encontrados em links de páginas mod_page
foreach ($pagelinks as $plink) {
    fwrite($debuglog, "[mod_page] Evaluating link: {$plink['url']} | contextid={$plink['contextid']} | cmid={$plink['cmid']}\n");
    // Tenta obter o arquivo local se for pluginfile.php ou @@PLUGINFILE@@
    $filehash = null;
    $filename = basename($plink['url']);
    // Remove duplo .pdf do nome
    if (substr_count($filename, '.pdf') > 1) {
        $filename = preg_replace('/(\.pdf)+$/', '.pdf', $filename);
    }
    $pluginfile_pattern = '/(@@PLUGINFILE@@|\/pluginfile\.php\/)/';
    if (preg_match($pluginfile_pattern, $plink['url'])) {
        // Buscar o arquivo pelo nome e contexto
        $fsql = "SELECT contenthash FROM {files} WHERE filename = :filename AND contextid = :contextid";
        $frec = $DB->get_record_sql($fsql, [
            'filename' => $filename,
            'contextid' => $plink['contextid']
        ]);
        if ($frec) {
            $filehash = $frec->contenthash;
            if ($debuglog !== false) fwrite($debuglog, "[mod_page] Found filehash for pluginfile: $filehash\n");
        } else {
            if ($debuglog !== false) fwrite($debuglog, "[mod_page] No filehash found for pluginfile link: $filename\n");
        }
    }
    // Só avalia se não estiver na base de dados
    if ($filehash) {
        $pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', [
            'filehash' => $filehash,
            'courseid' => $COURSE->id
        ]);
        if ($pdfrecord) {
            if ($debuglog !== false) fwrite($debuglog, "[mod_page] PDF already in DB: $filename | hash: $filehash\n");
        } else {
            $filepath = $CFG->dataroot . '/filedir/' . substr($filehash, 0, 2) . '/' . substr($filehash, 2, 2) . '/' . $filehash;
            if (!file_exists($filepath)) {
                if ($debuglog !== false) fwrite($debuglog, "[mod_page] File not found: $filepath\n");
                continue;
            }
            $python_commands = ['python3', 'python', 'py', 'python.exe'];
            $script = $CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility.py';
            $result = null;
            foreach ($python_commands as $python) {
                $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($filepath);
                if ($debuglog !== false) fwrite($debuglog, "[mod_page] Running: $command\n");
                $output = shell_exec($command . ' 2>&1');
                if ($debuglog !== false) fwrite($debuglog, "[mod_page] Output: $output\n");
                $decoded = json_decode($output, true);
                if ($decoded && is_array($decoded)) {
                    $result = $decoded;
                    break;
                }
            }
            if (!$result || !is_array($result)) {
                if ($debuglog !== false) fwrite($debuglog, "[mod_page] Python analysis failed for $filepath\n");
                continue;
            }
            $pdfrecord = new stdClass();
            $pdfrecord->courseid = $COURSE->id;
            $pdfrecord->userid = $USER->id;
            $pdfrecord->filehash = $filehash;
            $pdfrecord->filename = $filename;
            $pdfrecord->timecreated = time();
            $pdfid = $DB->insert_record('block_pdfaccessibility_pdf_files', $pdfrecord, true);
            pdf_accessibility_config::process_and_store_results($DB, $result, $pdfid);
            if ($debuglog !== false) fwrite($debuglog, "[mod_page] PDF evaluated and stored: $filename\n");
        }
    }
}

// Avaliar e guardar PDFs encontrados em links de mod_url
foreach ($urllinks as $ulink) {
    fwrite($debuglog, "[mod_url] Evaluating link: {$ulink['url']} | contextid={$ulink['contextid']} | cmid={$ulink['cmid']}\n");
    $filehash = null;
    $filename = basename($ulink['url']);
 
    if (substr_count($filename, '.pdf') > 1) {
        $filename = preg_replace('/(\.pdf)+$/', '.pdf', $filename);
    }
    // Tenta obter o arquivo local se for pluginfile.php
    if (strpos($ulink['url'], '/pluginfile.php/') !== false) {
        $fsql = "SELECT contenthash FROM {files} WHERE filename = :filename AND contextid = :contextid";
        $frec = $DB->get_record_sql($fsql, [
            'filename' => $filename,
            'contextid' => $ulink['contextid']
        ]);
        if ($frec) {
            $filehash = $frec->contenthash;
            fwrite($debuglog, "[mod_url] Found filehash for pluginfile.php: $filehash\n");
        } else {
            fwrite($debuglog, "[mod_url] No filehash found for pluginfile.php link: $filename\n");
        }
    }
    // Se for link externo, faz download temporário e avalia
    if (!$filehash && preg_match('/^https?:\/\/.+\.pdf($|\?)/i', $ulink['url'])) {
        $temphash = null;
        // Verifica se já existe na base pelo hash do arquivo remoto
        $tempfile = tempnam(sys_get_temp_dir(), 'pdfext_');
        $downloaded = @file_put_contents($tempfile, @file_get_contents($ulink['url']));
        if ($downloaded && file_exists($tempfile)) {
            $temphash = sha1_file($tempfile);
            $pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', [
                'filehash' => $temphash,
                'courseid' => $COURSE->id
            ]);
            if ($pdfrecord) {
                fwrite($debuglog, "[mod_url] External PDF already in DB: $filename | hash: $temphash\n");
            } else {
                fwrite($debuglog, "[mod_url] Downloaded external PDF to $tempfile\n");
                $python_commands = ['python3', 'python', 'py', 'python.exe'];
                $script = $CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility.py';
                $result = null;
                foreach ($python_commands as $python) {
                    $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($tempfile);
                    fwrite($debuglog, "[mod_url] Running: $command\n");
                    $output = shell_exec($command . ' 2>&1');
                    fwrite($debuglog, "[mod_url] Output: $output\n");
                    $decoded = json_decode($output, true);
                    if ($decoded && is_array($decoded)) {
                        $result = $decoded;
                        break;
                    }
                }
                if (!$result || !is_array($result)) {
                    fwrite($debuglog, "[mod_url] Python analysis failed for $tempfile\n");
                } else {
                    fwrite($debuglog, "[mod_url] External PDF evaluated: {$ulink['url']}\n");
                    // Salva resultado no banco de dados
                    $pdfrecord = new stdClass();
                    $pdfrecord->courseid = $COURSE->id;
                    $pdfrecord->userid = $USER->id;
                    $pdfrecord->filehash = $temphash;
                    $pdfrecord->filename = $filename;
                    $pdfrecord->timecreated = time();
                    $pdfid = $DB->insert_record('block_pdfaccessibility_pdf_files', $pdfrecord, true);
                    pdf_accessibility_config::process_and_store_results($DB, $result, $pdfid);
                    fwrite($debuglog, "[mod_url] External PDF saved in DB: $filename | hash: $temphash\n");
                }
            }
            @unlink($tempfile);
        } else {
            fwrite($debuglog, "[mod_url] Failed to download external PDF: {$ulink['url']}\n");
        }
    }
    if ($filehash) {
        $pdfrecord = $DB->get_record('block_pdfaccessibility_pdf_files', [
            'filehash' => $filehash,
            'courseid' => $COURSE->id
        ]);
        if (!$pdfrecord) {
            $filepath = $CFG->dataroot . '/filedir/' . substr($filehash, 0, 2) . '/' . substr($filehash, 2, 2) . '/' . $filehash;
            if (!file_exists($filepath)) {
                fwrite($debuglog, "[mod_url] File not found: $filepath\n");
                continue;
            }
            $python_commands = ['python3', 'python', 'py', 'python.exe'];
            $script = $CFG->dirroot . '/blocks/pdfaccessibility/pdf_accessibility.py';
            $result = null;
            foreach ($python_commands as $python) {
                $command = escapeshellarg($python) . ' ' . escapeshellarg($script) . ' ' . escapeshellarg($filepath);
                fwrite($debuglog, "[mod_url] Running: $command\n");
                $output = shell_exec($command . ' 2>&1');
                fwrite($debuglog, "[mod_url] Output: $output\n");
                $decoded = json_decode($output, true);
                if ($decoded && is_array($decoded)) {
                    $result = $decoded;
                    break;
                }
            }
            if (!$result || !is_array($result)) {
                fwrite($debuglog, "[mod_url] Python analysis failed for $filepath\n");
                continue;
            }
            $pdfrecord = new stdClass();
            $pdfrecord->courseid = $COURSE->id;
            $pdfrecord->userid = $USER->id;
            $pdfrecord->filehash = $filehash;
            $pdfrecord->filename = $filename;
            $pdfrecord->timecreated = time();
            $pdfid = $DB->insert_record('block_pdfaccessibility_pdf_files', $pdfrecord, true);
            pdf_accessibility_config::process_and_store_results($DB, $result, $pdfid);
            fwrite($debuglog, "[mod_url] PDF evaluated and stored: $filename\n");
        }
    }
}

        // Remover PDFs da base de dados que não estão mais visíveis (inclui PDFs em pastas)
        $dbpdfs = $DB->get_records('block_pdfaccessibility_pdf_files', ['courseid' => $COURSE->id]);
        $visible_hashes = array();
        foreach ($files as $file) {
            if (substr($file->filename, -4) === '.pdf') {
                $visible_hashes[] = $file->contenthash;
            }
        }
        // Adiciona também os PDFs detectados em mod_page e mod_url
        foreach ($pagelinks as $plink) {
            $filename = basename($plink['url']);
            $pluginfile_pattern = '/(@@PLUGINFILE@@|\/pluginfile\.php\/)/';
            if (preg_match($pluginfile_pattern, $plink['url'])) {
                $fsql = "SELECT contenthash FROM {files} WHERE filename = :filename AND contextid = :contextid";
                $frec = $DB->get_record_sql($fsql, [
                    'filename' => $filename,
                    'contextid' => $plink['contextid']
                ]);
                if ($frec) {
                    $visible_hashes[] = $frec->contenthash;
                }
            }
        }
        foreach ($urllinks as $ulink) {
            $filename = basename($ulink['url']);
            if (strpos($ulink['url'], '/pluginfile.php/') !== false) {
                $fsql = "SELECT contenthash FROM {files} WHERE filename = :filename AND contextid = :contextid";
                $frec = $DB->get_record_sql($fsql, [
                    'filename' => $filename,
                    'contextid' => $ulink['contextid']
                ]);
                if ($frec) {
                    $visible_hashes[] = $frec->contenthash;
                }
            }
            // Se for link externo, adiciona o hash do arquivo baixado
            if (preg_match('/^https?:\/\/.+\.pdf($|\?)/i', $ulink['url'])) {
                $tempfile = tempnam(sys_get_temp_dir(), 'pdfext_');
                $downloaded = @file_put_contents($tempfile, @file_get_contents($ulink['url']));
                if ($downloaded && file_exists($tempfile)) {
                    $temphash = sha1_file($tempfile);
                    $visible_hashes[] = $temphash;
                    @unlink($tempfile);
                }
            }
        }
        foreach ($dbpdfs as $dbpdf) {
            if (!in_array($dbpdf->filehash, $visible_hashes)) {
                if ($debuglog !== false) fwrite($debuglog, "[cleanup] Removing PDF from DB: {$dbpdf->filename} | hash: {$dbpdf->filehash}\n");
                $DB->delete_records('block_pdfaccessibility_test_results', ['fileid' => $dbpdf->id]);
                $DB->delete_records('block_pdfaccessibility_pdf_files', ['id' => $dbpdf->id]);
            }
        }
        // DEBUG: Close debug log file
        if ($debuglog !== false) {
            fwrite($debuglog, "==== PDFCOUNTER DEBUG END ====\n\n");
            fclose($debuglog);
        }

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
                            this.innerHTML = "<i class=\\"fa fa-times\\" aria-hidden=\\"true\\"></i> Close";
                        } else {
                            learnMoreContent.style.display = "none";
                            this.innerHTML = "<i class=\\"fa fa-info-circle\\" aria-hidden=\\"true\\"></i> Read More";
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
          
            $filename = $issue['filename'];
            if (substr_count($filename, '.pdf') > 1) {
                $filename = preg_replace('/(\.pdf)+$/', '.pdf', $filename);
            }
            $params = [
                'filename' => urlencode($filename),
                'courseid' => $COURSE->id
            ];
            // Se for PDF externo (hash não igual ao de arquivos locais), adiciona filehash
            if (isset($issue['fileid'])) {
                $pdfrec = $DB->get_record('block_pdfaccessibility_pdf_files', ['id' => $issue['fileid']]);
                if ($pdfrec && !empty($pdfrec->filehash)) {
                    $params['filehash'] = $pdfrec->filehash;
                }
            }
            $download_url = new moodle_url('/blocks/pdfcounter/download_report.php', $params);
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
                        <i class="fa-solid fa-file-pdf"></i> ' . htmlspecialchars($issue['display_name']) .'
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