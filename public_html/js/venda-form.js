/**
 * Formulário de Venda
 * Gerencia criação e edição de vendas com itens, pagamentos, endereços
 */

const VendaFormManager = {
    // Estado
    state: {
        vendaId: null,
        itens: [],
        pagamentos: [],
        permissoes: {
            criar: false,
            editar: false
        }
    },

    // Elementos DOM
    elements: {
        formContainer: document.getElementById('formContainer'),
        loadingContainer: document.getElementById('loadingContainer'),
        permissionDenied: document.getElementById('permissionDenied'),
        errorContainer: document.getElementById('errorContainer'),
        errorMessage: document.getElementById('errorMessage'),
        formVenda: document.getElementById('formVenda'),
        pageTitle: document.getElementById('pageTitle'),
        vendaId: document.getElementById('vendaId'),
        btnAddItem: document.getElementById('btnAddItem'),
        btnAddPagamento: document.getElementById('btnAddPagamento'),
        listaItens: document.getElementById('listaItens'),
        listaPagamentos: document.getElementById('listaPagamentos'),
        totalItens: document.getElementById('totalItens')
    },

    /**
     * Inicializa o formulário
     */
    async init() {
        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            window.location.href = './auth.html';
            return;
        }

        // Configura event listeners
        this.setupEventListeners();
        this.setupTabs();

        // Verifica permissões
        await this.verificarPermissoes();

        // Obtém ID da URL
        const params = new URLSearchParams(window.location.search);
        this.state.vendaId = params.get('id');

        // Verifica permissão específica
        if (this.state.vendaId && !this.state.permissoes.editar) {
            this.showPermissionDenied();
            return;
        }

        if (!this.state.vendaId && !this.state.permissoes.criar) {
            this.showPermissionDenied();
            return;
        }

        // Carrega dados auxiliares
        await this.carregarDadosAuxiliares();

        // Se está editando, carrega a venda
        if (this.state.vendaId) {
            this.elements.pageTitle.textContent = 'Editar Venda';
            await this.carregarVenda();
        } else {
            // Define data atual
            document.getElementById('dataVenda').valueAsDate = new Date();
            this.showForm();
        }
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.formVenda.addEventListener('submit', (e) => this.salvar(e));
        this.elements.btnAddItem.addEventListener('click', () => this.adicionarItem());
        this.elements.btnAddPagamento.addEventListener('click', () => this.adicionarPagamento());
    },

    /**
     * Configura sistema de tabs
     */
    setupTabs() {
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', function () {
                const tabName = this.getAttribute('data-tab');

                document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

                this.classList.add('active');
                document.getElementById('tab' + tabName.charAt(0).toUpperCase() + tabName.slice(1)).classList.add('active');
            });
        });
    },

    /**
     * Verifica permissões
     */
    async verificarPermissoes() {
        try {
            const permissoes = await aguardarPermissoes();

            if (permissoes) {
                this.state.permissoes = {
                    criar: permissoes.includes('venda.criar'),
                    editar: permissoes.includes('venda.editar')
                };
            }
        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
        }
    },

    /**
     * Carrega dados auxiliares (clientes, colaboradores, etc.)
     */
    async carregarDadosAuxiliares() {
        try {
            // Carrega situações de vendas
            const situacoesResp = await API.get('/situacoes-vendas?ativo=1&limite=100');
            if (situacoesResp.sucesso) {
                const situacoes = situacoesResp.dados?.itens || situacoesResp.dados || [];
                this.popularSelect('situacaoVendaId', situacoes, 'id', 'nome');
            }

            // Carrega clientes
            const clientesResp = await API.get('/cliente?ativo=1&limite=1000');
            if (clientesResp.sucesso) {
                const clientes = clientesResp.dados?.itens || clientesResp.dados || [];
                this.popularSelect('clienteId', clientes, 'id', 'nome');
            }

            // Carrega colaboradores (vendedores/técnicos)
            const colaboradoresResp = await API.get('/colaboradores?ativo=1&limite=1000');
            if (colaboradoresResp.sucesso) {
                const colaboradores = colaboradoresResp.dados?.itens || colaboradoresResp.dados || [];
                this.popularSelect('vendedorId', colaboradores, 'id', 'nome');
                this.popularSelect('tecnicoId', colaboradores, 'id', 'nome');
            }

            // Carrega lojas
            const lojasResp = await API.get('/lojas?ativo=1&limite=100');
            if (lojasResp.sucesso) {
                const lojas = lojasResp.dados?.itens || lojasResp.dados || [];
                this.popularSelect('lojaId', lojas, 'id', 'nome');
            }

        } catch (error) {
            console.error('Erro ao carregar dados auxiliares:', error);
        }
    },

    /**
     * Popula um select com opções
     */
    popularSelect(elementId, dados, valorField, textoField) {
        const select = document.getElementById(elementId);
        if (!select) return;

        const optionPadrao = select.querySelector('option[value=""]');
        select.innerHTML = '';

        if (optionPadrao) {
            select.appendChild(optionPadrao);
        }

        dados.forEach(item => {
            const option = document.createElement('option');
            option.value = item[valorField];
            option.textContent = item[textoField];
            select.appendChild(option);
        });
    },

    /**
     * Carrega dados de uma venda existente
     */
    async carregarVenda() {
        try {
            const response = await API.get(`/venda/${this.state.vendaId}`);

            if (response.sucesso && response.dados) {
                const venda = response.dados;

                // Preenche campos gerais
                document.getElementById('vendaId').value = venda.id;
                document.getElementById('codigo').value = venda.codigo || '';
                document.getElementById('dataVenda').value = venda.data_venda || '';
                document.getElementById('situacaoVendaId').value = venda.situacao_venda_id || '';
                document.getElementById('clienteId').value = venda.cliente_id || '';
                document.getElementById('vendedorId').value = venda.vendedor_id || '';
                document.getElementById('tecnicoId').value = venda.tecnico_id || '';
                document.getElementById('lojaId').value = venda.loja_id || '';
                document.getElementById('canalVenda').value = venda.canal_venda || '';
                document.getElementById('prazoEntrega').value = venda.prazo_entrega || '';
                document.getElementById('valorFrete').value = venda.valor_frete || '0.00';
                document.getElementById('descontoValor').value = venda.desconto_valor || '0.00';
                document.getElementById('ativo').value = venda.ativo || '1';

                // Observações
                document.getElementById('introducao').value = venda.introducao || '';
                document.getElementById('observacoes').value = venda.observacoes || '';
                document.getElementById('observacoesInterna').value = venda.observacoes_interna || '';
                document.getElementById('aosCuidadosDe').value = venda.aos_cuidados_de || '';

                // Carrega itens
                this.state.itens = venda.itens || [];
                this.renderizarItens();

                // Carrega pagamentos
                this.state.pagamentos = venda.pagamentos || [];
                this.renderizarPagamentos();

                // Carrega endereço
                if (venda.enderecos && venda.enderecos.length > 0) {
                    const endereco = venda.enderecos[0];
                    document.getElementById('enderecoCep').value = endereco.cep || '';
                    document.getElementById('enderecoLogradouro').value = endereco.logradouro || '';
                    document.getElementById('enderecoNumero').value = endereco.numero || '';
                    document.getElementById('enderecoComplemento').value = endereco.complemento || '';
                    document.getElementById('enderecoBairro').value = endereco.bairro || '';
                    document.getElementById('enderecoCidadeId').value = endereco.cidade_id || '';
                }

                this.showForm();
            }
        } catch (error) {
            this.showError('Erro ao carregar venda: ' + (error.message || 'Erro desconhecido'));
        }
    },

    /**
     * Adiciona item à lista
     */
    adicionarItem() {
        const item = {
            id: Date.now(),
            tipo: 'produto',
            produto_id: '',
            nome_produto: '',
            quantidade: 1,
            valor_venda: 0,
            desconto_valor: 0,
            valor_total: 0
        };

        this.state.itens.push(item);
        this.renderizarItens();
    },

    /**
     * Remove item da lista
     */
    removerItem(itemId) {
        this.state.itens = this.state.itens.filter(item => item.id !== itemId);
        this.renderizarItens();
    },

    /**
     * Renderiza lista de itens
     */
    renderizarItens() {
        const container = this.elements.listaItens;
        container.innerHTML = '';

        if (this.state.itens.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">Nenhum item adicionado</p>';
            this.atualizarTotalItens();
            return;
        }

        this.state.itens.forEach((item, index) => {
            const div = document.createElement('div');
            div.className = 'dynamic-list-item';
            div.innerHTML = `
                <div class="dynamic-list-item-header">
                    <strong>Item ${index + 1}</strong>
                    <button type="button" class="btn-remove-item" data-id="${item.id}">
                        <i class="fas fa-trash"></i> Remover
                    </button>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select data-field="tipo" data-id="${item.id}">
                            <option value="produto" ${item.tipo === 'produto' ? 'selected' : ''}>Produto</option>
                            <option value="servico" ${item.tipo === 'servico' ? 'selected' : ''}>Serviço</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Descrição</label>
                        <input type="text" data-field="nome_produto" data-id="${item.id}" value="${item.nome_produto || ''}" placeholder="Descrição do item">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade</label>
                        <input type="number" step="0.01" data-field="quantidade" data-id="${item.id}" value="${item.quantidade || 1}">
                    </div>
                    <div class="form-group">
                        <label>Valor Unitário</label>
                        <input type="number" step="0.01" data-field="valor_venda" data-id="${item.id}" value="${item.valor_venda || 0}">
                    </div>
                    <div class="form-group">
                        <label>Desconto</label>
                        <input type="number" step="0.01" data-field="desconto_valor" data-id="${item.id}" value="${item.desconto_valor || 0}">
                    </div>
                    <div class="form-group">
                        <label>Total</label>
                        <input type="text" value="${this.formatarMoeda(item.valor_total || 0)}" disabled>
                    </div>
                </div>
            `;

            // Event listeners
            div.querySelector('.btn-remove-item').addEventListener('click', () => this.removerItem(item.id));

            div.querySelectorAll('input[data-field], select[data-field]').forEach(input => {
                input.addEventListener('change', (e) => this.atualizarItem(item.id, e.target.dataset.field, e.target.value));
            });

            container.appendChild(div);
        });

        this.atualizarTotalItens();
    },

    /**
     * Atualiza campo de um item
     */
    atualizarItem(itemId, field, value) {
        const item = this.state.itens.find(i => i.id === itemId);
        if (!item) return;

        item[field] = value;

        // Recalcula total do item
        const quantidade = parseFloat(item.quantidade) || 0;
        const valorVenda = parseFloat(item.valor_venda) || 0;
        const descontoValor = parseFloat(item.desconto_valor) || 0;

        item.valor_total = (quantidade * valorVenda) - descontoValor;

        this.renderizarItens();
    },

    /**
     * Atualiza total dos itens
     */
    atualizarTotalItens() {
        const total = this.state.itens.reduce((sum, item) => sum + (parseFloat(item.valor_total) || 0), 0);
        this.elements.totalItens.textContent = this.formatarMoeda(total);
    },

    /**
     * Adiciona pagamento à lista
     */
    adicionarPagamento() {
        const pagamento = {
            id: Date.now(),
            parcela: this.state.pagamentos.length + 1,
            data_vencimento: '',
            valor: 0,
            forma_pagamento_id: '',
            pago: 0
        };

        this.state.pagamentos.push(pagamento);
        this.renderizarPagamentos();
    },

    /**
     * Remove pagamento da lista
     */
    removerPagamento(pagamentoId) {
        this.state.pagamentos = this.state.pagamentos.filter(pag => pag.id !== pagamentoId);
        // Reordena parcelas
        this.state.pagamentos.forEach((pag, index) => {
            pag.parcela = index + 1;
        });
        this.renderizarPagamentos();
    },

    /**
     * Renderiza lista de pagamentos
     */
    renderizarPagamentos() {
        const container = this.elements.listaPagamentos;
        container.innerHTML = '';

        if (this.state.pagamentos.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: var(--text-secondary);">Nenhum pagamento adicionado</p>';
            return;
        }

        this.state.pagamentos.forEach((pagamento, index) => {
            const div = document.createElement('div');
            div.className = 'dynamic-list-item';
            div.innerHTML = `
                <div class="dynamic-list-item-header">
                    <strong>Parcela ${pagamento.parcela}</strong>
                    <button type="button" class="btn-remove-item" data-id="${pagamento.id}">
                        <i class="fas fa-trash"></i> Remover
                    </button>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data Vencimento</label>
                        <input type="date" data-field="data_vencimento" data-id="${pagamento.id}" value="${pagamento.data_vencimento || ''}">
                    </div>
                    <div class="form-group">
                        <label>Valor</label>
                        <input type="number" step="0.01" data-field="valor" data-id="${pagamento.id}" value="${pagamento.valor || 0}">
                    </div>
                    <div class="form-group">
                        <label>Pago</label>
                        <select data-field="pago" data-id="${pagamento.id}">
                            <option value="0" ${pagamento.pago == 0 ? 'selected' : ''}>Não</option>
                            <option value="1" ${pagamento.pago == 1 ? 'selected' : ''}>Sim</option>
                        </select>
                    </div>
                </div>
            `;

            // Event listeners
            div.querySelector('.btn-remove-item').addEventListener('click', () => this.removerPagamento(pagamento.id));

            div.querySelectorAll('input[data-field], select[data-field]').forEach(input => {
                input.addEventListener('change', (e) => this.atualizarPagamento(pagamento.id, e.target.dataset.field, e.target.value));
            });

            container.appendChild(div);
        });
    },

    /**
     * Atualiza campo de um pagamento
     */
    atualizarPagamento(pagamentoId, field, value) {
        const pagamento = this.state.pagamentos.find(p => p.id === pagamentoId);
        if (!pagamento) return;

        pagamento[field] = value;
    },

    /**
     * Salva a venda
     */
    async salvar(e) {
        e.preventDefault();

        try {
            // Monta dados da venda
            const dados = {
                codigo: document.getElementById('codigo').value,
                data_venda: document.getElementById('dataVenda').value,
                situacao_venda_id: document.getElementById('situacaoVendaId').value || null,
                cliente_id: document.getElementById('clienteId').value || null,
                vendedor_id: document.getElementById('vendedorId').value || null,
                tecnico_id: document.getElementById('tecnicoId').value || null,
                loja_id: document.getElementById('lojaId').value || null,
                canal_venda: document.getElementById('canalVenda').value || null,
                prazo_entrega: document.getElementById('prazoEntrega').value || null,
                valor_frete: parseFloat(document.getElementById('valorFrete').value) || 0,
                desconto_valor: parseFloat(document.getElementById('descontoValor').value) || 0,
                ativo: parseInt(document.getElementById('ativo').value),
                introducao: document.getElementById('introducao').value || null,
                observacoes: document.getElementById('observacoes').value || null,
                observacoes_interna: document.getElementById('observacoesInterna').value || null,
                aos_cuidados_de: document.getElementById('aosCuidadosDe').value || null,
                itens: this.state.itens.map(item => ({
                    tipo: item.tipo,
                    produto_id: item.produto_id || null,
                    servico_id: item.servico_id || null,
                    nome_produto: item.nome_produto,
                    quantidade: parseFloat(item.quantidade) || 0,
                    valor_venda: parseFloat(item.valor_venda) || 0,
                    desconto_valor: parseFloat(item.desconto_valor) || 0,
                    valor_total: parseFloat(item.valor_total) || 0
                })),
                pagamentos: this.state.pagamentos.map(pag => ({
                    parcela: pag.parcela,
                    data_vencimento: pag.data_vencimento,
                    valor: parseFloat(pag.valor) || 0,
                    pago: parseInt(pag.pago) || 0,
                    forma_pagamento_id: pag.forma_pagamento_id || null
                })),
                enderecos: []
            };

            // Adiciona endereço se preenchido
            const cep = document.getElementById('enderecoCep').value;
            if (cep) {
                dados.enderecos.push({
                    cep: cep,
                    logradouro: document.getElementById('enderecoLogradouro').value || null,
                    numero: document.getElementById('enderecoNumero').value || null,
                    complemento: document.getElementById('enderecoComplemento').value || null,
                    bairro: document.getElementById('enderecoBairro').value || null,
                    cidade_id: document.getElementById('enderecoCidadeId').value || null
                });
            }

            let response;
            if (this.state.vendaId) {
                // Atualiza
                response = await API.put(`/venda/${this.state.vendaId}`, dados);
            } else {
                // Cria
                response = await API.post('/venda', dados);
            }

            if (response.sucesso) {
                Utils.Notificacao.sucesso(this.state.vendaId ? 'Venda atualizada com sucesso!' : 'Venda criada com sucesso!');
                setTimeout(() => {
                    window.location.href = 'vendas.html';
                }, 1500);
            }

        } catch (error) {
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao salvar venda';

            this.showError(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao salvar venda:', error);
        }
    },

    /**
     * Helpers de formatação
     */
    formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor || 0);
    },

    /**
     * UI helpers
     */
    showForm() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.permissionDenied.style.display = 'none';
        this.elements.formContainer.style.display = 'block';
    },

    showPermissionDenied() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.formContainer.style.display = 'none';
        this.elements.permissionDenied.style.display = 'block';
    },

    showError(mensagem) {
        this.elements.errorMessage.textContent = mensagem;
        this.elements.errorContainer.style.display = 'block';
        setTimeout(() => {
            this.elements.errorContainer.style.display = 'none';
        }, 5000);
    }
};

// Inicializa
document.addEventListener('DOMContentLoaded', () => {
    VendaFormManager.init();
});
