<?php
/**
 * Auto-config QualWeb - Execute uma vez para habilitar
 */

require_once('../../config.php');
require_login();

// Verificar se é admin
if (!is_siteadmin()) {
    die('Apenas administradores podem executar este script');
}

echo "<h1>🔧 Auto-configuração QualWeb</h1>";

// Habilitar QualWeb
set_config('qualweb_enabled', 1, 'block_pdfcounter');
set_config('qualweb_api_url', 'http://localhost:8081', 'block_pdfcounter');
set_config('qualweb_mode', 'backend', 'block_pdfcounter');

echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
echo "<h2>✅ QualWeb Configurado com Sucesso!</h2>";
echo "<ul>";
echo "<li>✅ QualWeb habilitado</li>";
echo "<li>✅ API URL configurada: http://localhost:8081</li>";
echo "<li>✅ Modo definido: backend</li>";
echo "</ul>";
echo "</div>";

echo "<h2>🚀 Próximos Passos:</h2>";
echo "<ol>";
echo "<li><strong>Vá para o curso:</strong> <a href='" . $CFG->wwwroot . "/course/view.php?id=3' target='_blank'>Abrir Curso</a></li>";
echo "<li><strong>Recarregue a página</strong> (F5)</li>";
echo "<li><strong>Procure pela seção QualWeb</strong> - o botão deve estar como 'Evaluate Now'</li>";
echo "<li><strong>Clique em 'Evaluate Now'</strong> para testar</li>";
echo "</ol>";

echo "<h2>🐳 Status do Docker:</h2>";
echo "<p>Verifique se o container está rodando:</p>";
echo "<pre>docker ps</pre>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2 { color: #333; }
pre { background: #f4f4f4; padding: 10px; border-radius: 5px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>