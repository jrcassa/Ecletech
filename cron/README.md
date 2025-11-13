# Cron do WhatsApp

Este diretório contém scripts cron para processamento automatizado da fila do WhatsApp.

## 📋 Scripts Disponíveis

### `processar_whatsapp.php`

Processa mensagens pendentes na fila do WhatsApp.

**Funcionalidades:**
- Busca mensagens pendentes na tabela `whatsapp_queue`
- Envia mensagens via API Baileys
- Atualiza status das mensagens
- Implementa retry automático em caso de falha
- Aplica delay anti-ban entre mensagens
- Registra logs de execução

## ⚙️ Configuração

### 1. Configurar Crontab

Edite o crontab do usuário:

```bash
crontab -e
```

Adicione uma das seguintes linhas:

**Executar a cada 1 minuto (recomendado para alto volume):**
```bash
* * * * * php /caminho/completo/para/cron/processar_whatsapp.php >> /var/log/whatsapp_cron.log 2>&1
```

**Executar a cada 5 minutos:**
```bash
*/5 * * * * php /caminho/completo/para/cron/processar_whatsapp.php >> /var/log/whatsapp_cron.log 2>&1
```

**Executar a cada 10 minutos:**
```bash
*/10 * * * * php /caminho/completo/para/cron/processar_whatsapp.php >> /var/log/whatsapp_cron.log 2>&1
```

### 2. Criar Diretório de Logs

```bash
sudo mkdir -p /var/log
sudo touch /var/log/whatsapp_cron.log
sudo chmod 666 /var/log/whatsapp_cron.log
```

### 3. Configurações do Sistema

As seguintes configurações podem ser ajustadas na tabela `whatsapp_config`:

| Chave | Descrição | Padrão |
|-------|-----------|--------|
| `cron_habilitado` | Habilita/desabilita o processamento | `true` |
| `cron_limite_mensagens` | Número máximo de mensagens por execução | `10` |
| `antiban_delay_min` | Delay mínimo entre mensagens (segundos) | `3` |
| `antiban_delay_max` | Delay máximo entre mensagens (segundos) | `7` |
| `retry_max_tentativas` | Número máximo de tentativas de reenvio | `3` |
| `retry_base_delay` | Delay base para retry (segundos) | `60` |
| `retry_multiplicador` | Multiplicador para backoff exponencial | `2` |

## 📊 Monitoramento

### Ver Logs em Tempo Real

```bash
tail -f /var/log/whatsapp_cron.log
```

### Ver Últimas 100 Linhas

```bash
tail -n 100 /var/log/whatsapp_cron.log
```

### Limpar Logs Antigos

```bash
echo "" > /var/log/whatsapp_cron.log
```

### Filtrar Erros

```bash
grep -i "erro" /var/log/whatsapp_cron.log
```

## 🧪 Teste Manual

Para testar o script manualmente:

```bash
php /caminho/completo/para/cron/processar_whatsapp.php
```

Ou se estiver na raiz do projeto:

```bash
php cron/processar_whatsapp.php
```

## 🔧 Troubleshooting

### Cron Não Está Executando

1. Verifique se o crontab está configurado:
   ```bash
   crontab -l
   ```

2. Verifique logs do sistema:
   ```bash
   grep CRON /var/log/syslog
   ```

3. Teste execução manual do script

### Erros de Permissão

```bash
chmod +x /caminho/completo/para/cron/processar_whatsapp.php
chown www-data:www-data /caminho/completo/para/cron/processar_whatsapp.php
```

### Script Não Encontra Classes

Certifique-se de que o caminho para o diretório `App` está correto no script.

## 📈 Boas Práticas

1. **Intervalo Recomendado**: 1-5 minutos para processamento contínuo
2. **Limite de Mensagens**: Ajuste conforme volume (padrão: 10)
3. **Monitoramento**: Configure alertas para erros críticos
4. **Backup de Logs**: Implemente rotação de logs
5. **Horário de Pico**: Considere aumentar frequência em horários de maior volume

## 🚨 Alertas e Notificações

Para receber alertas de erros, você pode modificar o script para:

- Enviar email em caso de falha
- Postar notificação em webhook
- Integrar com ferramentas de monitoramento (Sentry, New Relic, etc.)

## 📝 Exemplo de Output

```
[2025-01-13 10:00:01] === Iniciando processamento da fila WhatsApp ===
[2025-01-13 10:00:01] Limite de mensagens: 10
[2025-01-13 10:00:01] Processadas: 5
[2025-01-13 10:00:01] Sucesso: 4
[2025-01-13 10:00:01] Erro: 1
[2025-01-13 10:00:01]   - Erro queue_id 123: Número inválido
[2025-01-13 10:00:01] Duração: 12 segundos
[2025-01-13 10:00:01] === Processamento concluído ===
```

## 🔐 Segurança

- O script verifica se está sendo executado via CLI
- Usa autoloader seguro
- Carrega variáveis de ambiente do `.env`
- Registra todas as operações em log

## 📚 Referências

- [Documentação do WhatsApp](/README_WHATSAPP.md)
- [Configuração do ACL](/ACL_DOCUMENTATION.md)
- [Crontab Guru](https://crontab.guru/) - Ajuda com sintaxe do cron
