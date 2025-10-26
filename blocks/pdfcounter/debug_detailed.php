<?php
/**
 * Debug detalhado do QualWeb
 */

require_once('../../config.php');
require_once('./classes/qualweb_factory.php');

require_login();

echo "<h1>🔍 Debug Detalhado QualWeb</h1>";

// 1. Verificar configurações
echo "<h2>1. Configurações:</h2>";
$enabled = get_config('block_pdfcounter', 'qualweb_enabled');
$api_url = get_config('block_pdfcounter', 'qualweb_api_url');
$mode = get_config('block_pdfcounter', 'qualweb_mode');

echo "<ul>";
echo "<li><strong>Enabled:</strong> " . ($enabled ? 'YES' : 'NO') . "</li>";
echo "<li><strong>API URL:</strong> " . htmlspecialchars($api_url) . "</li>";
echo "<li><strong>Mode:</strong> " . htmlspecialchars($mode) . "</li>";
echo "</ul>";

// 2. Testar conexão direta
echo "<h2>2. Teste de Conexão Direta:</h2>";
$test_url = 'https://example.com';
$api_endpoint = rtrim($api_url, '/') . '/app/url';

echo "<p><strong>Testando:</strong> " . htmlspecialchars($api_endpoint) . "</p>";
echo "<p><strong>Payload:</strong> " . htmlspecialchars(json_encode(['url' => $test_url])) . "</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_endpoint);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $test_url]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

echo "<h3>Resultado da Conexão:</h3>";
echo "<ul>";
echo "<li><strong>HTTP Code:</strong> " . $http_code . "</li>";
echo "<li><strong>Error:</strong> " . ($error ?: 'None') . "</li>";
echo "<li><strong>Response Length:</strong> " . strlen($response) . " bytes</li>";
echo "<li><strong>Content Type:</strong> " . ($info['content_type'] ?? 'Unknown') . "</li>";
echo "</ul>";

if ($response) {
    echo "<h3>Raw Response:</h3>";
    echo "<pre style='background: #f4f4f4; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
    echo htmlspecialchars(substr($response, 0, 2000));
    if (strlen($response) > 2000) echo "\n... (truncated)";
    echo "</pre>";
    
    // Tentar decodificar JSON
    $decoded = json_decode($response, true);
    if ($decoded) {
        echo "<h3>Decoded JSON:</h3>";
        echo "<pre style='background: #e8f5e8; padding: 10px; border-radius: 5px; max-height: 300px; overflow-y: auto;'>";
        echo htmlspecialchars(print_r($decoded, true));
        echo "</pre>";
    } else {
        echo "<p><strong>JSON Decode Error:</strong> " . json_last_error_msg() . "</p>";
    }
}

// 3. Testar factory
echo "<h2>3. Teste do Factory:</h2>";
try {
    $evaluator = qualweb_factory::create_evaluator();
    echo "<p>✅ <strong>Evaluator criado:</strong> " . get_class($evaluator) . "</p>";
    
    echo "<p>🔄 <strong>Testando evaluate_page()...</strong></p>";
    $result = $evaluator->evaluate_page($test_url);
    
    echo "<h3>Resultado do Evaluator:</h3>";
    echo "<pre style='background: #fff3cd; padding: 10px; border-radius: 5px;'>";
    echo htmlspecialchars(print_r($result, true));
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<p>❌ <strong>Erro:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>Stack trace:</strong></p>";
    echo "<pre style='background: #f8d7da; padding: 10px; border-radius: 5px; font-size: 12px;'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}

// 4. Verificar páginas do curso
echo "<h2>4. Páginas do Curso Detectadas:</h2>";
if (isset($_GET['courseid'])) {
    $courseid = (int)$_GET['courseid'];
    echo "<p><strong>Course ID:</strong> $courseid</p>";
    
    // Simular detecção de páginas
    global $DB, $CFG;
    
    $pages = [];
    $pages[] = [
        'url' => $CFG->wwwroot . '/course/view.php?id=' . $courseid,
        'title' => 'Course Main Page'
    ];
    
    $modules = $DB->get_records_sql("
        SELECT cm.id, m.name as modname, cm.instance
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE cm.course = ? AND cm.visible = 1
        AND m.name IN ('page', 'resource', 'quiz', 'forum', 'book')
        LIMIT 10
    ", [$courseid]);
    
    foreach ($modules as $module) {
        $pages[] = [
            'url' => $CFG->wwwroot . '/mod/' . $module->modname . '/view.php?id=' . $module->id,
            'title' => ucfirst($module->modname) . ' ' . $module->id
        ];
    }
    
    echo "<p><strong>Total de páginas detectadas:</strong> " . count($pages) . "</p>";
    echo "<ul>";
    foreach ($pages as $page) {
        echo "<li><strong>" . htmlspecialchars($page['title']) . ":</strong> " . htmlspecialchars($page['url']) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>Adicione <code>?courseid=3</code> na URL para ver as páginas detectadas</p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #333; }
pre { border-radius: 5px; overflow-x: auto; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
</style>