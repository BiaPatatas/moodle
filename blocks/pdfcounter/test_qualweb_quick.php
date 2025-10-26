<?php
/**
 * Teste rápido do QualWeb
 */

require_once('../../config.php');
require_once('./classes/qualweb_factory.php');

require_login();

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/blocks/pdfcounter/test_qualweb_quick.php');
$PAGE->set_title('QualWeb Test');
$PAGE->set_heading('QualWeb Test');

echo $OUTPUT->header();

echo "<h1>🧪 Teste QualWeb</h1>";

echo "<h2>1. Status do Serviço:</h2>";
$available = qualweb_factory::is_available();
echo "<p><strong>Disponível:</strong> " . ($available ? "✅ SIM" : "❌ NÃO") . "</p>";

echo "<h2>2. Modo Atual:</h2>";
$mode = qualweb_factory::get_current_mode();
echo "<p><strong>Modo:</strong> " . htmlspecialchars($mode) . "</p>";

echo "<h2>3. Configurações:</h2>";
$enabled = get_config('block_pdfcounter', 'qualweb_enabled');
$api_url = get_config('block_pdfcounter', 'qualweb_api_url');
echo "<p><strong>Habilitado:</strong> " . ($enabled ? "✅ SIM" : "❌ NÃO") . "</p>";
echo "<p><strong>API URL:</strong> " . htmlspecialchars($api_url) . "</p>";

if ($available && $enabled) {
    echo "<h2>4. Teste de Avaliação:</h2>";
    try {
        $evaluator = qualweb_factory::create_evaluator();
        echo "<p>✅ Evaluator criado: " . get_class($evaluator) . "</p>";
        
        // Teste com uma URL simples
        echo "<p>🔄 Testando avaliação de exemplo.com...</p>";
        $result = $evaluator->evaluate_page('https://example.com');
        echo "<p>✅ Resultado recebido!</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
        
    } catch (Exception $e) {
        echo "<p>❌ Erro: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<h2>4. ⚠️ Não é possível testar</h2>";
    echo "<p>Configure o QualWeb primeiro.</p>";
}

echo '<p><a href="./qualweb_settings_simple.php">🔧 Ir para Configurações</a></p>';
echo '<p><a href="' . $CFG->wwwroot . '/course/view.php?id=3">🔙 Voltar ao Curso</a></p>';

echo $OUTPUT->footer();

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
</style>