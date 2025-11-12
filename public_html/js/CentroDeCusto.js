/**
 * Gerenciador de Centro de Custo
 * Implementa CRUD completo de centros de custo com validação de permissões ACL
 */

const CentroDeCustoManager = {
    // Estado da aplicação
    state: {
        centros: [],
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
            ativo: '1'
        },
        editandoId: null,
        centroParaDeletar: null
    },

    // Elementos DOM
    elements: {
        tableBody: document.getElementById('tableBody'),
        btnNovo: document.getElementById('btnNovo'),
        inputBusca: document.getElementById('inputBusca'),
        selectAtivo: document.getElementById('selectAtivo'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        btnCloseModal: document.getElementById('btnCloseModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        btnSalvar: document.getElementById('btnSalvar'),
        formCentroDeCusto: document.getElementById('formCentroDeCusto'),
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
        pagination: document.getElementById('pagination')
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
            API.showError('Você não tem permissão para visualizar centros de custo');
            return;
        }

        // Mostra o conteúdo da página
        document.getElementById('pageContent').style.display = 'block';

        // Mostra botão de novo se tiver permissão
        if (this.state.permissoes.criar && this.elements.btnNovo) {
            this.elements.btnNovo.style.display = 'inline-flex';
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
                    visualizar: permissoes.includes('centro_de_custo.visualizar'),
                    criar: permissoes.includes('centro_de_custo.criar'),
                    editar: permissoes.includes('centro_de_custo.editar'),
                    deletar: permissoes.includes('centro_de_custo.deletar')
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
     * Carrega os dados dos centros de custo
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

            const response = await API.get(`/centro-de-custo?${params.toString()}`);

            if (response.sucesso) {
                this.state.centros = response.dados.itens || [];
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
                this.elements.errorMessage.textContent = response.mensagem || 'Erro ao carregar centros de custo';
            }
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            this.elements.loadingContainer.style.display = 'none';
            this.elements.errorContainer.style.display = 'block';
            this.elements.errorMessage.textContent = 'Erro ao carregar centros de custo';
        }
    },

    /**
     * Renderiza a tabela de centros de custo
     */
    renderizarTabela() {
        if (!this.elements.tableBody) return;

        if (this.state.centros.length === 0) {
            this.elements.tableBody.innerHTML = `
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i>
                        <p style="color: #666;">Nenhum centro de custo encontrado</p>
                    </td>
                </tr>
            `;
            return;
        }

        this.elements.tableBody.innerHTML = this.state.centros.map(centro => `
            <tr>
                <td>${centro.id}</td>
                <td><strong>${this.escapeHtml(centro.nome)}</strong></td>
                <td>${this.escapeHtml(centro.external_id || '-')}</td>
                <td style="text-align: center;">
                    <span class="badge ${centro.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${centro.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div class="btn-group">
                        ${this.state.permissoes.editar ? `
                            <button class="btn btn-sm btn-primary" onclick="CentroDeCustoManager.editar(${centro.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${this.state.permissoes.deletar ? `
                            <button class="btn btn-sm btn-danger" onclick="CentroDeCustoManager.deletar(${centro.id})" title="Excluir">
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
     * Abre modal para novo centro de custo
     */
    abrirModalNovo() {
        if (!this.state.permissoes.criar) {
            API.showError('Você não tem permissão para criar centros de custo');
            return;
        }

        this.state.editandoId = null;
        if (this.elements.modalTitle) {
            this.elements.modalTitle.textContent = 'Novo Centro de Custo';
        }
        this.limparFormulario();
        this.abrirModal();
    },

    /**
     * Edita um centro de custo
     */
    async editar(id) {
        if (!this.state.permissoes.editar) {
            API.showError('Você não tem permissão para editar centros de custo');
            return;
        }

        try {
            const response = await API.get(`/centro-de-custo/${id}`);

            if (response.sucesso && response.dados) {
                this.state.editandoId = id;
                if (this.elements.modalTitle) {
                    this.elements.modalTitle.textContent = 'Editar Centro de Custo';
                }
                this.preencherFormulario(response.dados);
                this.abrirModal();
            } else {
                API.showError(response.mensagem || 'Erro ao carregar centro de custo');
            }
        } catch (error) {
            console.error('Erro ao editar:', error);
            API.showError('Erro ao carregar centro de custo');
        }
    },

    /**
     * Deleta um centro de custo
     */
    deletar(id) {
        if (!this.state.permissoes.deletar) {
            API.showError('Você não tem permissão para deletar centros de custo');
            return;
        }

        this.state.centroParaDeletar = id;
        this.abrirModalConfirm();
    },

    /**
     * Confirma a exclusão
     */
    async confirmarDeletar() {
        if (!this.state.centroParaDeletar) return;

        try {
            const response = await API.delete(`/centro-de-custo/${this.state.centroParaDeletar}`);

            if (response.sucesso) {
                API.showSuccess('Centro de custo excluído com sucesso');
                this.fecharModalConfirm();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao excluir centro de custo');
            }
        } catch (error) {
            console.error('Erro ao deletar:', error);
            API.showError('Erro ao excluir centro de custo');
        }
    },

    /**
     * Salva o centro de custo (cria ou atualiza)
     */
    async salvar() {
        try {
            const dados = this.obterDadosFormulario();

            // Validação básica
            if (!dados.nome) {
                API.showError('Nome é obrigatório');
                return;
            }

            let response;
            if (this.state.editandoId) {
                response = await API.put(`/centro-de-custo/${this.state.editandoId}`, dados);
            } else {
                response = await API.post('/centro-de-custo', dados);
            }

            if (response.sucesso) {
                API.showSuccess(
                    this.state.editandoId ?
                    'Centro de custo atualizado com sucesso' :
                    'Centro de custo cadastrado com sucesso'
                );
                this.fecharModal();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao salvar centro de custo');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            API.showError('Erro ao salvar centro de custo');
        }
    },

    /**
     * Obtém dados do formulário
     */
    obterDadosFormulario() {
        const form = this.elements.formCentroDeCusto;
        if (!form) return {};

        return {
            nome: form.querySelector('#inputNome')?.value,
            external_id: form.querySelector('#inputExternalId')?.value || null,
            observacoes: form.querySelector('#inputObservacoes')?.value || null,
            ativo: form.querySelector('#checkAtivo')?.checked ? 1 : 0
        };
    },

    /**
     * Preenche o formulário com dados do centro de custo
     */
    preencherFormulario(centro) {
        const form = this.elements.formCentroDeCusto;
        if (!form) return;

        form.querySelector('#centroId').value = centro.id || '';
        form.querySelector('#inputNome').value = centro.nome || '';
        form.querySelector('#inputExternalId').value = centro.external_id || '';
        form.querySelector('#inputObservacoes').value = centro.observacoes || '';
        form.querySelector('#checkAtivo').checked = centro.ativo == 1;
    },

    /**
     * Limpa o formulário
     */
    limparFormulario() {
        const form = this.elements.formCentroDeCusto;
        if (!form) return;

        form.querySelector('#centroId').value = '';
        form.querySelector('#inputNome').value = '';
        form.querySelector('#inputExternalId').value = '';
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
        this.state.centroParaDeletar = null;
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
