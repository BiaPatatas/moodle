# QualWeb Integration for Moodle PDF Counter Block

Esta integração permite avaliar a acessibilidade das páginas do curso usando a API do QualWeb conforme o Swagger fornecido.

## 🎯 Funcionalidades

- ✅ **Avaliação automática** de páginas do curso
- ✅ **Score WCAG 2.1 AA** das páginas
- ✅ **Contagem de problemas** encontrados
- ✅ **Interface visual** no bloco PDF Counter
- ✅ **Cache de resultados** para performance
- ✅ **Configuração flexível** via admin

## 🔧 Como Funciona

### 1. Workflow da API QualWeb

A integração segue o workflow oficial da API:

1. **Crawl** → Cria monitoring registry e descobre URLs
2. **Set Metric** → Define WCAG 2.1 AA como padrão
3. **Evaluate** → Executa avaliação das páginas
4. **Get Results** → Obtém resultados e calcula score

### 2. Endpoints Utilizados

- `POST /monitoring/crawl` - Criar registry e descobrir páginas
- `POST /monitoring/set-accessibility-metric` - Definir métrica WCAG
- `GET /monitoring/{id}/monitored-webpages` - Listar páginas
- `POST /monitoring/{id}/evaluate` - Iniciar avaliação
- `GET /monitoring/{id}/latest-evaluations` - Obter resultados

## 🚀 Instalação

### 1. Docker QualWeb
```bash
# Executar QualWeb API
docker run -d --name qualweb-api \
  -p 8081:8080 \
  qualweb/api:latest

# Verificar se está funcionando
curl http://localhost:8081/api/monitoring/1
```

### 2. Configurar no Moodle
1. Acesse **Admin → Plugins → Blocos → PDF Counter**
2. Configure:
   - **URL da API**: `http://localhost:8081/api`
   - **Habilitar QualWeb**: ✓ Sim
   - **API Key**: (opcional)

### 3. Testar
1. Vá para qualquer curso
2. Adicione bloco "Accessibility Dashboard"
3. Clique "Evaluate Now" na seção "Page Accessibility"
4. Aguarde os resultados aparecerem

## 📊 Dados Coletados

Para cada página avaliada:
- **URL** da página
- **Testes passados** (passed)
- **Testes falhados** (failed)
- **Avisos** (warnings)
- **Score calculado** (0-100%)
- **Status visual** (Good/Warning/Critical)

## 🎨 Interface

### No Bloco PDF Counter
```
┌─────────────────────────────┐
│ Page Accessibility          │
├─────────────────────────────┤
│ 85.2%              Good     │
│                             │
│ Pages Evaluated: 3/5        │
│ Issues Found: 8             │
│                             │
│ Last: 16/10/2025 14:30      │
│                             │
│ [ Evaluate Now ]            │
└─────────────────────────────┘
```

## 🔍 Detalhes Técnicos

### Cache de Resultados
- Resultados salvos na tabela `block_pdfcounter_qualweb`
- Cache por curso para melhor performance
- Atualização manual via botão "Evaluate Now"

### Páginas Avaliadas
- Página principal do curso
- Todas as atividades visíveis
- Recursos e módulos ativos

### Critérios WCAG
- **WCAG 2.1 Level AA** por padrão
- Testes de acessibilidade automáticos
- Foco em problemas críticos

## ⚙️ Configurações Avançadas

### Autenticação API
Se o QualWeb requer autenticação:
```bash
docker run -d --name qualweb-api \
  -p 8081:8080 \
  -e API_KEY=sua_chave_aqui \
  qualweb/api:latest
```

Então configure a chave na interface do Moodle.

### Performance
- Avaliações podem demorar 30-60 segundos
- Use cache para evitar re-avaliações frequentes
- Monitore logs Docker: `docker logs qualweb-api`

## 🐛 Solução de Problemas

### "Service Unavailable"
```bash
# Verificar se container está rodando
docker ps

# Ver logs
docker logs qualweb-api

# Reiniciar se necessário
docker restart qualweb-api
```

### "Evaluation Failed"
- Verifique URL da API (deve incluir `/api`)
- Confirme que a porta está correta (8081)
- Teste manualmente: `curl http://localhost:8081/api/monitoring/1`

### Performance Lenta
- QualWeb pode demorar para páginas complexas
- Considere limitar número de páginas avaliadas
- Use servidor dedicado para produção

## 📝 Logs e Debug

### Logs Moodle
```php
// Ativar debug no config.php
$CFG->debug = E_ALL;
$CFG->debugdisplay = 1;
```

### Logs Docker
```bash
docker logs -f qualweb-api
```

## 🔗 Links Úteis

- **Swagger UI**: http://localhost:8081/docs
- **QualWeb GitHub**: https://github.com/qualweb/core
- **WCAG Guidelines**: https://www.w3.org/WAI/WCAG21/quickref/

## 📞 Suporte

Para problemas específicos do Moodle, contate o administrador do sistema.
Para questões do QualWeb, consulte a documentação oficial ou GitHub issues.