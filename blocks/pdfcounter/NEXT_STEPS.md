# 🎯 CONFIGURAÇÃO RÁPIDA - QualWeb Funcionando

## ✅ SUCESSO! A seção QualWeb está aparecendo!

### Agora faça isso:

## 1. 🔧 Configurar QualWeb

**Clique no botão "Configure QualWeb"** ou vá para:
`http://localhost/Moodles/server/moodle/blocks/pdfcounter/qualweb_settings.php`

## 2. 🐳 Configurar Docker (se estiver rodando)

Na página de configurações:
1. ✅ **Marque "Enable QualWeb Integration"**
2. 📝 **Configure API URL:** `http://localhost:8081`
3. 💾 **Salve as configurações**
4. 🔍 **Teste a conexão**

## 3. 📊 Ver resultado

Depois de configurar e salvar:
1. Volte para o curso
2. Recarregue a página
3. A seção deve mostrar um botão "Evaluate Now" em vez de "Configure QualWeb"
4. Clique em "Evaluate Now" para testar

## 4. 🐳 Verificar se Docker está rodando

No terminal:
```bash
docker ps
curl http://localhost:8081/ping
```

Se Docker não estiver rodando:
```bash
docker run -d --name qualweb -p 8081:8080 qualweb/qualweb
```

## 5. 🎉 Resultado Esperado

Após configurar corretamente, a seção deve mostrar:
- 📊 Score de acessibilidade (%)
- 📄 Número de páginas avaliadas
- ⚠️ Número de issues encontradas
- 🔄 Botão "Re-evaluate"

---

## 🔧 Se tiver problemas:

### Docker não rodando?
```bash
docker run -d -p 8081:8080 qualweb/qualweb
```

### Erro de conexão?
1. Verificar se URL está correta: `http://localhost:8081`
2. Testar manualmente: `curl http://localhost:8081/ping`
3. Verificar firewall

### Configuração não salva?
1. Verificar se é administrador
2. Verificar permissões do arquivo
3. Ver logs do Moodle (se debug habilitado)

---

## 📁 Links úteis:

- **Configurações:** `qualweb_settings.php`
- **Configurações Avançadas:** `qualweb_settings_advanced.php`
- **Teste de Conexão:** `qualweb_test_connection.php`
- **Debug Completo:** `debug_qualweb.php`