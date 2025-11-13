# WhatsApp - Modo de Envio (Fila vs Direto)

## 📋 Visão Geral

O sistema WhatsApp agora suporta dois modos de envio de mensagens:

### 1. **Via Fila (Assíncrono)** 🔄
- Mensagem é adicionada na tabela `whatsapp_queue`
- Processada pelo cron em segundo plano
- **Vantagens:**
  - Resposta rápida ao usuário
  - Retry automático em caso de falha
  - Controle de taxa de envio (anti-ban)
  - Processamento em lote
  - Ideal para grandes volumes

### 2. **Envio Direto (Síncrono)** ⚡
- Mensagem é enviada imediatamente via API
- Aguarda resposta da API antes de retornar
- **Vantagens:**
  - Feedback imediato sobre sucesso/falha
  - Sem necessidade de cron
  - Ideal para mensagens urgentes
  - Confirmação instantânea

---

## 🏗️ Arquitetura

### Fluxo via Fila

```
Interface → POST /whatsapp/enviar (modo_envio=fila)
    ↓
ControllerWhatsappEnvio::enviar()
    ↓
ServiceWhatsapp::enviarMensagem()
    ↓
ServiceWhatsapp::enviarViaFila()
    ↓
Adiciona na whatsapp_queue (status=pendente)
    ↓
Cron (processar_whatsapp.php) - executa a cada 1 minuto
    ↓
ServiceWhatsapp::processarFila()
    ↓
Envia via ModelWhatsappBaileys
    ↓
Atualiza status na queue (status=enviado)
```

### Fluxo Direto

```
Interface → POST /whatsapp/enviar (modo_envio=direto)
    ↓
ControllerWhatsappEnvio::enviar()
    ↓
ServiceWhatsapp::enviarMensagem()
    ↓
ServiceWhatsapp::enviarDireto()
    ↓
Envia via ModelWhatsappBaileys (imediato)
    ↓
Registra no whatsapp_historico
    ↓
Retorna message_id
```

---

## 🔧 Implementação

### 1. Backend (PHP)

#### ServiceWhatsapp.php

```php
public function enviarMensagem(array $dados): array
{
    // Determina modo de envio
    $modoEnvio = $dados['modo_envio'] ??
                 $this->configModel->obter('modo_envio_padrao', 'fila');

    if ($modoEnvio === 'direto') {
        return $this->enviarDireto($dadosCompletos);
    } else {
        return $this->enviarViaFila($dadosCompletos);
    }
}

private function enviarViaFila(array $dados): array
{
    // Adiciona na whatsapp_queue
    $queueId = $this->queueModel->adicionar($dadosFila);

    return [
        'sucesso' => true,
        'modo' => 'fila',
        'queue_id' => $queueId
    ];
}

private function enviarDireto(array $dados): array
{
    // Envia imediatamente via Baileys
    $response = $this->getBaileys()->sendText(...);

    return [
        'sucesso' => true,
        'modo' => 'direto',
        'message_id' => $messageId
    ];
}
```

### 2. Frontend (JavaScript)

#### whatsapp.html

```html
<div class="mb-3">
    <label class="form-label">Modo de Envio</label>
    <div class="btn-group w-100" role="group">
        <input type="radio" name="modo-envio" value="fila" checked>
        <label>Via Fila (Assíncrono)</label>

        <input type="radio" name="modo-envio" value="direto">
        <label>Envio Direto (Síncrono)</label>
    </div>
</div>
```

#### Whatsapp.js

```javascript
const modoEnvio = document.querySelector('input[name="modo-envio"]:checked')?.value || 'fila';

const dados = {
    destinatario: numero,
    tipo: tipoMensagem,
    modo_envio: modoEnvio,
    mensagem: texto
};

const response = await API.post('/whatsapp/enviar', dados);

if (response.sucesso) {
    if (response.dados.modo === 'fila') {
        alert(`Adicionado à fila! ID: ${response.dados.queue_id}`);
    } else {
        alert(`Enviado! ID: ${response.dados.message_id}`);
    }
}
```

### 3. Cron Job

#### processar_whatsapp.php

```php
#!/usr/bin/env php
<?php
// Processa fila automaticamente

$service = new ServiceWhatsapp();
$limite = $config->obter('cron_limite_mensagens', 10);

$resultado = $service->processarFila($limite);

echo "Processadas: {$resultado['processadas']}\n";
echo "Sucesso: {$resultado['sucesso']}\n";
echo "Erro: {$resultado['erro']}\n";
```

**Configuração crontab:**
```bash
# Executar a cada 1 minuto
* * * * * php /caminho/para/cron/processar_whatsapp.php >> /var/log/whatsapp_cron.log 2>&1
```

---

## ⚙️ Configurações

### Banco de Dados (whatsapp_configuracoes)

| Chave | Valor Padrão | Descrição |
|-------|--------------|-----------|
| `modo_envio_padrao` | `fila` | Modo padrão: `fila` ou `direto` |
| `cron_habilitado` | `true` | Habilita processamento via cron |
| `cron_limite_mensagens` | `10` | Mensagens por execução |
| `antiban_delay_min` | `3` | Delay mínimo entre envios (seg) |
| `antiban_delay_max` | `7` | Delay máximo entre envios (seg) |
| `retry_max_tentativas` | `3` | Tentativas de reenvio |
| `retry_base_delay` | `60` | Delay base para retry (seg) |
| `retry_multiplicador` | `2` | Multiplicador backoff exponencial |

---

## 📊 API

### POST /whatsapp/enviar

**Request:**
```json
{
    "destinatario": "5515999999999",
    "tipo": "text",
    "mensagem": "Olá!",
    "modo_envio": "fila"
}
```

**Response (Fila):**
```json
{
    "sucesso": true,
    "mensagem": "Mensagem adicionada à fila",
    "dados": {
        "modo": "fila",
        "queue_id": 123
    }
}
```

**Response (Direto):**
```json
{
    "sucesso": true,
    "mensagem": "Mensagem enviada diretamente",
    "dados": {
        "modo": "direto",
        "message_id": "3EB0C127E5D5E8E8F0B2",
        "dados": {
            "key": {...},
            "message": {...}
        }
    }
}
```

---

## 🚀 Instalação

### 1. Executar Migration

```bash
php executar_migration_whatsapp_modo_envio.php
```

Isso criará/atualizará as configurações necessárias.

### 2. Configurar Cron

Edite o crontab:
```bash
crontab -e
```

Adicione:
```bash
* * * * * php /caminho/completo/para/cron/processar_whatsapp.php >> /var/log/whatsapp_cron.log 2>&1
```

### 3. Criar Log

```bash
sudo touch /var/log/whatsapp_cron.log
sudo chmod 666 /var/log/whatsapp_cron.log
```

### 4. Testar Cron Manualmente

```bash
php cron/processar_whatsapp.php
```

---

## 📈 Comparação

| Aspecto | Via Fila | Direto |
|---------|----------|--------|
| **Velocidade de Resposta** | ⚡⚡⚡ Rápida | 🐌 Lenta (aguarda API) |
| **Confiabilidade** | ✅ Alta (retry) | ⚠️ Média (sem retry) |
| **Processamento** | 🔄 Assíncrono | ⏱️ Síncrono |
| **Anti-ban** | ✅ Delay automático | ❌ Sem controle |
| **Confirmação** | ⏳ Posterior | ✅ Imediata |
| **Uso Ideal** | 📦 Envios em massa | 🎯 Mensagens urgentes |
| **Requisitos** | ⚙️ Cron necessário | ❌ Sem dependências |

---

## 🧪 Testes

### Teste Via Fila

1. Acesse whatsapp.html
2. Selecione "Via Fila"
3. Preencha os dados
4. Clique em "Enviar"
5. Verifique na aba "Fila" o status
6. Aguarde o cron processar (1 minuto)
7. Verifique se status mudou para "Enviado"

### Teste Direto

1. Acesse whatsapp.html
2. Selecione "Envio Direto"
3. Preencha os dados
4. Clique em "Enviar"
5. Aguarde resposta (pode demorar alguns segundos)
6. Veja o message_id na resposta

---

## 🔍 Monitoramento

### Ver Logs do Cron

```bash
tail -f /var/log/whatsapp_cron.log
```

### Verificar Fila

```sql
SELECT * FROM whatsapp_queue WHERE status_code = 1 ORDER BY criado_em;
```

### Verificar Histórico

```sql
SELECT * FROM whatsapp_historico
WHERE tipo_evento IN ('enviado_direto', 'adicionado_fila')
ORDER BY criado_em DESC
LIMIT 10;
```

---

## 🛠️ Troubleshooting

### Cron Não Está Processando

1. Verifique se está configurado:
   ```bash
   crontab -l
   ```

2. Verifique logs:
   ```bash
   grep CRON /var/log/syslog
   ```

3. Teste manualmente:
   ```bash
   php cron/processar_whatsapp.php
   ```

### Mensagens Ficam Pendentes

1. Verifique configuração:
   ```sql
   SELECT * FROM whatsapp_configuracoes WHERE chave = 'cron_habilitado';
   ```

2. Ative se necessário:
   ```sql
   UPDATE whatsapp_configuracoes SET valor = 'true' WHERE chave = 'cron_habilitado';
   ```

### Envio Direto Falha

- Verifique conexão com API Baileys
- Verifique se WhatsApp está conectado
- Veja logs de erro no console do navegador

---

## 📝 Changelog

### v1.1.0 (2025-01-13)
- ✨ Implementado modo de envio direto
- ✨ Implementado modo de envio via fila
- ✨ Criado script cron para processamento
- ✨ Interface atualizada com seleção de modo
- 📝 Documentação completa

---

## 📚 Referências

- [README WhatsApp](/README_WHATSAPP.md)
- [Documentação do Cron](/cron/README.md)
- [API Baileys](https://github.com/WhiskeySockets/Baileys)
