<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recalcular Totais de Vendas</title>
    <script src="js/API.js"></script>
    <script src="js/Auth.js"></script>
    <script src="js/Utils.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
        }
        .log {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 20px 0;
            max-height: 400px;
            overflow-y: auto;
            font-family: monospace;
            font-size: 12px;
        }
        .log-item {
            margin: 5px 0;
        }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        button {
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
        }
        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <h1>Recalcular Totais de Vendas</h1>
    <p>Este script irá recalcular o valor_total de todas as vendas baseado nos itens cadastrados.</p>

    <button id="btnRecalcular" onclick="recalcularTodas()">Iniciar Recálculo</button>

    <div id="log" class="log"></div>

    <script>
        let processando = false;

        function log(mensagem, tipo = 'info') {
            const logDiv = document.getElementById('log');
            const item = document.createElement('div');
            item.className = `log-item ${tipo}`;
            item.textContent = `[${new Date().toLocaleTimeString()}] ${mensagem}`;
            logDiv.appendChild(item);
            logDiv.scrollTop = logDiv.scrollHeight;
        }

        async function recalcularTodas() {
            if (processando) return;

            if (!AuthAPI.isAuthenticated()) {
                alert('Você precisa estar autenticado');
                window.location.href = './auth.html';
                return;
            }

            if (!confirm('Tem certeza que deseja recalcular TODAS as vendas? Isso pode demorar um pouco.')) {
                return;
            }

            processando = true;
            document.getElementById('btnRecalcular').disabled = true;
            document.getElementById('log').innerHTML = '';

            try {
                log('Buscando todas as vendas...', 'info');

                // Busca todas as vendas (sem paginação)
                const response = await API.get('/venda?por_pagina=1000');

                if (!response.sucesso || !response.dados || !response.dados.itens) {
                    throw new Error('Erro ao buscar vendas');
                }

                const vendas = response.dados.itens;
                log(`Encontradas ${vendas.length} vendas`, 'info');
                log('Iniciando recálculo...', 'info');
                log('', 'info');

                let atualizadas = 0;
                let erros = 0;

                for (const venda of vendas) {
                    try {
                        // Busca venda completa com itens
                        const vendaCompleta = await API.get(`/venda/${venda.id}`);

                        if (!vendaCompleta.sucesso) {
                            throw new Error('Erro ao buscar venda completa');
                        }

                        const dados = vendaCompleta.dados;

                        // Calcula valor total dos itens
                        const valorItens = (dados.itens || []).reduce((total, item) => {
                            return total + (parseFloat(item.valor_total) || 0);
                        }, 0);

                        const valorFrete = parseFloat(dados.valor_frete) || 0;
                        const descontoValor = parseFloat(dados.desconto_valor) || 0;
                        const valorTotal = valorItens + valorFrete - descontoValor;

                        // Atualiza apenas se o valor estiver diferente
                        const valorAtual = parseFloat(dados.valor_total) || 0;

                        if (Math.abs(valorAtual - valorTotal) > 0.01) {
                            // Prepara dados para atualização
                            const dadosAtualizacao = {
                                codigo: dados.codigo,
                                data_venda: dados.data_venda,
                                cliente_id: dados.cliente_id,
                                vendedor_id: dados.vendedor_id,
                                situacao_venda_id: dados.situacao_venda_id,
                                valor_frete: valorFrete,
                                desconto_valor: descontoValor,
                                valor_total: valorTotal,
                                itens: dados.itens || [],
                                pagamentos: dados.pagamentos || []
                            };

                            // Atualiza a venda
                            await API.put(`/venda/${venda.id}`, dadosAtualizacao);

                            log(`✓ Venda #${dados.codigo} (ID: ${venda.id}) - R$ ${valorAtual.toFixed(2)} → R$ ${valorTotal.toFixed(2)}`, 'success');
                            atualizadas++;
                        } else {
                            log(`- Venda #${dados.codigo} (ID: ${venda.id}) - Já está correto: R$ ${valorTotal.toFixed(2)}`, 'info');
                        }

                    } catch (error) {
                        log(`✗ Erro na venda #${venda.codigo} (ID: ${venda.id}): ${error.message}`, 'error');
                        erros++;
                    }

                    // Pequeno delay para não sobrecarregar o servidor
                    await new Promise(resolve => setTimeout(resolve, 100));
                }

                log('', 'info');
                log('========================================', 'info');
                log(`Total de vendas: ${vendas.length}`, 'info');
                log(`Atualizadas: ${atualizadas}`, atualizadas > 0 ? 'success' : 'info');
                log(`Já corretas: ${vendas.length - atualizadas - erros}`, 'info');
                log(`Erros: ${erros}`, erros > 0 ? 'error' : 'info');
                log('========================================', 'info');

                if (erros === 0) {
                    log('✓ Recálculo concluído com sucesso!', 'success');
                } else {
                    log('⚠ Recálculo concluído com erros', 'error');
                }

            } catch (error) {
                log(`ERRO FATAL: ${error.message}`, 'error');
                console.error('Erro:', error);
            } finally {
                processando = false;
                document.getElementById('btnRecalcular').disabled = false;
            }
        }
    </script>
</body>
</html>
