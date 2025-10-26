<?php
/**
 * QualWeb settings - versão simplificada (funciona para todos)
 */

require_once('../../config.php');
require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/pdfcounter/qualweb_settings_simple.php');
$PAGE->set_title('QualWeb Settings');
$PAGE->set_heading('QualWeb Settings');

// Verificar se é admin
$isadmin = is_siteadmin();

// Handle form submission (apenas para admins)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && confirm_sesskey() && $isadmin) {
    $api_url = optional_param('qualweb_api_url', '', PARAM_URL);
    $enabled = optional_param('qualweb_enabled', 0, PARAM_INT);
    
    set_config('qualweb_api_url', $api_url, 'block_pdfcounter');
    set_config('qualweb_enabled', $enabled, 'block_pdfcounter');
    
    redirect($PAGE->url, 'Settings saved successfully!', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Configurações atuais
$enabled = get_config('block_pdfcounter', 'qualweb_enabled');
$api_url = get_config('block_pdfcounter', 'qualweb_api_url') ?: 'http://localhost:8081';

echo $OUTPUT->header();

?>

<div class="container">
    <div class="row">
        <div class="col-md-8">
            <h2>🔧 QualWeb Configuration</h2>
            
            <?php if (!$isadmin): ?>
            <div class="alert alert-info">
                <h4>👁️ View Only Mode</h4>
                <p>You can view the current settings, but only administrators can modify them.</p>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <div class="card-header">Current Settings</div>
                <div class="card-body">
                    <p><strong>Status:</strong> 
                        <span class="badge <?php echo $enabled ? 'bg-success' : 'bg-secondary'; ?>">
                            <?php echo $enabled ? 'Enabled' : 'Disabled'; ?>
                        </span>
                    </p>
                    <p><strong>API URL:</strong> <?php echo htmlspecialchars($api_url); ?></p>
                </div>
            </div>
            
            <?php if ($isadmin): ?>
            <div class="card mt-3">
                <div class="card-header">Configuration</div>
                <div class="card-body">
                    <form method="post" action="<?php echo $PAGE->url->out(); ?>">
                        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                        
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

                        <div class="form-group mb-3">
                            <label for="qualweb_api_url" class="form-label">API URL:</label>
                            <input type="url" class="form-control" id="qualweb_api_url" 
                                   name="qualweb_api_url" value="<?php echo htmlspecialchars($api_url); ?>"
                                   placeholder="http://localhost:8081" required>
                            <small class="form-text text-muted">
                                URL where QualWeb Docker container is running
                            </small>
                        </div>

                        <button type="submit" class="btn btn-primary">💾 Save Settings</button>
                        <a href="./qualweb_test_connection.php" class="btn btn-secondary" target="_blank">🔍 Test Connection</a>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">🐳 Docker Setup</div>
                <div class="card-body">
                    <p><strong>1. Start QualWeb Docker:</strong></p>
                    <pre><code>docker run -d --name qualweb \
-p 8081:8080 \
qualweb/qualweb</code></pre>
                    
                    <p><strong>2. Test Connection:</strong></p>
                    <pre><code>curl http://localhost:8081/ping</code></pre>
                    
                    <p><strong>3. Configure above and save</strong></p>
                    
                    <div class="mt-3">
                        <button onclick="testDockerConnection()" class="btn btn-sm btn-outline-primary">
                            🔍 Test Docker
                        </button>
                        <div id="docker_test_result" class="mt-2"></div>
                    </div>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">📚 Quick Links</div>
                <div class="card-body">
                    <a href="./configure.php" class="btn btn-sm btn-outline-info d-block mb-2">
                        🏠 Main Config Page
                    </a>
                    <a href="./debug_qualweb.php" class="btn btn-sm btn-outline-warning d-block mb-2" target="_blank">
                        🔧 Debug Tool
                    </a>
                    <a href="<?php echo $CFG->wwwroot; ?>/course/" class="btn btn-sm btn-outline-secondary d-block">
                        🔙 Back to Courses
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testDockerConnection() {
    const resultDiv = document.getElementById('docker_test_result');
    resultDiv.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Testing...';
    
    // Testar curl localhost:8081/ping
    fetch('<?php echo $CFG->wwwroot; ?>/blocks/pdfcounter/qualweb_test_connection.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = '<div class="alert alert-success alert-sm p-2">✅ Docker is running!</div>';
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger alert-sm p-2">❌ ' + data.error + '</div>';
            }
        })
        .catch(error => {
            resultDiv.innerHTML = '<div class="alert alert-warning alert-sm p-2">⚠️ Connection test failed</div>';
        });
}
</script>

<?php
echo $OUTPUT->footer();
?>