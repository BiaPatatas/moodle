<?php
defined('MOODLE_INTERNAL') || die();

function block_pdfaccessibility_get_pdfs_from_context($contextid) {
    global $DB;

    $fs = get_file_storage();
    $context = context::instance_by_id($contextid);

    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', false, 'filename', false);

    $pdfs = [];
    foreach ($files as $file) {
        if (strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) === 'pdf') {
            $pdfs[] = $file;
        }
    }

    return $pdfs;
}
