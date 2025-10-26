# 🚀 Guia de Implementação QualWeb em Produção

## 📋 Resumo das Opções

### ✅ **Opção 1: Docker no Servidor (RECOMENDADO)**
- **Quando usar:** Servidor suporta Docker
- **Configuração:** Mais simples
- **Performance:** Melhor (localhost)
- **Segurança:** Maior (sem exposição externa)

### ⚡ **Opção 2: CLI sem Docker**
- **Quando usar:** Servidor NÃO suporta Docker
- **Configuração:** Requer Node.js
- **Performance:** Boa
- **Compatibilidade:** Maior

### 🌐 **Opção 3: Servidor Externo**
- **Quando usar:** Múltiplos servidores Moodle
- **Configuração:** Mais complexa
- **Escalabilidade:** Melhor
- **Manutenção:** Centralizada

---

## 🐳 IMPLEMENTAÇÃO DOCKER (Recomendado)

### No Servidor de Produção:

```bash
# 1. Instalar Docker (Ubuntu/Debian)
sudo apt update
sudo apt install docker.io docker-compose

# 2. Executar QualWeb
sudo docker run -d --name qualweb-prod \
  -p 8081:8080 \
  --restart=unless-stopped \
  --memory=2g \
  --cpus=1 \
  qualweb/qualweb

# 3. Verificar
sudo docker ps
curl http://localhost:8081/ping
```

### Configuração no Moodle:
1. Acesse: `blocks/pdfcounter/qualweb_settings_advanced.php`
2. Escolha: **🐳 Docker Mode**
3. URL: `http://localhost:8081`
4. Teste a conexão

---

## ⚡ IMPLEMENTAÇÃO CLI (Sem Docker)

### No Servidor de Produção:

```bash
# 1. Instalar Node.js (versão LTS)
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# 2. Instalar QualWeb CLI
sudo npm install -g @qualweb/cli

# 3. Verificar instalação
qw --version
which qw
```

### Configuração no Moodle:
1. Acesse: `blocks/pdfcounter/qualweb_settings_advanced.php`
2. Escolha: **⚡ CLI Mode**
3. Path: `/usr/local/bin/qw` (ou resultado do `which qw`)
4. Teste a conexão

---

## 🌐 SERVIDOR EXTERNO (Múltiplos Moodles)

### Setup em Servidor Dedicado:

```bash
# Servidor dedicado para QualWeb
docker run -d --name qualweb \
  -p 8081:8080 \
  --restart=unless-stopped \
  qualweb/qualweb

# Com nginx proxy (opcional)
sudo nginx -s reload
```

### Configuração em cada Moodle:
- URL: `http://IP_DO_SERVIDOR:8081`
- Exemplo: `http://192.168.1.100:8081`

### Firewall (importante):
```bash
# Permitir apenas IPs dos servidores Moodle
sudo ufw allow from 192.168.1.50 to any port 8081
sudo ufw allow from 192.168.1.51 to any port 8081
```

---

## 🔧 CONFIGURAÇÕES DE PRODUÇÃO

### Docker Compose (Recomendado):
```yaml
version: '3.8'
services:
  qualweb:
    image: qualweb/qualweb:latest
    container_name: qualweb-prod
    ports:
      - "8081:8080"
    restart: unless-stopped
    environment:
      - NODE_ENV=production
    deploy:
      resources:
        limits:
          memory: 2G
          cpus: '1.0'
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/ping"]
      interval: 30s
      timeout: 10s
      retries: 3
```

### Monitoramento:
```bash
# Logs do container
sudo docker logs qualweb-prod -f

# Status de saúde
sudo docker inspect qualweb-prod | grep Health

# Recursos utilizados
sudo docker stats qualweb-prod
```

---

## 🔐 CONSIDERAÇÕES DE SEGURANÇA

### 1. Firewall Local:
```bash
# Permitir apenas localhost (se Docker local)
sudo ufw allow from 127.0.0.1 to any port 8081
```

### 2. Nginx Proxy (se necessário):
```nginx
location /qualweb/ {
    proxy_pass http://127.0.0.1:8081/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    
    # Rate limiting
    limit_req zone=api burst=20 nodelay;
}
```

### 3. SSL/TLS (se necessário):
- Use proxy reverso com certificado
- Configure HTTPS no Moodle
- Ajuste URLs nas configurações

---

## 📊 MONITORAMENTO E MANUTENÇÃO

### Scripts Úteis:

```bash
#!/bin/bash
# restart_qualweb.sh
sudo docker restart qualweb-prod
echo "QualWeb restarted at $(date)"

#!/bin/bash
# check_qualweb.sh
if curl -f http://localhost:8081/ping > /dev/null 2>&1; then
    echo "QualWeb OK"
else
    echo "QualWeb DOWN - Restarting..."
    sudo docker restart qualweb-prod
fi
```

### Cron Jobs:
```bash
# Verificar a cada 5 minutos
*/5 * * * * /path/to/check_qualweb.sh

# Restart diário (opcional)
0 3 * * * /path/to/restart_qualweb.sh
```

---

## 🎯 CHECKLIST DE PRODUÇÃO

### Antes de Colocar em Produção:

- [ ] Docker/Node.js instalado e funcionando
- [ ] QualWeb respondendo na porta configurada
- [ ] Firewall configurado adequadamente
- [ ] Configurações do Moodle testadas
- [ ] Backup dos arquivos de configuração
- [ ] Scripts de monitoramento configurados
- [ ] Documentação da infraestrutura atualizada

### Após Implementação:

- [ ] Testar avaliação em curso real
- [ ] Verificar logs por alguns dias
- [ ] Monitorar recursos (CPU/RAM)
- [ ] Configurar alertas se necessário
- [ ] Treinar usuários administradores

---

## 🆘 TROUBLESHOOTING COMUM

### QualWeb não responde:
```bash
# Verificar se está rodando
sudo docker ps | grep qualweb

# Verificar logs
sudo docker logs qualweb-prod --tail 50

# Reiniciar
sudo docker restart qualweb-prod
```

### Erro de conexão no Moodle:
1. Verificar URL nas configurações
2. Testar conexão manualmente: `curl http://localhost:8081/ping`
3. Verificar firewall
4. Verificar logs do Moodle

### Performance ruim:
- Aumentar recursos do Docker
- Verificar se há muitas avaliações simultâneas
- Considerar cache mais eficiente
- Monitorar uso de CPU/RAM

---

## 📞 SUPORTE

Para problemas específicos:
1. Verificar logs do QualWeb
2. Verificar logs do Moodle (modo debug)
3. Testar conexão manual
4. Executar scripts de debug criados

**Arquivos de debug disponíveis:**
- `checklist_qualweb.php` - Verificação completa
- `qualweb_test_connection.php` - Teste de conexão
- `debug_qualweb.php` - Debug detalhado