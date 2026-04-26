
<?php
/**
 * Language strings for block_pdfaccessibility.
 *
 * @package    block_pdfaccessibility.
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'PDF Accessibility Checker';
$string['summary'] = 'Summary';
$string['report'] = 'Detailed Report';
$string['nocourse'] = 'There is no course available to display this block.';
$string['pdfs_found'] = 'PDFs found:';
$string['no_pdfs_found'] = 'No PDFs found.';
$string['pdfs_in_draft'] = 'Draft PDFs:';
$string['add_pdf_to_evaluate'] = 'Add a PDF to be evaluated.';
$string['context_not_found'] = 'Context not found in the database. This block only works on course pages or already created resources.';
$string['not_evaluated'] = 'Not evaluated';
$string['error_analyzing'] = 'Error analyzing PDF.';
$string['network_error'] = 'Network or server error while analyzing PDF.';
$string['analyzing'] = 'Analyzing PDF accessibility, please wait...';
$string['status_pass'] = 'Pass';
$string['status_fail'] = 'Fail';
$string['status_nonapplicable'] = 'Non applicable';
$string['status_not_tagged'] = 'PDF not tagged';
$string['tests_passed_label'] = 'passed';
$string['tests_failed_label'] = 'failed';
$string['not_tagged_help'] = 'This PDF is not tagged. We are unable to check the accessibility of this content.';

// Test labels, descriptions and help link text (used in detailed report)
$string['test_title_label'] = 'Document Title Check';
$string['test_title_desc'] = 'Checks if the PDF has a real title set in its properties.';

$string['test_languagesmatch_label'] = 'Language Consistency Check';
$string['test_languagesmatch_desc'] = 'Ensures the document\'s language setting matches the actual content.';

$string['test_pdfonlyimage_label'] = 'OCR Application Check';
$string['test_pdfonlyimage_desc'] = 'Checks if the PDF is just a scanned image of text.';

$string['test_linksvalid_label'] = 'Link Validity Check';
$string['test_linksvalid_desc'] = 'Verifies that all hyperlinks are functional and correctly tagged.';

$string['test_figuresalt_label'] = 'Image Alt Text Check';
$string['test_figuresalt_desc'] = 'Checks if images have "Alternative Text" descriptions.';

$string['test_lists_label'] = 'List Tagging Check';
$string['test_lists_desc'] = 'Ensures that visual lists are correctly tagged in the code.';

$string['test_tableheaders_label'] = 'Table Header Check';
$string['test_tableheaders_desc'] = 'Verifies that data tables have defined headers.';

$string['test_howtofix'] = 'How to fix?';

$string['test_title_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321690';
$string['test_languagesmatch_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321690';
$string['test_pdfonlyimage_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321690';
$string['test_linksvalid_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321690';
$string['test_figuresalt_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321690';
$string['test_lists_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321690';
$string['test_tableheaders_url'] = 'https://moodle.ciencias.ulisboa.pt/mod/resource/view.php?id=321690';
