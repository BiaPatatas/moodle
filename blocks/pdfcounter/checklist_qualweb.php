<?php
/**
 * ✅ CHECKLIST COMPLETO - QualWeb no Bloco PDF Counter
 */

require_once('../../config.php');
require_login();

if (!is_siteadmin()) {
    die('Apenas administradores podem executar este script');
}

echo "<h1>✅ Checklist QualWeb Integration</h1>";

echo "<div style='background:#e7f3ff;padding:15px;border-radius:5px;margin:10px 0;'>";
echo "<h2>🔧 PASSO 1: Configurações Básicas</h2>";

// 1. Verificar debug
echo "<h3>1.1 Debug do Moodle</h3>";
if ($CFG->debug) {
    echo "✅ Debug habilitado: " . $CFG->debug . "<br>";
} else {
    echo "❌ <strong>AÇÃO NECESSÁRIA:</strong> Adicione no config.php:<br>";
    echo "<code>\$CFG->debug = E_ALL;<br>\$CFG->debugdisplay = 1;</code><br>";
}

// 2. Verificar config QualWeb
echo "<h3>1.2 Configuração QualWeb</h3>";
$enabled = get_config('block_pdfcounter', 'qualweb_enabled');
$url = get_config('block_pdfcounter', 'qualweb_api_url');

if ($enabled) {
    echo "✅ QualWeb habilitado<br>";
} else {
    echo "❌ <strong>AÇÃO NECESSÁRIA:</strong> <a href='./qualweb_settings.php' target='_blank'>Configurar QualWeb</a><br>";
}

if ($url) {
    echo "✅ API URL configurada: " . htmlspecialchars($url) . "<br>";
} else {
    echo "❌ <strong>AÇÃO NECESSÁRIA:</strong> Configurar URL da API QualWeb<br>";
}

// 3. Verificar banco
echo "<h3>1.3 Banco de Dados</h3>";
global $DB;
$dbman = $DB->get_manager();
$table_exists = $dbman->table_exists('block_pdfcounter_qualweb');

if ($table_exists) {
    echo "✅ Tabela 'block_pdfcounter_qualweb' existe<br>";
} else {
    echo "❌ <strong>AÇÃO NECESSÁRIA:</strong> <a href='" . $CFG->wwwroot . "/admin/index.php' target='_blank'>Executar upgrade do banco</a><br>";
}

echo "</div>";

echo "<div style='background:#fff3cd;padding:15px;border-radius:5px;margin:10px 0;'>";
echo "<h2>🐳 PASSO 2: Docker QualWeb</h2>";

echo "<p><strong>Comandos para verificar:</strong></p>";
echo "<pre>docker ps                 # Verificar se container está rodando
docker logs [container_id] # Ver logs do QualWeb
curl http://localhost:8081/ping # Testar se API responde</pre>";

echo "<p><strong>Se não estiver rodando:</strong></p>";
echo "<pre>docker run -d -p 8081:8080 qualweb/qualweb</pre>";

echo "</div>";

echo "<div style='background:#d4edda;padding:15px;border-radius:5px;margin:10px 0;'>";
echo "<h2>👁️ PASSO 3: Verificar Exibição do Bloco</h2>";

echo "<ol>";
echo "<li><strong>Ir para um curso qualquer</strong></li>";
echo "<li><strong>Adicionar o bloco:</strong> Configurações → Editar esta página → Adicionar bloco → 'Accessibility Dashboard'</li>";
echo "<li><strong>Recarregar a página</strong></li>";
echo "<li><strong>Procurar pela seção:</strong> '🔍 Page Accessibility (QualWeb)'</li>";
echo "</ol>";

echo "<p><strong>Deve aparecer:</strong> Um quadrado branco com título e botão, mesmo se QualWeb estiver desabilitado.</p>";

echo "</div>";

echo "<div style='background:#f8d7da;padding:15px;border-radius:5px;margin:10px 0;'>";
echo "<h2>🔍 PASSO 4: Troubleshooting</h2>";

echo "<h3>Se a seção QualWeb NÃO aparecer:</h3>";
echo "<ol>";
echo "<li><strong>Verificar logs:</strong> Procurar por 'QualWeb section called' nos logs do Moodle</li>";
echo "<li><strong>Teste isolado:</strong> <a href='./test_qualweb_section.php' target='_blank'>Executar teste de seção</a></li>";
echo "<li><strong>Debug completo:</strong> <a href='./debug_qualweb.php' target='_blank'>Executar debug completo</a></li>";
echo "<li><strong>Verificar arquivos:</strong> Conferir se todos os arquivos foram criados corretamente</li>";
echo "</ol>";

echo "<h3>Arquivos importantes:</h3>";
$files = [
    'block_pdfcounter.php' => 'Bloco principal',
    'classes/qualweb_evaluator.php' => 'Integração com API',
    'qualweb_settings.php' => 'Página de configurações',
    'ajax/qualweb_evaluation.php' => 'Endpoint AJAX',
    'db/upgrade.php' => 'Schema do banco',
    'version.php' => 'Versão do plugin'
];

foreach ($files as $file => $desc) {
    $fullpath = __DIR__ . '/' . $file;
    if (file_exists($fullpath)) {
        echo "✅ $file ($desc)<br>";
    } else {
        echo "❌ $file FALTANDO ($desc)<br>";
    }
}

echo "</div>";

echo "<div style='background:#e2e3e5;padding:15px;border-radius:5px;margin:10px 0;'>";
echo "<h2>📞 Próximos Passos</h2>";

echo "<p><strong>Execute este checklist na ordem:</strong></p>";
echo "<ol>";
echo "<li>Habilitar debug no Moodle</li>";
echo "<li>Configurar QualWeb nas configurações</li>";
echo "<li>Executar upgrade do banco de dados</li>";
echo "<li>Verificar se Docker está rodando</li>";
echo "<li>Adicionar bloco em um curso</li>";
echo "<li>Se não aparecer, executar troubleshooting</li>";
echo "</ol>";

echo "<p><strong>Links úteis:</strong></p>";
echo "<ul>";
echo "<li><a href='./qualweb_settings.php' target='_blank'>Configurações QualWeb</a></li>";
echo "<li><a href='./test_qualweb_section.php' target='_blank'>Teste de Seção</a></li>";
echo "<li><a href='./debug_qualweb.php' target='_blank'>Debug Completo</a></li>";
echo "<li><a href='" . $CFG->wwwroot . "/admin/index.php' target='_blank'>Upgrade do Sistema</a></li>";
echo "</ul>";

echo "</div>";

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h1, h2, h3 { color: #333; }
code, pre { background: #f4f4f4; padding: 4px 8px; border-radius: 3px; font-family: monospace; }
pre { display: block; padding: 10px; overflow-x: auto; }
ol, ul { margin: 10px 0; padding-left: 30px; }
li { margin: 5px 0; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>