# 🔧 ACESSO ÀS CONFIGURAÇÕES QUALWEB

## ❌ Problema: Não consegue acessar arquivos PHP diretamente

### ✅ Soluções:

## 1. **Método Mais Simples - Via Bloco:**

1. **Vá para o curso:** `http://localhost/Moodles/server/moodle/course/view.php?id=3`
2. **Clique no link "🔧 Settings Page"** que aparece embaixo do botão
3. **Configure diretamente**

## 2. **Método Alternativo - Via URL Completa:**

Tente acessar:
```
http://localhost/Moodles/server/moodle/blocks/pdfcounter/qualweb_settings_simple.php
```

## 3. **Método Debug - Habilitar QualWeb via Banco:**

Se nada funcionar, vamos habilitar via SQL:

### No phpMyAdmin ou MySQL:
```sql
-- Habilitar QualWeb
INSERT INTO mdl_config_plugins (plugin, name, value) 
VALUES ('block_pdfcounter', 'qualweb_enabled', '1')
ON DUPLICATE KEY UPDATE value = '1';

-- Configurar URL
INSERT INTO mdl_config_plugins (plugin, name, value) 
VALUES ('block_pdfcounter', 'qualweb_api_url', 'http://localhost:8081')
ON DUPLICATE KEY UPDATE value = 'http://localhost:8081';

-- Configurar modo
INSERT INTO mdl_config_plugins (plugin, name, value) 
VALUES ('block_pdfcounter', 'qualweb_mode', 'backend')
ON DUPLICATE KEY UPDATE value = 'backend';
```

## 4. **Teste Direto no Curso:**

Depois de configurar (qualquer método acima):
1. Vá para o curso
2. Recarregue a página (F5)
3. O botão deve mudar para "Evaluate Now"
4. Clique e teste

---

## 🚀 **Método Recomendado:**

**Use o Método 1** (via bloco) - é o mais simples e deve funcionar.