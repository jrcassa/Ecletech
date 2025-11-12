/**
 * Gerenciador de Plano de Contas
 * Implementa CRUD completo de plano de contas com validação de permissões ACL
 */

const PlanoDeContasManager = {
    // Estado da aplicação
    state: {
        contas: [],
        contasPrincipais: [],
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
            tipo: '',
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
        selectTipo: document.getElementById('selectTipo'),
        selectAtivo: document.getElementById('selectAtivo'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        btnCloseModal: document.getElementById('btnCloseModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        btnSalvar: document.getElementById('btnSalvar'),
        formConta: document.getElementById('formConta'),
        modalConfirm: document.getElementById('modalConfirm'),
        btnConfirmDelete: document.getElementById('btnConfirmDelete'),
        btnFiltrar: document.getElementById('btnFiltrar'),
        btnPrevious: document.getElementById('btnPrevious'),
        btnNext: document.getElementById('btnNext'),
        pageInfo: document.getElementById('pageInfo'),
        loadingContainer: document.getElementById('loadingContainer'),
        errorContainer: document.getElementById('errorContainer'),
        errorMessage: document.getElementById('errorMessage'),
        tableContainer: document.getElementById('tableContainer'),
        noData: document.getElementById('noData'),
        pagination: document.getElementById('pagination'),
        selectContaMae: document.getElementById('selectContaMae')
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
            document.getElementById('permissionDenied').style.display = 'block';
            API.showError('Você não tem permissão para visualizar o plano de contas');
            return;
        }

        // Mostra o conteúdo da página
        document.getElementById('pageContent').style.display = 'block';

        // Mostra botão de novo se tiver permissão
        if (this.state.permissoes.criar && this.elements.btnNovo) {
            this.elements.btnNovo.style.display = 'inline-flex';
        }

        // Carrega contas principais para o select
        await this.carregarContasPrincipais();

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
        this.elements.selectTipo?.addEventListener('change', () => this.aplicarFiltros());
        this.elements.selectAtivo?.addEventListener('change', () => this.aplicarFiltros());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());

        // Modal
        this.elements.btnCloseModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.btnSalvar?.addEventListener('click', () => this.salvar());

        // Confirmação de exclusão
        this.elements.btnConfirmDelete?.addEventListener('click', () => this.confirmarDeletar());

        // Paginação
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());

        // Fecha modal ao clicar no backdrop
        this.elements.modalForm?.querySelector('.modal-backdrop')?.addEventListener('click', () => {
            this.fecharModal();
        });
    },

    /**
     * Verifica permissões do usuário
     */
    async verificarPermissoes() {
        try {
            // Aguarda as permissões serem carregadas pelo sidebar
            const permissoes = await aguardarPermissoes();

            if (permissoes) {
                this.state.permissoes = {
                    visualizar: permissoes.includes('plano_de_contas.visualizar'),
                    criar: permissoes.includes('plano_de_contas.criar'),
                    editar: permissoes.includes('plano_de_contas.editar'),
                    deletar: permissoes.includes('plano_de_contas.deletar')
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
     * Carrega as contas principais para o select
     */
    async carregarContasPrincipais() {
        try {
            const response = await API.get('/plano-de-contas/principais');

            if (response.sucesso && response.dados) {
                this.state.contasPrincipais = response.dados;
                this.popularSelectContaMae();
            }
        } catch (error) {
            console.error('Erro ao carregar contas principais:', error);
        }
    },

    /**
     * Popula o select de contas mãe
     */
    popularSelectContaMae() {
        if (!this.elements.selectContaMae) return;

        // Limpa o select mantendo a opção vazia
        this.elements.selectContaMae.innerHTML = '<option value="">Nenhuma (Conta Principal)</option>';

        // Adiciona as contas principais
        this.state.contasPrincipais.forEach(conta => {
            const option = document.createElement('option');
            option.value = conta.id;
            option.textContent = `${conta.classificacao} - ${conta.nome}`;
            this.elements.selectContaMae.appendChild(option);
        });
    },

    /**
     * Carrega os dados das contas
     */
    async carregarDados() {
        try {
            // Mostra loading
            this.elements.loadingContainer.style.display = 'block';
            this.elements.errorContainer.style.display = 'none';
            this.elements.tableContainer.style.display = 'none';

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

            const response = await API.get(`/plano-de-contas?${params.toString()}`);

            if (response.sucesso) {
                this.state.contas = response.dados.itens || [];
                this.state.paginacao.total = response.dados.total || 0;
                this.state.paginacao.totalPaginas = response.dados.total_paginas || 1;

                // Esconde loading e mostra tabela
                this.elements.loadingContainer.style.display = 'none';
                this.elements.tableContainer.style.display = 'block';

                this.renderizarTabela();
                this.atualizarPaginacao();
            } else {
                this.elements.loadingContainer.style.display = 'none';
                this.elements.errorContainer.style.display = 'block';
                this.elements.errorMessage.textContent = response.mensagem || 'Erro ao carregar plano de contas';
            }
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            this.elements.loadingContainer.style.display = 'none';
            this.elements.errorContainer.style.display = 'block';
            this.elements.errorMessage.textContent = 'Erro ao carregar plano de contas';
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
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i>
                        <p style="color: #666;">Nenhuma conta encontrada</p>
                    </td>
                </tr>
            `;
            return;
        }

        this.elements.tableBody.innerHTML = this.state.contas.map(conta => `
            <tr>
                <td>${conta.id}</td>
                <td><strong>${this.escapeHtml(conta.classificacao || '-')}</strong></td>
                <td>${this.escapeHtml(conta.nome)}</td>
                <td>
                    <span class="badge ${conta.tipo === 'D' ? 'badge-danger' : 'badge-success'}">
                        ${conta.tipo === 'D' ? 'Débito' : 'Crédito'}
                    </span>
                </td>
                <td>${this.escapeHtml(conta.nome_tipo || '-')}</td>
                <td>
                    ${conta.nome_conta_mae ?
                        `<small>${this.escapeHtml(conta.nome_conta_mae)}</small>` :
                        '<span class="badge badge-secondary">Principal</span>'}
                </td>
                <td style="text-align: center;">
                    <span class="badge ${conta.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${conta.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div class="btn-group">
                        ${this.state.permissoes.editar ? `
                            <button class="btn btn-sm btn-primary" onclick="PlanoDeContasManager.editar(${conta.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${this.state.permissoes.deletar ? `
                            <button class="btn btn-sm btn-danger" onclick="PlanoDeContasManager.deletar(${conta.id})" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    },

    /**
     * Atualiza informações de paginação
     */
    atualizarPaginacao() {
        if (this.elements.pageInfo) {
            this.elements.pageInfo.textContent = `Página ${this.state.paginacao.pagina} de ${this.state.paginacao.totalPaginas}`;
        }

        // Controla botões de navegação
        if (this.elements.btnPrevious) {
            this.elements.btnPrevious.disabled = this.state.paginacao.pagina === 1;
        }

        if (this.elements.btnNext) {
            this.elements.btnNext.disabled = this.state.paginacao.pagina >= this.state.paginacao.totalPaginas;
        }
    },

    /**
     * Aplicar filtros
     */
    aplicarFiltros() {
        this.state.filtros.busca = this.elements.inputBusca?.value || '';
        this.state.filtros.tipo = this.elements.selectTipo?.value || '';
        this.state.filtros.ativo = this.elements.selectAtivo?.value || '';
        this.state.paginacao.pagina = 1;
        this.carregarDados();
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
            API.showError('Você não tem permissão para criar contas');
            return;
        }

        this.state.editandoId = null;
        if (this.elements.modalTitle) {
            this.elements.modalTitle.textContent = 'Nova Conta';
        }
        this.limparFormulario();
        this.abrirModal();
    },

    /**
     * Edita uma conta
     */
    async editar(id) {
        if (!this.state.permissoes.editar) {
            API.showError('Você não tem permissão para editar contas');
            return;
        }

        try {
            const response = await API.get(`/plano-de-contas/${id}`);

            if (response.sucesso && response.dados) {
                this.state.editandoId = id;
                if (this.elements.modalTitle) {
                    this.elements.modalTitle.textContent = 'Editar Conta';
                }
                this.preencherFormulario(response.dados);
                this.abrirModal();
            } else {
                API.showError(response.mensagem || 'Erro ao carregar conta');
            }
        } catch (error) {
            console.error('Erro ao editar:', error);
            API.showError('Erro ao carregar conta');
        }
    },

    /**
     * Deleta uma conta
     */
    deletar(id) {
        if (!this.state.permissoes.deletar) {
            API.showError('Você não tem permissão para deletar contas');
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
            const response = await API.delete(`/plano-de-contas/${this.state.contaParaDeletar}`);

            if (response.sucesso) {
                API.showSuccess('Conta excluída com sucesso');
                this.fecharModalConfirm();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao excluir conta');
            }
        } catch (error) {
            console.error('Erro ao deletar:', error);
            API.showError('Erro ao excluir conta');
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
                API.showError('Nome da conta é obrigatório');
                return;
            }

            if (!dados.tipo) {
                API.showError('Tipo é obrigatório');
                return;
            }

            if (!dados.classificacao) {
                API.showError('Classificação é obrigatória');
                return;
            }

            // Valida formato da classificação
            if (!/^\d+(\.\d+)*$/.test(dados.classificacao)) {
                API.showError('Classificação deve estar no formato correto (ex: 1.1.1)');
                return;
            }

            let response;
            if (this.state.editandoId) {
                response = await API.put(`/plano-de-contas/${this.state.editandoId}`, dados);
            } else {
                response = await API.post('/plano-de-contas', dados);
            }

            if (response.sucesso) {
                API.showSuccess(
                    this.state.editandoId ?
                    'Conta atualizada com sucesso' :
                    'Conta cadastrada com sucesso'
                );
                this.fecharModal();
                this.carregarContasPrincipais();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao salvar conta');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            API.showError('Erro ao salvar conta');
        }
    },

    /**
     * Obtém dados do formulário
     */
    obterDadosFormulario() {
        const form = this.elements.formConta;
        if (!form) return {};

        const contaMaeId = form.querySelector('#selectContaMae')?.value;

        return {
            external_id: form.querySelector('#inputExternalId')?.value || null,
            nome: form.querySelector('#inputNome')?.value,
            classificacao: form.querySelector('#inputClassificacao')?.value,
            tipo: form.querySelector('#selectTipoForm')?.value,
            nome_tipo: form.querySelector('#inputNomeTipo')?.value || null,
            conta_mae_id: contaMaeId ? parseInt(contaMaeId) : null,
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
        form.querySelector('#inputClassificacao').value = conta.classificacao || '';
        form.querySelector('#selectTipoForm').value = conta.tipo || '';
        form.querySelector('#inputNomeTipo').value = conta.nome_tipo || '';
        form.querySelector('#selectContaMae').value = conta.conta_mae_id || '';
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
        form.querySelector('#inputClassificacao').value = '';
        form.querySelector('#selectTipoForm').value = '';
        form.querySelector('#inputNomeTipo').value = '';
        form.querySelector('#selectContaMae').value = '';
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
    },

    /**
     * Escapa HTML para prevenir XSS
     */
    escapeHtml(text) {
        if (text === null || text === undefined) return '';

        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
