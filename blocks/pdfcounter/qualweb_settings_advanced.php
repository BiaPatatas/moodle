<?php
/**
 * Configurações QualWeb - Suporte Docker + CLI
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once(__DIR__ . '/classes/qualweb_evaluator.php');
require_once(__DIR__ . '/classes/qualweb_evaluator_cli.php');

require_login();
require_capability('moodle/site:config', context_system::instance());

admin_externalpage_setup('block_pdfcounter_qualweb');

$PAGE->set_url('/blocks/pdfcounter/qualweb_settings_advanced.php');
$PAGE->set_title(get_string('qualweb_settings', 'block_pdfcounter'));
$PAGE->set_heading(get_string('qualweb_settings', 'block_pdfcounter'));

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey()) {
    $mode = required_param('qualweb_mode', PARAM_ALPHA);
    $enabled = optional_param('qualweb_enabled', 0, PARAM_INT);
    
    set_config('qualweb_enabled', $enabled, 'block_pdfcounter');
    set_config('qualweb_mode', $mode, 'block_pdfcounter');
    
    if ($mode === 'docker') {
        $api_url = required_param('qualweb_api_url', PARAM_URL);
        set_config('qualweb_api_url', $api_url, 'block_pdfcounter');
    } else if ($mode === 'cli') {
        $cli_path = required_param('qualweb_cli_path', PARAM_PATH);
        set_config('qualweb_cli_path', $cli_path, 'block_pdfcounter');
    }
    
    redirect($PAGE->url, 'Settings saved successfully!', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Configurações atuais
$enabled = get_config('block_pdfcounter', 'qualweb_enabled');
$mode = get_config('block_pdfcounter', 'qualweb_mode') ?: 'docker';
$api_url = get_config('block_pdfcounter', 'qualweb_api_url') ?: 'http://localhost:8081';
$cli_path = get_config('block_pdfcounter', 'qualweb_cli_path') ?: '/usr/local/bin/qw';

echo $OUTPUT->header();

?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <h2>🔧 QualWeb Configuration</h2>
            
            <form method="post" action="">
                <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                
                <!-- Enable/Disable -->
                <div class="form-group mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="qualweb_enabled" 
                               name="qualweb_enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="qualweb_enabled">
                            <strong>Enable QualWeb Integration</strong>
                        </label>
                    </div>
                    <small class="form-text text-muted">Enable accessibility evaluation for course pages</small>
                </div>

                <!-- Mode Selection -->
                <div class="form-group mb-4">
                    <label class="form-label"><strong>QualWeb Mode:</strong></label>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="qualweb_mode" 
                               id="mode_docker" value="docker" <?php echo $mode === 'docker' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mode_docker">
                            🐳 <strong>Docker Mode</strong> (Recommended)
                        </label>
                        <div class="ms-4 mt-1">
                            <small class="text-muted">Uses QualWeb Docker container via API</small>
                        </div>
                    </div>
                    
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="qualweb_mode" 
                               id="mode_backend" value="backend" <?php echo $mode === 'backend' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mode_backend">
                            🔧 <strong>Backend Mode</strong> (qualweb/backend)
                        </label>
                        <div class="ms-4 mt-1">
                            <small class="text-muted">Uses QualWeb backend Docker image</small>
                        </div>
                    </div>
                    
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="radio" name="qualweb_mode" 
                               id="mode_cli" value="cli" <?php echo $mode === 'cli' ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="mode_cli">
                            ⚡ <strong>CLI Mode</strong> (No Docker)
                        </label>
                        <div class="ms-4 mt-1">
                            <small class="text-muted">Uses QualWeb installed via npm</small>
                        </div>
                    </div>
                </div>

                <!-- Docker Settings -->
                <div id="docker_settings" style="display: none;">
                    <div class="card mb-3">
                        <div class="card-header">🐳 Docker Configuration</div>
                        <div class="card-body">
                            <div class="form-group mb-3">
                                <label for="qualweb_api_url" class="form-label">API URL:</label>
                                <input type="url" class="form-control" id="qualweb_api_url" 
                                       name="qualweb_api_url" value="<?php echo htmlspecialchars($api_url); ?>"
                                       placeholder="http://localhost:8081">
                                <small class="form-text text-muted">
                                    URL where QualWeb Docker container is running
                                </small>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Setup Docker Backend:</strong><br>
                                <code>docker run -d --name qualweb -p 8081:8080 --restart=unless-stopped qualweb/backend</code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CLI Settings -->
                <div id="cli_settings" style="display: none;">
                    <div class="card mb-3">
                        <div class="card-header">⚡ CLI Configuration</div>
                        <div class="card-body">
                            <div class="form-group mb-3">
                                <label for="qualweb_cli_path" class="form-label">CLI Path:</label>
                                <input type="text" class="form-control" id="qualweb_cli_path" 
                                       name="qualweb_cli_path" value="<?php echo htmlspecialchars($cli_path); ?>"
                                       placeholder="/usr/local/bin/qw">
                                <small class="form-text text-muted">
                                    Full path to QualWeb CLI executable
                                </small>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong>Install QualWeb CLI:</strong><br>
                                <code>sudo npm install -g @qualweb/cli</code><br>
                                <code>which qw</code> (to find path)
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">💾 Save Settings</button>
                <a href="./qualweb_test_connection.php" class="btn btn-secondary" target="_blank">🔍 Test Connection</a>
            </form>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">📊 Current Status</div>
                <div class="card-body">
                    <p><strong>Status:</strong> 
                        <span class="badge <?php echo $enabled ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </p>
                    
                    <p><strong>Mode:</strong> 
                        <span class="badge bg-info">
                            <?php 
                            if ($mode === 'docker') echo '🐳 Docker';
                            else if ($mode === 'backend') echo '🔧 Backend';
                            else if ($mode === 'cli') echo '⚡ CLI';
                            else echo '❓ Unknown';
                            ?>
                        </span>
                    </p>
                    
                    <?php if ($mode === 'docker'): ?>
                        <p><strong>API URL:</strong><br>
                        <small><?php echo htmlspecialchars($api_url); ?></small></p>
                    <?php else: ?>
                        <p><strong>CLI Path:</strong><br>
                        <small><?php echo htmlspecialchars($cli_path); ?></small></p>
                    <?php endif; ?>
                    
                    <hr>
                    
                    <div id="connection_status">
                        <button onclick="testConnection()" class="btn btn-sm btn-outline-primary">
                            🔍 Test Connection
                        </button>
                        <div id="test_result" class="mt-2"></div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">📚 Documentation</div>
                <div class="card-body">
                    <p><strong>For Production:</strong></p>
                    <ul class="small">
                        <li>Use Docker mode when possible</li>
                        <li>CLI mode for servers without Docker</li>
                        <li>Test connection before enabling</li>
                    </ul>
                    
                    <a href="./docker_production_guide.md" target="_blank" class="btn btn-sm btn-outline-info">
                        📖 Production Guide
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide settings based on mode
function toggleSettings() {
    const mode = document.querySelector('input[name="qualweb_mode"]:checked').value;
    
    document.getElementById('docker_settings').style.display = (mode === 'docker' || mode === 'backend') ? 'block' : 'none';
    document.getElementById('cli_settings').style.display = mode === 'cli' ? 'block' : 'none';
}

// Event listeners
document.querySelectorAll('input[name="qualweb_mode"]').forEach(radio => {
    radio.addEventListener('change', toggleSettings);
});

// Initialize
toggleSettings();

// Test connection
function testConnection() {
    const resultDiv = document.getElementById('test_result');
    resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Testing...';
    
    fetch('./qualweb_test_connection.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success alert-sm">✅ Connection OK</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger alert-sm">❌ ' + data.error + '</div>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class="alert alert-danger alert-sm">❌ Test failed</div>';
        });
}
</script>

<?php
echo $OUTPUT->footer();
?>