/**
 * Gerenciador de Recebimentos (Contas a Pagar e Receber)
 * Implementa CRUD completo de recebimentos com validação de permissões ACL
 */

const RecebimentoManager = {
    state: {
        recebimentos: [],
        clientes: [],
        fornecedores: [],
        transportadoras: [],
        planoContas: [],
        centroCusto: [],
        contasBancarias: [],
        formasRecebimento: [],
        permissoes: {
            visualizar: false,
            criar: false,
            editar: false,
            deletar: false,
            baixar: false
        },
        paginacao: {
            pagina: 1,
            porPagina: 20,
            total: 0,
            totalPaginas: 0
        },
        filtros: {
            busca: '',
            liquidado: '0',
            entidade: ''
        },
        editandoId: null,
        recebimentoParaDeletar: null,
        recebimentoParaLiquidar: null
    },

    elements: {
        tableBody: document.getElementById('tableBody'),
        btnNovo: document.getElementById('btnNovo'),
        inputBusca: document.getElementById('inputBusca'),
        selectLiquidado: document.getElementById('selectLiquidado'),
        selectEntidade: document.getElementById('selectEntidade'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        btnCloseModal: document.getElementById('btnCloseModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        btnSalvar: document.getElementById('btnSalvar'),
        formRecebimento: document.getElementById('formRecebimento'),
        modalConfirm: document.getElementById('modalConfirm'),
        btnConfirmDelete: document.getElementById('btnConfirmDelete'),
        modalLiquidar: document.getElementById('modalLiquidar'),
        btnConfirmLiquidar: document.getElementById('btnConfirmLiquidar'),
        btnFiltrar: document.getElementById('btnFiltrar'),
        btnPrevious: document.getElementById('btnPrevious'),
        btnNext: document.getElementById('btnNext'),
        pageInfo: document.getElementById('pageInfo'),
        loadingContainer: document.getElementById('loadingContainer'),
        errorContainer: document.getElementById('errorContainer'),
        errorMessage: document.getElementById('errorMessage'),
        tableContainer: document.getElementById('tableContainer'),
        selectEntidadeForm: document.getElementById('selectEntidadeForm'),
        divCliente: document.getElementById('divCliente'),
        divFornecedor: document.getElementById('divFornecedor'),
        divTransportadora: document.getElementById('divTransportadora')
    },

    async init() {
        if (!AuthAPI.isAuthenticated()) {
            return;
        }

        this.setupEventListeners();
        await this.verificarPermissoes();

        if (!this.state.permissoes.visualizar) {
            document.getElementById('permissionDenied').style.display = 'block';
            API.showError('Você não tem permissão para visualizar recebimentos');
            return;
        }

        document.getElementById('pageContent').style.display = 'block';

        if (this.state.permissoes.criar && this.elements.btnNovo) {
            this.elements.btnNovo.style.display = 'inline-flex';
        }

        await Promise.all([
            this.carregarDadosRelacionados(),
            this.carregarDados()
        ]);
    },

    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());
        this.elements.inputBusca?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') this.aplicarFiltros();
        });
        this.elements.selectLiquidado?.addEventListener('change', () => this.aplicarFiltros());
        this.elements.selectEntidade?.addEventListener('change', () => this.aplicarFiltros());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.btnCloseModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.btnSalvar?.addEventListener('click', () => this.salvar());
        this.elements.btnConfirmDelete?.addEventListener('click', () => this.confirmarDeletar());
        this.elements.btnConfirmLiquidar?.addEventListener('click', () => this.confirmarLiquidar());
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());

        this.elements.selectEntidadeForm?.addEventListener('change', (e) => {
            this.mostrarCamposEntidade(e.target.value);
        });

        ['inputValor', 'inputJuros', 'inputDesconto', 'inputTaxaBanco', 'inputTaxaOperadora'].forEach(id => {
            document.getElementById(id)?.addEventListener('input', () => this.calcularValorTotal());
        });

        document.getElementById('checkLiquidado')?.addEventListener('change', (e) => {
            const dataLiquidacao = document.getElementById('inputDataLiquidacao');
            if (e.target.checked && !dataLiquidacao.value) {
                dataLiquidacao.value = new Date().toISOString().split('T')[0];
            }
        });

        this.elements.modalForm?.querySelector('.modal-backdrop')?.addEventListener('click', () => {
            this.fecharModal();
        });

        // Limpar erro de validação quando o usuário começar a digitar
        const formInputs = this.elements.formRecebimento?.querySelectorAll('input, select, textarea');
        formInputs?.forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = '';
            });
            input.addEventListener('change', function() {
                this.style.borderColor = '';
            });
        });

        // Setup de autocomplete para os 3 tipos de entidades
        this.setupClienteAutocomplete();
        this.setupFornecedorAutocomplete();
        this.setupTransportadoraAutocomplete();
    },

    async verificarPermissoes() {
        try {
            const permissoes = await aguardarPermissoes();
            if (permissoes) {
                this.state.permissoes = {
                    visualizar: permissoes.includes('recebimento.visualizar'),
                    criar: permissoes.includes('recebimento.criar'),
                    editar: permissoes.includes('recebimento.editar'),
                    deletar: permissoes.includes('recebimento.deletar'),
                    baixar: permissoes.includes('recebimento.baixar')
                };
            }

            if (!this.state.permissoes.criar && this.elements.btnNovo) {
                this.elements.btnNovo.style.display = 'none';
            }
        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
        }
    },

    async carregarDadosRelacionados() {
        try {
            const [clientes, fornecedores, transportadoras, planoContas, centroCusto, contasBancarias, formasRecebimento] = await Promise.all([
                API.get('/cliente?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/fornecedor?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/transportadora?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/plano-de-contas?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/centro-de-custo?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/conta-bancaria?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/forma-de-pagamento?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } }))
            ]);

            this.state.clientes = clientes.dados?.itens || [];
            this.state.fornecedores = fornecedores.dados?.itens || [];
            this.state.transportadoras = transportadoras.dados?.itens || [];
            this.state.planoContas = planoContas.dados?.itens || [];
            this.state.centroCusto = centroCusto.dados?.itens || [];
            this.state.contasBancarias = contasBancarias.dados?.itens || [];
            this.state.formasRecebimento = formasRecebimento.dados?.itens || [];

            this.popularSelects();
        } catch (error) {
            console.error('Erro ao carregar dados relacionados:', error);
        }
    },

    popularSelects() {
        // Clientes, fornecedores e transportadoras usam autocomplete agora
        this.popularSelect('selectPlanoContas', this.state.planoContas, 'Selecione...');
        this.popularSelect('selectCentroCusto', this.state.centroCusto, 'Selecione...');
        this.popularSelect('selectContaBancaria', this.state.contasBancarias, 'Selecione...');
        this.popularSelect('selectFormaRecebimento', this.state.formasRecebimento, 'Selecione...');
    },

    popularSelect(elementId, items, placeholder) {
        const select = document.getElementById(elementId);
        if (!select) return;

        select.innerHTML = '<option value="">' + placeholder + '</option>';
        items.forEach(item => {
            const option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.nome;
            select.appendChild(option);
        });
    },

    mostrarCamposEntidade(entidade) {
        this.elements.divCliente.style.display = entidade === 'C' ? 'block' : 'none';
        this.elements.divFornecedor.style.display = entidade === 'F' ? 'block' : 'none';
        this.elements.divTransportadora.style.display = entidade === 'T' ? 'block' : 'none';
    },

    calcularValorTotal() {
        const valor = parseFloat(document.getElementById('inputValor')?.value || 0);
        const juros = parseFloat(document.getElementById('inputJuros')?.value || 0);
        const desconto = parseFloat(document.getElementById('inputDesconto')?.value || 0);
        const taxaBanco = parseFloat(document.getElementById('inputTaxaBanco')?.value || 0);
        const taxaOperadora = parseFloat(document.getElementById('inputTaxaOperadora')?.value || 0);

        const valorTotal = valor + juros - desconto - taxaBanco - taxaOperadora;
        document.getElementById('inputValorTotal').value = valorTotal.toFixed(2);
    },

    async carregarDados() {
        try {
            this.elements.loadingContainer.style.display = 'block';
            this.elements.errorContainer.style.display = 'none';
            this.elements.tableContainer.style.display = 'none';

            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina
            });

            Object.keys(this.state.filtros).forEach(key => {
                if (this.state.filtros[key]) {
                    params.append(key, this.state.filtros[key]);
                }
            });

            const queryString = params.toString();
            const response = await API.get('/recebimento?' + queryString);

            if (response.sucesso) {
                this.state.recebimentos = response.dados.itens || [];
                this.state.paginacao.total = response.dados.total || 0;
                this.state.paginacao.totalPaginas = response.dados.total_paginas || 1;

                this.elements.loadingContainer.style.display = 'none';
                this.elements.tableContainer.style.display = 'block';

                this.renderizarTabela();
                this.atualizarPaginacao();
            } else {
                this.elements.loadingContainer.style.display = 'none';
                this.elements.errorContainer.style.display = 'block';
                this.elements.errorMessage.textContent = response.mensagem || 'Erro ao carregar recebimentos';
            }
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            this.elements.loadingContainer.style.display = 'none';
            this.elements.errorContainer.style.display = 'block';
            this.elements.errorMessage.textContent = 'Erro ao carregar recebimentos';
        }
    },

    renderizarTabela() {
        if (!this.elements.tableBody) return;

        if (this.state.recebimentos.length === 0) {
            this.elements.tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px;"><i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i><p style="color: #666;">Nenhum recebimento encontrado</p></td></tr>';
            return;
        }

        this.elements.tableBody.innerHTML = this.state.recebimentos.map(pag => {
            const tipoLabel = this.getTipoLabel(pag.entidade);
            const nomeEntidade = this.getNomeEntidade(pag);

            let acoes = '<div class="btn-group">';
            if (this.state.permissoes.editar) {
                acoes += '<button class="btn btn-sm btn-primary" onclick="RecebimentoManager.editar(' + pag.id + ')" title="Editar"><i class="fas fa-edit"></i></button>';
            }
            if (this.state.permissoes.baixar && pag.liquidado == 0) {
                acoes += '<button class="btn btn-sm btn-primary" onclick="RecebimentoManager.abrirModalLiquidar(' + pag.id + ')" title="Liquidar"><i class="fas fa-check"></i></button>';
            }
            if (this.state.permissoes.deletar) {
                acoes += '<button class="btn btn-sm btn-danger" onclick="RecebimentoManager.deletar(' + pag.id + ')" title="Excluir"><i class="fas fa-trash"></i></button>';
            }
            acoes += '</div>';

            return '<tr><td>' + pag.id + '</td><td>' + this.escapeHtml(pag.codigo || '-') + '</td><td><strong>' + this.escapeHtml(pag.descricao) + '</strong></td><td><span class="badge ' + this.getBadgeClassTipo(pag.entidade) + '">' + tipoLabel + '</span></td><td>' + this.escapeHtml(nomeEntidade) + '</td><td style="text-align: right;"><strong>R$ ' + this.formatarValor(pag.valor_total) + '</strong></td><td>' + this.formatarData(pag.data_vencimento) + '</td><td style="text-align: center;"><span class="badge ' + (pag.liquidado == 1 ? 'badge-success' : 'badge-warning') + '">' + (pag.liquidado == 1 ? 'Liquidado' : 'Pendente') + '</span></td><td style="text-align: center;">' + acoes + '</td></tr>';
        }).join('');
    },

    getTipoLabel(entidade) {
        const tipos = { 'C': 'Receber', 'F': 'Pagar', 'T': 'Transporte', 'U': 'Funcionário' };
        return tipos[entidade] || entidade;
    },

    getBadgeClassTipo(entidade) {
        return entidade === 'C' ? 'badge-success' : 'badge-danger';
    },

    getNomeEntidade(pag) {
        switch (pag.entidade) {
            case 'C': return pag.nome_cliente || '-';
            case 'F': return pag.nome_fornecedor || '-';
            case 'T': return pag.nome_transportadora || '-';
            default: return '-';
        }
    },

    atualizarPaginacao() {
        if (this.elements.pageInfo) {
            this.elements.pageInfo.textContent = 'Página ' + this.state.paginacao.pagina + ' de ' + this.state.paginacao.totalPaginas;
        }

        if (this.elements.btnPrevious) {
            this.elements.btnPrevious.disabled = this.state.paginacao.pagina === 1;
        }

        if (this.elements.btnNext) {
            this.elements.btnNext.disabled = this.state.paginacao.pagina >= this.state.paginacao.totalPaginas;
        }
    },

    aplicarFiltros() {
        this.state.filtros.busca = this.elements.inputBusca?.value || '';
        this.state.filtros.liquidado = this.elements.selectLiquidado?.value || '';
        this.state.filtros.entidade = this.elements.selectEntidade?.value || '';
        this.state.paginacao.pagina = 1;
        this.carregarDados();
    },

    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarDados();
        }
    },

    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarDados();
        }
    },

    abrirModalNovo() {
        if (!this.state.permissoes.criar) {
            API.showError('Você não tem permissão para criar recebimentos');
            return;
        }

        this.state.editandoId = null;
        if (this.elements.modalTitle) {
            this.elements.modalTitle.textContent = 'Novo Recebimento';
        }
        this.limparFormulario();
        this.abrirModal();
    },

    async editar(id) {
        if (!this.state.permissoes.editar) {
            API.showError('Você não tem permissão para editar recebimentos');
            return;
        }

        try {
            const response = await API.get('/recebimento/' + id);

            if (response.sucesso && response.dados) {
                this.state.editandoId = id;
                if (this.elements.modalTitle) {
                    this.elements.modalTitle.textContent = 'Editar Recebimento';
                }
                this.preencherFormulario(response.dados);
                this.abrirModal();
            } else {
                API.showError(response.mensagem || 'Erro ao carregar recebimento');
            }
        } catch (error) {
            console.error('Erro ao editar:', error);
            API.showError('Erro ao carregar recebimento');
        }
    },

    deletar(id) {
        if (!this.state.permissoes.deletar) {
            API.showError('Você não tem permissão para deletar recebimentos');
            return;
        }

        this.state.recebimentoParaDeletar = id;
        this.abrirModalConfirm();
    },

    async confirmarDeletar() {
        if (!this.state.recebimentoParaDeletar) return;

        try {
            const response = await API.delete('/recebimento/' + this.state.recebimentoParaDeletar);

            if (response.sucesso) {
                API.showSuccess('Recebimento excluído com sucesso');
                this.fecharModalConfirm();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao excluir recebimento');
            }
        } catch (error) {
            console.error('Erro ao deletar:', error);
            API.showError('Erro ao excluir recebimento');
        }
    },

    abrirModalLiquidar(id) {
        if (!this.state.permissoes.baixar) {
            API.showError('Você não tem permissão para baixar recebimentos');
            return;
        }

        this.state.recebimentoParaLiquidar = id;
        document.getElementById('inputDataLiquidacaoModal').value = new Date().toISOString().split('T')[0];
        this.elements.modalLiquidar.classList.add('show');
    },

    fecharModalLiquidar() {
        this.elements.modalLiquidar.classList.remove('show');
        this.state.recebimentoParaLiquidar = null;
    },

    async confirmarLiquidar() {
        if (!this.state.recebimentoParaLiquidar) return;

        const dataLiquidacao = document.getElementById('inputDataLiquidacaoModal').value;
        if (!dataLiquidacao) {
            API.showError('Data de liquidação é obrigatória');
            return;
        }

        try {
            const response = await API.post('/recebimento/' + this.state.recebimentoParaLiquidar + '/baixar', {
                data_liquidacao: dataLiquidacao
            });

            if (response.sucesso) {
                API.showSuccess('Recebimento liquidado com sucesso');
                this.fecharModalLiquidar();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao baixar recebimento');
            }
        } catch (error) {
            console.error('Erro ao baixar:', error);
            API.showError('Erro ao baixar recebimento');
        }
    },

    limparErrosValidacao() {
        // Remove bordas vermelhas de todos os inputs
        const form = this.elements.formRecebimento;
        if (!form) return;

        form.querySelectorAll('input, select, textarea').forEach(input => {
            input.style.borderColor = '';
        });
    },

    destacarCampoComErro(fieldName) {
        const fieldMap = {
            'descricao': 'inputDescricao',
            'codigo': 'inputCodigo',
            'entidade': 'selectEntidadeForm',
            'valor': 'inputValor',
            'juros': 'inputJuros',
            'desconto': 'inputDesconto',
            'taxa_banco': 'inputTaxaBanco',
            'taxa_operadora': 'inputTaxaOperadora',
            'data_vencimento': 'inputDataVencimento',
            'data_liquidacao': 'inputDataLiquidacao',
            'data_competencia': 'inputDataCompetencia',
            'cliente_id': 'inputClienteBusca',
            'fornecedor_id': 'inputFornecedorBusca',
            'transportadora_id': 'inputTransportadoraBusca',
            'plano_contas_id': 'selectPlanoContas',
            'centro_custo_id': 'selectCentroCusto',
            'conta_bancaria_id': 'selectContaBancaria',
            'forma_recebimento_id': 'selectFormaRecebimento'
        };

        const inputId = fieldMap[fieldName];
        if (inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                input.style.borderColor = '#dc3545';
                // Focar no primeiro campo com erro
                if (!this._firstErrorFocused) {
                    input.focus();
                    this._firstErrorFocused = true;
                }
            }
        }
    },

    async salvar() {
        try {
            // Limpar erros anteriores e reset flag de foco
            this.limparErrosValidacao();
            this._firstErrorFocused = false;

            const dados = this.obterDadosFormulario();

            // Validação básica no frontend
            if (!dados.descricao || dados.descricao.trim().length < 3) {
                API.showError('Descrição deve ter no mínimo 3 caracteres');
                this.destacarCampoComErro('descricao');
                return;
            }

            if (!dados.entidade) {
                API.showError('Tipo é obrigatório');
                this.destacarCampoComErro('entidade');
                return;
            }

            if (!dados.valor || dados.valor <= 0) {
                API.showError('Valor deve ser maior que zero');
                this.destacarCampoComErro('valor');
                return;
            }

            if (!dados.data_vencimento) {
                API.showError('Data de vencimento é obrigatória');
                this.destacarCampoComErro('data_vencimento');
                return;
            }

            // Validação de entidade específica
            switch (dados.entidade) {
                case 'C':
                    if (!dados.cliente_id) {
                        API.showError('Cliente é obrigatório');
                        this.destacarCampoComErro('cliente_id');
                        return;
                    }
                    break;
                case 'F':
                    if (!dados.fornecedor_id) {
                        API.showError('Fornecedor é obrigatório');
                        this.destacarCampoComErro('fornecedor_id');
                        return;
                    }
                    break;
                case 'T':
                    if (!dados.transportadora_id) {
                        API.showError('Transportadora é obrigatória');
                        this.destacarCampoComErro('transportadora_id');
                        return;
                    }
                    break;
            }

            let response;
            if (this.state.editandoId) {
                response = await API.put('/recebimento/' + this.state.editandoId, dados);
            } else {
                response = await API.post('/recebimento', dados);
            }

            // Tratar resposta
            if (response.sucesso) {
                API.showSuccess(this.state.editandoId ? 'Recebimento atualizado com sucesso' : 'Recebimento cadastrado com sucesso');
                this.fecharModal();
                this.carregarDados();
            } else if (response.codigo === 422 && response.erros) {
                // Tratar erros de validação do backend
                this._firstErrorFocused = false;
                Object.entries(response.erros).forEach(([field, messages]) => {
                    this.destacarCampoComErro(field);
                    const errorMessages = Array.isArray(messages) ? messages : [messages];
                    errorMessages.forEach(msg => API.showError(msg));
                });
            } else {
                API.showError(response.mensagem || 'Erro ao salvar recebimento');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            if (error.data && error.data.erros) {
                // Tratar erros de validação
                this._firstErrorFocused = false;
                Object.entries(error.data.erros).forEach(([field, messages]) => {
                    this.destacarCampoComErro(field);
                });
                API.showValidationErrors(error.data.erros);
            } else {
                API.showError('Erro ao salvar recebimento');
            }
        }
    },

    obterDadosFormulario() {
        const form = this.elements.formRecebimento;
        if (!form) return {};

        const dados = {
            descricao: form.querySelector('#inputDescricao')?.value,
            codigo: form.querySelector('#inputCodigo')?.value || null,
            entidade: form.querySelector('#selectEntidadeForm')?.value,
            valor: parseFloat(form.querySelector('#inputValor')?.value) || 0,
            juros: parseFloat(form.querySelector('#inputJuros')?.value) || 0,
            desconto: parseFloat(form.querySelector('#inputDesconto')?.value) || 0,
            data_vencimento: form.querySelector('#inputDataVencimento')?.value,
            data_liquidacao: form.querySelector('#inputDataLiquidacao')?.value || null,
            data_competencia: form.querySelector('#inputDataCompetencia')?.value || null,
            liquidado: form.querySelector('#checkLiquidado')?.checked ? 1 : 0,
            external_id: form.querySelector('#inputExternalId')?.value || null
        };

        // Entidades (conforme seleção)
        const clienteId = form.querySelector('#inputClienteId')?.value;
        const fornecedorId = form.querySelector('#inputFornecedorId')?.value;
        const transportadoraId = form.querySelector('#inputTransportadoraId')?.value;

        if (clienteId) dados.cliente_id = parseInt(clienteId);
        if (fornecedorId) dados.fornecedor_id = parseInt(fornecedorId);
        if (transportadoraId) dados.transportadora_id = parseInt(transportadoraId);

        // Campos obrigatórios
        const planoContasId = form.querySelector('#selectPlanoContas')?.value;
        const centroCustoId = form.querySelector('#selectCentroCusto')?.value;
        const contaBancariaId = form.querySelector('#selectContaBancaria')?.value;
        const formaPagamentoId = form.querySelector('#selectFormaPagamento')?.value;

        if (planoContasId) dados.plano_contas_id = parseInt(planoContasId);
        if (centroCustoId) dados.centro_custo_id = parseInt(centroCustoId);
        if (contaBancariaId) dados.conta_bancaria_id = parseInt(contaBancariaId);
        if (formaPagamentoId) dados.forma_pagamento_id = parseInt(formaPagamentoId);

        return dados;
    },

    preencherFormulario(pag) {
        const form = this.elements.formRecebimento;
        if (!form) return;

        form.querySelector('#recebimentoId').value = pag.id || '';
        form.querySelector('#inputDescricao').value = pag.descricao || '';
        form.querySelector('#inputCodigo').value = pag.codigo || '';
        form.querySelector('#selectEntidadeForm').value = pag.entidade || '';
        this.mostrarCamposEntidade(pag.entidade);

        // Preencher campos de autocomplete para entidades
        const inputClienteId = form.querySelector('#inputClienteId');
        const inputClienteBusca = form.querySelector('#inputClienteBusca');
        if (inputClienteId) inputClienteId.value = pag.cliente_id || '';
        if (inputClienteBusca) inputClienteBusca.value = pag.nome_cliente || '';

        const inputFornecedorId = form.querySelector('#inputFornecedorId');
        const inputFornecedorBusca = form.querySelector('#inputFornecedorBusca');
        if (inputFornecedorId) inputFornecedorId.value = pag.fornecedor_id || '';
        if (inputFornecedorBusca) inputFornecedorBusca.value = pag.nome_fornecedor || '';

        const inputTransportadoraId = form.querySelector('#inputTransportadoraId');
        const inputTransportadoraBusca = form.querySelector('#inputTransportadoraBusca');
        if (inputTransportadoraId) inputTransportadoraId.value = pag.transportadora_id || '';
        if (inputTransportadoraBusca) inputTransportadoraBusca.value = pag.nome_transportadora || '';

        form.querySelector('#inputValor').value = pag.valor || '0';
        form.querySelector('#inputJuros').value = pag.juros || '0';
        form.querySelector('#inputDesconto').value = pag.desconto || '0';
        form.querySelector('#inputTaxaBanco').value = pag.taxa_banco || '0';
        form.querySelector('#inputTaxaOperadora').value = pag.taxa_operadora || '0';
        form.querySelector('#inputValorTotal').value = pag.valor_total || '0';

        form.querySelector('#selectPlanoContas').value = pag.plano_contas_id || '';
        form.querySelector('#selectCentroCusto').value = pag.centro_custo_id || '';
        form.querySelector('#selectContaBancaria').value = pag.conta_bancaria_id || '';
        form.querySelector('#selectFormaRecebimento').value = pag.forma_recebimento_id || '';

        form.querySelector('#inputDataVencimento').value = pag.data_vencimento || '';
        form.querySelector('#inputDataLiquidacao').value = pag.data_liquidacao || '';
        form.querySelector('#inputDataCompetencia').value = pag.data_competencia || '';
        form.querySelector('#checkLiquidado').checked = pag.liquidado == 1;
        form.querySelector('#inputExternalId').value = pag.external_id || '';
    },

    limparFormulario() {
        const form = this.elements.formRecebimento;
        if (!form) return;

        form.querySelector('#recebimentoId').value = '';
        form.querySelector('#inputDescricao').value = '';
        form.querySelector('#inputCodigo').value = '';
        form.querySelector('#selectEntidadeForm').value = '';
        this.mostrarCamposEntidade('');

        // Limpar campos de autocomplete para entidades
        const inputClienteBusca = form.querySelector('#inputClienteBusca');
        const inputClienteId = form.querySelector('#inputClienteId');
        if (inputClienteBusca) inputClienteBusca.value = '';
        if (inputClienteId) inputClienteId.value = '';

        const inputFornecedorBusca = form.querySelector('#inputFornecedorBusca');
        const inputFornecedorId = form.querySelector('#inputFornecedorId');
        if (inputFornecedorBusca) inputFornecedorBusca.value = '';
        if (inputFornecedorId) inputFornecedorId.value = '';

        const inputTransportadoraBusca = form.querySelector('#inputTransportadoraBusca');
        const inputTransportadoraId = form.querySelector('#inputTransportadoraId');
        if (inputTransportadoraBusca) inputTransportadoraBusca.value = '';
        if (inputTransportadoraId) inputTransportadoraId.value = '';

        form.querySelector('#inputValor').value = '0';
        form.querySelector('#inputJuros').value = '0';
        form.querySelector('#inputDesconto').value = '0';
        form.querySelector('#inputTaxaBanco').value = '0';
        form.querySelector('#inputTaxaOperadora').value = '0';
        form.querySelector('#inputValorTotal').value = '0';

        form.querySelector('#selectPlanoContas').value = '';
        form.querySelector('#selectCentroCusto').value = '';
        form.querySelector('#selectContaBancaria').value = '';
        form.querySelector('#selectFormaRecebimento').value = '';

        form.querySelector('#inputDataVencimento').value = '';
        form.querySelector('#inputDataLiquidacao').value = '';
        form.querySelector('#inputDataCompetencia').value = '';
        form.querySelector('#checkLiquidado').checked = false;
        form.querySelector('#inputExternalId').value = '';
    },

    abrirModal() {
        if (this.elements.modalForm) {
            this.elements.modalForm.classList.add('show');
        }
    },

    fecharModal() {
        if (this.elements.modalForm) {
            this.elements.modalForm.classList.remove('show');
        }
        this.state.editandoId = null;
    },

    abrirModalConfirm() {
        if (this.elements.modalConfirm) {
            this.elements.modalConfirm.classList.add('show');
        }
    },

    fecharModalConfirm() {
        if (this.elements.modalConfirm) {
            this.elements.modalConfirm.classList.remove('show');
        }
        this.state.recebimentoParaDeletar = null;
    },

    formatarValor(valor) {
        return parseFloat(valor || 0).toFixed(2).replace('.', ',');
    },

    formatarData(data) {
        if (!data) return '-';
        const partes = data.split('-');
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    },

    escapeHtml(text) {
        if (text === null || text === undefined) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    // ============= AUTOCOMPLETE =============

    autocompleteTimeouts: {},

    setupAutocomplete(config) {
        const inputBusca = document.getElementById(config.inputBuscaId);
        const inputId = document.getElementById(config.inputIdId);
        const divSugestoes = document.getElementById(config.suggestoesId);

        if (!inputBusca) return;

        inputBusca.addEventListener('input', async function() {
            const termo = this.value.trim();

            // Limpa timeout anterior
            if (RecebimentoManager.autocompleteTimeouts[config.tipo]) {
                clearTimeout(RecebimentoManager.autocompleteTimeouts[config.tipo]);
            }

            // Limpa ID se campo foi apagado
            if (!termo) {
                inputId.value = '';
                divSugestoes.style.display = 'none';
                return;
            }

            // Aguarda 300ms após usuário parar de digitar
            RecebimentoManager.autocompleteTimeouts[config.tipo] = setTimeout(async () => {
                try {
                    divSugestoes.innerHTML = '<div class="autocomplete-loading">Buscando...</div>';
                    divSugestoes.style.display = 'block';

                    const response = await API.get(`${config.endpoint}?busca=${encodeURIComponent(termo)}&ativo=1&por_pagina=10`);

                    if (response.sucesso && response.dados?.itens?.length > 0) {
                        divSugestoes.innerHTML = response.dados.itens.map(item => {
                            const info = config.formatInfo(item);
                            return `
                                <div class="autocomplete-item" data-id="${item.id}" data-nome="${RecebimentoManager.escapeHtml(item.nome)}">
                                    <strong>${RecebimentoManager.escapeHtml(item.nome)}</strong>
                                    ${info ? `<small>${info}</small>` : ''}
                                </div>
                            `;
                        }).join('');

                        // Adiciona evento de clique
                        divSugestoes.querySelectorAll('.autocomplete-item').forEach(itemDiv => {
                            itemDiv.addEventListener('click', function() {
                                inputBusca.value = this.dataset.nome;
                                inputId.value = this.dataset.id;
                                divSugestoes.style.display = 'none';
                            });
                        });
                    } else {
                        divSugestoes.innerHTML = `<div class="autocomplete-no-results">Nenhum ${config.tipo} encontrado</div>`;
                    }
                } catch (error) {
                    divSugestoes.innerHTML = `<div class="autocomplete-no-results">Erro ao buscar ${config.tipo}s</div>`;
                    console.error(`Erro ao buscar ${config.tipo}s:`, error);
                }
            }, 300);
        });

        // Fecha sugestões ao clicar fora
        document.addEventListener('click', function(e) {
            if (!inputBusca.contains(e.target) && !divSugestoes.contains(e.target)) {
                divSugestoes.style.display = 'none';
            }
        });
    },

    setupClienteAutocomplete() {
        this.setupAutocomplete({
            tipo: 'cliente',
            inputBuscaId: 'inputClienteBusca',
            inputIdId: 'inputClienteId',
            suggestoesId: 'clienteSugestoes',
            endpoint: '/cliente',
            formatInfo: (item) => {
                const parts = [];
                if (item.cpf_cnpj) parts.push(item.cpf_cnpj);
                if (item.email) parts.push(item.email);
                return parts.join(' • ');
            }
        });
    },

    setupFornecedorAutocomplete() {
        this.setupAutocomplete({
            tipo: 'fornecedor',
            inputBuscaId: 'inputFornecedorBusca',
            inputIdId: 'inputFornecedorId',
            suggestoesId: 'fornecedorSugestoes',
            endpoint: '/fornecedor',
            formatInfo: (item) => {
                const parts = [];
                if (item.cnpj) parts.push(item.cnpj);
                if (item.email) parts.push(item.email);
                return parts.join(' • ');
            }
        });
    },

    setupTransportadoraAutocomplete() {
        this.setupAutocomplete({
            tipo: 'transportadora',
            inputBuscaId: 'inputTransportadoraBusca',
            inputIdId: 'inputTransportadoraId',
            suggestoesId: 'transportadoraSugestoes',
            endpoint: '/transportadora',
            formatInfo: (item) => {
                const parts = [];
                if (item.cnpj) parts.push(item.cnpj);
                if (item.telefone) parts.push(item.telefone);
                return parts.join(' • ');
            }
        });
    }
};
