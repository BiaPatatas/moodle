<?php
/**
 * Debug script para verificar configuração QualWeb
 */

require_once('../../config.php');
require_once('./classes/qualweb_evaluator.php');

require_login();

echo "<h2>QualWeb Debug Information</h2>";

// Verificar configurações
echo "<h3>1. Configurações QualWeb:</h3>";
$qualweb_enabled = get_config('block_pdfcounter', 'qualweb_enabled');
$api_url = get_config('block_pdfcounter', 'qualweb_api_url');
$api_key = get_config('block_pdfcounter', 'qualweb_api_key');

echo "Enabled: " . ($qualweb_enabled ? 'YES' : 'NO') . "<br>";
echo "API URL: " . ($api_url ?: 'NOT SET') . "<br>";
echo "API Key: " . ($api_key ? 'SET' : 'NOT SET') . "<br><br>";

// Testar conectividade
echo "<h3>2. Teste de Conectividade:</h3>";
try {
    $evaluator = new qualweb_evaluator();
    $status = $evaluator->get_service_status();
    
    echo "Service Available: " . ($status['available'] ? 'YES' : 'NO') . "<br>";
    echo "Last Check: " . date('Y-m-d H:i:s', $status['last_check']) . "<br><br>";
    
    if (!$status['available']) {
        echo "<strong style='color:red;'>PROBLEMA: Serviço QualWeb não está acessível!</strong><br>";
        echo "Verifique se o Docker está rodando e acessível em: " . $api_url . "<br><br>";
    }
    
} catch (Exception $e) {
    echo "<strong style='color:red;'>ERRO: " . $e->getMessage() . "</strong><br><br>";
}

// Verificar se arquivo de classe existe
echo "<h3>3. Verificação de Arquivos:</h3>";
$files_to_check = [
    __DIR__ . '/classes/qualweb_evaluator.php',
    __DIR__ . '/ajax/qualweb_evaluation.php'
];

foreach ($files_to_check as $file) {
    echo basename($file) . ": " . (file_exists($file) ? 'EXISTS' : 'MISSING') . "<br>";
}

echo "<br>";

// Verificar tabela de banco
echo "<h3>4. Verificação de Banco:</h3>";
global $DB;
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists('block_pdfcounter_qualweb');
echo "Tabela block_pdfcounter_qualweb: " . ($table_exists ? 'EXISTS' : 'MISSING') . "<br>";

if (!$table_exists) {
    echo "<strong style='color:red;'>PROBLEMA: Tabela do banco não existe!</strong><br>";
    echo "Execute: Administração do Site → Notificações para atualizar o banco de dados<br>";
}

echo "<br>";

// Teste direto da API
echo "<h3>5. Teste Direto da API:</h3>";
if ($api_url) {
    $test_url = rtrim($api_url, '/') . '/monitoring/1';
    echo "Testando: $test_url<br>";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $test_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $http_code<br>";
    if ($error) {
        echo "<strong style='color:red;'>CURL Error: $error</strong><br>";
    } else {
        echo "Response: " . substr($response, 0, 200) . "...<br>";
    }
}

echo "<hr>";
echo "<h3>Próximos Passos:</h3>";
echo "1. Se 'Enabled' = NO, configure em: <a href='./qualweb_settings.php'>QualWeb Settings</a><br>";
echo "2. Se API não está acessível, verifique Docker: <code>docker ps</code><br>";
echo "3. Se tabela está missing, vá em Admin → Notificações<br>";
echo "4. Recarregue a página do curso após corrigir os problemas<br>";
?>