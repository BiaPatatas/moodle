# QualWeb Docker Setup Guide

## Instalação Rápida do QualWeb via Docker

### 1. Instalar Docker (se ainda não tiver)

**Windows:**
- Baixe Docker Desktop: https://www.docker.com/products/docker-desktop
- Instale e reinicie o computador

**Linux:**
```bash
sudo apt update
sudo apt install docker.io docker-compose
sudo systemctl start docker
sudo systemctl enable docker
```

### 2. Executar QualWeb API

```bash
# Opção 1: Versão simples (recomendada)
docker run -d --name qualweb-api \
  -p 8081:8080 \
  qualweb/api:latest

# Opção 2: Com configurações personalizadas
docker run -d --name qualweb-api \
  -p 8081:8080 \
  -e API_KEY=your_optional_api_key \
  -e TIMEOUT=60000 \
  qualweb/api:latest
```

### 3. Verificar se está funcionando

```bash
# Verificar status do container
docker ps

# Testar API (ajuste a porta se necessário)
curl http://localhost:8081/api/monitoring/1
```

### 4. Configurar no Moodle

1. Acesse: **Administração do Site → Plugins → Blocos → PDF Counter**
2. Configurações QualWeb:
   - **Habilitar QualWeb**: ✓ Sim
   - **URL da API**: `http://localhost:8081/api`
   - **Chave API**: (deixe vazio se não configurou)

### 5. Testar Integração

1. Vá para qualquer curso
2. Adicione o bloco "Accessibility Dashboard"
3. Clique em "Evaluate Now" na seção "Page Accessibility"

## Swagger Documentation

Acesse a documentação da API em: http://localhost:8081/docs

## Comandos Úteis

```bash
# Parar o serviço
docker stop qualweb-api

# Iniciar novamente
docker start qualweb-api

# Ver logs
docker logs qualweb-api

# Remover container
docker rm -f qualweb-api

# Atualizar para versão mais recente
docker pull qualweb/api:latest
docker rm -f qualweb-api
docker run -d --name qualweb-api -p 8081:8080 qualweb/api:latest
```

## Solução de Problemas

### Erro: "Port 8081 already in use"
```bash
# Usar porta diferente
docker run -d --name qualweb-api -p 8082:8080 qualweb/api:latest
# Então configurar URL como: http://localhost:8082/api
```

### Erro: "Service unavailable"
```bash
# Verificar se container está rodando
docker ps

# Verificar logs do container
docker logs qualweb-api

# Reiniciar container
docker restart qualweb-api
```

### Performance Issues
- O QualWeb pode demorar alguns segundos para avaliar páginas complexas
- Para melhor performance, considere usar um servidor dedicado
- Monitore uso de CPU/RAM com `docker stats qualweb-api`

## Configuração Avançada

### Docker Compose (Opcional)

Crie arquivo `docker-compose.yml`:

```yaml
version: '3'
services:
  qualweb:
    image: qualweb/api:latest
    ports:
      - "8081:8080"
    environment:
      - API_KEY=optional_api_key
      - TIMEOUT=60000
    restart: unless-stopped
    volumes:
      - qualweb_data:/app/data

volumes:
  qualweb_data:
```

Execute com:
```bash
docker-compose up -d
```

## Segurança

- Se expor para internet, use HTTPS e configure API_KEY
- Considere usar reverse proxy (nginx/Apache)
- Limite acesso por firewall/iptables

## Suporte

- Documentação QualWeb: https://github.com/qualweb/core
- Issues QualWeb: https://github.com/qualweb/core/issues
- Para problemas no Moodle: contate o administrador do sistema