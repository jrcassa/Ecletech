# Cron Scripts - CRM

Scripts para sincronização automática e manual com sistemas CRM externos.

---

## 📁 Estrutura de Arquivos

```
cron/crm/
├── README.md                    # Este arquivo
├── crm_sync.php                 # ⚙️ Processamento da fila (principal)
├── crm_cleanup.php              # 🧹 Limpeza de logs antigos
├── sync_clientes.php            # 👥 Sincronização manual de clientes
├── sync_produtos.php            # 📦 Sincronização manual de produtos
├── sync_vendas.php              # 💰 Sincronização manual de vendas
└── crm_sync_full_clientes.php   # 👥 Sincronização completa de clientes
```

---

## ⚙️ Scripts Principais

### 1. `crm_sync.php` - Processamento da Fila

**Propósito:** Processa itens da fila de sincronização

**Frequência:** A cada 1 minuto

**Funcionamento:**
- Busca até 100 itens pendentes da fila
- Processa cada item (Ecletech → CRM)
- Marca como processado ou incrementa tentativas
- Registra logs de sucesso/erro

**Crontab:**
```bash
* * * * * /usr/bin/php /caminho/Ecletech/cron/crm/crm_sync.php
```

**Saída:**
```
Processando fila CRM...
✅ Processados: 23/23
❌ Erros: 0
⏱️ Tempo: 2.3s
```

---

### 2. `crm_cleanup.php` - Limpeza de Logs

**Propósito:** Remove logs e itens antigos do banco

**Frequência:** 1x por dia (madrugada)

**Funcionamento:**
- Remove logs com mais de 30 dias
- Remove itens processados da fila com mais de 7 dias

**Crontab:**
```bash
0 3 * * * /usr/bin/php /caminho/Ecletech/cron/crm/crm_cleanup.php
```

**Saída:**
```
Limpando registros antigos...
✅ Logs removidos: 1.523
✅ Fila limpa: 342 itens
```

---

## 🔄 Scripts de Sincronização Manual

### 3. `sync_clientes.php` - Clientes

**Propósito:** Enfileira todos os clientes ativos para sincronização

**Uso:**
```bash
php /caminho/Ecletech/cron/crm/sync_clientes.php
```

**Funcionamento:**
- Busca todos os clientes ativos (não deletados)
- Enfileira cada um com prioridade média (3)
- Serão processados pelo `crm_sync.php`

**Saída:**
```
Loja 1: 237 clientes encontrados
✅ Total enfileirado: 237 clientes
Os registros serão processados pelo cron principal (crm_sync.php)
```

**Quando usar:**
- Primeira sincronização
- Ressincronização após correções
- Sincronização em massa após mudanças

---

### 4. `sync_produtos.php` - Produtos

**Propósito:** Enfileira todos os produtos ativos para sincronização

**Uso:**
```bash
php /caminho/Ecletech/cron/crm/sync_produtos.php
```

**Funcionamento:**
- Busca todos os produtos ativos
- Enfileira cada um com prioridade média (3)

**Saída:**
```
Loja 1: 1.042 produtos encontrados
✅ Total enfileirado: 1.042 produtos
Os registros serão processados pelo cron principal (crm_sync.php)
```

---

### 5. `sync_vendas.php` - Vendas

**Propósito:** Enfileira vendas recentes (últimos 30 dias)

**Uso:**
```bash
php /caminho/Ecletech/cron/crm/sync_vendas.php
```

**Funcionamento:**
- Busca vendas dos últimos 30 dias
- Enfileira com prioridade alta (5)

**Saída:**
```
Loja 1: 89 vendas encontradas (últimos 30 dias)
✅ Total enfileirado: 89 vendas
Os registros serão processados pelo cron principal (crm_sync.php)
```

---

### 6. `crm_sync_full_clientes.php` - Sync Completa (Legado)

**Propósito:** Script original de sincronização completa

**Frequência:** 1x por semana (domingos)

**Crontab:**
```bash
0 2 * * 0 /usr/bin/php /caminho/Ecletech/cron/crm/crm_sync_full_clientes.php
```

---

## 🎯 Fluxo de Sincronização

```
┌─────────────────┐
│  Evento no      │
│  Ecletech       │ (cliente criado/editado)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Enfileirar     │
│  crm_sync_queue │ (hook no Model)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  crm_sync.php   │ (cron a cada 1 min)
│  Processa fila  │ (até 100 itens)
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  API CRM        │ (GestãoClick, etc)
│  POST/PUT       │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│  Salva          │
│  external_id    │
│  + Log          │
└─────────────────┘
```

---

## 📊 Monitoramento

### Ver Fila em Tempo Real

```sql
-- Itens pendentes
SELECT entidade, COUNT(*) as total
FROM crm_sync_queue
WHERE processado = 0
GROUP BY entidade;

-- Itens com erro
SELECT *
FROM crm_sync_queue
WHERE tentativas >= 3
ORDER BY criado_em DESC
LIMIT 10;
```

### Ver Logs de Sincronização

```sql
-- Taxa de sucesso (últimas 24h)
SELECT
    status,
    COUNT(*) as total,
    ROUND(COUNT(*) / (SELECT COUNT(*) FROM crm_sync_log WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) * 100, 2) as percentual
FROM crm_sync_log
WHERE criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY status;

-- Últimos erros
SELECT *
FROM crm_sync_log
WHERE status = 'erro'
ORDER BY criado_em DESC
LIMIT 10;
```

---

## 🔧 Configuração do Crontab

Adicione ao crontab (`crontab -e`):

```bash
# Sincronização CRM - Processar fila (100/min)
* * * * * /usr/bin/php /var/www/Ecletech/cron/crm/crm_sync.php >> /var/log/crm_sync.log 2>&1

# Limpeza de logs antigos (1x por dia às 03:00)
0 3 * * * /usr/bin/php /var/www/Ecletech/cron/crm/crm_cleanup.php >> /var/log/crm_cleanup.log 2>&1

# Sincronização completa semanal (domingos às 02:00)
0 2 * * 0 /usr/bin/php /var/www/Ecletech/cron/crm/crm_sync_full_clientes.php >> /var/log/crm_sync_full.log 2>&1
```

**Ajuste o caminho** conforme sua instalação!

---

## 🚀 Execução Manual via Interface

Os scripts de sincronização manual também podem ser executados via interface web:

1. Acesse: `http://localhost/public_html/crm_integracoes.html`
2. Na seção "Sincronização Manual"
3. Clique em "Sincronizar Clientes", "Sincronizar Produtos" ou "Sincronizar Vendas"
4. O sistema enfileira os registros
5. O cron `crm_sync.php` processa automaticamente

---

## ⚠️ Notas Importantes

### Rate Limiting

- **Máximo:** 100 requisições/minuto (configurado em `config.php`)
- **Batch:** 100 itens por execução do cron
- **SEM delays:** Processamento direto, sem `usleep()`

### Prioridades

| Entidade | Prioridade | Ordem de Processamento |
|----------|------------|------------------------|
| Vendas | 5 (alta) | Primeira |
| Clientes | 3 (média) | Segunda |
| Produtos | 3 (média) | Segunda |
| Atividades | 2 (baixa) | Terceira |

### Retry Logic

- **Máximo de tentativas:** 3
- **Delay inicial:** 2 segundos
- **Multiplicador:** 2 (backoff exponencial)
- Após 3 falhas: item permanece na fila marcado com erro

---

## 🆘 Troubleshooting

### Cron não executa

```bash
# Verificar se o cron está rodando
systemctl status cron

# Ver logs do cron
tail -f /var/log/syslog | grep CRON

# Testar execução manual
php /caminho/Ecletech/cron/crm/crm_sync.php
```

### Fila não processa

```sql
-- Ver se há itens pendentes
SELECT COUNT(*) FROM crm_sync_queue WHERE processado = 0;

-- Ver últimos logs
SELECT * FROM crm_sync_log ORDER BY criado_em DESC LIMIT 10;

-- Resetar tentativas (CUIDADO!)
UPDATE crm_sync_queue SET tentativas = 0 WHERE tentativas >= 3;
```

### Erros de permissão

```bash
# Dar permissão de execução
chmod +x /caminho/Ecletech/cron/crm/*.php

# Verificar usuário do cron
whoami

# Ajustar proprietário se necessário
chown www-data:www-data /caminho/Ecletech/cron/crm/*.php
```

---

## 📝 Logs

Direcione a saída para arquivos de log:

```bash
# Criar diretório de logs
mkdir -p /var/log/ecletech/crm

# Ajustar crontab com logs
* * * * * /usr/bin/php /caminho/crm_sync.php >> /var/log/ecletech/crm/sync.log 2>&1
```

Rotacionar logs com logrotate (`/etc/logrotate.d/ecletech-crm`):

```
/var/log/ecletech/crm/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
}
```

---

**Última atualização:** 2025-01-15
**Versão:** 2.0.0
