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
        produtos: [],
        servicos: [],
        formasPagamento: [],
        cidades: [],
        permissoes: {
            criar: false,
            editar: false
        },
        tipoPagamento: 'avista', // 'avista' ou 'parcelado'
        tipoEntrega: 'retirar' // 'retirar' ou 'entrega'
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
        totalItens: document.getElementById('totalItens'),
        tipoPagamentoAvista: document.getElementById('tipoPagamentoAvista'),
        tipoPagamentoParcelado: document.getElementById('tipoPagamentoParcelado'),
        tipoEntregaRetirar: document.getElementById('tipoEntregaRetirar'),
        tipoEntregaEntregar: document.getElementById('tipoEntregaEntregar'),
        enderecoContainer: document.getElementById('enderecoContainer')
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

        // Tipo de pagamento
        this.elements.tipoPagamentoAvista?.addEventListener('change', () => this.atualizarTipoPagamento());
        this.elements.tipoPagamentoParcelado?.addEventListener('change', () => this.atualizarTipoPagamento());

        // Tipo de entrega
        this.elements.tipoEntregaRetirar?.addEventListener('change', () => this.atualizarTipoEntrega());
        this.elements.tipoEntregaEntregar?.addEventListener('change', () => this.atualizarTipoEntrega());

        // Autocomplete de cliente
        this.configurarAutocompleteCliente();
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
     * Carrega dados auxiliares (clientes, colaboradores, produtos, serviços, etc.)
     */
    async carregarDadosAuxiliares() {
        try {
            // Carrega situações de vendas
            const situacoesResp = await API.get('/situacoes-vendas?ativo=1&limite=100');
            if (situacoesResp.sucesso) {
                const situacoes = situacoesResp.dados?.itens || situacoesResp.dados || [];
                this.popularSelect('situacaoVendaId', situacoes, 'id', 'nome');
            }

            // Clientes serão carregados via busca (autocomplete)

            // Carrega colaboradores (vendedores/técnicos)
            const colaboradoresResp = await API.get('/colaboradores?ativo=1&limite=1000');
            if (colaboradoresResp.sucesso) {
                const colaboradores = colaboradoresResp.dados?.itens || colaboradoresResp.dados || [];
                this.popularSelect('vendedorId', colaboradores, 'id', 'nome');
                this.popularSelect('tecnicoId', colaboradores, 'id', 'nome');
            }

            // Carrega lojas (singleton - retorna apenas 1 registro)
            const lojasResp = await API.get('/loja');
            if (lojasResp.sucesso && lojasResp.dados) {
                // Loja é singleton, vem apenas 1 objeto, não array
                const loja = lojasResp.dados;
                const lojaSelect = document.getElementById('lojaId');
                if (lojaSelect) {
                    lojaSelect.innerHTML = `
                        <option value="">Selecione...</option>
                        <option value="${loja.id}">${loja.nome_fantasia || loja.nome || 'Loja Principal'}</option>
                    `;
                }
            }

            // Produtos e serviços serão carregados via busca (não pré-carregamos todos)
            this.state.produtos = [];
            this.state.servicos = [];

            // Carrega formas de pagamento
            try {
                const formasPagResp = await API.get('/forma-de-pagamento?ativo=1&limite=100');
                if (formasPagResp.sucesso) {
                    this.state.formasPagamento = formasPagResp.dados?.itens || formasPagResp.dados || [];
                }
            } catch (error) {
                console.error('Erro ao carregar formas de pagamento:', error);
                this.state.formasPagamento = [];
            }

            // Carrega cidades
            const cidadesResp = await API.get('/cidades?limite=1000');
            if (cidadesResp.sucesso) {
                this.state.cidades = cidadesResp.dados?.itens || cidadesResp.dados || [];
                this.popularSelect('enderecoCidadeId', this.state.cidades, 'id', 'nome');
            }

        } catch (error) {
            console.error('Erro ao carregar dados auxiliares:', error);
        }
    },

    /**
     * Busca clientes por termo (para autocomplete)
     */
    async buscarClientes(termo) {
        try {
            if (!termo || termo.length < 2) {
                return [];
            }

            const params = new URLSearchParams({
                busca: termo,
                ativo: 1,
                por_pagina: 20
            });

            const response = await API.get(`/cliente?${params.toString()}`);

            if (response.sucesso && response.dados) {
                const itens = response.dados.itens || response.dados;
                return Array.isArray(itens) ? itens : [];
            }

            return [];
        } catch (erro) {
            console.error('Erro ao buscar clientes:', erro);
            return [];
        }
    },

    /**
     * Busca cliente por ID
     */
    async buscarClientePorId(id) {
        try {
            const response = await API.get(`/cliente/${id}`);
            if (response.sucesso && response.dados) {
                return response.dados;
            }
            return null;
        } catch (erro) {
            console.error('Erro ao buscar cliente por ID:', erro);
            return null;
        }
    },

    /**
     * Configura autocomplete de cliente
     */
    configurarAutocompleteCliente() {
        const inputNome = document.getElementById('clienteNome');
        const inputId = document.getElementById('clienteId');
        const listElement = document.getElementById('clienteAutocompleteList');

        if (!inputNome || !inputId || !listElement) return;

        let timeoutId = null;

        // Evento de digitação
        inputNome.addEventListener('input', async (e) => {
            const termo = e.target.value.trim();

            // Limpa timeout anterior
            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            // Limpa ID quando o texto muda
            if (inputId.value) {
                inputId.value = '';
            }

            if (!termo || termo.length < 2) {
                listElement.style.display = 'none';
                listElement.innerHTML = '';
                return;
            }

            // Debounce
            timeoutId = setTimeout(async () => {
                const clientes = await this.buscarClientes(termo);

                if (clientes.length === 0) {
                    listElement.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Nenhum cliente encontrado</div>';
                    listElement.style.display = 'block';
                    return;
                }

                listElement.innerHTML = '';
                clientes.forEach(cliente => {
                    const item = document.createElement('div');
                    item.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border-light); transition: background 0.2s;';

                    const cpfCnpj = cliente.cpf || cliente.cnpj || 'N/A';
                    item.innerHTML = `
                        <div style="font-weight: 600; color: var(--text-primary);">${cliente.nome}</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">CPF/CNPJ: ${cpfCnpj}</div>
                    `;

                    item.addEventListener('mouseover', () => {
                        item.style.background = 'var(--bg-hover)';
                    });

                    item.addEventListener('mouseout', () => {
                        item.style.background = 'transparent';
                    });

                    item.addEventListener('click', () => {
                        inputNome.value = cliente.nome;
                        inputId.value = cliente.id;
                        listElement.style.display = 'none';
                        listElement.innerHTML = '';
                    });

                    listElement.appendChild(item);
                });

                listElement.style.display = 'block';
            }, 300);
        });

        // Fecha a lista ao clicar fora
        document.addEventListener('click', (e) => {
            if (!inputNome.contains(e.target) && !listElement.contains(e.target)) {
                listElement.style.display = 'none';
            }
        });
    },

    /**
     * Busca produtos por termo (para autocomplete)
     */
    async buscarProdutos(termo) {
        try {
            if (!termo || termo.length < 2) {
                return [];
            }

            const params = new URLSearchParams({
                busca: termo,
                ativo: 1,
                por_pagina: 20
            });

            const response = await API.get(`/produtos?${params.toString()}`);

            if (response.sucesso && response.dados) {
                const itens = response.dados.itens || response.dados;
                return Array.isArray(itens) ? itens : [];
            }

            return [];
        } catch (erro) {
            console.error('Erro ao buscar produtos:', erro);
            return [];
        }
    },

    /**
     * Busca produto por ID
     */
    async buscarProdutoPorId(id) {
        try {
            const response = await API.get(`/produtos/${id}`);
            if (response.sucesso && response.dados) {
                return response.dados;
            }
            return null;
        } catch (erro) {
            console.error('Erro ao buscar produto por ID:', erro);
            return null;
        }
    },

    /**
     * Busca serviços por termo (para autocomplete)
     */
    async buscarServicos(termo) {
        try {
            if (!termo || termo.length < 2) {
                return [];
            }

            const params = new URLSearchParams({
                busca: termo,
                ativo: 1,
                por_pagina: 20
            });

            const response = await API.get(`/servicos?${params.toString()}`);

            if (response.sucesso && response.dados) {
                const itens = response.dados.itens || response.dados;
                return Array.isArray(itens) ? itens : [];
            }

            return [];
        } catch (erro) {
            console.error('Erro ao buscar serviços:', erro);
            return [];
        }
    },

    /**
     * Busca serviço por ID
     */
    async buscarServicoPorId(id) {
        try {
            const response = await API.get(`/servicos/${id}`);
            if (response.sucesso && response.dados) {
                return response.dados;
            }
            return null;
        } catch (erro) {
            console.error('Erro ao buscar serviço por ID:', erro);
            return null;
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

                // Carrega nome do cliente
                if (venda.cliente_id) {
                    document.getElementById('clienteId').value = venda.cliente_id;
                    const cliente = await this.buscarClientePorId(venda.cliente_id);
                    if (cliente) {
                        document.getElementById('clienteNome').value = cliente.nome || '';
                    }
                }

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
                    this.state.tipoEntrega = 'entrega';
                    this.elements.tipoEntregaEntregar.checked = true;
                    document.getElementById('enderecoCep').value = endereco.cep || '';
                    document.getElementById('enderecoLogradouro').value = endereco.logradouro || '';
                    document.getElementById('enderecoNumero').value = endereco.numero || '';
                    document.getElementById('enderecoComplemento').value = endereco.complemento || '';
                    document.getElementById('enderecoBairro').value = endereco.bairro || '';
                    document.getElementById('enderecoCidadeId').value = endereco.cidade_id || '';
                    this.atualizarTipoEntrega();
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
            produto_id: null,
            servico_id: null,
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
     * Renderiza lista de itens COM AUTOCOMPLETE
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
                        <label>Tipo *</label>
                        <select data-field="tipo" data-id="${item.id}" required>
                            <option value="produto" ${item.tipo === 'produto' ? 'selected' : ''}>Produto</option>
                            <option value="servico" ${item.tipo === 'servico' ? 'selected' : ''}>Serviço</option>
                        </select>
                    </div>
                    <div class="form-group item-autocomplete-produto" style="display: ${item.tipo === 'produto' ? 'block' : 'none'}; position: relative;">
                        <label>Produto *</label>
                        <input type="text"
                               class="produto-nome"
                               placeholder="Digite para buscar..."
                               autocomplete="off"
                               value="${item.nome_produto && item.tipo === 'produto' ? item.nome_produto : ''}">
                        <input type="hidden" class="produto-id" value="${item.produto_id || ''}">
                        <div class="autocomplete-list" style="display: none; position: absolute; z-index: 1000; background: var(--card-bg); border: 1px solid var(--border-color); max-height: 200px; overflow-y: auto; width: 100%; border-radius: 6px; box-shadow: 0 4px 12px var(--shadow-color);"></div>
                    </div>
                    <div class="form-group item-autocomplete-servico" style="display: ${item.tipo === 'servico' ? 'block' : 'none'}; position: relative;">
                        <label>Serviço *</label>
                        <input type="text"
                               class="servico-nome"
                               placeholder="Digite para buscar..."
                               autocomplete="off"
                               value="${item.nome_produto && item.tipo === 'servico' ? item.nome_produto : ''}">
                        <input type="hidden" class="servico-id" value="${item.servico_id || ''}">
                        <div class="autocomplete-list" style="display: none; position: absolute; z-index: 1000; background: var(--card-bg); border: 1px solid var(--border-color); max-height: 200px; overflow-y: auto; width: 100%; border-radius: 6px; box-shadow: 0 4px 12px var(--shadow-color);"></div>
                    </div>
                    <div class="form-group">
                        <label>Nome/Descrição</label>
                        <input type="text" data-field="nome_produto" data-id="${item.id}" value="${item.nome_produto || ''}" placeholder="Preenchido automaticamente" readonly style="background: var(--bg-tertiary);">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Quantidade *</label>
                        <input type="number" step="0.01" min="0.01" data-field="quantidade" data-id="${item.id}" value="${item.quantidade || 1}" required>
                    </div>
                    <div class="form-group">
                        <label>Valor Unitário *</label>
                        <input type="number" step="0.01" min="0" data-field="valor_venda" data-id="${item.id}" value="${item.valor_venda || 0}">
                    </div>
                    <div class="form-group">
                        <label>Desconto (R$)</label>
                        <input type="number" step="0.01" min="0" data-field="desconto_valor" data-id="${item.id}" value="${item.desconto_valor || 0}">
                    </div>
                    <div class="form-group">
                        <label>Total</label>
                        <input type="text" value="${this.formatarMoeda(item.valor_total || 0)}" disabled style="background: var(--bg-tertiary); font-weight: 600;">
                    </div>
                </div>
            `;

            // Event listeners
            div.querySelector('.btn-remove-item').addEventListener('click', () => this.removerItem(item.id));

            // Listener para mudança de tipo
            const tipoSelect = div.querySelector('select[data-field="tipo"]');
            tipoSelect.addEventListener('change', (e) => {
                const novoTipo = e.target.value;
                item.tipo = novoTipo;

                // Limpa dados do tipo anterior
                if (novoTipo === 'produto') {
                    item.servico_id = null;
                } else {
                    item.produto_id = null;
                }
                item.nome_produto = '';
                item.valor_venda = 0;

                this.renderizarItens();
            });

            // Configura autocomplete de produto
            const inputProdutoNome = div.querySelector('.produto-nome');
            const inputProdutoId = div.querySelector('.produto-id');
            const autocompleteProdutoList = div.querySelector('.item-autocomplete-produto .autocomplete-list');

            if (inputProdutoNome && inputProdutoId && autocompleteProdutoList) {
                this.configurarAutocompleteProduto(inputProdutoNome, inputProdutoId, autocompleteProdutoList, item.id);
            }

            // Configura autocomplete de serviço
            const inputServicoNome = div.querySelector('.servico-nome');
            const inputServicoId = div.querySelector('.servico-id');
            const autocompleteServicoList = div.querySelector('.item-autocomplete-servico .autocomplete-list');

            if (inputServicoNome && inputServicoId && autocompleteServicoList) {
                this.configurarAutocompleteServico(inputServicoNome, inputServicoId, autocompleteServicoList, item.id);
            }

            // Listeners para outros campos
            div.querySelectorAll('input[data-field]').forEach(input => {
                input.addEventListener('input', (e) => {
                    this.atualizarItem(item.id, e.target.dataset.field, e.target.value);
                });
            });

            container.appendChild(div);
        });

        this.atualizarTotalItens();
    },

    /**
     * Configura autocomplete de produto
     */
    configurarAutocompleteProduto(inputNome, inputId, listElement, itemId) {
        let timeoutId = null;

        // Evento de digitação
        inputNome.addEventListener('input', async (e) => {
            const termo = e.target.value.trim();

            // Limpa timeout anterior
            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            if (!termo || termo.length < 2) {
                listElement.style.display = 'none';
                listElement.innerHTML = '';
                return;
            }

            // Debounce
            timeoutId = setTimeout(async () => {
                const produtos = await this.buscarProdutos(termo);

                if (produtos.length === 0) {
                    listElement.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Nenhum produto encontrado</div>';
                    listElement.style.display = 'block';
                    return;
                }

                listElement.innerHTML = '';
                produtos.forEach(produto => {
                    const item = document.createElement('div');
                    item.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border-light); transition: background 0.2s;';
                    item.innerHTML = `
                        <div style="font-weight: 600; color: var(--text-primary);">${produto.nome}</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Cód: ${produto.codigo || 'N/A'} - ${this.formatarMoeda(produto.valor_venda || 0)}</div>
                    `;

                    item.addEventListener('mouseover', () => {
                        item.style.background = 'var(--bg-hover)';
                    });

                    item.addEventListener('mouseout', () => {
                        item.style.background = 'transparent';
                    });

                    item.addEventListener('click', () => {
                        inputNome.value = produto.nome;
                        inputId.value = produto.id;

                        // Atualiza o item
                        const itemObj = this.state.itens.find(i => i.id === itemId);
                        if (itemObj) {
                            itemObj.produto_id = produto.id;
                            itemObj.nome_produto = produto.nome;
                            itemObj.valor_venda = produto.valor_venda || 0;
                            this.atualizarItem(itemId, 'recalcular', true);
                        }

                        listElement.style.display = 'none';
                        listElement.innerHTML = '';
                    });

                    listElement.appendChild(item);
                });

                listElement.style.display = 'block';
            }, 300);
        });

        // Fecha a lista ao clicar fora
        document.addEventListener('click', (e) => {
            if (!inputNome.contains(e.target) && !listElement.contains(e.target)) {
                listElement.style.display = 'none';
            }
        });
    },

    /**
     * Configura autocomplete de serviço
     */
    configurarAutocompleteServico(inputNome, inputId, listElement, itemId) {
        let timeoutId = null;

        // Evento de digitação
        inputNome.addEventListener('input', async (e) => {
            const termo = e.target.value.trim();

            // Limpa timeout anterior
            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            if (!termo || termo.length < 2) {
                listElement.style.display = 'none';
                listElement.innerHTML = '';
                return;
            }

            // Debounce
            timeoutId = setTimeout(async () => {
                const servicos = await this.buscarServicos(termo);

                if (servicos.length === 0) {
                    listElement.innerHTML = '<div style="padding: 10px; color: var(--text-secondary);">Nenhum serviço encontrado</div>';
                    listElement.style.display = 'block';
                    return;
                }

                listElement.innerHTML = '';
                servicos.forEach(servico => {
                    const item = document.createElement('div');
                    item.style.cssText = 'padding: 10px; cursor: pointer; border-bottom: 1px solid var(--border-light); transition: background 0.2s;';
                    item.innerHTML = `
                        <div style="font-weight: 600; color: var(--text-primary);">${servico.nome}</div>
                        <div style="font-size: 12px; color: var(--text-secondary);">Cód: ${servico.codigo || 'N/A'} - ${this.formatarMoeda(servico.valor_venda || 0)}</div>
                    `;

                    item.addEventListener('mouseover', () => {
                        item.style.background = 'var(--bg-hover)';
                    });

                    item.addEventListener('mouseout', () => {
                        item.style.background = 'transparent';
                    });

                    item.addEventListener('click', () => {
                        inputNome.value = servico.nome;
                        inputId.value = servico.id;

                        // Atualiza o item
                        const itemObj = this.state.itens.find(i => i.id === itemId);
                        if (itemObj) {
                            itemObj.servico_id = servico.id;
                            itemObj.nome_produto = servico.nome;
                            itemObj.valor_venda = servico.valor_venda || 0;
                            this.atualizarItem(itemId, 'recalcular', true);
                        }

                        listElement.style.display = 'none';
                        listElement.innerHTML = '';
                    });

                    listElement.appendChild(item);
                });

                listElement.style.display = 'block';
            }, 300);
        });

        // Fecha a lista ao clicar fora
        document.addEventListener('click', (e) => {
            if (!inputNome.contains(e.target) && !listElement.contains(e.target)) {
                listElement.style.display = 'none';
            }
        });
    },

    /**
     * Atualiza campo de um item
     */
    atualizarItem(itemId, field, value) {
        const item = this.state.itens.find(i => i.id === itemId);
        if (!item) return;

        if (field !== 'recalcular') {
            item[field] = value;
        }

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
     * Atualiza tipo de pagamento (À Vista / Parcelado)
     */
    atualizarTipoPagamento() {
        if (this.elements.tipoPagamentoAvista.checked) {
            this.state.tipoPagamento = 'avista';

            // Se à vista, mantém apenas 1 parcela com valor total
            const totalItens = this.state.itens.reduce((sum, item) => sum + (parseFloat(item.valor_total) || 0), 0);

            this.state.pagamentos = [{
                id: Date.now(),
                parcela: 1,
                data_vencimento: '',
                valor: totalItens,
                forma_pagamento_id: this.state.pagamentos[0]?.forma_pagamento_id || '',
                pago: 0
            }];

            // Oculta botão de adicionar parcela
            if (this.elements.btnAddPagamento) {
                this.elements.btnAddPagamento.style.display = 'none';
            }
        } else {
            this.state.tipoPagamento = 'parcelado';

            // Se parcelado, permite adicionar parcelas
            if (this.elements.btnAddPagamento) {
                this.elements.btnAddPagamento.style.display = 'inline-flex';
            }

            // Se não tem nenhuma parcela, adiciona uma
            if (this.state.pagamentos.length === 0) {
                this.adicionarPagamento();
            }
        }

        this.renderizarPagamentos();
    },

    /**
     * Atualiza tipo de entrega (Retirar / Entregar)
     */
    atualizarTipoEntrega() {
        if (this.elements.tipoEntregaRetirar.checked) {
            this.state.tipoEntrega = 'retirar';

            // Oculta campos de endereço
            if (this.elements.enderecoContainer) {
                this.elements.enderecoContainer.style.display = 'none';
            }

            // Remove required dos campos de endereço
            document.querySelectorAll('#tabEndereco input, #tabEndereco select').forEach(field => {
                field.removeAttribute('required');
            });
        } else {
            this.state.tipoEntrega = 'entrega';

            // Mostra campos de endereço
            if (this.elements.enderecoContainer) {
                this.elements.enderecoContainer.style.display = 'block';
            }
        }
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

            // Monta opções de formas de pagamento
            let formasOptions = '<option value="">Selecione...</option>';
            this.state.formasPagamento.forEach(forma => {
                const selected = pagamento.forma_pagamento_id == forma.id ? 'selected' : '';
                formasOptions += `<option value="${forma.id}" ${selected}>${forma.nome}</option>`;
            });

            div.innerHTML = `
                <div class="dynamic-list-item-header">
                    <strong>Parcela ${pagamento.parcela}</strong>
                    ${this.state.tipoPagamento === 'parcelado' && this.state.pagamentos.length > 1 ? `
                        <button type="button" class="btn-remove-item" data-id="${pagamento.id}">
                            <i class="fas fa-trash"></i> Remover
                        </button>
                    ` : ''}
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Data Vencimento *</label>
                        <input type="date" data-field="data_vencimento" data-id="${pagamento.id}" value="${pagamento.data_vencimento || ''}" required>
                    </div>
                    <div class="form-group">
                        <label>Valor (R$) *</label>
                        <input type="number" step="0.01" min="0" data-field="valor" data-id="${pagamento.id}" value="${pagamento.valor || 0}" required>
                    </div>
                    <div class="form-group">
                        <label>Forma de Pagamento *</label>
                        <select data-field="forma_pagamento_id" data-id="${pagamento.id}" required>
                            ${formasOptions}
                        </select>
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
            const btnRemover = div.querySelector('.btn-remove-item');
            if (btnRemover) {
                btnRemover.addEventListener('click', () => this.removerPagamento(pagamento.id));
            }

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
     * Valida campos obrigatórios e navega para aba com erro
     */
    validarCamposObrigatorios() {
        // Verifica campos da aba GERAL
        const camposGerais = ['dataVenda', 'codigo', 'situacaoVendaId'];
        for (const campoId of camposGerais) {
            const campo = document.getElementById(campoId);
            if (campo && !campo.value.trim()) {
                this.navegarParaAba('geral');
                campo.focus();

                const label = document.querySelector(`label[for="${campoId}"]`);
                const nomeCampo = label ? label.textContent.replace('*', '').trim() : campoId;

                Utils.Notificacao.erro(`O campo "${nomeCampo}" é obrigatório`);
                return false;
            }
        }

        // Verifica ITENS (validação dos campos dinâmicos)
        if (this.state.itens.length > 0) {
            for (let i = 0; i < this.state.itens.length; i++) {
                const item = this.state.itens[i];

                // Valida tipo
                if (!item.tipo) {
                    this.navegarParaAba('itens');
                    Utils.Notificacao.erro(`Item ${i + 1}: Selecione o tipo (Produto ou Serviço)`);
                    return false;
                }

                // Valida se tem produto ou serviço selecionado
                if (item.tipo === 'produto' && !item.produto_id) {
                    this.navegarParaAba('itens');
                    Utils.Notificacao.erro(`Item ${i + 1}: Selecione um produto`);
                    return false;
                }

                if (item.tipo === 'servico' && !item.servico_id) {
                    this.navegarParaAba('itens');
                    Utils.Notificacao.erro(`Item ${i + 1}: Selecione um serviço`);
                    return false;
                }

                // Valida quantidade
                if (!item.quantidade || parseFloat(item.quantidade) <= 0) {
                    this.navegarParaAba('itens');
                    Utils.Notificacao.erro(`Item ${i + 1}: Informe uma quantidade válida`);
                    return false;
                }

                // Valida valor unitário
                if (!item.valor_venda || parseFloat(item.valor_venda) <= 0) {
                    this.navegarParaAba('itens');
                    Utils.Notificacao.erro(`Item ${i + 1}: Informe um valor unitário válido`);
                    return false;
                }
            }
        }

        // Verifica PAGAMENTOS (validação dos campos dinâmicos)
        if (this.state.pagamentos.length > 0) {
            for (let i = 0; i < this.state.pagamentos.length; i++) {
                const pag = this.state.pagamentos[i];

                // Valida data de vencimento
                if (!pag.data_vencimento) {
                    this.navegarParaAba('pagamentos');
                    Utils.Notificacao.erro(`Parcela ${i + 1}: Informe a data de vencimento`);
                    return false;
                }

                // Valida valor
                if (!pag.valor || parseFloat(pag.valor) <= 0) {
                    this.navegarParaAba('pagamentos');
                    Utils.Notificacao.erro(`Parcela ${i + 1}: Informe um valor válido`);
                    return false;
                }

                // Valida forma de pagamento
                if (!pag.forma_pagamento_id) {
                    this.navegarParaAba('pagamentos');
                    Utils.Notificacao.erro(`Parcela ${i + 1}: Selecione a forma de pagamento`);
                    return false;
                }
            }
        }

        return true;
    },

    /**
     * Navega para uma aba específica
     */
    navegarParaAba(nomeAba) {
        // Remove active de todas as abas
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

        // Ativa a aba desejada
        const botaoAba = document.querySelector(`.tab-button[data-tab="${nomeAba}"]`);
        const conteudoAba = document.getElementById('tab' + nomeAba.charAt(0).toUpperCase() + nomeAba.slice(1));

        if (botaoAba) botaoAba.classList.add('active');
        if (conteudoAba) conteudoAba.classList.add('active');
    },

    /**
     * Salva a venda
     */
    async salvar(e) {
        e.preventDefault();

        try {
            // Validação de campos obrigatórios
            if (!this.validarCamposObrigatorios()) {
                return;
            }

            // Validação básica
            if (this.state.itens.length === 0) {
                this.navegarParaAba('itens');
                Utils.Notificacao.erro('Adicione pelo menos um item à venda');
                return;
            }

            if (this.state.pagamentos.length === 0) {
                this.navegarParaAba('pagamentos');
                Utils.Notificacao.erro('Adicione pelo menos uma forma de pagamento');
                return;
            }

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

            // Adiciona endereço se tipo for "entrega"
            if (this.state.tipoEntrega === 'entrega') {
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
