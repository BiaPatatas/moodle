<?php
// services.php for block_pdfcounter
$functions = array(
    'block_pdfcounter_qualweb_eval' => array(
        'classname'   => 'block_pdfcounter\\external\\qualweb_eval',
        'methodname'  => 'eval',
        'classpath'   => 'blocks/pdfcounter/classes/external/qualweb_eval.php',
        'description' => 'Async QualWeb evaluation and status',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities'=> '',
        'services'    => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
