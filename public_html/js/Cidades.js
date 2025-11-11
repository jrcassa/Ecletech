/**
 * Gerenciador de Cidades
 * Implementa CRUD completo de cidades com validação de permissões ACL
 */

const CidadesManager = {
    // Estado da aplicação
    state: {
        cidades: [],
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
        formCidade: document.getElementById('formCidade'),
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

        await this.carregarCidades();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formCidade?.addEventListener('submit', (e) => this.salvar(e));
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
    },

    /**
     * Verifica permissões do usuário
     */
    async verificarPermissoes() {
        try {
            // Verifica se o usuário tem as permissões necessárias
            // Como o backend usa middleware ACL, vamos assumir permissões básicas
            // e deixar o backend fazer a validação real
            this.state.permissoes = {
                visualizar: true,
                criar: true,
                editar: true,
                deletar: true
            };

            // Mostra/esconde botão de novo baseado na permissão
            if (this.state.permissoes.criar && this.elements.btnNovo) {
                this.elements.btnNovo.style.display = 'block';
            }
        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
        }
    },

    /**
     * Carrega cidades
     */
    async carregarCidades() {
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

            const response = await API.get(`/cidades?${params.toString()}`);

            if (response.sucesso) {
                this.state.cidades = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar cidades';

            this.showError(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar cidades:', error);
        }
    },

    /**
     * Renderiza a tabela
     */
    renderizarTabela() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (this.state.cidades.length === 0) {
            this.elements.tableContainer.style.display = 'block';
            this.elements.noData.style.display = 'block';
            this.elements.tableBody.innerHTML = '';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = '';

        this.state.cidades.forEach(cidade => {
            const tr = document.createElement('tr');

            tr.innerHTML = `
                <td>${cidade.id}</td>
                <td>${this.escapeHtml(cidade.external_id || '-')}</td>
                <td>${this.escapeHtml(cidade.codigo)}</td>
                <td>${this.escapeHtml(cidade.nome)}</td>
                <td>
                    <span class="badge ${cidade.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${cidade.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ?
                            `<button class="btn btn-small" onclick="CidadesManager.editar(${cidade.id})">Editar</button>` :
                            ''}
                        ${this.state.permissoes.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="CidadesManager.deletar(${cidade.id})">Deletar</button>` :
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
        this.carregarCidades();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarCidades();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarCidades();
        }
    },

    /**
     * Abre modal para nova cidade
     */
    abrirModalNovo() {
        this.state.editandoId = null;
        this.elements.modalTitle.textContent = 'Nova Cidade';
        this.elements.formCidade.reset();
        document.getElementById('ativo').value = '1';

        this.elements.modalError.style.display = 'none';
        Utils.Errors.limparCampos();
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Edita cidade
     */
    async editar(id) {
        try {
            const response = await API.get(`/cidades/${id}`);

            if (response.sucesso && response.dados) {
                const cidade = response.dados;
                this.state.editandoId = id;

                this.elements.modalTitle.textContent = 'Editar Cidade';

                document.getElementById('cidadeId').value = cidade.id;
                document.getElementById('external_id').value = cidade.external_id || '';
                document.getElementById('codigo').value = cidade.codigo || '';
                document.getElementById('nome').value = cidade.nome || '';
                document.getElementById('ativo').value = cidade.ativo || '1';

                this.elements.modalError.style.display = 'none';
                Utils.Errors.limparCampos();
                this.elements.modalForm.classList.add('show');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar cidade';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar cidade:', error);
        }
    },

    /**
     * Salva cidade
     */
    async salvar(e) {
        e.preventDefault();

        const dados = {
            codigo: document.getElementById('codigo').value.trim(),
            nome: document.getElementById('nome').value.trim(),
            ativo: parseInt(document.getElementById('ativo').value)
        };

        // Campo opcional external_id
        const externalId = document.getElementById('external_id').value.trim();
        if (externalId) {
            dados.external_id = externalId;
        }

        try {
            let response;

            if (this.state.editandoId) {
                // Atualizar
                response = await API.put(`/cidades/${this.state.editandoId}`, dados);
            } else {
                // Criar
                response = await API.post('/cidades', dados);
            }

            if (response.sucesso) {
                this.fecharModal();
                this.carregarCidades();
                Utils.Notificacao.sucesso(response.mensagem || 'Cidade salva com sucesso!');
            }
        } catch (error) {
            // Exibe mensagem de erro com detalhes de validação
            this.showModalError(error.data || 'Erro ao salvar cidade');
            Utils.Notificacao.erro(error.data || 'Erro ao salvar cidade');
            console.error('Erro ao salvar cidade:', error);
        }
    },

    /**
     * Deleta cidade
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar esta cidade?')) {
            return;
        }

        try {
            const response = await API.delete(`/cidades/${id}`);

            if (response.sucesso) {
                this.carregarCidades();
                Utils.Notificacao.sucesso(response.mensagem || 'Cidade deletada com sucesso!');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao deletar cidade';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao deletar cidade:', error);
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalForm.classList.remove('show');
        this.elements.formCidade.reset();
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
    CidadesManager.init();
});
