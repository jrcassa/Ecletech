/**
 * Gerenciador de Estados
 * Implementa CRUD completo de estados com validação de permissões ACL
 */

const EstadosManager = {
    // Estado da aplicação
    state: {
        estados: [],
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
        editandoId: null
    },

    // Elementos DOM
    elements: {
        permissionDenied: document.getElementById('permissionDenied'),
        mainContent: document.getElementById('pageContent'),
        loadingContainer: document.getElementById('loadingContainer'),
        errorContainer: document.getElementById('errorContainer'),
        errorMessage: document.getElementById('errorMessage'),
        tableContainer: document.getElementById('tableContainer'),
        tableBody: document.getElementById('tableBody'),
        noData: document.getElementById('noData'),
        btnNovo: document.getElementById('btnNovo'),
        btnFiltrar: document.getElementById('btnFiltrar'),
        filtroBusca: document.getElementById('filtroBusca'),
        filtroAtivo: document.getElementById('filtroAtivo'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        closeModal: document.getElementById('closeModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        formEstado: document.getElementById('formEstado'),
        modalError: document.getElementById('modalError'),
        modalErrorMessage: document.getElementById('modalErrorMessage'),
        pagination: document.getElementById('pagination'),
        btnPrevious: document.getElementById('btnPrevious'),
        btnNext: document.getElementById('btnNext'),
        pageInfo: document.getElementById('pageInfo'),
        logoutBtn: document.getElementById('logoutBtn')
    },

    /**
     * Inicializa o gerenciador
     */
    async init() {
        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            window.location.href = './auth.html';
            return;
        }

        // Configura event listeners
        this.setupEventListeners();

        // Verifica permissões
        await this.verificarPermissoes();

        // Se não tem permissão de visualizar, exibe mensagem
        if (!this.state.permissoes.visualizar) {
            this.elements.permissionDenied.style.display = 'block';
            this.elements.mainContent.style.display = 'none';
            this.elements.loadingContainer.style.display = 'none';
            return;
        }

        // Carrega dados iniciais
        this.elements.permissionDenied.style.display = 'none';
        this.elements.mainContent.style.display = 'block';

        await this.carregarEstados();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formEstado?.addEventListener('submit', (e) => this.salvar(e));
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());
        this.elements.logoutBtn?.addEventListener('click', async () => {
            if (confirm('Tem certeza que deseja sair?')) {
                await AuthAPI.logout();
            }
        });

        // Filtro em tempo real
        this.elements.filtroBusca?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                this.aplicarFiltros();
            }
        });

        // Converte sigla para maiúscula automaticamente
        const siglaInput = document.getElementById('sigla');
        if (siglaInput) {
            siglaInput.addEventListener('input', (e) => {
                e.target.value = e.target.value.toUpperCase();
            });
        }
    },

    /**
     * Verifica permissões do usuário
     */
    async verificarPermissoes() {
        try {
            const permissoes = window.permissoesUsuario;

            if (permissoes) {
                this.state.permissoes = {
                    visualizar: permissoes.includes('estados.visualizar'),
                    criar: permissoes.includes('estados.criar'),
                    editar: permissoes.includes('estados.editar'),
                    deletar: permissoes.includes('estados.deletar')
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
     * Carrega estados
     */
    async carregarEstados() {
        this.showLoading();

        try {
            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina
            });

            if (this.state.filtros.busca) {
                params.append('busca', this.state.filtros.busca);
            }

            if (this.state.filtros.ativo !== '') {
                params.append('ativo', this.state.filtros.ativo);
            }

            const response = await API.get(`/estados?${params.toString()}`);

            if (response.sucesso) {
                this.state.estados = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar estados';

            this.showError(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar estados:', error);
        }
    },

    /**
     * Renderiza a tabela
     */
    renderizarTabela() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (this.state.estados.length === 0) {
            this.elements.tableContainer.style.display = 'block';
            this.elements.noData.style.display = 'block';
            this.elements.tableBody.innerHTML = '';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = '';

        this.state.estados.forEach(estado => {
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>${estado.id}</td>
                <td>${this.escapeHtml(estado.external_id || '-')}</td>
                <td>${this.escapeHtml(estado.codigo)}</td>
                <td>${this.escapeHtml(estado.sigla)}</td>
                <td>${this.escapeHtml(estado.nome)}</td>
                <td>
                    <span class="badge ${estado.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${estado.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ?
                            `<button class="btn btn-small" onclick="EstadosManager.editar(${estado.id})">Editar</button>` :
                            ''}
                        ${this.state.permissoes.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="EstadosManager.deletar(${estado.id})">Deletar</button>` :
                            ''}
                    </div>
                </td>
            `;

            this.elements.tableBody.appendChild(tr);
        });
    },

    /**
     * Atualiza paginação
     */
    atualizarPaginacao() {
        const inicio = (this.state.paginacao.pagina - 1) * this.state.paginacao.porPagina + 1;
        const fim = Math.min(
            this.state.paginacao.pagina * this.state.paginacao.porPagina,
            this.state.paginacao.total
        );

        this.elements.pageInfo.textContent =
            `${inicio}-${fim} de ${this.state.paginacao.total}`;

        this.elements.btnPrevious.disabled = this.state.paginacao.pagina <= 1;
        this.elements.btnNext.disabled =
            this.state.paginacao.pagina >= this.state.paginacao.totalPaginas;
    },

    /**
     * Aplica filtros
     */
    aplicarFiltros() {
        this.state.filtros.busca = this.elements.filtroBusca.value;
        this.state.filtros.ativo = this.elements.filtroAtivo.value;
        this.state.paginacao.pagina = 1;
        this.carregarEstados();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarEstados();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarEstados();
        }
    },

    /**
     * Abre modal para novo estado
     */
    abrirModalNovo() {
        this.state.editandoId = null;
        this.elements.modalTitle.textContent = 'Novo Estado';
        this.elements.formEstado.reset();
        document.getElementById('ativo').value = '1';

        this.elements.modalError.style.display = 'none';
        Utils.Errors.limparCampos();
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Edita estado
     */
    async editar(id) {
        try {
            const response = await API.get(`/estados/${id}`);

            if (response.sucesso && response.dados) {
                const estado = response.dados;
                this.state.editandoId = id;

                this.elements.modalTitle.textContent = 'Editar Estado';

                document.getElementById('estadoId').value = estado.id;
                document.getElementById('external_id').value = estado.external_id || '';
                document.getElementById('codigo').value = estado.codigo || '';
                document.getElementById('sigla').value = estado.sigla || '';
                document.getElementById('nome').value = estado.nome || '';
                document.getElementById('ativo').value = estado.ativo || '1';

                this.elements.modalError.style.display = 'none';
                Utils.Errors.limparCampos();
                this.elements.modalForm.classList.add('show');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar estado';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar estado:', error);
        }
    },

    /**
     * Salva estado
     */
    async salvar(e) {
        e.preventDefault();

        const dados = {
            codigo: document.getElementById('codigo').value.trim(),
            sigla: document.getElementById('sigla').value.trim().toUpperCase(),
            nome: document.getElementById('nome').value.trim(),
            ativo: parseInt(document.getElementById('ativo').value)
        };

        // Campo opcional external_id
        const externalId = document.getElementById('external_id').value.trim();
        if (externalId) {
            dados.external_id = externalId;
        }

        // Validação da sigla
        if (dados.sigla.length !== 2) {
            this.showModalError('A sigla deve ter exatamente 2 caracteres');
            Utils.Notificacao.erro('A sigla deve ter exatamente 2 caracteres');
            return;
        }

        try {
            let response;

            if (this.state.editandoId) {
                // Atualizar
                response = await API.put(`/estados/${this.state.editandoId}`, dados);
            } else {
                // Criar
                response = await API.post('/estados', dados);
            }

            if (response.sucesso) {
                this.fecharModal();
                this.carregarEstados();
                Utils.Notificacao.sucesso(response.mensagem || 'Estado salvo com sucesso!');
            }
        } catch (error) {
            // Exibe mensagem de erro com detalhes de validação
            this.showModalError(error.data || 'Erro ao salvar estado');
            Utils.Notificacao.erro(error.data || 'Erro ao salvar estado');
            console.error('Erro ao salvar estado:', error);
        }
    },

    /**
     * Deleta estado
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar este estado?')) {
            return;
        }

        try {
            const response = await API.delete(`/estados/${id}`);

            if (response.sucesso) {
                this.carregarEstados();
                Utils.Notificacao.sucesso(response.mensagem || 'Estado deletado com sucesso!');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao deletar estado';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao deletar estado:', error);
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalForm.classList.remove('show');
        this.elements.formEstado.reset();
        this.elements.modalError.style.display = 'none';
        Utils.Errors.limparCampos();
        this.state.editandoId = null;
    },

    /**
     * Exibe loading
     */
    showLoading() {
        this.elements.loadingContainer.style.display = 'flex';
        this.elements.errorContainer.style.display = 'none';
        this.elements.tableContainer.style.display = 'none';
    },

    /**
     * Exibe erro
     */
    showError(mensagem) {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.tableContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'block';
        this.elements.errorMessage.textContent = Utils.Errors.formatarMensagem(mensagem);
    },

    /**
     * Exibe erro no modal
     */
    showModalError(mensagem) {
        this.elements.modalError.style.display = 'block';
        this.elements.modalErrorMessage.textContent = Utils.Errors.formatarMensagem(mensagem);
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

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    EstadosManager.init();
});
