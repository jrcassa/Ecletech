# Configuração do Crontab - Sistema Ecletech

Este arquivo contém todas as configurações de crontab necessárias para o sistema Ecletech, organizadas por categoria.

## 📋 Como Usar

1. Abra o crontab para edição:
```bash
sudo crontab -e
```

2. Cole as configurações desejadas (ajustando os caminhos conforme sua instalação)

3. Salve e saia (CTRL+X, Y, ENTER no nano)

4. Verifique se foram adicionadas:
```bash
crontab -l
```

---

## 📢 NOTIFICAÇÕES

### Processamento de WhatsApp
```bash
# Processa fila WhatsApp a cada 1 minuto (alta demanda)
* * * * * /usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_whatsapp.php >> /var/log/ecletech/whatsapp_cron.log 2>&1

# OU a cada 5 minutos (demanda moderada)
*/5 * * * * /usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_whatsapp.php >> /var/log/ecletech/whatsapp_cron.log 2>&1
```

### Processamento de Email
```bash
# Processa fila de email a cada 1 minuto (alta demanda)
* * * * * /usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_email.php >> /var/log/ecletech/email_cron.log 2>&1

# OU a cada 5 minutos (demanda moderada)
*/5 * * * * /usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_email.php >> /var/log/ecletech/email_cron.log 2>&1
```

---

## 📊 RELATÓRIOS

### Geração de Snapshots
```bash
# Gera snapshots diários às 2:00 da manhã
0 2 * * * /usr/bin/php /var/www/Ecletech/cron/relatorios/gerar_snapshots.php >> /var/log/ecletech/gerar_snapshots.log 2>&1
```

### Relatório Semanal
```bash
# Envia relatório semanal toda segunda-feira às 8:00
0 8 * * 1 /usr/bin/php /var/www/Ecletech/cron/relatorios/relatorio_semanal.php >> /var/log/ecletech/relatorio_semanal.log 2>&1
```

### Relatório Mensal
```bash
# Envia relatório mensal todo dia 1 às 8:00
0 8 1 * * /usr/bin/php /var/www/Ecletech/cron/relatorios/relatorio_mensal.php >> /var/log/ecletech/relatorio_mensal.log 2>&1
```

### Reprocessamento de Relatórios
```bash
# Retenta enviar relatórios com erro a cada 2 horas
0 */2 * * * /usr/bin/php /var/www/Ecletech/cron/relatorios/reprocessar_relatorios.php >> /var/log/ecletech/reprocessar_relatorios.log 2>&1
```

---

## 🚗 ABASTECIMENTO

### Ordens Expiradas
```bash
# Verifica e marca ordens expiradas a cada hora
0 * * * * /usr/bin/php /var/www/Ecletech/cron/abastecimento/ordens_expiradas.php >> /var/log/ecletech/ordens_expiradas.log 2>&1
```

---

## 🔧 CONFIGURAÇÃO COMPLETA RECOMENDADA

Cole este bloco completo no seu crontab:

```bash
#####################################
# ECLETECH - CRON JOBS
#####################################

# === NOTIFICAÇÕES ===
# WhatsApp (a cada 1 minuto)
* * * * * /usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_whatsapp.php >> /var/log/ecletech/whatsapp_cron.log 2>&1

# Email (a cada 1 minuto)
* * * * * /usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_email.php >> /var/log/ecletech/email_cron.log 2>&1

# === RELATÓRIOS ===
# Snapshots diários (02:00)
0 2 * * * /usr/bin/php /var/www/Ecletech/cron/relatorios/gerar_snapshots.php >> /var/log/ecletech/gerar_snapshots.log 2>&1

# Relatório semanal (segunda 08:00)
0 8 * * 1 /usr/bin/php /var/www/Ecletech/cron/relatorios/relatorio_semanal.php >> /var/log/ecletech/relatorio_semanal.log 2>&1

# Relatório mensal (dia 1 às 08:00)
0 8 1 * * /usr/bin/php /var/www/Ecletech/cron/relatorios/relatorio_mensal.php >> /var/log/ecletech/relatorio_mensal.log 2>&1

# Reprocessar relatórios com erro (a cada 2 horas)
0 */2 * * * /usr/bin/php /var/www/Ecletech/cron/relatorios/reprocessar_relatorios.php >> /var/log/ecletech/reprocessar_relatorios.log 2>&1

# === ABASTECIMENTO ===
# Ordens expiradas (a cada hora)
0 * * * * /usr/bin/php /var/www/Ecletech/cron/abastecimento/ordens_expiradas.php >> /var/log/ecletech/ordens_expiradas.log 2>&1
```

---

## 📝 Formato do Crontab

```
* * * * * comando
│ │ │ │ │
│ │ │ │ └─── Dia da semana (0-7, onde 0 e 7 = domingo)
│ │ │ └───── Mês (1-12)
│ │ └─────── Dia do mês (1-31)
│ └───────── Hora (0-23)
└─────────── Minuto (0-59)
```

### Exemplos de Frequências

```bash
* * * * *        # A cada minuto
*/5 * * * *      # A cada 5 minutos
0 * * * *        # A cada hora (no minuto 0)
0 */2 * * *      # A cada 2 horas
0 8 * * *        # Todo dia às 8:00
0 8 * * 1        # Toda segunda-feira às 8:00
0 8 1 * *        # Todo dia 1 de cada mês às 8:00
0 2 * * *        # Todo dia às 2:00 da manhã
30 14 * * 0      # Todo domingo às 14:30
0 0 1 1 *        # Todo dia 1º de janeiro à meia-noite
```

---

## 🔍 Monitoramento

### Verificar se cron está rodando
```bash
# Ver jobs ativos
crontab -l

# Status do serviço cron
sudo systemctl status cron

# Logs do sistema
sudo tail -f /var/log/syslog | grep CRON
```

### Verificar logs dos jobs
```bash
# Todos os logs
ls -lh /var/log/ecletech/

# Últimas execuções
tail -20 /var/log/ecletech/*.log

# Acompanhar em tempo real
tail -f /var/log/ecletech/whatsapp_cron.log

# Buscar erros
grep -i "erro\|error\|fatal" /var/log/ecletech/*.log
```

### Estatísticas de execução
```bash
# Contar execuções do dia
grep "$(date +%Y-%m-%d)" /var/log/ecletech/whatsapp_cron.log | grep "Iniciando" | wc -l

# Ver últimos sucessos
grep "concluído com sucesso" /var/log/ecletech/*.log | tail -10

# Ver últimos erros
grep "ERRO" /var/log/ecletech/*.log | tail -10
```

---

## 🛠️ Troubleshooting

### Cron não executa

1. Verifique se o serviço está ativo:
```bash
sudo systemctl status cron
sudo systemctl start cron  # Se estiver parado
```

2. Verifique o caminho do PHP:
```bash
which php
# Use o caminho retornado no crontab
```

3. Teste manualmente:
```bash
/usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_whatsapp.php
```

### Permissões

```bash
# Dar permissão de execução
chmod +x /var/www/Ecletech/cron/notificacoes/*.php
chmod +x /var/www/Ecletech/cron/relatorios/*.php
chmod +x /var/www/Ecletech/cron/abastecimento/*.php

# Criar diretório de logs
sudo mkdir -p /var/log/ecletech
sudo chown www-data:www-data /var/log/ecletech
sudo chmod 755 /var/log/ecletech
```

### Variáveis de ambiente

Se o cron não consegue acessar variáveis de ambiente:

```bash
# Adicione no topo do crontab
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
```

---

## ⚠️ Notas Importantes

1. **Ajuste os caminhos**: Substitua `/var/www/Ecletech` pelo caminho real da sua instalação

2. **Fuso horário**: Todos os horários são relativos ao timezone configurado no sistema

3. **Logs**: Mantenha os logs organizados e faça rotação periódica para evitar uso excessivo de disco

4. **Backup**: Sempre faça backup do crontab antes de modificar:
```bash
crontab -l > ~/crontab_backup_$(date +%Y%m%d).txt
```

5. **Teste primeiro**: Execute manualmente cada script antes de adicionar ao cron

6. **Monitore**: Configure alertas para quando jobs falharem consecutivamente

---

## 📊 Resumo de Frequências

| Job | Frequência | Horário | Categoria |
|-----|-----------|---------|-----------|
| processar_whatsapp.php | 1 min | Sempre | Notificações |
| processar_email.php | 1 min | Sempre | Notificações |
| gerar_snapshots.php | Diário | 02:00 | Relatórios |
| relatorio_semanal.php | Semanal | Seg 08:00 | Relatórios |
| relatorio_mensal.php | Mensal | Dia 1 08:00 | Relatórios |
| reprocessar_relatorios.php | 2h | Sempre | Relatórios |
| ordens_expiradas.php | 1h | Sempre | Abastecimento |

---

## 📞 Suporte

Para dúvidas sobre configuração de crontab ou problemas com jobs:
1. Consulte a documentação em `cron/README.md`
2. Verifique os logs em `/var/log/ecletech/`
3. Entre em contato com a equipe de desenvolvimento
