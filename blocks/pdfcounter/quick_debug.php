<?php
/**
 * Script rápido para habilitar debug no Moodle
 */

require_once('../../config.php');
require_login();

// Para ver se é admin
if (!is_siteadmin()) {
    die('Apenas administradores podem executar este script');
}

echo "<h2>🔧 Debug QualWeb - Steps</h2>";

echo "<h3>1. Primeiro, execute estes passos:</h3>";
echo "<ol>";
echo "<li><strong>Habilitar debug:</strong> Adicione no config.php:<br>";
echo "<code>\$CFG->debug = E_ALL;<br>\$CFG->debugdisplay = 1;</code></li>";
echo "<li><strong>Configurar QualWeb:</strong> <a href='./qualweb_settings.php' target='_blank'>Abrir Configurações</a></li>";
echo "<li><strong>Verificar Docker:</strong> <code>docker ps</code> (deve mostrar container rodando)</li>";
echo "<li><strong>Atualizar banco:</strong> <a href='" . $CFG->wwwroot . "/admin/index.php' target='_blank'>Admin → Notificações</a></li>";
echo "</ol>";

echo "<hr>";

echo "<h3>2. Status atual:</h3>";

// Verificar debug
echo "Debug habilitado: " . ($CFG->debug ? "SIM" : "NÃO") . "<br>";

// Verificar config QualWeb
$enabled = get_config('block_pdfcounter', 'qualweb_enabled');
$url = get_config('block_pdfcounter', 'qualweb_api_url');

echo "QualWeb habilitado: " . ($enabled ? "SIM" : "NÃO") . "<br>";
echo "API URL: " . ($url ?: "NÃO CONFIGURADO") . "<br>";

// Verificar tabela
global $DB;
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists('block_pdfcounter_qualweb');
echo "Tabela banco: " . ($table_exists ? "EXISTE" : "FALTANDO") . "<br>";

echo "<hr>";

echo "<h3>3. Para ver a seção QualWeb:</h3>";
echo "<ol>";
echo "<li>Vá para qualquer curso</li>";
echo "<li>Adicione o bloco 'Accessibility Dashboard' (se não estiver presente)</li>";
echo "<li>Recarregue a página</li>";
echo "<li>Deve aparecer uma seção '🔍 Page Accessibility (QualWeb)'</li>";
echo "</ol>";

echo "<hr>";

echo "<h3>4. Se ainda não aparecer:</h3>";
echo "<p>Execute o debug script: <a href='./debug_qualweb.php' target='_blank'>Debug QualWeb</a></p>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
code { background: #f4f4f4; padding: 2px 4px; border-radius: 3px; }
ol, ul { margin: 10px 0; }
li { margin: 5px 0; }
hr { margin: 20px 0; }
</style>