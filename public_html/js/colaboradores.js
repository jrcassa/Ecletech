/**
 * Gerenciador de Administradores
 * Implementa CRUD completo com validação de permissões ACL
 */

const AdministradoresManager = {
    // Estado da aplicação
    state: {
        administradores: [],
        niveis: [],
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
        filtoBusca: document.getElementById('filtoBusca'),
        filtroAtivo: document.getElementById('filtroAtivo'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        closeModal: document.getElementById('closeModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        formAdministrador: document.getElementById('formAdministrador'),
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

        await this.carregarNiveis();
        await this.carregarAdministradores();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formAdministrador?.addEventListener('submit', (e) => this.salvar(e));
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());
        this.elements.logoutBtn?.addEventListener('click', async () => {
            if (confirm('Tem certeza que deseja sair?')) {
                await AuthAPI.logout();
            }
        });

        // Filtro em tempo real
        this.elements.filtoBusca?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                this.aplicarFiltros();
            }
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
                    visualizar: permissoes.includes('colaboradores.visualizar'),
                    criar: permissoes.includes('colaboradores.criar'),
                    editar: permissoes.includes('colaboradores.editar'),
                    deletar: permissoes.includes('colaboradores.deletar')
                };

                // Esconde botão novo se não tem permissão de criar
                if (!this.state.permissoes.criar && this.elements.btnNovo) {
                    this.elements.btnNovo.style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
        }
    },

    /**
     * Carrega níveis disponíveis
     */
    async carregarNiveis() {
        try {
            const response = await API.get('/roles?ativo=1');

            if (response.sucesso && response.dados) {
                this.state.niveis = response.dados;
                this.popularSelectNiveis();
            }
        } catch (error) {
            console.error('Erro ao carregar níveis:', error);
        }
    },

    /**
     * Popula o select de níveis
     */
    popularSelectNiveis() {
        const select = document.getElementById('nivelId');
        if (!select) return;

        select.innerHTML = '<option value="">Selecione...</option>';

        this.state.niveis.forEach(nivel => {
            const option = document.createElement('option');
            option.value = nivel.id;
            option.textContent = nivel.nome;
            select.appendChild(option);
        });
    },

    /**
     * Carrega administradores
     */
    async carregarAdministradores() {
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

            const response = await API.get(`/colaboradores?${params.toString()}`);

            if (response.sucesso) {
                this.state.administradores = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar colaboradores';

            this.showError(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar colaboradores:', error);
        }
    },

    /**
     * Renderiza a tabela
     */
    renderizarTabela() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (this.state.administradores.length === 0) {
            this.elements.tableContainer.style.display = 'none';
            this.elements.noData.style.display = 'block';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = '';

        this.state.administradores.forEach(admin => {
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>${admin.id}</td>
                <td>${this.escapeHtml(admin.nome)}</td>
                <td>${this.escapeHtml(admin.email)}</td>
                <td>${this.escapeHtml(admin.nivel_nome || '-')}</td>
                <td>
                    <span class="badge ${admin.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${admin.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>${this.formatarData(admin.criado_em)}</td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ?
                            `<button class="btn btn-small" onclick="AdministradoresManager.editar(${admin.id})">Editar</button>` :
                            ''}
                        ${this.state.permissoes.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="AdministradoresManager.deletar(${admin.id})">Deletar</button>` :
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
        this.state.filtros.busca = this.elements.filtoBusca.value;
        this.state.filtros.ativo = this.elements.filtroAtivo.value;
        this.state.paginacao.pagina = 1;
        this.carregarAdministradores();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarAdministradores();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarAdministradores();
        }
    },

    /**
     * Abre modal para novo administrador
     */
    abrirModalNovo() {
        this.state.editandoId = null;
        this.elements.modalTitle.textContent = 'Novo Colaborador';
        this.elements.formAdministrador.reset();

        // Senha obrigatória para novo
        document.getElementById('senha').required = true;

        this.elements.modalError.style.display = 'none';
        Utils.Errors.limparCampos();
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Edita administrador
     */
    async editar(id) {
        try {
            const response = await API.get(`/colaboradores/${id}`);

            if (response.sucesso && response.dados) {
                const admin = response.dados;
                this.state.editandoId = id;

                this.elements.modalTitle.textContent = 'Editar Colaborador';

                document.getElementById('adminId').value = admin.id;
                document.getElementById('nome').value = admin.nome;
                document.getElementById('email').value = admin.email;
                document.getElementById('nivelId').value = admin.nivel_id;
                document.getElementById('ativo').value = admin.ativo;

                // Senha opcional para edição
                document.getElementById('senha').value = '';
                document.getElementById('senha').required = false;

                this.elements.modalError.style.display = 'none';
                Utils.Errors.limparCampos();
                this.elements.modalForm.classList.add('show');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar colaborador';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar colaborador:', error);
        }
    },

    /**
     * Salva administrador
     */
    async salvar(e) {
        e.preventDefault();

        const dados = {
            nome: document.getElementById('nome').value,
            email: document.getElementById('email').value,
            nivel_id: parseInt(document.getElementById('nivelId').value),
            ativo: parseInt(document.getElementById('ativo').value)
        };

        const senha = document.getElementById('senha').value;
        if (senha) {
            dados.senha = senha;
        }

        try {
            let response;

            if (this.state.editandoId) {
                // Atualizar
                response = await API.put(`/colaboradores/${this.state.editandoId}`, dados);
            } else {
                // Criar
                response = await API.post('/colaboradores', dados);
            }

            if (response.sucesso) {
                this.fecharModal();
                this.carregarAdministradores();
                Utils.Notificacao.sucesso(response.mensagem || 'Colaborador salvo com sucesso!');
            }
        } catch (error) {
            // Exibe mensagem de erro com detalhes de validação
            this.showModalError(error.data || 'Erro ao salvar colaborador');
            Utils.Notificacao.erro(error.data || 'Erro ao salvar colaborador');
            console.error('Erro ao salvar colaborador:', error);
        }
    },

    /**
     * Deleta administrador
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar este colaborador?')) {
            return;
        }

        try {
            const response = await API.delete(`/colaboradores/${id}`);

            if (response.sucesso) {
                this.carregarAdministradores();
                Utils.Notificacao.sucesso(response.mensagem || 'Colaborador deletado com sucesso!');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao deletar colaborador';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao deletar colaborador:', error);
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalForm.classList.remove('show');
        this.elements.formAdministrador.reset();
        this.state.editandoId = null;
        this.elements.modalError.style.display = 'none';
        Utils.Errors.limparCampos();
    },

    /**
     * Mostra loading
     */
    showLoading() {
        this.elements.loadingContainer.style.display = 'flex';
        this.elements.errorContainer.style.display = 'none';
        this.elements.tableContainer.style.display = 'none';
        this.elements.noData.style.display = 'none';
    },

    /**
     * Mostra erro
     */
    showError(message) {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'block';
        this.elements.errorMessage.textContent = message;
        this.elements.tableContainer.style.display = 'none';
        this.elements.noData.style.display = 'none';
    },

    /**
     * Mostra erro no modal usando Utils
     */
    showModalError(error) {
        // Limpa destaques anteriores
        Utils.Errors.limparCampos();

        // Exibe erro usando Utils
        Utils.Errors.exibir(
            this.elements.modalError,
            this.elements.modalErrorMessage,
            error
        );

        // Destaca campos com erro
        if (error && error.erros && typeof error.erros === 'object') {
            // Mapeamento de campos do backend para IDs dos inputs
            const mapeamentoCampos = {
                'nome': 'nome',
                'email': 'email',
                'senha': 'senha',
                'nivel_id': 'nivelId',
                'ativo': 'ativo'
            };

            Utils.Errors.destacarCampos(error.erros, mapeamentoCampos);
        }
    },

    /**
     * Formata data
     */
    formatarData(data) {
        if (!data) return '-';
        const date = new Date(data);
        return date.toLocaleString('pt-BR');
    },

    /**
     * Escape HTML usando Utils
     */
    escapeHtml(text) {
        return Utils.DOM.escapeHtml(text);
    }
};

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    AdministradoresManager.init();
});

// Expõe globalmente para uso nos event handlers inline
window.AdministradoresManager = AdministradoresManager;
