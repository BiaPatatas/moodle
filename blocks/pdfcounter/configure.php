<?php
/**
 * Link direto para configurações QualWeb
 */

require_once('../../config.php');
require_login();

// Redirecionar para as configurações
$settings_url = new moodle_url('/blocks/pdfcounter/qualweb_settings.php');

echo "
<html>
<head>
    <title>QualWeb Settings</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; text-align: center; }
        .btn { background: #007bff; color: white; padding: 15px 30px; 
               text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px; }
        .btn:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>🔧 QualWeb Configuration</h1>
    <p>Choose how you want to configure QualWeb:</p>
    
    <a href=\"./qualweb_settings.php\" class=\"btn\">📝 Basic Settings</a>
    <a href=\"./qualweb_settings_advanced.php\" class=\"btn\">⚙️ Advanced Settings</a>
    <a href=\"./qualweb_test_connection.php\" class=\"btn\">🔍 Test Connection</a>
    
    <hr style=\"margin: 40px 0;\">
    
    <h2>Quick Setup:</h2>
    <ol style=\"text-align: left; max-width: 600px; margin: 0 auto;\">
        <li><strong>Start Docker:</strong><br>
            <code>docker run -d --name qualweb -p 8081:8080 qualweb/qualweb</code>
        </li>
        <li><strong>Go to Basic Settings</strong> (button above)</li>
        <li><strong>Enable QualWeb</strong> and set URL to: <code>http://localhost:8081</code></li>
        <li><strong>Save</strong> and test</li>
    </ol>
    
    <p><a href=\"" . $CFG->wwwroot . "/course/view.php?id=" . (isset($COURSE) ? $COURSE->id : '1') . "\">🔙 Back to Course</a></p>
</body>
</html>";
?>