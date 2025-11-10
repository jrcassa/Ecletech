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
        mainContent: document.getElementById('mainContent'),
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
            const response = await API.get('/administradores/permissoes');

            if (response.sucesso && response.dados) {
                this.state.permissoes = response.dados;

                // Mostra/esconde botão de novo baseado na permissão
                if (this.state.permissoes.criar && this.elements.btnNovo) {
                    this.elements.btnNovo.style.display = 'block';
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

            const response = await API.get(`/administradores?${params.toString()}`);

            if (response.sucesso) {
                this.state.administradores = response.dados || [];
                this.state.paginacao.total = response.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            }
        } catch (error) {
            this.showError('Erro ao carregar administradores');
            console.error(error);
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
        this.elements.modalTitle.textContent = 'Novo Administrador';
        this.elements.formAdministrador.reset();

        // Senha obrigatória para novo
        document.getElementById('senha').required = true;

        this.elements.modalError.style.display = 'none';
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Edita administrador
     */
    async editar(id) {
        try {
            const response = await API.get(`/administradores/${id}`);

            if (response.sucesso && response.dados) {
                const admin = response.dados;
                this.state.editandoId = id;

                this.elements.modalTitle.textContent = 'Editar Administrador';

                document.getElementById('adminId').value = admin.id;
                document.getElementById('nome').value = admin.nome;
                document.getElementById('email').value = admin.email;
                document.getElementById('nivelId').value = admin.nivel_id;
                document.getElementById('ativo').value = admin.ativo;

                // Senha opcional para edição
                document.getElementById('senha').value = '';
                document.getElementById('senha').required = false;

                this.elements.modalError.style.display = 'none';
                this.elements.modalForm.classList.add('show');
            }
        } catch (error) {
            alert('Erro ao carregar administrador');
            console.error(error);
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
                response = await API.put(`/administradores/${this.state.editandoId}`, dados);
            } else {
                // Criar
                response = await API.post('/administradores', dados);
            }

            if (response.sucesso) {
                this.fecharModal();
                this.carregarAdministradores();
                alert(response.mensagem || 'Administrador salvo com sucesso!');
            }
        } catch (error) {
            this.showModalError(error.data?.erro || 'Erro ao salvar administrador');
            console.error(error);
        }
    },

    /**
     * Deleta administrador
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar este administrador?')) {
            return;
        }

        try {
            const response = await API.delete(`/administradores/${id}`);

            if (response.sucesso) {
                this.carregarAdministradores();
                alert(response.mensagem || 'Administrador deletado com sucesso!');
            }
        } catch (error) {
            alert(error.data?.erro || 'Erro ao deletar administrador');
            console.error(error);
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
     * Mostra erro no modal
     */
    showModalError(message) {
        this.elements.modalError.style.display = 'block';
        this.elements.modalErrorMessage.textContent = message;
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
     * Escape HTML
     */
    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    AdministradoresManager.init();
});

// Expõe globalmente para uso nos event handlers inline
window.AdministradoresManager = AdministradoresManager;
