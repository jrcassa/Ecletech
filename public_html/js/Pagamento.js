/**
 * Gerenciador de Pagamentos (Contas a Pagar e Receber)
 * Implementa CRUD completo de pagamentos com validação de permissões ACL
 */

const PagamentoManager = {
    state: {
        pagamentos: [],
        clientes: [],
        fornecedores: [],
        transportadoras: [],
        colaboradores: [],
        planoContas: [],
        centroCusto: [],
        contasBancarias: [],
        formasPagamento: [],
        permissoes: {
            visualizar: false,
            criar: false,
            editar: false,
            deletar: false,
            liquidar: false
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
        pagamentoParaDeletar: null,
        pagamentoParaLiquidar: null
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
        formPagamento: document.getElementById('formPagamento'),
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
        divTransportadora: document.getElementById('divTransportadora'),
        divFuncionario: document.getElementById('divFuncionario')
    },

    async init() {
        if (!AuthAPI.isAuthenticated()) {
            return;
        }

        this.setupEventListeners();
        await this.verificarPermissoes();

        if (!this.state.permissoes.visualizar) {
            document.getElementById('permissionDenied').style.display = 'block';
            API.showError('Você não tem permissão para visualizar pagamentos');
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
    },

    async verificarPermissoes() {
        try {
            const permissoes = await aguardarPermissoes();
            if (permissoes) {
                this.state.permissoes = {
                    visualizar: permissoes.includes('pagamento.visualizar'),
                    criar: permissoes.includes('pagamento.criar'),
                    editar: permissoes.includes('pagamento.editar'),
                    deletar: permissoes.includes('pagamento.deletar'),
                    liquidar: permissoes.includes('pagamento.liquidar')
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
            const [clientes, fornecedores, transportadoras, colaboradores, planoContas, centroCusto, contasBancarias, formasPagamento] = await Promise.all([
                API.get('/cliente?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/fornecedor?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/transportadora?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/colaboradores?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/plano-de-contas?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/centro-de-custo?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/conta-bancaria?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } })),
                API.get('/forma-de-pagamento?ativo=1&por_pagina=1000').catch(() => ({ dados: { itens: [] } }))
            ]);

            this.state.clientes = clientes.dados?.itens || [];
            this.state.fornecedores = fornecedores.dados?.itens || [];
            this.state.transportadoras = transportadoras.dados?.itens || [];
            this.state.colaboradores = colaboradores.dados?.itens || [];
            this.state.planoContas = planoContas.dados?.itens || [];
            this.state.centroCusto = centroCusto.dados?.itens || [];
            this.state.contasBancarias = contasBancarias.dados?.itens || [];
            this.state.formasPagamento = formasPagamento.dados?.itens || [];

            this.popularSelects();
        } catch (error) {
            console.error('Erro ao carregar dados relacionados:', error);
        }
    },

    popularSelects() {
        this.popularSelect('selectCliente', this.state.clientes, 'Selecione um cliente');
        this.popularSelect('selectFornecedor', this.state.fornecedores, 'Selecione um fornecedor');
        this.popularSelect('selectTransportadora', this.state.transportadoras, 'Selecione uma transportadora');
        this.popularSelect('selectFuncionario', this.state.colaboradores, 'Selecione um funcionário');
        this.popularSelect('selectPlanoContas', this.state.planoContas, 'Selecione...');
        this.popularSelect('selectCentroCusto', this.state.centroCusto, 'Selecione...');
        this.popularSelect('selectContaBancaria', this.state.contasBancarias, 'Selecione...');
        this.popularSelect('selectFormaPagamento', this.state.formasPagamento, 'Selecione...');
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
        this.elements.divFuncionario.style.display = entidade === 'U' ? 'block' : 'none';
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
            const response = await API.get('/pagamento?' + queryString);

            if (response.sucesso) {
                this.state.pagamentos = response.dados.itens || [];
                this.state.paginacao.total = response.dados.total || 0;
                this.state.paginacao.totalPaginas = response.dados.total_paginas || 1;

                this.elements.loadingContainer.style.display = 'none';
                this.elements.tableContainer.style.display = 'block';

                this.renderizarTabela();
                this.atualizarPaginacao();
            } else {
                this.elements.loadingContainer.style.display = 'none';
                this.elements.errorContainer.style.display = 'block';
                this.elements.errorMessage.textContent = response.mensagem || 'Erro ao carregar pagamentos';
            }
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            this.elements.loadingContainer.style.display = 'none';
            this.elements.errorContainer.style.display = 'block';
            this.elements.errorMessage.textContent = 'Erro ao carregar pagamentos';
        }
    },

    renderizarTabela() {
        if (!this.elements.tableBody) return;

        if (this.state.pagamentos.length === 0) {
            this.elements.tableBody.innerHTML = '<tr><td colspan="9" style="text-align: center; padding: 40px;"><i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i><p style="color: #666;">Nenhum pagamento encontrado</p></td></tr>';
            return;
        }

        this.elements.tableBody.innerHTML = this.state.pagamentos.map(pag => {
            const tipoLabel = this.getTipoLabel(pag.entidade);
            const nomeEntidade = this.getNomeEntidade(pag);

            let acoes = '<div class="btn-group">';
            if (this.state.permissoes.editar) {
                acoes += '<button class="btn btn-sm btn-primary" onclick="PagamentoManager.editar(' + pag.id + ')" title="Editar"><i class="fas fa-edit"></i></button>';
            }
            if (this.state.permissoes.liquidar && pag.liquidado == 0) {
                acoes += '<button class="btn btn-sm btn-primary" onclick="PagamentoManager.abrirModalLiquidar(' + pag.id + ')" title="Liquidar"><i class="fas fa-check"></i></button>';
            }
            if (this.state.permissoes.deletar) {
                acoes += '<button class="btn btn-sm btn-danger" onclick="PagamentoManager.deletar(' + pag.id + ')" title="Excluir"><i class="fas fa-trash"></i></button>';
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
            case 'U': return pag.nome_funcionario || '-';
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
            API.showError('Você não tem permissão para criar pagamentos');
            return;
        }

        this.state.editandoId = null;
        if (this.elements.modalTitle) {
            this.elements.modalTitle.textContent = 'Novo Pagamento';
        }
        this.limparFormulario();
        this.abrirModal();
    },

    async editar(id) {
        if (!this.state.permissoes.editar) {
            API.showError('Você não tem permissão para editar pagamentos');
            return;
        }

        try {
            const response = await API.get('/pagamento/' + id);

            if (response.sucesso && response.dados) {
                this.state.editandoId = id;
                if (this.elements.modalTitle) {
                    this.elements.modalTitle.textContent = 'Editar Pagamento';
                }
                this.preencherFormulario(response.dados);
                this.abrirModal();
            } else {
                API.showError(response.mensagem || 'Erro ao carregar pagamento');
            }
        } catch (error) {
            console.error('Erro ao editar:', error);
            API.showError('Erro ao carregar pagamento');
        }
    },

    deletar(id) {
        if (!this.state.permissoes.deletar) {
            API.showError('Você não tem permissão para deletar pagamentos');
            return;
        }

        this.state.pagamentoParaDeletar = id;
        this.abrirModalConfirm();
    },

    async confirmarDeletar() {
        if (!this.state.pagamentoParaDeletar) return;

        try {
            const response = await API.delete('/pagamento/' + this.state.pagamentoParaDeletar);

            if (response.sucesso) {
                API.showSuccess('Pagamento excluído com sucesso');
                this.fecharModalConfirm();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao excluir pagamento');
            }
        } catch (error) {
            console.error('Erro ao deletar:', error);
            API.showError('Erro ao excluir pagamento');
        }
    },

    abrirModalLiquidar(id) {
        if (!this.state.permissoes.liquidar) {
            API.showError('Você não tem permissão para liquidar pagamentos');
            return;
        }

        this.state.pagamentoParaLiquidar = id;
        document.getElementById('inputDataLiquidacaoModal').value = new Date().toISOString().split('T')[0];
        this.elements.modalLiquidar.classList.add('show');
    },

    fecharModalLiquidar() {
        this.elements.modalLiquidar.classList.remove('show');
        this.state.pagamentoParaLiquidar = null;
    },

    async confirmarLiquidar() {
        if (!this.state.pagamentoParaLiquidar) return;

        const dataLiquidacao = document.getElementById('inputDataLiquidacaoModal').value;
        if (!dataLiquidacao) {
            API.showError('Data de liquidação é obrigatória');
            return;
        }

        try {
            const response = await API.post('/pagamento/' + this.state.pagamentoParaLiquidar + '/liquidar', {
                data_liquidacao: dataLiquidacao
            });

            if (response.sucesso) {
                API.showSuccess('Pagamento liquidado com sucesso');
                this.fecharModalLiquidar();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao liquidar pagamento');
            }
        } catch (error) {
            console.error('Erro ao liquidar:', error);
            API.showError('Erro ao liquidar pagamento');
        }
    },

    async salvar() {
        try {
            const dados = this.obterDadosFormulario();

            if (!dados.descricao) {
                API.showError('Descrição é obrigatória');
                return;
            }

            if (!dados.entidade) {
                API.showError('Tipo é obrigatório');
                return;
            }

            if (!dados.valor || dados.valor <= 0) {
                API.showError('Valor deve ser maior que zero');
                return;
            }

            if (!dados.data_vencimento) {
                API.showError('Data de vencimento é obrigatória');
                return;
            }

            switch (dados.entidade) {
                case 'C':
                    if (!dados.cliente_id) {
                        API.showError('Cliente é obrigatório');
                        return;
                    }
                    break;
                case 'F':
                    if (!dados.fornecedor_id) {
                        API.showError('Fornecedor é obrigatório');
                        return;
                    }
                    break;
                case 'T':
                    if (!dados.transportadora_id) {
                        API.showError('Transportadora é obrigatória');
                        return;
                    }
                    break;
                case 'U':
                    if (!dados.funcionario_id) {
                        API.showError('Funcionário é obrigatório');
                        return;
                    }
                    break;
            }

            let response;
            if (this.state.editandoId) {
                response = await API.put('/pagamento/' + this.state.editandoId, dados);
            } else {
                response = await API.post('/pagamento', dados);
            }

            if (response.sucesso) {
                API.showSuccess(this.state.editandoId ? 'Pagamento atualizado com sucesso' : 'Pagamento cadastrado com sucesso');
                this.fecharModal();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao salvar pagamento');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            API.showError('Erro ao salvar pagamento');
        }
    },

    obterDadosFormulario() {
        const form = this.elements.formPagamento;
        if (!form) return {};

        const dados = {
            descricao: form.querySelector('#inputDescricao')?.value,
            codigo: form.querySelector('#inputCodigo')?.value || null,
            entidade: form.querySelector('#selectEntidadeForm')?.value,
            valor: parseFloat(form.querySelector('#inputValor')?.value) || 0,
            juros: parseFloat(form.querySelector('#inputJuros')?.value) || 0,
            desconto: parseFloat(form.querySelector('#inputDesconto')?.value) || 0,
            taxa_banco: parseFloat(form.querySelector('#inputTaxaBanco')?.value) || 0,
            taxa_operadora: parseFloat(form.querySelector('#inputTaxaOperadora')?.value) || 0,
            valor_total: parseFloat(form.querySelector('#inputValorTotal')?.value) || 0,
            data_vencimento: form.querySelector('#inputDataVencimento')?.value,
            data_liquidacao: form.querySelector('#inputDataLiquidacao')?.value || null,
            data_competencia: form.querySelector('#inputDataCompetencia')?.value || null,
            liquidado: form.querySelector('#checkLiquidado')?.checked ? 1 : 0,
            external_id: form.querySelector('#inputExternalId')?.value || null
        };

        const clienteId = form.querySelector('#selectCliente')?.value;
        const fornecedorId = form.querySelector('#selectFornecedor')?.value;
        const transportadoraId = form.querySelector('#selectTransportadora')?.value;
        const funcionarioId = form.querySelector('#selectFuncionario')?.value;

        if (clienteId) dados.cliente_id = clienteId;
        if (fornecedorId) dados.fornecedor_id = fornecedorId;
        if (transportadoraId) dados.transportadora_id = transportadoraId;
        if (funcionarioId) dados.funcionario_id = funcionarioId;

        const planoContasId = form.querySelector('#selectPlanoContas')?.value;
        const centroCustoId = form.querySelector('#selectCentroCusto')?.value;
        const contaBancariaId = form.querySelector('#selectContaBancaria')?.value;
        const formaPagamentoId = form.querySelector('#selectFormaPagamento')?.value;

        if (planoContasId) dados.plano_contas_id = planoContasId;
        if (centroCustoId) dados.centro_custo_id = centroCustoId;
        if (contaBancariaId) dados.conta_bancaria_id = contaBancariaId;
        if (formaPagamentoId) dados.forma_pagamento_id = formaPagamentoId;

        return dados;
    },

    preencherFormulario(pag) {
        const form = this.elements.formPagamento;
        if (!form) return;

        form.querySelector('#pagamentoId').value = pag.id || '';
        form.querySelector('#inputDescricao').value = pag.descricao || '';
        form.querySelector('#inputCodigo').value = pag.codigo || '';
        form.querySelector('#selectEntidadeForm').value = pag.entidade || '';
        this.mostrarCamposEntidade(pag.entidade);

        form.querySelector('#selectCliente').value = pag.cliente_id || '';
        form.querySelector('#selectFornecedor').value = pag.fornecedor_id || '';
        form.querySelector('#selectTransportadora').value = pag.transportadora_id || '';
        form.querySelector('#selectFuncionario').value = pag.funcionario_id || '';

        form.querySelector('#inputValor').value = pag.valor || '0';
        form.querySelector('#inputJuros').value = pag.juros || '0';
        form.querySelector('#inputDesconto').value = pag.desconto || '0';
        form.querySelector('#inputTaxaBanco').value = pag.taxa_banco || '0';
        form.querySelector('#inputTaxaOperadora').value = pag.taxa_operadora || '0';
        form.querySelector('#inputValorTotal').value = pag.valor_total || '0';

        form.querySelector('#selectPlanoContas').value = pag.plano_contas_id || '';
        form.querySelector('#selectCentroCusto').value = pag.centro_custo_id || '';
        form.querySelector('#selectContaBancaria').value = pag.conta_bancaria_id || '';
        form.querySelector('#selectFormaPagamento').value = pag.forma_pagamento_id || '';

        form.querySelector('#inputDataVencimento').value = pag.data_vencimento || '';
        form.querySelector('#inputDataLiquidacao').value = pag.data_liquidacao || '';
        form.querySelector('#inputDataCompetencia').value = pag.data_competencia || '';
        form.querySelector('#checkLiquidado').checked = pag.liquidado == 1;
        form.querySelector('#inputExternalId').value = pag.external_id || '';
    },

    limparFormulario() {
        const form = this.elements.formPagamento;
        if (!form) return;

        form.querySelector('#pagamentoId').value = '';
        form.querySelector('#inputDescricao').value = '';
        form.querySelector('#inputCodigo').value = '';
        form.querySelector('#selectEntidadeForm').value = '';
        this.mostrarCamposEntidade('');

        form.querySelector('#selectCliente').value = '';
        form.querySelector('#selectFornecedor').value = '';
        form.querySelector('#selectTransportadora').value = '';
        form.querySelector('#selectFuncionario').value = '';

        form.querySelector('#inputValor').value = '0';
        form.querySelector('#inputJuros').value = '0';
        form.querySelector('#inputDesconto').value = '0';
        form.querySelector('#inputTaxaBanco').value = '0';
        form.querySelector('#inputTaxaOperadora').value = '0';
        form.querySelector('#inputValorTotal').value = '0';

        form.querySelector('#selectPlanoContas').value = '';
        form.querySelector('#selectCentroCusto').value = '';
        form.querySelector('#selectContaBancaria').value = '';
        form.querySelector('#selectFormaPagamento').value = '';

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
        this.state.pagamentoParaDeletar = null;
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
    }
};
