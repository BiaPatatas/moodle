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
        global $COURSE, $DB, $CFG;

        if ($this->content !== null) {
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
                $filename = $file->get_filename();
                $filepath = $CFG->dataroot . '/filedir/' . substr($file->get_contenthash(), 0, 2) . '/' . substr($file->get_contenthash(), 2, 2) . '/' . $file->get_contenthash();
                // Aqui você pode chamar sua função de avaliação do PDF e exibir o resultado detalhado
                $avaliacao = '';
                if (function_exists('block_pdfaccessibility_avaliar_pdf')) {
                    $avaliacao = block_pdfaccessibility_avaliar_pdf($filepath, $filename);
                } else {
                    $avaliacao = '<div style="margin-bottom:10px;"><strong>' . htmlspecialchars($filename) . '</strong><br><span style="color:gray;">(Exemplo: aqui entraria o relatório de acessibilidade deste PDF)</span></div>';
                }
                $this->content->text .= $avaliacao;
            }
        } else {
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
        return array(
            'course-view' => true,
            'site' => false,
            'mod' => true,
            'my' => false,
            'admin' => true,
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


