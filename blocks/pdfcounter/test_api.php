<?php
/**
 * Teste simples da API QualWeb
 */

require_once('../../config.php');

$test_url = 'https://example.com';
$api_url = 'http://localhost:8081/app/url';

echo "<h1>🧪 Teste Simples QualWeb API</h1>";
echo "<p><strong>URL de teste:</strong> $test_url</p>";
echo "<p><strong>API endpoint:</strong> $api_url</p>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['url' => $test_url]));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

echo "<h2>⏳ Executando requisição...</h2>";
$start_time = microtime(true);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);
curl_close($ch);

$end_time = microtime(true);
$duration = round($end_time - $start_time, 2);

echo "<h2>📊 Resultados:</h2>";
echo "<ul>";
echo "<li><strong>Duração:</strong> {$duration}s</li>";
echo "<li><strong>HTTP Code:</strong> $http_code</li>";
echo "<li><strong>Erro:</strong> " . ($error ?: 'Nenhum') . "</li>";
echo "<li><strong>Tamanho resposta:</strong> " . strlen($response) . " bytes</li>";
echo "<li><strong>Content-Type:</strong> " . ($info['content_type'] ?? 'Desconhecido') . "</li>";
echo "</ul>";

if ($response) {
    echo "<h3>📄 Resposta Completa:</h3>";
    echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    // Tentar decodificar JSON
    $json = json_decode($response, true);
    if ($json) {
        echo "<h3>🔍 JSON Decodificado:</h3>";
        echo "<pre style='background: #e8f5e8; padding: 15px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; max-height: 400px; overflow-y: auto;'>";
        echo htmlspecialchars(print_r($json, true));
        echo "</pre>";
        
        // Análise dos dados
        echo "<h3>📈 Análise dos Dados:</h3>";
        echo "<ul>";
        
        if (isset($json['modules'])) {
            echo "<li><strong>Módulos encontrados:</strong> " . count($json['modules']) . "</li>";
            foreach ($json['modules'] as $module_name => $module) {
                echo "<li><strong>Módulo '$module_name':</strong>";
                if (isset($module['metadata'])) {
                    $meta = $module['metadata'];
                    echo " Passed: {$meta['passed']}, Warning: {$meta['warning']}, Failed: {$meta['failed']}";
                } else {
                    echo " Sem metadata";
                }
                echo "</li>";
            }
        }
        
        if (isset($json['metadata'])) {
            echo "<li><strong>Metadata global:</strong> " . print_r($json['metadata'], true) . "</li>";
        }
        
        if (isset($json['assertions'])) {
            echo "<li><strong>Assertions:</strong> " . count($json['assertions']) . " encontradas</li>";
        }
        
        echo "</ul>";
        
    } else {
        echo "<p><strong>❌ Erro JSON:</strong> " . json_last_error_msg() . "</p>";
    }
} else {
    echo "<p><strong>❌ Nenhuma resposta recebida</strong></p>";
}

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #333; }
pre { border-radius: 5px; overflow-x: auto; border: 1px solid #ddd; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
</style>