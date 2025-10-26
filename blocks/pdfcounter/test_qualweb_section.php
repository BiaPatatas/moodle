<?php
/**
 * Teste forçado do bloco QualWeb
 */

require_once('../../config.php');
require_once('./classes/qualweb_evaluator.php');

// Debug forçado
$CFG->debug = E_ALL;
$CFG->debugdisplay = 1;

require_login();

echo "<h2>🧪 Teste Bloco QualWeb</h2>";

// Simular uma instância do bloco
class test_block {
    public function get_qualweb_section() {
        global $COURSE, $PAGE;
        
        echo "<p>🔍 <strong>DEBUG:</strong> Iniciando get_qualweb_section()</p>";
        
        if (empty($COURSE) || $COURSE->id == SITEID) {
            echo "<p>❌ Sem curso ativo</p>";
            return '';
        }
        
        echo "<p>✅ Curso ativo: ID {$COURSE->id}</p>";
        
        // Verificar se QualWeb está habilitado
        $enabled = get_config('block_pdfcounter', 'qualweb_enabled');
        echo "<p>QualWeb habilitado: " . ($enabled ? "SIM" : "NÃO") . "</p>";
        
        if (!$enabled) {
            echo "<p>❌ QualWeb não está habilitado nas configurações</p>";
            return '<div class="alert alert-info">
                <h4>🔧 QualWeb - Configuração Necessária</h4>
                <p>Para avaliar a acessibilidade da página, configure o QualWeb nas <a href="./qualweb_settings.php">configurações do bloco</a>.</p>
            </div>';
        }
        
        // Tentar criar evaluator
        try {
            $evaluator = new qualweb_evaluator();
            echo "<p>✅ QualWeb evaluator criado</p>";
            
            $status = $evaluator->is_service_available();
            echo "<p>Status do serviço: " . ($status ? "ONLINE" : "OFFLINE") . "</p>";
            
        } catch (Exception $e) {
            echo "<p>❌ Erro ao criar evaluator: " . $e->getMessage() . "</p>";
        }
        
        // HTML de teste
        $html = '
        <div class="mt-4 p-3 border rounded" style="background-color: #f8f9fa;">
            <h4>🔍 Page Accessibility (QualWeb)</h4>
            <div id="qualweb-status">
                <div class="d-flex align-items-center">
                    <div class="spinner-border spinner-border-sm me-2" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <span>Evaluating page accessibility...</span>
                </div>
            </div>
            <div id="qualweb-results" style="display: none;">
                <div class="row mt-3">
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success">Passed</h5>
                                <p class="card-text display-6" id="qualweb-passed">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-warning">Warnings</h5>
                                <p class="card-text display-6" id="qualweb-warnings">0</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-danger">Failed</h5>
                                <p class="card-text display-6" id="qualweb-failed">0</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button class="btn btn-primary btn-sm mt-2" onclick="evaluatePageAccessibility()">
                🔄 Re-evaluate
            </button>
        </div>';
        
        echo "<p>✅ HTML gerado com sucesso</p>";
        return $html;
    }
}

// Executar teste
echo "<h3>Executando teste:</h3>";
$test = new test_block();
$result = $test->get_qualweb_section();

echo "<h3>Resultado:</h3>";
echo $result;

?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
.alert { padding: 15px; margin: 10px 0; border-radius: 5px; }
.alert-info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
</style>