
<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Language strings for block_pdfcounter
 *
 * @package    block_pdfcounter
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Accessibility Dashboard';
$string['pdfresources'] = 'Number of PDF files';
$string['nocourse'] = 'No course available to display PDF count.';

$string['overall'] = 'Overall Accessibility';
$string['pendingmsg_analyzing'] = 'This tool is still analyzing {$a} PDF(s) on this page.';
$string['pendingmsg_loading'] = 'This tool is currently analyzing the accessibility of this course\'s PDFs…';
$string['results_title'] = 'PDF Accessibility Results';
$string['noissues'] = 'No PDF accessibility issues found.';
$string['tests_failed'] = '{$a->failed} of {$a->total} tests failed';
$string['download_report'] = 'Download Report';
$string['historical_trends'] = 'Historical Trends';

$string['learnmore'] = 'Read More';
$string['learnmore_close'] = 'Close';
$string['learnmore_intro'] = 'The Accessibility Dashboard offers an overview of course accessibility, tracks progress, and highlights PDF accessibility issues, with detailed reports for each file.';
$string['learnmore_resources'] = 'Resources:';
$string['learnmore_fcul_guide'] = 'FCUL Accessibility Guide';
$string['learnmore_fcul_guide_title'] = 'Open FCUL Accessibility Guide';
$string['learnmore_wcag'] = 'Accessible PDF Best Practices - WCAG 2.2';
$string['learnmore_wcag_title'] = 'WCAG 2.2 PDF Techniques';
$string['progress_chart_label'] = 'Progress (%)';
$string['totalpdfs'] = '{$a} PDFs';

// QualWeb integration.
$string['qualweb_title'] = 'Website accessibility (QualWeb)';
$string['qualweb_issues_summary'] = 'Passed {$a->passed}, Warnings {$a->warnings}, Failed {$a->failed}';

// Settings.
$string['settings_qualweb_header'] = 'QualWeb integration';
$string['settings_qualweb_api_baseurl'] = 'QualWeb API base URL';
$string['settings_qualweb_api_baseurl_desc'] = 'Base URL of the QualWeb Accessibility Monitoring REST API (e.g. http://localhost:8081/api).';
$string['settings_qualweb_apikey'] = 'QualWeb API key';
$string['settings_qualweb_apikey_desc'] = 'Optional API key to send in the X-API-Key header when calling the QualWeb API.';
$string['settings_qualweb_monitoring_id'] = 'Monitoring registry ID';
$string['settings_qualweb_monitoring_id_desc'] = 'ID of the monitoring registry in QualWeb whose overall score and issue statistics should be displayed in the block.';
