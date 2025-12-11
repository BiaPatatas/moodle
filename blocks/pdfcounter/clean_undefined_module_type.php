<?php
// clean_undefined_module_type.php
require_once(__DIR__ . '/../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Apenas administradores podem executar este script.');
}

global $DB;
$count = $DB->count_records_select('block_pdfcounter_qualweb_jobs', "status = 'error' AND (module_type = 'undefined' OR module_type IS NULL)");
if ($count == 0) {
    echo "Nenhum job com module_type 'undefined' encontrado.\n";
    exit;
}
$DB->delete_records_select('block_pdfcounter_qualweb_jobs', "status = 'error' AND (module_type = 'undefined' OR module_type IS NULL)");
echo "Jobs antigos com module_type 'undefined' removidos: $count\n";
