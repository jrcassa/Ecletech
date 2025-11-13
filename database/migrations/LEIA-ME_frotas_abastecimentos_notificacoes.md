# ⚠️ Tabela `frotas_abastecimentos_notificacoes` DEPRECATED

## Status: **NÃO USAR**

Esta tabela foi **descontinuada** e **não deve mais ser utilizada**.

## Por quê?

O sistema de notificações foi **centralizado e unificado** para usar apenas o sistema de WhatsApp com as tabelas:

- **`whatsapp_queue`** - Fila de mensagens a serem enviadas
- **`whatsapp_historico`** - Histórico completo de todos os envios

## Vantagens do novo sistema

✅ **Fila única** para todas as mensagens WhatsApp do sistema
✅ **Histórico completo** com status de entrega e leitura
✅ **Sistema de retry** automático em caso de falha
✅ **Controle de prioridade** (alta, normal, baixa)
✅ **Delay anti-ban** entre envios
✅ **Rastreamento completo** de status (enviado → entregue → lido)
✅ **Centralização** - todos os módulos usam o mesmo sistema

## Como usar o novo sistema?

```php
use App\Services\Whatsapp\ServiceWhatsapp;

$whatsapp = new ServiceWhatsapp();

$resultado = $whatsapp->enviarMensagem([
    'destinatario' => [
        'tipo_entidade' => 'colaborador',
        'entidade_id' => 123,
        'numero' => '5511999999999',
        'nome' => 'João Silva'
    ],
    'tipo' => 'text',
    'mensagem' => 'Sua mensagem aqui',
    'prioridade' => 'alta',
    'metadata' => [
        'modulo' => 'frota_abastecimento',
        'tipo_notificacao' => 'ordem_criada'
    ]
]);
```

## Arquivos afetados

### ❌ NÃO USAR MAIS:
- `App/Models/FrotaAbastecimento/ModelFrotaAbastecimentoNotificacao.php` - DEPRECATED
- Tabela `frotas_abastecimentos_notificacoes` - DEPRECATED

### ✅ USAR:
- `App/Services/Whatsapp/ServiceWhatsapp.php` - Sistema unificado
- Tabela `whatsapp_queue` - Fila de envio
- Tabela `whatsapp_historico` - Histórico completo

## Migration de remoção

Para remover a tabela deprecated do banco de dados, execute:

```bash
# Execute a migration de drop (opcional)
mysql -u usuario -p banco < database/migrations/999_drop_tabela_frotas_abastecimentos_notificacoes_deprecated.sql
```

**⚠️ IMPORTANTE:** Faça backup antes de dropar a tabela!

## Histórico

- **2025-11-13**: Tabela descontinuada em favor do sistema unificado de WhatsApp
- **Motivo**: Centralizar todas as notificações WhatsApp em um único sistema

---

Para dúvidas, consulte a documentação do sistema de WhatsApp.
