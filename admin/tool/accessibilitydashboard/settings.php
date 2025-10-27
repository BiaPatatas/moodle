<?php
/**
 * PDF Accessibility Admin Tool Settings
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Add to the Reports section of site administration
    $ADMIN->add('reports', new admin_externalpage(
        'tool_accessibilitydashboard',
        get_string('pluginname', 'tool_accessibilitydashboard'),
        new moodle_url('/admin/tool/accessibilitydashboard/index.php'),
        'moodle/site:config'
    ));
}