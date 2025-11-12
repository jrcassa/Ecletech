/**
 * Gerenciador de Contas Bancárias
 * Implementa CRUD completo de contas bancárias com validação de permissões ACL
 */

const ContasBancariasManager = {
    // Estado da aplicação
    state: {
        contas: [],
        permissoes: {
            visualizar: false,
            criar: false,
            editar: false,
            deletar: false
        },
        paginacao: {
            pagina: 1,
            porPagina: 20,
            total: 0,
            totalPaginas: 0
        },
        filtros: {
            busca: '',
            tipo_conta: '',
            ativo: '1'
        },
        editandoId: null,
        contaParaDeletar: null
    },

    // Elementos DOM
    elements: {
        tableBody: document.getElementById('tableBody'),
        btnNovo: document.getElementById('btnNovo'),
        inputBusca: document.getElementById('inputBusca'),
        selectTipoConta: document.getElementById('selectTipoConta'),
        selectAtivo: document.getElementById('selectAtivo'),
        btnLimparFiltros: document.getElementById('btnLimparFiltros'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        btnCloseModal: document.getElementById('btnCloseModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        btnSalvar: document.getElementById('btnSalvar'),
        formConta: document.getElementById('formConta'),
        modalConfirm: document.getElementById('modalConfirm'),
        btnConfirmDelete: document.getElementById('btnConfirmDelete'),
        paginationInfo: document.getElementById('paginationInfo'),
        currentPage: document.getElementById('currentPage'),
        totalPages: document.getElementById('totalPages'),
        btnPrevPage: document.getElementById('btnPrevPage'),
        btnNextPage: document.getElementById('btnNextPage'),
        btnToggleSidebar: document.getElementById('btnToggleSidebar'),
        sidebar: document.getElementById('sidebar')
    },

    /**
     * Inicializa o gerenciador
     */
    async init() {
        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            return;
        }

        // Configura event listeners
        this.setupEventListeners();

        // Verifica permissões
        await this.verificarPermissoes();

        // Se não tem permissão de visualizar, exibe mensagem
        if (!this.state.permissoes.visualizar) {
            Utils.showError('Você não tem permissão para visualizar contas bancárias');
            return;
        }

        // Carrega dados iniciais
        await this.carregarDados();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        // Botão novo
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());

        // Filtros
        this.elements.inputBusca?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                this.aplicarFiltros();
            }
        });
        this.elements.selectTipoConta?.addEventListener('change', () => this.aplicarFiltros());
        this.elements.selectAtivo?.addEventListener('change', () => this.aplicarFiltros());
        this.elements.btnLimparFiltros?.addEventListener('click', () => this.limparFiltros());

        // Modal
        this.elements.btnCloseModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.btnSalvar?.addEventListener('click', () => this.salvar());

        // Confirmação de exclusão
        this.elements.btnConfirmDelete?.addEventListener('click', () => this.confirmarDeletar());

        // Paginação
        this.elements.btnPrevPage?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNextPage?.addEventListener('click', () => this.proximaPagina());

        // Toggle sidebar
        this.elements.btnToggleSidebar?.addEventListener('click', () => {
            this.elements.sidebar?.classList.toggle('closed');
        });

        // Fecha modal ao clicar no backdrop
        this.elements.modalForm?.querySelector('.modal-backdrop')?.addEventListener('click', () => {
            this.fecharModal();
        });

        // Máscara de saldo
        this.elements.formConta?.querySelector('#inputSaldoInicial')?.addEventListener('input', (e) => {
            e.target.value = Utils.formatarMoeda(e.target.value);
        });
    },

    /**
     * Verifica permissões do usuário
     */
    async verificarPermissoes() {
        try {
            const permissoes = AuthAPI.getPermissions();

            if (permissoes) {
                this.state.permissoes = {
                    visualizar: permissoes.includes('conta_bancaria.visualizar'),
                    criar: permissoes.includes('conta_bancaria.criar'),
                    editar: permissoes.includes('conta_bancaria.editar'),
                    deletar: permissoes.includes('conta_bancaria.deletar')
                };
            }

            // Esconde botão novo se não tem permissão de criar
            if (!this.state.permissoes.criar && this.elements.btnNovo) {
                this.elements.btnNovo.style.display = 'none';
            }
        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
        }
    },

    /**
     * Carrega os dados das contas bancárias
     */
    async carregarDados() {
        try {
            // Monta query string com filtros e paginação
            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina,
                ...this.state.filtros
            });

            // Remove parâmetros vazios
            for (const [key, value] of [...params.entries()]) {
                if (!value) {
                    params.delete(key);
                }
            }

            const response = await API.get(`/conta-bancaria?${params.toString()}`);

            if (response.sucesso) {
                this.state.contas = response.dados.itens || [];
                this.state.paginacao.total = response.dados.total || 0;
                this.state.paginacao.totalPaginas = response.dados.total_paginas || 1;

                this.renderizarTabela();
                this.atualizarPaginacao();
            } else {
                Utils.showError(response.mensagem || 'Erro ao carregar contas bancárias');
            }
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            Utils.showError('Erro ao carregar contas bancárias');
        }
    },

    /**
     * Renderiza a tabela de contas
     */
    renderizarTabela() {
        if (!this.elements.tableBody) return;

        if (this.state.contas.length === 0) {
            this.elements.tableBody.innerHTML = `
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i>
                        <p style="color: #666;">Nenhuma conta bancária encontrada</p>
                    </td>
                </tr>
            `;
            return;
        }

        this.elements.tableBody.innerHTML = this.state.contas.map(conta => `
            <tr>
                <td>${conta.id}</td>
                <td><strong>${Utils.escapeHtml(conta.nome)}</strong></td>
                <td>
                    ${conta.banco_codigo ? `<span class="badge badge-secondary">${conta.banco_codigo}</span>` : '-'}
                    ${conta.banco_nome ? `<br><small>${Utils.escapeHtml(conta.banco_nome)}</small>` : ''}
                </td>
                <td>${conta.agencia || '-'}${conta.agencia_dv ? '-' + conta.agencia_dv : ''}</td>
                <td>${conta.conta || '-'}${conta.conta_dv ? '-' + conta.conta_dv : ''}</td>
                <td>
                    <span class="badge badge-info">${this.formatarTipoConta(conta.tipo_conta)}</span>
                </td>
                <td style="text-align: right;">
                    ${Utils.formatarValor(conta.saldo_inicial || 0)}
                </td>
                <td>
                    <span class="badge ${conta.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${conta.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="btn-group">
                        ${this.state.permissoes.editar ? `
                            <button class="btn btn-sm btn-primary" onclick="ContasBancariasManager.editar(${conta.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${this.state.permissoes.deletar ? `
                            <button class="btn btn-sm btn-danger" onclick="ContasBancariasManager.deletar(${conta.id})" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    },

    /**
     * Formata o tipo de conta para exibição
     */
    formatarTipoConta(tipo) {
        const tipos = {
            'corrente': 'Corrente',
            'poupanca': 'Poupança',
            'investimento': 'Investimento',
            'outro': 'Outro'
        };
        return tipos[tipo] || tipo;
    },

    /**
     * Atualiza informações de paginação
     */
    atualizarPaginacao() {
        const inicio = (this.state.paginacao.pagina - 1) * this.state.paginacao.porPagina + 1;
        const fim = Math.min(this.state.paginacao.pagina * this.state.paginacao.porPagina, this.state.paginacao.total);

        if (this.elements.paginationInfo) {
            this.elements.paginationInfo.textContent = `Mostrando ${inicio} a ${fim} de ${this.state.paginacao.total} registros`;
        }

        if (this.elements.currentPage) {
            this.elements.currentPage.textContent = this.state.paginacao.pagina;
        }

        if (this.elements.totalPages) {
            this.elements.totalPages.textContent = this.state.paginacao.totalPaginas;
        }

        // Controla botões de navegação
        if (this.elements.btnPrevPage) {
            this.elements.btnPrevPage.disabled = this.state.paginacao.pagina === 1;
        }

        if (this.elements.btnNextPage) {
            this.elements.btnNextPage.disabled = this.state.paginacao.pagina >= this.state.paginacao.totalPaginas;
        }
    },

    /**
     * Aplicar filtros
     */
    aplicarFiltros() {
        this.state.filtros.busca = this.elements.inputBusca?.value || '';
        this.state.filtros.tipo_conta = this.elements.selectTipoConta?.value || '';
        this.state.filtros.ativo = this.elements.selectAtivo?.value || '';
        this.state.paginacao.pagina = 1;
        this.carregarDados();
    },

    /**
     * Limpar filtros
     */
    limparFiltros() {
        if (this.elements.inputBusca) this.elements.inputBusca.value = '';
        if (this.elements.selectTipoConta) this.elements.selectTipoConta.value = '';
        if (this.elements.selectAtivo) this.elements.selectAtivo.value = '1';
        this.aplicarFiltros();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarDados();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarDados();
        }
    },

    /**
     * Abre modal para nova conta
     */
    abrirModalNovo() {
        if (!this.state.permissoes.criar) {
            Utils.showError('Você não tem permissão para criar contas bancárias');
            return;
        }

        this.state.editandoId = null;
        if (this.elements.modalTitle) {
            this.elements.modalTitle.textContent = 'Nova Conta Bancária';
        }
        this.limparFormulario();
        this.abrirModal();
    },

    /**
     * Edita uma conta
     */
    async editar(id) {
        if (!this.state.permissoes.editar) {
            Utils.showError('Você não tem permissão para editar contas bancárias');
            return;
        }

        try {
            const response = await API.get(`/conta-bancaria/${id}`);

            if (response.sucesso && response.dados) {
                this.state.editandoId = id;
                if (this.elements.modalTitle) {
                    this.elements.modalTitle.textContent = 'Editar Conta Bancária';
                }
                this.preencherFormulario(response.dados);
                this.abrirModal();
            } else {
                Utils.showError(response.mensagem || 'Erro ao carregar conta bancária');
            }
        } catch (error) {
            console.error('Erro ao editar:', error);
            Utils.showError('Erro ao carregar conta bancária');
        }
    },

    /**
     * Deleta uma conta
     */
    deletar(id) {
        if (!this.state.permissoes.deletar) {
            Utils.showError('Você não tem permissão para deletar contas bancárias');
            return;
        }

        this.state.contaParaDeletar = id;
        this.abrirModalConfirm();
    },

    /**
     * Confirma a exclusão
     */
    async confirmarDeletar() {
        if (!this.state.contaParaDeletar) return;

        try {
            const response = await API.delete(`/conta-bancaria/${this.state.contaParaDeletar}`);

            if (response.sucesso) {
                Utils.showSuccess('Conta bancária excluída com sucesso');
                this.fecharModalConfirm();
                this.carregarDados();
            } else {
                Utils.showError(response.mensagem || 'Erro ao excluir conta bancária');
            }
        } catch (error) {
            console.error('Erro ao deletar:', error);
            Utils.showError('Erro ao excluir conta bancária');
        }
    },

    /**
     * Salva a conta (cria ou atualiza)
     */
    async salvar() {
        try {
            const dados = this.obterDadosFormulario();

            // Validação básica
            if (!dados.nome) {
                Utils.showError('Nome da conta é obrigatório');
                return;
            }

            let response;
            if (this.state.editandoId) {
                response = await API.put(`/conta-bancaria/${this.state.editandoId}`, dados);
            } else {
                response = await API.post('/conta-bancaria', dados);
            }

            if (response.sucesso) {
                Utils.showSuccess(
                    this.state.editandoId ?
                    'Conta bancária atualizada com sucesso' :
                    'Conta bancária cadastrada com sucesso'
                );
                this.fecharModal();
                this.carregarDados();
            } else {
                Utils.showError(response.mensagem || 'Erro ao salvar conta bancária');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            Utils.showError('Erro ao salvar conta bancária');
        }
    },

    /**
     * Obtém dados do formulário
     */
    obterDadosFormulario() {
        const form = this.elements.formConta;
        if (!form) return {};

        const saldoInicial = form.querySelector('#inputSaldoInicial')?.value || '0';

        return {
            external_id: form.querySelector('#inputExternalId')?.value || null,
            nome: form.querySelector('#inputNome')?.value,
            banco_codigo: form.querySelector('#inputBancoCodigo')?.value || null,
            banco_nome: form.querySelector('#inputBancoNome')?.value || null,
            agencia: form.querySelector('#inputAgencia')?.value || null,
            agencia_dv: form.querySelector('#inputAgenciaDv')?.value || null,
            conta: form.querySelector('#inputConta')?.value || null,
            conta_dv: form.querySelector('#inputContaDv')?.value || null,
            tipo_conta: form.querySelector('#selectTipoContaForm')?.value || 'corrente',
            saldo_inicial: Utils.parseMoeda(saldoInicial),
            observacoes: form.querySelector('#inputObservacoes')?.value || null,
            ativo: form.querySelector('#checkAtivo')?.checked ? 1 : 0
        };
    },

    /**
     * Preenche o formulário com dados da conta
     */
    preencherFormulario(conta) {
        const form = this.elements.formConta;
        if (!form) return;

        form.querySelector('#contaId').value = conta.id || '';
        form.querySelector('#inputExternalId').value = conta.external_id || '';
        form.querySelector('#inputNome').value = conta.nome || '';
        form.querySelector('#inputBancoCodigo').value = conta.banco_codigo || '';
        form.querySelector('#inputBancoNome').value = conta.banco_nome || '';
        form.querySelector('#inputAgencia').value = conta.agencia || '';
        form.querySelector('#inputAgenciaDv').value = conta.agencia_dv || '';
        form.querySelector('#inputConta').value = conta.conta || '';
        form.querySelector('#inputContaDv').value = conta.conta_dv || '';
        form.querySelector('#selectTipoContaForm').value = conta.tipo_conta || 'corrente';
        form.querySelector('#inputSaldoInicial').value = Utils.formatarValor(conta.saldo_inicial || 0);
        form.querySelector('#inputObservacoes').value = conta.observacoes || '';
        form.querySelector('#checkAtivo').checked = conta.ativo == 1;
    },

    /**
     * Limpa o formulário
     */
    limparFormulario() {
        const form = this.elements.formConta;
        if (!form) return;

        form.querySelector('#contaId').value = '';
        form.querySelector('#inputExternalId').value = '';
        form.querySelector('#inputNome').value = '';
        form.querySelector('#inputBancoCodigo').value = '';
        form.querySelector('#inputBancoNome').value = '';
        form.querySelector('#inputAgencia').value = '';
        form.querySelector('#inputAgenciaDv').value = '';
        form.querySelector('#inputConta').value = '';
        form.querySelector('#inputContaDv').value = '';
        form.querySelector('#selectTipoContaForm').value = 'corrente';
        form.querySelector('#inputSaldoInicial').value = '0,00';
        form.querySelector('#inputObservacoes').value = '';
        form.querySelector('#checkAtivo').checked = true;
    },

    /**
     * Abre o modal
     */
    abrirModal() {
        if (this.elements.modalForm) {
            this.elements.modalForm.classList.add('show');
        }
    },

    /**
     * Fecha o modal
     */
    fecharModal() {
        if (this.elements.modalForm) {
            this.elements.modalForm.classList.remove('show');
        }
        this.state.editandoId = null;
    },

    /**
     * Abre modal de confirmação
     */
    abrirModalConfirm() {
        if (this.elements.modalConfirm) {
            this.elements.modalConfirm.classList.add('show');
        }
    },

    /**
     * Fecha modal de confirmação
     */
    fecharModalConfirm() {
        if (this.elements.modalConfirm) {
            this.elements.modalConfirm.classList.remove('show');
        }
        this.state.contaParaDeletar = null;
    }
};
