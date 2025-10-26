# 🐳 Guia Docker QualWeb para Produção

## Opção 1: Docker no Mesmo Servidor (Recomendado)

### Instalação no Servidor:
```bash
# 1. Instalar Docker (se não tiver)
sudo apt update
sudo apt install docker.io docker-compose

# 2. Executar QualWeb
sudo docker run -d --name qualweb-prod \
  -p 8081:8080 \
  --restart=unless-stopped \
  qualweb/qualweb

# 3. Verificar se está rodando
sudo docker ps
curl http://localhost:8081/ping
```

### Configuração no Moodle:
- URL API: `http://localhost:8081`
- Acesso: Interno ao servidor

---

## Opção 2: Servidor Separado para QualWeb

### Se o servidor Moodle não suporta Docker:

```bash
# Em outro servidor com Docker
docker run -d --name qualweb \
  -p 8081:8080 \
  --restart=unless-stopped \
  qualweb/qualweb
```

### Configuração no Moodle:
- URL API: `http://IP_DO_SERVIDOR:8081`
- Exemplo: `http://192.168.1.100:8081`

---

## Opção 3: Serviço Cloud (AWS/DigitalOcean)

### Docker Compose para produção:
```yaml
version: '3.8'
services:
  qualweb:
    image: qualweb/qualweb
    ports:
      - "8081:8080"
    restart: unless-stopped
    environment:
      - NODE_ENV=production
```

---

## Opção 4: Instalação Manual (Sem Docker)

### Se não puder usar Docker:
```bash
# 1. Instalar Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# 2. Instalar QualWeb
npm install -g @qualweb/cli

# 3. Criar serviço simples
# (Código PHP customizado para chamar qualweb via CLI)
```

---

## 🔧 Configuração de Segurança

### Firewall (se usando porta externa):
```bash
# Permitir apenas do servidor Moodle
sudo ufw allow from IP_MOODLE to any port 8081
```

### Nginx Proxy (Opcional):
```nginx
location /qualweb/ {
    proxy_pass http://localhost:8081/;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
}
```

---

## 🚀 Recomendação Final

**Para produção, use Opção 1** (Docker no mesmo servidor):
- ✅ Simples de configurar
- ✅ Melhor performance (localhost)
- ✅ Mais seguro (sem exposição externa)
- ✅ Fácil manutenção