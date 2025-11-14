/**
 * vendas-detalhes.js
 * Gerencia a visualização detalhada de uma venda
 */

const API_URL = '/api';
let vendaAtual = null;

// Obtém ID da venda da URL
function obterVendaId() {
    const params = new URLSearchParams(window.location.search);
    return params.get('id');
}

// Carrega dados da venda
async function carregarVenda() {
    const vendaId = obterVendaId();

    if (!vendaId) {
        mostrarErro('ID da venda não fornecido');
        return;
    }

    try {
        mostrarLoading(true);

        const response = await fetch(`${API_URL}/venda/${vendaId}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('token')}`
            }
        });

        if (!response.ok) {
            throw new Error('Erro ao carregar venda');
        }

        const data = await response.json();

        if (data.sucesso) {
            vendaAtual = data.dados;
            renderizarVenda(vendaAtual);
        } else {
            throw new Error(data.mensagem || 'Erro ao carregar venda');
        }

    } catch (error) {
        console.error('Erro:', error);
        mostrarErro(error.message);
    } finally {
        mostrarLoading(false);
    }
}

// Renderiza todos os dados da venda
function renderizarVenda(venda) {
    // Atualiza título
    document.getElementById('page-title').textContent = `Venda #${venda.codigo}`;
    document.getElementById('page-subtitle').textContent = `Hash: ${venda.hash || '-'}`;

    // Renderiza cards de resumo
    renderizarResumo(venda);

    // Renderiza informações gerais
    renderizarInformacoesGerais(venda);

    // Renderiza itens
    renderizarItens(venda.itens || []);

    // Renderiza pagamentos
    renderizarPagamentos(venda.pagamentos || []);

    // Renderiza endereço
    renderizarEndereco(venda.enderecos && venda.enderecos.length > 0 ? venda.enderecos[0] : null);

    // Renderiza observações
    renderizarObservacoes(venda);

    // Renderiza status
    renderizarStatus(venda);

    // Renderiza valores
    renderizarValores(venda);

    // Renderiza atributos
    renderizarAtributos(venda.atributos || []);

    // Configura botão editar
    document.getElementById('btn-editar').onclick = () => {
        window.location.href = `venda-form.html?id=${venda.id}`;
    };

    // Mostra conteúdo
    document.getElementById('content').style.display = 'block';
}

// Renderiza cards de resumo
function renderizarResumo(venda) {
    document.getElementById('valor-total').textContent = formatarMoeda(venda.valor_total);
    document.getElementById('valor-produtos').textContent = formatarMoeda(venda.valor_produtos || 0);
    document.getElementById('valor-servicos').textContent = formatarMoeda(venda.valor_servicos || 0);
    document.getElementById('situacao-venda').textContent = venda.nome_situacao || '-';
}

// Renderiza informações gerais
function renderizarInformacoesGerais(venda) {
    const container = document.getElementById('info-geral');
    container.innerHTML = '';

    const infos = [
        { label: 'Código', value: venda.codigo },
        { label: 'Data da Venda', value: formatarData(venda.data_venda) },
        { label: 'Cliente', value: venda.nome_cliente || '-' },
        { label: 'Vendedor', value: venda.nome_vendedor || '-' },
        { label: 'Técnico', value: venda.nome_tecnico || '-' },
        { label: 'Loja', value: venda.nome_loja || '-' },
        { label: 'Canal de Venda', value: venda.canal_venda || '-' },
        { label: 'Prazo de Entrega', value: venda.prazo_entrega ? formatarData(venda.prazo_entrega) : '-' },
        { label: 'Condição Pagamento', value: venda.condicao_pagamento || '-' },
        { label: 'Nº Parcelas', value: venda.numero_parcelas || '-' },
        { label: 'Transportadora', value: venda.nome_transportadora || '-' },
        { label: 'Centro de Custo', value: venda.nome_centro_custo || '-' }
    ];

    infos.forEach(info => {
        container.innerHTML += `
            <div class="info-item">
                <div class="info-label">${info.label}</div>
                <div class="info-value">${info.value}</div>
            </div>
        `;
    });
}

// Renderiza itens da venda
function renderizarItens(itens) {
    const tbody = document.getElementById('itens-tbody');
    tbody.innerHTML = '';

    if (!itens || itens.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 20px; color: var(--text-secondary);">Nenhum item encontrado</td></tr>';
        return;
    }

    itens.forEach(item => {
        const tipo = item.tipo || 'produto';
        const desconto = item.tipo_desconto === '%'
            ? `${item.desconto_porcentagem || 0}%`
            : formatarMoeda(item.desconto_valor || 0);

        tbody.innerHTML += `
            <tr>
                <td>
                    <span class="badge ${tipo === 'produto' ? 'badge-info' : 'badge-success'}">
                        ${tipo === 'produto' ? 'Produto' : 'Serviço'}
                    </span>
                </td>
                <td>${item.nome_produto || '-'}</td>
                <td>${item.detalhes || '-'}</td>
                <td>${item.quantidade} ${item.sigla_unidade || ''}</td>
                <td>${formatarMoeda(item.valor_venda)}</td>
                <td>${desconto}</td>
                <td><strong>${formatarMoeda(item.valor_total)}</strong></td>
            </tr>
        `;
    });
}

// Renderiza pagamentos
function renderizarPagamentos(pagamentos) {
    const tbody = document.getElementById('pagamentos-tbody');
    tbody.innerHTML = '';

    if (!pagamentos || pagamentos.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; padding: 20px; color: var(--text-secondary);">Nenhum pagamento encontrado</td></tr>';
        return;
    }

    pagamentos.forEach(pag => {
        const pago = pag.pago == 1;
        const badgeClass = pago ? 'badge-success' : 'badge-warning';
        const statusText = pago ? 'Pago' : 'Pendente';

        tbody.innerHTML += `
            <tr>
                <td>${formatarData(pag.data_vencimento)}</td>
                <td>${pag.nome_forma_pagamento || '-'}</td>
                <td><strong>${formatarMoeda(pag.valor)}</strong></td>
                <td><span class="badge ${badgeClass}">${statusText}</span></td>
            </tr>
        `;
    });
}

// Renderiza endereço
function renderizarEndereco(endereco) {
    const section = document.getElementById('endereco-section');
    const container = document.getElementById('endereco-info');

    if (!endereco) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    container.innerHTML = `
        <div class="info-grid">
            <div class="info-item">
                <div class="info-label">CEP</div>
                <div class="info-value">${endereco.cep || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Logradouro</div>
                <div class="info-value">${endereco.logradouro || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Número</div>
                <div class="info-value">${endereco.numero || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Complemento</div>
                <div class="info-value">${endereco.complemento || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Bairro</div>
                <div class="info-value">${endereco.bairro || '-'}</div>
            </div>
            <div class="info-item">
                <div class="info-label">Cidade</div>
                <div class="info-value">${endereco.nome_cidade || '-'} - ${endereco.estado || '-'}</div>
            </div>
        </div>
    `;
}

// Renderiza observações
function renderizarObservacoes(venda) {
    const section = document.getElementById('observacoes-section');
    const container = document.getElementById('observacoes-content');

    const temObservacoes = venda.observacoes || venda.observacoes_interna;

    if (!temObservacoes) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    let html = '';

    if (venda.observacoes) {
        html += `
            <div class="info-item">
                <div class="info-label">Observações para o Cliente</div>
                <div class="info-value" style="white-space: pre-wrap;">${venda.observacoes}</div>
            </div>
        `;
    }

    if (venda.observacoes_interna) {
        html += `
            <div class="info-item" style="margin-top: 12px;">
                <div class="info-label">Observações Internas</div>
                <div class="info-value" style="white-space: pre-wrap;">${venda.observacoes_interna}</div>
            </div>
        `;
    }

    container.innerHTML = html;
}

// Renderiza status
function renderizarStatus(venda) {
    const container = document.getElementById('status-info');

    const situacaoFinanceiro = getSituacaoFinanceiro(venda.situacao_financeiro);
    const situacaoEstoque = getSituacaoEstoque(venda.situacao_estoque);

    container.innerHTML = `
        <div class="info-item">
            <div class="info-label">Situação Venda</div>
            <div class="info-value">${venda.nome_situacao || '-'}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Situação Financeira</div>
            <div class="info-value">
                <span class="badge ${situacaoFinanceiro.class}">${situacaoFinanceiro.text}</span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">Situação Estoque</div>
            <div class="info-value">
                <span class="badge ${situacaoEstoque.class}">${situacaoEstoque.text}</span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label">Status</div>
            <div class="info-value">
                <span class="badge ${venda.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                    ${venda.ativo == 1 ? 'Ativo' : 'Inativo'}
                </span>
            </div>
        </div>
    `;
}

// Renderiza valores
function renderizarValores(venda) {
    const container = document.getElementById('valores-info');

    container.innerHTML = `
        <div class="info-item">
            <div class="info-label">Produtos</div>
            <div class="info-value">${formatarMoeda(venda.valor_produtos || 0)}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Serviços</div>
            <div class="info-value">${formatarMoeda(venda.valor_servicos || 0)}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Frete</div>
            <div class="info-value">${formatarMoeda(venda.valor_frete || 0)}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Desconto</div>
            <div class="info-value">${formatarMoeda(venda.desconto_valor || 0)}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Custo Total</div>
            <div class="info-value">${formatarMoeda(venda.valor_custo || 0)}</div>
        </div>
        <div class="info-item" style="background: #d1fae5; border: 1px solid #10b981;">
            <div class="info-label" style="color: #065f46;">Total da Venda</div>
            <div class="info-value" style="color: #065f46; font-size: 18px;">${formatarMoeda(venda.valor_total)}</div>
        </div>
    `;
}

// Renderiza atributos
function renderizarAtributos(atributos) {
    const section = document.getElementById('atributos-section');
    const container = document.getElementById('atributos-content');

    if (!atributos || atributos.length === 0) {
        section.style.display = 'none';
        return;
    }

    section.style.display = 'block';
    let html = '<div class="info-grid">';

    atributos.forEach(attr => {
        html += `
            <div class="info-item">
                <div class="info-label">${attr.descricao}</div>
                <div class="info-value">${attr.conteudo || '-'}</div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
}

// Helpers
function getSituacaoFinanceiro(situacao) {
    switch (parseInt(situacao)) {
        case 0:
            return { text: 'Pendente', class: 'badge-warning' };
        case 1:
            return { text: 'Pago', class: 'badge-success' };
        case 2:
            return { text: 'Parcial', class: 'badge-info' };
        default:
            return { text: '-', class: 'badge-info' };
    }
}

function getSituacaoEstoque(situacao) {
    switch (parseInt(situacao)) {
        case 0:
            return { text: 'Pendente', class: 'badge-warning' };
        case 1:
            return { text: 'Separado', class: 'badge-info' };
        case 2:
            return { text: 'Expedido', class: 'badge-success' };
        default:
            return { text: '-', class: 'badge-info' };
    }
}

function formatarMoeda(valor) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(valor || 0);
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data + 'T00:00:00');
    return d.toLocaleDateString('pt-BR');
}

function mostrarLoading(show) {
    document.getElementById('loading').style.display = show ? 'block' : 'none';
}

function mostrarErro(mensagem) {
    document.getElementById('error-message').textContent = mensagem;
    document.getElementById('error-container').style.display = 'block';
    document.getElementById('content').style.display = 'none';
}

// Inicialização
document.addEventListener('DOMContentLoaded', () => {
    carregarVenda();
});
