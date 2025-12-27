<?php
header('Content-Type: application/json');
ini_set('display_errors', 0); // Para AJAX, não mostrar erros no output
error_reporting(E_ALL);

defined('MOODLE_INTERNAL') || die();

class block_pdfaccessibility extends block_base {
    // Função auxiliar para logar mensagens de debug
    private function pdfaccessibility_debug_log($msg) {
        $logfile = __DIR__ . '/debug/debug.txt';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logfile, "[$timestamp] $msg\n", FILE_APPEND);
    }


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
                        $this->content->text = '<div id="analyzer-result" style="margin-top: 1em; color: green;">PDFs encontrados:</div>';
                        foreach ($pdfs as $file) {
                            if (!is_object($file)) continue;
                            $filename = $file->get_filename();
                            $filepath = $CFG->dataroot . '/filedir/' . substr($file->get_contenthash(), 0, 2) . '/' . substr($file->get_contenthash(), 2, 2) . '/' . $file->get_contenthash();
                            $avaliacao = function_exists('block_pdfaccessibility_avaliar_pdf') ? block_pdfaccessibility_avaliar_pdf($filepath, $filename) : '<div style="margin-bottom:10px;"><strong>' . htmlspecialchars($filename) . '</strong><br><span style="color:gray;">(Exemplo: aqui entraria o relatório de acessibilidade deste PDF)</span></div>';
                            $this->content->text .= $avaliacao;
                        }
                    } else {
                        $this->content->text = '<div id="analyzer-result" style="margin-top: 1em; color: red;">Nenhum PDF encontrado.</div>';
                    }
                    $this->content->footer = '';
                    return $this->content;
                }
            }
            // Se não existe contexto de módulo, buscar PDFs na área de rascunho do usuário
            require_once(__DIR__ . '/lib.php');
            
            $pdfs = [];
         
            if (count($pdfs) > 0) {
                $this->content->text = '<div id="analyzer-result" style="margin-top: 1em; color: green;">PDFs em rascunho:</div>';
                foreach ($pdfs as $file) {
                    $filename = $file->get_filename();
                    $filepath = $CFG->dataroot . '/filedir/' . substr($file->get_contenthash(), 0, 2) . '/' . substr($file->get_contenthash(), 2, 2) . '/' . $file->get_contenthash();
                    $avaliacao = function_exists('block_pdfaccessibility_avaliar_pdf') ? block_pdfaccessibility_avaliar_pdf($filepath, $filename) : '<div style="margin-bottom:10px;"><strong>' . htmlspecialchars($filename) . '</strong><br><span style="color:gray;">(Exemplo: aqui entraria o relatório de acessibilidade deste PDF)</span></div>';
                    $this->content->text .= $avaliacao;
                }
            } else {
                $this->content->text = '<div id="analyzer-result" style="margin-top: 1em; color: orange;">Add a PDF to be evaluated</div>';
            }
            $this->content->footer = '';
            return $this->content;
        }
                // Verificar se o contextid existe na base de dados antes de buscar PDFs
                $context_exists = $DB->record_exists('context', array('id' => $contextid));
                $this->pdfaccessibility_debug_log('Contexto existe na base de dados? ' . ($context_exists ? 'SIM' : 'NÃO'));
                if (!$context_exists) {
                    $this->content = new stdClass();
                    $this->content->text = '<div style="color:red;">Contexto não encontrado na base de dados. O bloco só funciona em páginas de curso ou recurso já criados.</div>';
                    return $this->content;
                }
            
            $this->pdfaccessibility_debug_log('Usuário atual: ' . (isset($USER->id) ? $USER->id : 'N/A'));
        $this->pdfaccessibility_debug_log('DEBUG TESTE: método get_content chamado');
        
               
                
                if (isset($PAGE->cm) && isset($PAGE->cm->context)) {
                    $this->pdfaccessibility_debug_log('Contexto de módulo detectado: cmid=' . $PAGE->cm->id . ', contextid=' . $PAGE->cm->context->id);
                    $contextid = $PAGE->cm->context->id;
                } else {
                    $this->pdfaccessibility_debug_log('Usando contexto do curso: contextid=' . $contextid);
                }
                $this->pdfaccessibility_debug_log('DEBUG CONTEXTID: ' . $contextid);
        $this->pdfaccessibility_debug_log('Início do get_content');
        $this->pdfaccessibility_debug_log('Início do get_content');
        $this->pdfaccessibility_debug_log('COURSE id: ' . (isset($COURSE->id) ? $COURSE->id : 'N/A'));
        $this->pdfaccessibility_debug_log('CFG dataroot: ' . (isset($CFG->dataroot) ? $CFG->dataroot : 'N/A'));
        $this->pdfaccessibility_debug_log('Verificando contexto: ' . (isset($PAGE) ? 'PAGE OK' : 'PAGE N/A'));


        if ($this->content !== null) {
            return $this->content;
        }

        // Logar início do processamento
        $this->pdfaccessibility_debug_log('Iniciando processamento do bloco PDFAccessibility');
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
            $this->pdfaccessibility_debug_log('Contexto de módulo detectado: cmid=' . $PAGE->cm->id . ', contextid=' . $PAGE->cm->context->id);
            // Se estamos numa página de módulo (ex: pasta), usar o contextid do módulo
            $contextid = $PAGE->cm->context->id;
        }
        else {
    $this->pdfaccessibility_debug_log('Usando contexto do curso: contextid=' . $contextid);
}
        $this->pdfaccessibility_debug_log('Contextid usado para busca de PDFs: ' . $contextid);
        $pdfs = block_pdfaccessibility_get_pdfs_from_context($contextid);

        $this->pdfaccessibility_debug_log('PDFs encontrados: ' . count($pdfs));
        if (count($pdfs) > 0) {
            $this->content->text .= '<div id="analyzer-result" style="margin-top: 1em; color: green;">PDFs encontrados:</div>';
            foreach ($pdfs as $file) {
                if (!is_object($file)) {
                    $this->pdfaccessibility_debug_log('Arquivo não é objeto: ' . print_r($file, true));
                    continue;
                }
                $this->pdfaccessibility_debug_log('Processando arquivo: ' . $file->get_filename());
                $filename = $file->get_filename();
                $filepath = $CFG->dataroot . '/filedir/' . substr($file->get_contenthash(), 0, 2) . '/' . substr($file->get_contenthash(), 2, 2) . '/' . $file->get_contenthash();
                $this->pdfaccessibility_debug_log('Arquivo físico: ' . $filepath);
                if (!file_exists($filepath)) {
                    $this->pdfaccessibility_debug_log('Arquivo físico não existe: ' . $filepath);
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
                                // Se não encontrou resultado, mostra como não avaliado
                                $this->content->text .= '<div style="display:flex;align-items:flex-start;margin-top:8px;margin-bottom:10px; background:#f8f9fa; border-radius:6px;padding:6px 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);opacity:1;">';
                                $this->content->text .= '<div style="font-weight:bold; font-size: 0.925rem; color: #1e1e1e;">' . $label . ' ' . $icon . '</div>';
                                $this->content->text .= '<div style="margin-left:10px;">Not evaluated</div>';
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


