# Cron Jobs - Frota Abastecimento

Este diretório contém os scripts de cron jobs para o módulo de gestão de abastecimentos da frota.

## Scripts Disponíveis

### 1. relatorio_semanal.php
Processa e envia relatórios semanais via WhatsApp para colaboradores configurados.

**Execução:** Toda segunda-feira às 8:00
**Crontab:**
```bash
0 8 * * 1 /usr/bin/php /var/www/Ecletech/cron/relatorio_semanal.php >> /var/log/ecletech/relatorio_semanal.log 2>&1
```

### 2. relatorio_mensal.php
Processa e envia relatórios mensais via WhatsApp para colaboradores configurados.

**Execução:** Todo dia 1 de cada mês às 8:00
**Crontab:**
```bash
0 8 1 * * /usr/bin/php /var/www/Ecletech/cron/relatorio_mensal.php >> /var/log/ecletech/relatorio_mensal.log 2>&1
```

### 3. gerar_snapshots.php
Gera snapshots (dados pré-calculados) para melhorar a performance dos relatórios.

**Execução:** Todo dia às 2:00
**Crontab:**
```bash
0 2 * * * /usr/bin/php /var/www/Ecletech/cron/gerar_snapshots.php >> /var/log/ecletech/gerar_snapshots.log 2>&1
```

### 4. ordens_expiradas.php
Marca ordens de abastecimento como expiradas quando passam da data limite e envia notificações.

**Execução:** A cada hora
**Crontab:**
```bash
0 * * * * /usr/bin/php /var/www/Ecletech/cron/ordens_expiradas.php >> /var/log/ecletech/ordens_expiradas.log 2>&1
```

### 5. reprocessar_relatorios.php
Retenta enviar relatórios que falharam (máximo 3 tentativas).

**Execução:** A cada 2 horas
**Crontab:**
```bash
0 */2 * * * /usr/bin/php /var/www/Ecletech/cron/reprocessar_relatorios.php >> /var/log/ecletech/reprocessar_relatorios.log 2>&1
```

## Configuração

Ajuste o caminho `/var/www/Ecletech` conforme o local de instalação.
