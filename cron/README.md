# Cron Jobs - Sistema Ecletech

Este diretório contém os scripts de cron jobs do sistema, organizados por categoria para facilitar a manutenção e compreensão.

## 📁 Estrutura Organizada

```
cron/
├── notificacoes/          # Processamento de filas de mensagens
│   ├── processar_whatsapp.php
│   └── processar_email.php
├── relatorios/            # Geração e envio de relatórios
│   ├── gerar_snapshots.php
│   ├── relatorio_semanal.php
│   ├── relatorio_mensal.php
│   └── reprocessar_relatorios.php
└── abastecimento/         # Gestão de ordens de abastecimento
    └── ordens_expiradas.php
```

---

## 📢 Notificações

### processar_whatsapp.php
Processa mensagens pendentes na fila do WhatsApp.

**Frequência:** A cada 1 minuto (ou 5 minutos, conforme necessidade)
**Crontab:**
```bash
* * * * * /usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_whatsapp.php >> /var/log/ecletech/whatsapp_cron.log 2>&1
```

**Funcionalidades:**
- Verifica se processamento está habilitado via configuração
- Processa até N mensagens por execução (configurável)
- Registra logs detalhados de sucesso/erro
- Tratamento de erros com ErrorLogger

---

### processar_email.php
Processa emails pendentes na fila de envio.

**Frequência:** A cada 1 minuto (ou 5 minutos, conforme necessidade)
**Crontab:**
```bash
* * * * * /usr/bin/php /var/www/Ecletech/cron/notificacoes/processar_email.php >> /var/log/ecletech/email_cron.log 2>&1
```

**Funcionalidades:**
- Verifica horário de funcionamento configurado
- Processa até N emails por execução (configurável)
- Registra logs de execução no banco de dados
- Controle de memória e tempo de execução

---

## 📊 Relatórios

### gerar_snapshots.php
Gera snapshots (dados pré-calculados) para melhorar a performance dos relatórios.

**Frequência:** Todo dia às 2:00
**Crontab:**
```bash
0 2 * * * /usr/bin/php /var/www/Ecletech/cron/relatorios/gerar_snapshots.php >> /var/log/ecletech/gerar_snapshots.log 2>&1
```

**Funcionalidades:**
- Gera snapshot semanal (domingo a sábado anterior)
- Gera snapshot mensal (no dia 1 de cada mês)
- Otimiza consultas de relatórios
- Dados pré-calculados para análises rápidas

---

### relatorio_semanal.php
Processa e envia relatórios semanais via WhatsApp para colaboradores configurados.

**Frequência:** Toda segunda-feira às 8:00
**Crontab:**
```bash
0 8 * * 1 /usr/bin/php /var/www/Ecletech/cron/relatorios/relatorio_semanal.php >> /var/log/ecletech/relatorio_semanal.log 2>&1
```

**Funcionalidades:**
- Processa envios automáticos semanais
- Envia relatórios para colaboradores configurados
- Utiliza snapshots para performance otimizada

---

### relatorio_mensal.php
Processa e envia relatórios mensais via WhatsApp para colaboradores configurados.

**Frequência:** Todo dia 1 de cada mês às 8:00
**Crontab:**
```bash
0 8 1 * * /usr/bin/php /var/www/Ecletech/cron/relatorios/relatorio_mensal.php >> /var/log/ecletech/relatorio_mensal.log 2>&1
```

**Funcionalidades:**
- Processa envios automáticos mensais
- Envia relatórios consolidados do mês anterior
- Análise de tendências e indicadores

---

### reprocessar_relatorios.php
Retenta enviar relatórios que falharam (máximo 3 tentativas).

**Frequência:** A cada 2 horas
**Crontab:**
```bash
0 */2 * * * /usr/bin/php /var/www/Ecletech/cron/relatorios/reprocessar_relatorios.php >> /var/log/ecletech/reprocessar_relatorios.log 2>&1
```

**Funcionalidades:**
- Busca relatórios com erro das últimas 24h
- Retenta envios com falha (até 3 tentativas)
- Registra tentativas e status
- Evita perda de relatórios importantes

---

## 🚗 Abastecimento

### ordens_expiradas.php
Marca ordens de abastecimento como expiradas quando passam da data limite e envia notificações.

**Frequência:** A cada hora
**Crontab:**
```bash
0 * * * * /usr/bin/php /var/www/Ecletech/cron/abastecimento/ordens_expiradas.php >> /var/log/ecletech/ordens_expiradas.log 2>&1
```

**Funcionalidades:**
- Verifica ordens com status "aguardando" e data limite vencida
- Atualiza status para "expirado"
- Envia notificação via WhatsApp para motorista
- Informa placa, data limite e observações

---

## ⚙️ Configuração

### Pré-requisitos
- PHP 7.4+
- Composer (para dependências)
- Acesso ao banco de dados
- Configurações no arquivo `.env`

### Instalação

1. Ajuste o caminho base conforme sua instalação:
```bash
# Substituir /var/www/Ecletech pelo seu caminho
```

2. Crie o diretório de logs:
```bash
sudo mkdir -p /var/log/ecletech
sudo chown www-data:www-data /var/log/ecletech
```

3. Adicione os jobs ao crontab:
```bash
sudo crontab -e
# Cole as configurações do arquivo CRONTAB.md
```

### Monitoramento

Acompanhe a execução dos cron jobs:

```bash
# Logs gerais
tail -f /var/log/ecletech/*.log

# Log específico
tail -f /var/log/ecletech/processar_whatsapp.log

# Verificar últimas execuções
grep "concluído\|ERRO" /var/log/ecletech/*.log | tail -20
```

### Troubleshooting

**Cron não executa:**
- Verifique permissões dos arquivos PHP
- Confirme o caminho do PHP: `which php`
- Verifique o crontab: `crontab -l`

**Erros de execução:**
- Confira logs em `/var/log/ecletech/`
- Verifique conexão com banco de dados
- Confirme variáveis de ambiente no `.env`

**Performance:**
- Ajuste limites de processamento nas configurações
- Monitore uso de memória nos logs
- Considere ajustar frequência de execução

---

## 📝 Logs e Auditoria

Todos os cron jobs registram:
- ✅ Início e fim de execução
- ✅ Total de registros processados
- ✅ Sucessos e erros
- ✅ Tempo de execução
- ✅ Uso de memória
- ✅ Stack trace em caso de erro

Os erros são registrados na tabela `erros_log` via `ErrorLogger::log()`.

---

## 🔐 Segurança

- Execução apenas via CLI
- Validação de permissões
- Tratamento de exceções
- Logs auditáveis
- Limite de tentativas (retry)

---

## 📞 Suporte

Em caso de problemas:
1. Consulte os logs em `/var/log/ecletech/`
2. Verifique a tabela `erros_log` no banco
3. Revise as configurações no `.env`
4. Entre em contato com a equipe de desenvolvimento
