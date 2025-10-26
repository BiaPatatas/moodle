# 🔧 SOLUÇÃO RÁPIDA - Botão não funciona

## ✅ ACESSO DIRETO ÀS CONFIGURAÇÕES

Como o botão não está funcionando, use estes links diretos:

### 🎯 Método 1: Link Direto
**Abra no navegador:**
```
http://localhost/Moodles/server/moodle/blocks/pdfcounter/configure.php
```

### 🎯 Método 2: URL Direta
**Configurações básicas:**
```
http://localhost/Moodles/server/moodle/blocks/pdfcounter/qualweb_settings.php
```

### 🎯 Método 3: Configurações avançadas
```
http://localhost/Moodles/server/moodle/blocks/pdfcounter/qualweb_settings_advanced.php
```

---

## 🐳 SETUP RÁPIDO DO DOCKER

**1. No PowerShell, execute:**
```powershell
docker run -d --name qualweb -p 8081:8080 qualweb/qualweb
```

**2. Verificar se funcionou:**
```powershell
docker ps
curl http://localhost:8081/ping
```

**3. Configurar no Moodle:**
- Vá para: `configure.php` (link acima)
- Clique em "Basic Settings"
- ✅ Marque "Enable QualWeb"
- 📝 URL: `http://localhost:8081`
- 💾 Salvar

---

## 🔧 RESOLUÇÃO DO PROBLEMA DO BOTÃO

O problema pode ser:
1. **Popup bloqueado** pelo navegador
2. **JavaScript desabilitado**
3. **CSP (Content Security Policy)** do Moodle

**Solução temporária:** Use os links diretos acima.

**Solução permanente:** Depois de configurar, o botão deve funcionar normalmente.

---

## 📊 RESULTADO ESPERADO

Após configurar:
1. Volte ao curso
2. Recarregue a página
3. A seção QualWeb deve mostrar "Evaluate Now"
4. O botão deve funcionar normalmente

---

## 🆘 SE DOCKER NÃO RODAR

**Verificar se Docker está instalado:**
```powershell
docker --version
```

**Se não estiver instalado:**
1. Baixe Docker Desktop para Windows
2. Instale e reinicie
3. Execute o comando acima

**Alternativa (CLI sem Docker):**
Use as configurações avançadas e escolha modo CLI.