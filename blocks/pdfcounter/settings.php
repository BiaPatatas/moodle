<?php
// Settings for block_pdfcounter.

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // QualWeb integration settings.
    $settings->add(new admin_setting_heading(
        'block_pdfcounter/qualwebheader',
        get_string('settings_qualweb_header', 'block_pdfcounter'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'block_pdfcounter/qualweb_api_baseurl',
        get_string('settings_qualweb_api_baseurl', 'block_pdfcounter'),
        get_string('settings_qualweb_api_baseurl_desc', 'block_pdfcounter'),
        'http://localhost:8081/api',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'block_pdfcounter/qualweb_apikey',
        get_string('settings_qualweb_apikey', 'block_pdfcounter'),
        get_string('settings_qualweb_apikey_desc', 'block_pdfcounter'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configtext(
        'block_pdfcounter/qualweb_monitoring_id',
        get_string('settings_qualweb_monitoring_id', 'block_pdfcounter'),
        get_string('settings_qualweb_monitoring_id_desc', 'block_pdfcounter'),
        '',
        PARAM_RAW_TRIMMED
    ));
}
