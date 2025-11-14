# Sistema de Relatórios de Abastecimento

## 📋 Visão Geral

O sistema de relatórios automáticos de abastecimento permite que colaboradores recebam relatórios periódicos (semanais ou mensais) via WhatsApp com análises de consumo, custos e alertas da frota.

## ⚠️ IMPORTANTE: Incompatibilidade de Dia da Semana

**ATENÇÃO:** O cron de relatórios semanais está configurado para rodar **apenas às segundas-feiras** às 8h.

Se você configurar para receber relatórios em outro dia da semana (terça, quarta, quinta, sexta, sábado ou domingo), **os relatórios NÃO serão enviados automaticamente**!

### Soluções:

**Opção 1: Usar segunda-feira (recomendado)**
```json
{
  "tipo_relatorio": "semanal",
  "dia_envio_semanal": "segunda",
  "hora_envio": "08:00:00"
}
```

**Opção 2: Ajustar crontab para o dia desejado**

Por exemplo, para quinta-feira:
```cron
# Envia relatórios semanais (quinta às 8h)
0 8 * * 4 php /path/to/cron/relatorios/relatorio_semanal.php
```

**Opção 3: Criar múltiplos crons para cada dia**
```cron
# Segunda
0 8 * * 1 php /path/to/cron/relatorios/relatorio_semanal.php

# Quinta
0 8 * * 4 php /path/to/cron/relatorios/relatorio_semanal.php
```

**Opção 4: Usar envio manual**

Se preferir controle total, desative envio automático e use a API para enviar manualmente quando desejar.

---

## 🔄 Como Funciona

### 1. Fluxo Automático (Cron Jobs)

**a) Geração de Snapshots** (Diário às 2h)
```bash
cron/relatorios/gerar_snapshots.php
```
- Calcula dados agregados de abastecimentos
- Gera snapshot semanal (últimos 7 dias)
- No dia 1 do mês, também gera snapshot mensal
- Armazena em `frotas_abastecimentos_relatorios_snapshots`

**b) Envio Semanal** (Segunda-feira às 8h)
```bash
cron/relatorios/relatorio_semanal.php
```
- Busca configurações ativas para relatórios semanais
- Filtra por dia_envio_semanal = 'segunda'
- Envia para cada colaborador configurado

**c) Envio Mensal** (Dia 1 de cada mês às 8h)
```bash
cron/relatorios/relatorio_mensal.php
```
- Busca configurações ativas para relatórios mensais
- Filtra por dia_envio_mensal = 1
- Envia para cada colaborador configurado

**d) Reprocessamento de Falhas** (A cada 2 horas)
```bash
cron/relatorios/reprocessar_relatorios.php
```
- Busca logs com status 'erro'
- Retenta envio (máximo 3 tentativas)

### 2. Fluxo Manual (via API)

**a) Gerar Relatório (sem enviar)**
```http
POST /frota-abastecimento-relatorios/gerar-manual
```
```json
{
  "tipo_relatorio": "semanal|mensal",
  "periodo_inicio": "2025-01-01",
  "periodo_fim": "2025-01-07",
  "formato": "resumido|detalhado|completo"
}
```

**b) Enviar Relatório**
```http
POST /frota-abastecimento-relatorios/enviar-manual
```
```json
{
  "tipo_relatorio": "semanal|mensal",
  "periodo_inicio": "2025-01-01",
  "periodo_fim": "2025-01-07",
  "formato": "resumido|detalhado|completo"
}
```

## ⚙️ Configuração

### Passo 1: Criar Configuração para Colaborador

```http
POST /frota-abastecimento-relatorios/configurar
```
```json
{
  "tipo_relatorio": "semanal",
  "ativo": true,
  "dia_envio_semanal": "segunda",
  "hora_envio": "08:00:00",
  "formato_relatorio": "detalhado"
}
```

**Campos para relatório mensal:**
```json
{
  "tipo_relatorio": "mensal",
  "ativo": true,
  "dia_envio_mensal": 1,
  "hora_envio": "08:00:00",
  "formato_relatorio": "detalhado"
}
```

### Passo 2: Verificar Configuração

```http
GET /frota-abastecimento-relatorios/minhas-configuracoes
```

Retorna array de configurações do usuário logado.

## 📊 Estrutura de Dados

### Tabelas

1. **frotas_abastecimentos_relatorios_configuracoes**
   - Quem recebe relatórios
   - Tipo (semanal/mensal)
   - Dia e hora de envio
   - Formato preferido

2. **frotas_abastecimentos_relatorios_logs**
   - Histórico de todos os envios
   - Status: pendente, enviado, erro, cancelado
   - Conteúdo da mensagem
   - Tentativas de reenvio

3. **frotas_abastecimentos_relatorios_snapshots**
   - Dados pré-calculados
   - Totais e médias
   - Rankings de consumo/economia
   - Comparativos com período anterior

## 🔍 Diagnóstico de Problemas

### Por que não está enviando?

Verifique:

1. **Há configurações ativas?**
   ```sql
   SELECT * FROM frotas_abastecimentos_relatorios_configuracoes
   WHERE ativo = TRUE;
   ```

2. **Colaborador tem celular cadastrado?**
   ```sql
   SELECT c.*, col.celular
   FROM frotas_abastecimentos_relatorios_configuracoes c
   JOIN colaboradores col ON col.id = c.colaborador_id
   WHERE c.ativo = TRUE AND col.celular IS NULL;
   ```
   **IMPORTANTE:** Colaboradores SEM celular são ignorados automaticamente!

3. **Há dados de abastecimento?**
   ```sql
   SELECT COUNT(*) FROM frotas_abastecimentos
   WHERE data_abastecimento >= DATE_SUB(NOW(), INTERVAL 7 DAY);
   ```

4. **WhatsApp está configurado?**
   ```sql
   SELECT * FROM whatsapp_configuracoes
   WHERE chave IN ('api_url', 'api_token');
   ```
   Sem WhatsApp configurado, envios falharão!

5. **Crons estão rodando?**
   ```bash
   # Verificar se crontab está configurado
   crontab -l | grep relatorio

   # Esperado:
   # 0 2 * * * php /path/to/cron/relatorios/gerar_snapshots.php
   # 0 8 * * 1 php /path/to/cron/relatorios/relatorio_semanal.php
   # 0 8 1 * * php /path/to/cron/relatorios/relatorio_mensal.php
   # 0 */2 * * * php /path/to/cron/relatorios/reprocessar_relatorios.php
   ```

6. **Verificar logs de erro:**
   ```sql
   SELECT * FROM frotas_abastecimentos_relatorios_logs
   WHERE status_envio = 'erro'
   ORDER BY criado_em DESC
   LIMIT 10;
   ```

### Script de Teste

Execute o script de diagnóstico:
```bash
php test_relatorio.php
```

Este script verifica:
- Abastecimentos disponíveis
- Configurações ativas
- Snapshots gerados
- Logs de envio
- Gera relatório de teste
- Verifica configuração do WhatsApp

## 📝 Formatos de Relatório

### Resumido
- Totais gerais (abastecimentos, litros, valor, km)
- Médias (consumo, custo/km, custo/litro)
- Variação vs período anterior
- Total de alertas

### Detalhado (padrão)
- Tudo do resumido
- **Top 5 veículos** por consumo
- **Top 5 motoristas** por economia
- Dados por tipo de combustível

### Completo
- Tudo do detalhado
- **Ranking completo** de melhor/pior consumo
- **Ranking completo** de economia
- Top 3 em cada categoria

## 🚨 Problemas Comuns

### 1. "Não recebo relatórios"

**Causas possíveis:**
- ❌ Configuração não criada ou desativada
- ❌ Celular não cadastrado no perfil
- ❌ WhatsApp não configurado
- ❌ Crons não executando
- ❌ **Dia/hora de envio incompatível com crontab** (MUITO COMUM!)
  - Ex: Configurado para quinta, mas cron roda só segunda

**Solução:**
1. Verificar configuração via API
2. Cadastrar celular no perfil
3. Configurar WhatsApp (ver migration 2025_01_12_create_whatsapp_tables.sql)
4. Configurar crontab
5. Ajustar dia/hora de envio

### 2. "Relatório vazio ou sem dados"

**Causas possíveis:**
- ❌ Sem abastecimentos no período
- ❌ Abastecimentos sem métricas calculadas
- ❌ Snapshot não foi gerado

**Solução:**
1. Verificar se há abastecimentos no período
2. Forçar recálculo de snapshot via API:
   ```http
   POST /frota-abastecimento-relatorios/recalcular-snapshot
   {
     "tipo_periodo": "semanal",
     "periodo_inicio": "2025-01-13",
     "periodo_fim": "2025-01-19"
   }
   ```

### 3. "Erro ao enviar"

**Causas possíveis:**
- ❌ WhatsApp desconectado/inativo
- ❌ Número inválido
- ❌ API do WhatsApp fora do ar

**Solução:**
1. Verificar status da API: `/whatsapp/painel/estatisticas`
2. Validar número do colaborador
3. Checar logs em `frotas_abastecimentos_relatorios_logs`
4. Sistema retenta automaticamente até 3 vezes

## 🔒 Permissões Necessárias

- `frota_abastecimento.receber_relatorio` - Configurar e receber relatórios
- `frota_abastecimento.visualizar` - Gerar manualmente e ver snapshots

## 📌 Observações Importantes

1. **Envio Direto vs Fila**
   - Relatórios usam **modo direto** (não fila)
   - Garantia de status imediato (enviado/erro)
   - Mais apropriado para mensagens importantes

2. **Performance**
   - Snapshots são pré-calculados (cron diário)
   - Evita recalcular a cada envio
   - Envios rápidos e eficientes

3. **Dados Necessários**
   - Abastecimentos precisam ter métricas calculadas
   - Sistema ignora abastecimentos sem km_percorrido
   - Recomenda-se registrar hodômetro em cada abastecimento

4. **Limite de Caracteres**
   - WhatsApp tem limite de ~4096 caracteres
   - Use formato "resumido" para frotas grandes
   - Formato "completo" pode exceder limite

## 🛠️ Manutenção

### Limpar logs antigos
```sql
DELETE FROM frotas_abastecimentos_relatorios_logs
WHERE criado_em < DATE_SUB(NOW(), INTERVAL 6 MONTH);
```

### Limpar snapshots antigos
```sql
DELETE FROM frotas_abastecimentos_relatorios_snapshots
WHERE calculado_em < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Reprocessar período específico
```http
POST /frota-abastecimento-relatorios/recalcular-snapshot
{
  "tipo_periodo": "semanal",
  "periodo_inicio": "2025-01-06",
  "periodo_fim": "2025-01-12"
}
```
