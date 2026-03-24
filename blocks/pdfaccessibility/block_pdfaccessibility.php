<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // Para AJAX, não mostrar erros no output
error_reporting(E_ALL);

defined('MOODLE_INTERNAL') || die();

class block_pdfaccessibility extends block_base {


    public function init() {
        $this->title = get_string('pluginname', 'block_pdfaccessibility');
    }

    public function get_required_javascript() {
        parent::get_required_javascript(); // <-- This is important!
        global $PAGE;
        $PAGE->requires->js_call_amd('block_pdfaccessibility/pdf_analyzer', 'init');
    }

    public function get_content() {
        global $COURSE, $DB, $CFG, $USER, $PAGE;
        $contextid = $COURSE->id;
        $ismodedit = (strpos($_SERVER['SCRIPT_NAME'], 'modedit.php') !== false);
        $this->content = new stdClass();
        if ($ismodedit) {
            // Se já existe contexto de módulo, avalia PDFs normalmente
            if (isset($PAGE->cm) && isset($PAGE->cm->context)) {
                $contextid = $PAGE->cm->context->id;
                $context_exists = $DB->record_exists('context', array('id' => $contextid));
                if ($context_exists) {
                    require_once(__DIR__ . '/lib.php');
                    $pdfs = block_pdfaccessibility_get_pdfs_from_context($contextid);
                    if (count($pdfs) > 0) {
                        $this->content->text = '<div id="analyzer-result" style="margin-top: 1em; color: green;">' . get_string('pdfs_found', 'block_pdfaccessibility') . '</div>';
                        foreach ($pdfs as $file) {
                            if (!is_object($file)) continue;
                            $filename = $file->get_filename();
                            $filepath = $CFG->dataroot . '/filedir/' . substr($file->get_contenthash(), 0, 2) . '/' . substr($file->get_contenthash(), 2, 2) . '/' . $file->get_contenthash();
                            $avaliacao = function_exists('block_pdfaccessibility_avaliar_pdf') ? block_pdfaccessibility_avaliar_pdf($filepath, $filename) : '<div style="margin-bottom:10px;"><strong>' . htmlspecialchars($filename) . '</strong><br><span style="color:gray;">(Exemplo: aqui entraria o relatório de acessibilidade deste PDF)</span></div>';
                            $this->content->text .= $avaliacao;
                        }
                    } else {
                        $this->content->text = '<div id="analyzer-result" style="margin-top: 1em; color: red;">' . get_string('no_pdfs_found', 'block_pdfaccessibility') . '</div>';
                    }
                    $this->content->footer = '';
                    return $this->content;
                }
                // Contexto não encontrado no ramo modedit.
                if (!$context_exists) {
                    pdf_accessibility_log_error('block_pdfaccessibility: context not found (modedit branch)', [
                        'contextid' => $contextid,
                        'courseid' => $COURSE->id ?? null,
                        'script' => $_SERVER['SCRIPT_NAME'] ?? null,
                    ], 'block_pdfaccessibility.log');
                }
            }
            // Se não existe contexto de módulo, buscar PDFs na área de rascunho do usuário
            require_once(__DIR__ . '/lib.php');
            
            $pdfs = [];
         
            if (count($pdfs) > 0) {
                $this->content->text = '<div id="analyzer-result" style="margin-top: 1em; color: green;">' . get_string('pdfs_in_draft', 'block_pdfaccessibility') . '</div>';
                foreach ($pdfs as $file) {
                    $filename = $file->get_filename();
                    $filepath = $CFG->dataroot . '/filedir/' . substr($file->get_contenthash(), 0, 2) . '/' . substr($file->get_contenthash(), 2, 2) . '/' . $file->get_contenthash();
                    $avaliacao = function_exists('block_pdfaccessibility_avaliar_pdf') ? block_pdfaccessibility_avaliar_pdf($filepath, $filename) : '<div style="margin-bottom:10px;"><strong>' . htmlspecialchars($filename) . '</strong><br><span style="color:gray;">(Exemplo: aqui entraria o relatório de acessibilidade deste PDF)</span></div>';
                    $this->content->text .= $avaliacao;
                }
            } else {
                $this->content->text = '<div id="analyzer-result" style="margin-top: 1em; color: orange;">' . get_string('add_pdf_to_evaluate', 'block_pdfaccessibility') . '</div>';
            }
            $this->content->footer = '';
            return $this->content;
        }
        // Verificar se o contextid existe na base de dados antes de buscar PDFs
        $context_exists = $DB->record_exists('context', array('id' => $contextid));
        if (!$context_exists) {
            pdf_accessibility_log_error('block_pdfaccessibility: context not found (course view)', [
                'contextid' => $contextid,
                'courseid' => $COURSE->id ?? null,
                'script' => $_SERVER['SCRIPT_NAME'] ?? null,
            ], 'block_pdfaccessibility.log');
            $this->content = new stdClass();
            $this->content->text = '<div style="color:red;">' . get_string('context_not_found', 'block_pdfaccessibility') . '</div>';
            return $this->content;
        }

        $this->content = new stdClass();
        // Check if we're in a course
        if (empty($COURSE) || $COURSE->id == SITEID) {
            $this->content->text = get_string('nocourse', 'block_pdfaccessibility');
            return $this->content;
        }

        // Buscar todos os PDFs do contexto correto (curso ou módulo)
        require_once(__DIR__ . '/lib.php');
        global $PAGE;
        $contextid = $COURSE->id;
        if (isset($PAGE->cm) && isset($PAGE->cm->context)) {
            // Se estamos numa página de módulo (ex: pasta), usar o contextid do módulo
            $contextid = $PAGE->cm->context->id;
        }
        
        $pdfs = block_pdfaccessibility_get_pdfs_from_context($contextid);

        if (count($pdfs) > 0) {
            $this->content->text .= '<div id="analyzer-result" style="margin-top: 1em; color: green;">PDFs encontrados:</div>';
            foreach ($pdfs as $file) {
                if (!is_object($file)) {
                    continue;
                }
                $filename = $file->get_filename();
                $filepath = $CFG->dataroot . '/filedir/' . substr($file->get_contenthash(), 0, 2) . '/' . substr($file->get_contenthash(), 2, 2) . '/' . $file->get_contenthash();
                if (!file_exists($filepath)) {
                    pdf_accessibility_log_error('block_pdfaccessibility: physical file missing', [
                        'filepath' => $filepath,
                        'filename' => $filename,
                        'courseid' => $COURSE->id ?? null,
                    ], 'block_pdfaccessibility.log');
                }
                // Renderização manual do relatório de acessibilidade
                $this->content->text .= '<div style="margin-bottom:10px;">';
                $this->content->text .= '<strong>' . htmlspecialchars($filename) . '</strong>';
                // Buscar resultados dos testes
                $pdfid = null;
                if (isset($file->id)) {
                    $pdfid = $file->id;
                } else if (isset($file->fileid)) {
                    $pdfid = $file->fileid;
                }
                if ($pdfid) {
                    $testresults = $DB->get_records('block_pdfaccessibility_test_results', ['fileid' => $pdfid]);
                    if ($testresults) {
                        $this->content->text .= '<div style="margin-top:6px;">';
                        foreach (pdf_accessibility_config::TEST_CONFIG as $testkey => $testcfg) {
                            $label = $testcfg['label'];
                            $icon = pdf_accessibility_config::get_info_icon_html($testkey);
                            // Procura resultado do teste
                            $found = false;
                            foreach ($testresults as $test) {
                                if ($test->testname === $testkey) {
                                    $found = true;
                                    $status = $test->result;
                                    $color = ($status === 'pass') ? '#eafaf1' : (($status === 'fail') ? '#fff4f4' : '#fffbe6');
                                    $iconhtml = $icon;
                                    $this->content->text .= '<div style="display:flex;align-items:flex-start;margin-top:8px;margin-bottom:10px; background:' . $color . '; border-radius:6px;padding:6px 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);opacity:1;">';
                                    $this->content->text .= '<div style="font-weight:bold; font-size: 0.925rem; color: #1e1e1e;">' . $label . ' ' . $iconhtml . '</div>';
                                    $this->content->text .= '<div style="margin-left:10px;">' . ucfirst($status) . '</div>';
                                    $this->content->text .= '</div>';
                                    break;
                                }
                            }
                            if (!$found) {
                            
                                $this->content->text .= '<div style="display:flex;align-items:flex-start;margin-top:8px;margin-bottom:10px; background:#f8f9fa; border-radius:6px;padding:6px 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);opacity:1;">';
                                $this->content->text .= '<div style="font-weight:bold; font-size: 0.925rem; color: #1e1e1e;">' . $label . ' ' . $icon . '</div>';
                                $this->content->text .= '<div style="margin-left:10px;">' . get_string('not_evaluated', 'block_pdfaccessibility') . '</div>';
                                $this->content->text .= '</div>';
                            }
                        }
                        $this->content->text .= '</div>';
                    }
                }
                $this->content->text .= '</div>';
            }
        } else {
            $this->pdfaccessibility_debug_log('Nenhum PDF encontrado no contexto.');
            $this->content->text .= '<div id="analyzer-result" style="margin-top: 1em; color: red;">Nenhum PDF encontrado.</div>';
        }

        $this->content->footer = '';
        return $this->content;
    }


        
    


    

    /**
     * This block can be added to any page.
     *
     * @return boolean
     */
    public function applicable_formats() {
        // Permite o bloco em páginas de curso, módulos e edição de módulos
        return array(
            'course-view' => true,
            'mod' => true,
            'mod-edit' => true,
            'site' => false,
            'my' => false
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


