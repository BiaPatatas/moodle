<?php


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
        
        
        
       

    $this->content->text .= '<div id="analyzer-result" style="margin-top: 1em; color: green;"></div>';

        
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
            'all' => true, // Allow on all pages except site and mod pages
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


