<?php
defined('MOODLE_INTERNAL') || die();

function block_pdfaccessibility_get_pdfs_from_context($contextid) {
    $fs = get_file_storage();
    $context = context::instance_by_id($contextid);

    // Componentes e fileareas comuns do Moodle
    $areas = [
        ['component' => 'mod_resource', 'filearea' => 'content'],
        ['component' => 'mod_folder', 'filearea' => 'content'],
        ['component' => 'user', 'filearea' => 'draft'],
        ['component' => 'user', 'filearea' => 'private'],
        ['component' => 'course', 'filearea' => 'content'],
        ['component' => 'assignsubmission_file', 'filearea' => 'submission_files'],
        // Adicione outros componentes/fileareas relevantes do seu uso
    ];

    $pdfs = [];
    foreach ($areas as $area) {
        $files = $fs->get_area_files($context->id, $area['component'], $area['filearea'], false, 'filename', false);
        foreach ($files as $file) {
            if (strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION)) === 'pdf') {
                $pdfs[] = $file;
            }
        }
    }
    return $pdfs;
}
