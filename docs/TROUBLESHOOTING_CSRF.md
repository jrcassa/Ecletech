# Troubleshooting - Erro "Token CSRF inválido ou expirado"

## Problema
O sistema funciona normalmente em uma máquina, mas em outra aparece o erro: **"Token CSRF inválido ou expirado"** ao tentar fazer login.

## Causa Raiz
Esse problema ocorre quando há diferenças de configuração entre as máquinas, afetando:
- Sessões PHP
- Cookies
- Banco de dados
- Timezone
- Permissões de sistema de arquivos

---

## Diagnóstico Rápido

### 1. Acessar Endpoint de Diagnóstico

**URL:** `http://seu-dominio/public_html/api/diagnostico/csrf`

Acesse este endpoint em ambas as máquinas e compare os resultados.

#### O que verificar:

**Sessão:**
- `session_status` deve ser `PHP_SESSION_ACTIVE`
- `session_save_path_writable` deve ser `true`
- `session_id` deve ser diferente de vazio

**Banco de Dados:**
- `conectado` deve ser `true`
- `migration_012_executada` deve ser `true`

**Cookies:**
- `session_cookie_exists` deve ser `true` após tentar fazer login

**PHP:**
- `timezone` deve ser o mesmo em ambas máquinas
- Verifique se `current_time` está correto

---

## Soluções Comuns

### Problema 1: Sessão Não Persiste Entre Requisições

**Sintoma:** O `session_id` muda a cada requisição.

**Solução:**

1. Verificar se o diretório de sessão tem permissões corretas:
```bash
ls -la /tmp/ecletech_sessions
```

2. Se não existir ou não tiver permissões, criar:
```bash
sudo mkdir -p /tmp/ecletech_sessions
sudo chmod 700 /tmp/ecletech_sessions
sudo chown www-data:www-data /tmp/ecletech_sessions  # ou o usuário do Apache/Nginx
```

3. Verificar logs do PHP:
```bash
sudo tail -f /var/log/apache2/error.log
# ou
sudo tail -f /var/log/nginx/error.log
```

---

### Problema 2: Migration 012 Não Executada

**Sintoma:** `migration_012_executada` retorna `false` ou `erro`.

**Solução:**

1. Verificar se a coluna `colaborador_id` existe:
```bash
mysql -u root -p ecletech -e "SHOW COLUMNS FROM csrf_tokens LIKE 'colaborador_id';"
```

2. Se não existir, executar a migration manualmente:
```bash
mysql -u root -p ecletech < database/migrations/012_renomear_usuario_id_para_colaborador_id.sql
```

---

### Problema 3: Banco de Dados Não Conecta

**Sintoma:** `conectado` retorna `false`.

**Solução:**

1. Verificar credenciais no `.env`:
```bash
cat .env | grep DB_
```

2. Testar conexão:
```bash
mysql -h localhost -u root -p ecletech -e "SELECT 1;"
```

3. Verificar se o banco de dados existe:
```bash
mysql -u root -p -e "SHOW DATABASES LIKE 'ecletech';"
```

4. Verificar logs de erro:
```bash
sudo tail -f /var/log/apache2/error.log | grep TokenCsrf
```

---

### Problema 4: Cookies Não São Enviados

**Sintoma:** `session_cookie_exists` ou `auth_token_exists` retornam `false`.

**Causas:**
- **Domínio incorreto:** localhost vs 127.0.0.1 vs IP externo
- **Porta diferente:** não compartilha cookies
- **HTTPS vs HTTP:** cookies com flag `secure` não funcionam em HTTP
- **Navegador em modo privado:** pode bloquear cookies

**Soluções:**

1. **Usar o mesmo domínio em ambas máquinas:**
   - Se em uma é `http://localhost`, use em ambas
   - Se em uma é `http://192.168.1.10`, use em ambas

2. **Verificar flag secure:**
   - Em HTTP, o cookie não deve ter `secure=true`
   - Verifique o endpoint de diagnóstico: `php.session.cookie_secure`

3. **Desabilitar modo privado** do navegador

4. **Limpar cookies** e tentar novamente:
   - Chrome: F12 > Application > Cookies > Clear
   - Firefox: F12 > Storage > Cookies > Clear

---

### Problema 5: Timezone Diferente

**Sintoma:** Token expira imediatamente ou muito rápido.

**Solução:**

1. Verificar timezone no endpoint de diagnóstico: `php.timezone`

2. Se diferente, ajustar no `.env`:
```env
APP_TIMEZONE="America/Sao_Paulo"
```

3. Ou no `php.ini`:
```ini
date.timezone = "America/Sao_Paulo"
```

4. Reiniciar servidor:
```bash
sudo service apache2 restart
# ou
sudo service nginx restart
sudo service php8.1-fpm restart
```

---

### Problema 6: localStorage Não Compartilha Tokens

**Sintoma:** Token CSRF não é encontrado no frontend.

**Causa:** Diferentes origens (localhost vs 127.0.0.1) não compartilham localStorage.

**Solução:**

1. **Usar o mesmo domínio** em ambas máquinas

2. **Limpar localStorage:**
   - Abrir DevTools (F12)
   - Console:
   ```javascript
   localStorage.clear()
   location.reload()
   ```

3. **Verificar se o token está sendo salvo:**
   - DevTools (F12) > Application > Local Storage
   - Procurar por `csrf_token`

---

## Verificações Avançadas

### Logs Detalhados

Os logs agora incluem informações detalhadas sobre falhas de validação CSRF:

1. **Ver logs em tempo real:**
```bash
sudo tail -f /var/log/apache2/error.log | grep -i csrf
```

2. **Procurar por erros específicos:**
```bash
grep "MiddlewareCsrf" /var/log/apache2/error.log | tail -20
grep "TokenCsrf" /var/log/apache2/error.log | tail -20
```

### Informações nos Logs

- `Session ID`: Identificador da sessão
- `Token fornecido`: Primeiros e últimos 10 caracteres do token enviado
- `Token da sessão`: Primeiros e últimos 10 caracteres do token armazenado
- `Tempo do token`: Quando o token foi gerado
- `Diferença`: Quantos segundos se passaram desde a geração

---

## Passo a Passo de Diagnóstico

### Na Máquina com Problema:

1. **Acessar o endpoint de diagnóstico:**
   ```
   http://seu-dominio/public_html/api/diagnostico/csrf
   ```

2. **Salvar o resultado** (JSON)

3. **Verificar cada seção:**
   - ✅ Sessão ativa e gravável
   - ✅ Banco de dados conectado
   - ✅ Migration 012 executada
   - ✅ Cookies sendo recebidos
   - ✅ Timezone correto

4. **Tentar fazer login** e verificar logs:
   ```bash
   sudo tail -f /var/log/apache2/error.log
   ```

5. **Anotar erros específicos** que aparecem

6. **Comparar com a máquina funcionando:**
   - Acessar o mesmo endpoint na máquina funcionando
   - Comparar as configurações

---

## Soluções por Sintoma

### "Session ID muda a cada requisição"
→ **Problema de permissões ou cookies**
→ Ver: Problema 1 e Problema 4

### "Banco de dados não conectado"
→ **Credenciais ou migrations**
→ Ver: Problema 2 e Problema 3

### "Token expira muito rápido"
→ **Timezone incorreto**
→ Ver: Problema 5

### "Token não é enviado pelo frontend"
→ **localStorage ou cookies**
→ Ver: Problema 4 e Problema 6

### "Migration 012 não executada"
→ **Banco de dados desatualizado**
→ Ver: Problema 2

---

## Checklist de Configuração

Para garantir que ambas máquinas estejam configuradas corretamente:

- [ ] Mesmo `.env` (ou configurações equivalentes)
- [ ] Mesmo timezone configurado
- [ ] Diretório `/tmp/ecletech_sessions` existe e tem permissões
- [ ] Migration 012 executada
- [ ] Banco de dados acessível
- [ ] Mesmo domínio/IP sendo usado (não misturar localhost e 127.0.0.1)
- [ ] Mesma versão do PHP
- [ ] Logs de erro acessíveis e sendo monitorados

---

## Contato e Suporte

Se após seguir este guia o problema persistir:

1. **Enviar resultado do diagnóstico** de ambas máquinas
2. **Enviar logs** relevantes do Apache/Nginx
3. **Descrever o ambiente:**
   - Sistema operacional
   - Versão do PHP
   - Servidor web (Apache/Nginx)
   - Navegador usado

---

## Remover Rotas de Diagnóstico em Produção

⚠️ **IMPORTANTE:** Antes de colocar em produção, remover as rotas de diagnóstico:

1. Editar `/App/Middleware/MiddlewareCsrf.php` e remover:
   ```php
   '^/diagnostico/.*$'  // Rotas de diagnóstico (remover em produção)
   ```

2. Ou proteger com autenticação admin no arquivo `/App/Routes/diagnostico.php`
