<?php
/**
 * QualWeb settings page
 *
 * @package    block_pdfcounter
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

// Verificar se é admin - se não for, permitir apenas visualização
$isadmin = is_siteadmin();

if (!$isadmin) {
    // Para usuários não-admin, apenas mostrar as configurações atuais
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/blocks/pdfcounter/qualweb_settings.php');
    $PAGE->set_title('QualWeb Settings');
    $PAGE->set_heading('QualWeb Settings');
} else {
    // Para admins, usar setup completo
    admin_externalpage_setup('block_pdfcounter_qualweb');
}

$PAGE->set_title(get_string('qualweb_settings', 'block_pdfcounter'));
$PAGE->set_heading(get_string('qualweb_settings', 'block_pdfcounter'));

// Handle form submission (apenas para admins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey() && $isadmin) {
    $api_url = optional_param('qualweb_api_url', '', PARAM_URL);
    $api_key = optional_param('qualweb_api_key', '', PARAM_TEXT);
    $enabled = optional_param('qualweb_enabled', 0, PARAM_BOOL);
    
    set_config('qualweb_api_url', $api_url, 'block_pdfcounter');
    set_config('qualweb_api_key', $api_key, 'block_pdfcounter');
    set_config('qualweb_enabled', $enabled, 'block_pdfcounter');
    
    redirect($PAGE->url->out(), get_string('settingssaved', 'admin'));
}

// Get current settings
$api_url = get_config('block_pdfcounter', 'qualweb_api_url') ?: 'http://localhost:8081/api';
$api_key = get_config('block_pdfcounter', 

'qualweb_api_key') ?: '';
$enabled = get_config('block_pdfcounter', 'qualweb_enabled') ?: false;

// Test connection
$evaluator = new qualweb_evaluator();
$service_status = $evaluator->get_service_status();

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('qualweb_settings', 'block_pdfcounter'));

?>

<?php if (!$isadmin): ?>
<div class="alert alert-warning">
    <h4>⚠️ Access Restricted</h4>
    <p>You can view QualWeb settings, but only administrators can modify them.</p>
    <p><strong>Current Configuration:</strong></p>
    <ul>
        <li>QualWeb Enabled: <strong><?php echo $enabled ? 'YES' : 'NO'; ?></strong></li>
        <li>API URL: <strong><?php echo $api_url ?: 'Not configured'; ?></strong></li>
    </ul>
    <p><a href="<?php echo $CFG->wwwroot; ?>/course/" class="btn btn-secondary">🔙 Back to Courses</a></p>
</div>
<?php else: ?>

<form method="post" action="<?php echo $PAGE->url->out(); ?>">
    <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
    
    <div class="form-group">
        <label for="qualweb_enabled">
            <input type="checkbox" id="qualweb_enabled" name="qualweb_enabled" value="1" 
                   <?php echo $enabled ? 'checked' : ''; ?>>
            <?php echo get_string('enable_qualweb', 'block_pdfcounter'); ?>
        </label>
        <div class="form-help">
            <?php echo get_string('enable_qualweb_help', 'block_pdfcounter'); ?>
        </div>
    </div>
    
    <div class="form-group">
        <label for="qualweb_api_url"><?php echo get_string('qualweb_api_url', 'block_pdfcounter'); ?></label>
        <input type="url" id="qualweb_api_url" name="qualweb_api_url" 
               value="<?php echo s($api_url); ?>" class="form-control" required>
        <div class="form-help">
            <?php echo get_string('qualweb_api_url_help', 'block_pdfcounter'); ?>
        </div>
    </div>
    
    <div class="form-group">
        <label for="qualweb_api_key"><?php echo get_string('qualweb_api_key', 'block_pdfcounter'); ?></label>
        <input type="text" id="qualweb_api_key" name="qualweb_api_key" 
               value="<?php echo s($api_key); ?>" class="form-control">
        <div class="form-help">
            <?php echo get_string('qualweb_api_key_help', 'block_pdfcounter'); ?>
        </div>
    </div>
    
    <div class="form-group">
        <h4><?php echo get_string('service_status', 'block_pdfcounter'); ?></h4>
        <div class="alert <?php echo $service_status['available'] ? 'alert-success' : 'alert-danger'; ?>">
            <strong>Status:</strong> 
            <?php echo $service_status['available'] ? 
                get_string('service_available', 'block_pdfcounter') : 
                get_string('service_unavailable', 'block_pdfcounter'); ?>
            
            <?php if ($service_status['available']): ?>
                <br><strong>Version:</strong> <?php echo s($service_status['version']); ?>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="form-group">
        <button type="submit" class="btn btn-primary">
            <?php echo get_string('save_settings', 'block_pdfcounter'); ?>
        </button>
        <a href="<?php echo $CFG->wwwroot; ?>/admin/settings.php?section=blocksettingpdfcounter" 
           class="btn btn-secondary">
            <?php echo get_string('cancel'); ?>
        </a>
    </div>
</form>

<div class="mt-4">
    <h4><?php echo get_string('docker_setup', 'block_pdfcounter'); ?></h4>
    <div class="alert alert-info">
        <h5><?php echo get_string('docker_instructions', 'block_pdfcounter'); ?></h5>
        <pre><code>
# Para executar QualWeb via Docker:
docker run -d --name qualweb-api \
  -p 8081:8080 \
  qualweb/api:latest

# Para verificar se está funcionando:
curl http://localhost:8081/api/monitoring/1
        </code></pre>
        
        <p><strong><?php echo get_string('swagger_info', 'block_pdfcounter'); ?></strong></p>
        <p><?php echo get_string('swagger_url', 'block_pdfcounter'); ?>: 
           <a href="<?php echo s($api_url); ?>/docs" target="_blank">
               <?php echo s($api_url); ?>/docs
           </a>
        </p>
    </div>
</div>

<?php endif; ?>

<?php
echo $OUTPUT->footer();
?>