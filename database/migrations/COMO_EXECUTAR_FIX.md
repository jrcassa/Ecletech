# Como Corrigir Colunas Faltantes nas Tabelas de Dashboard

## Problema
As tabelas `dashboard_widgets` e `dashboard_templates` estão faltando as colunas:
- `ordem`
- `criado_em`
- `atualizado_em`

## Solução Rápida

### Opção 1: Via phpMyAdmin (RECOMENDADO)

1. Abra o phpMyAdmin
2. Selecione seu banco de dados (Ecletech)
3. Clique na aba "SQL" no topo
4. Cole TODO o conteúdo do arquivo `FIX_DASHBOARD_COLUNAS_ALTERNATIVO.sql`
5. Clique em "Executar"
6. **IMPORTANTE**: Se aparecer erro "Duplicate column name", IGNORE e continue
7. Após executar, verifique se as colunas foram adicionadas:
   - Clique em "Estrutura" da tabela `dashboard_widgets`
   - Verifique se existe: ordem, criado_em, atualizado_em
   - Repita para `dashboard_templates`

### Opção 2: Via Linha de Comando

```bash
mysql -u seu_usuario -p seu_banco < database/migrations/FIX_DASHBOARD_COLUNAS_ALTERNATIVO.sql
```

### Opção 3: Executar Comando por Comando

Se as opções acima não funcionarem, execute CADA linha separadamente no phpMyAdmin:

**Para dashboard_widgets:**
```sql
ALTER TABLE dashboard_widgets ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER altura;
ALTER TABLE dashboard_widgets ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER ativo;
ALTER TABLE dashboard_widgets ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em;
```

**Para dashboard_templates:**
```sql
ALTER TABLE dashboard_templates ADD COLUMN ordem INT NOT NULL DEFAULT 0 AFTER cor;
ALTER TABLE dashboard_templates ADD COLUMN criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_sistema;
ALTER TABLE dashboard_templates ADD COLUMN atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER criado_em;
```

## Verificação

Após executar, verifique se deu certo executando:

```sql
DESCRIBE dashboard_widgets;
DESCRIBE dashboard_templates;
```

Você deve ver as colunas `ordem`, `criado_em` e `atualizado_em` listadas.

## Após o Fix

1. Atualize a página do dashboard no navegador (F5)
2. Abra o console do navegador (F12)
3. Verifique se os erros de "Unknown column" desapareceram
4. O dashboard deve carregar normalmente

## Se Ainda Houver Problemas

- Limpe o cache do navegador
- Verifique se as migrations foram executadas corretamente
- Verifique os logs de erro do PHP/MySQL
