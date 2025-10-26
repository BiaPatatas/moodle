<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_block_pdfaccessibility_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025093001) {
        // Increase the length of the result field to accommodate longer values like 'non applicable', 'pdf not tagged', etc.
        $table = new xmldb_table('block_pdfaccessibility_test_results');
        $field = new xmldb_field('result', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, 'testname');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }

        upgrade_block_savepoint(true, 2025093001, 'pdfaccessibility');
    }

    return true;
}