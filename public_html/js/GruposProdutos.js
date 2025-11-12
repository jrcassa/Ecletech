/**
 * Gerenciador de Grupos de Produtos
 * Implementa CRUD completo de grupos de produtos com validação de permissões ACL
 */

const GruposProdutosManager = {
    // Estado da aplicação
    state: {
        gruposProdutos: [],
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
        formGrupoProdutos: document.getElementById('formGrupoProdutos'),
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

        await this.carregarGruposProdutos();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        // Botão novo
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());

        // Botão filtrar
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());

        // Enter no campo de busca
        this.elements.filtroBusca?.addEventListener('keypress', (e) => {
            if (e.key === 'Enter') {
                this.aplicarFiltros();
            }
        });

        // Fechar modal
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());

        // Submeter formulário
        this.elements.formGrupoProdutos?.addEventListener('submit', (e) => this.salvar(e));

        // Paginação
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());

        // Logout
        this.elements.logoutBtn?.addEventListener('click', () => this.logout());

        // Fechar modal ao clicar fora
        window.addEventListener('click', (e) => {
            if (e.target === this.elements.modalForm) {
                this.fecharModal();
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
                    visualizar: permissoes.includes('grupos_produtos.visualizar'),
                    criar: permissoes.includes('grupos_produtos.criar'),
                    editar: permissoes.includes('grupos_produtos.editar'),
                    deletar: permissoes.includes('grupos_produtos.deletar')
                };
            }

            // Controla visibilidade do botão novo baseado na permissão de criar
            if (this.elements.btnNovo) {
                if (this.state.permissoes.criar) {
                    this.elements.btnNovo.style.display = 'inline-flex';
                } else {
                    this.elements.btnNovo.style.display = 'none';
                }
            }
        } catch (erro) {
            console.error('Erro ao verificar permissões:', erro);
        }
    },

    /**
     * Carrega a lista de grupos de produtos
     */
    async carregarGruposProdutos() {
        try {
            this.exibirCarregando();

            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina,
                ...this.state.filtros
            });

            const response = await API.get(`/grupos-produtos?${params}`);

            this.state.gruposProdutos = response.dados.itens;
            this.state.paginacao.total = response.dados.total;
            this.state.paginacao.totalPaginas = Math.ceil(response.dados.total / this.state.paginacao.porPagina);

            this.renderizarTabela();
            this.renderizarPaginacao();
            this.esconderCarregando();
        } catch (erro) {
            console.error('Erro ao carregar grupos de produtos:', erro);
            this.exibirErro('Erro ao carregar grupos de produtos. ' + (erro.message || ''));
        }
    },

    /**
     * Renderiza a tabela de grupos de produtos
     */
    renderizarTabela() {
        if (this.state.gruposProdutos.length === 0) {
            this.elements.tableContainer.style.display = 'none';
            this.elements.noData.style.display = 'block';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = this.state.gruposProdutos.map(grupo => `
            <tr>
                <td>${this.escaparHtml(grupo.id)}</td>
                <td>${this.escaparHtml(grupo.external_id || '-')}</td>
                <td><strong>${this.escaparHtml(grupo.nome)}</strong></td>
                <td>${this.escaparHtml(grupo.descricao || '-')}</td>
                <td>
                    <span class="badge ${grupo.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${grupo.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>${this.formatarData(grupo.cadastrado_em)}</td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ? `
                            <button class="btn btn-small" onclick="GruposProdutosManager.editar(${grupo.id})" title="Editar">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        ` : ''}
                        ${this.state.permissoes.deletar ? `
                            <button class="btn btn-small btn-danger" onclick="GruposProdutosManager.confirmarDelecao(${grupo.id}, '${this.escaparHtml(grupo.nome)}')" title="Deletar">
                                <i class="fas fa-trash"></i> Deletar
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    },

    /**
     * Renderiza a paginação
     */
    renderizarPaginacao() {
        const inicio = (this.state.paginacao.pagina - 1) * this.state.paginacao.porPagina + 1;
        const fim = Math.min(this.state.paginacao.pagina * this.state.paginacao.porPagina, this.state.paginacao.total);

        this.elements.pageInfo.textContent = `${inicio}-${fim} de ${this.state.paginacao.total}`;

        this.elements.btnPrevious.disabled = this.state.paginacao.pagina === 1;
        this.elements.btnNext.disabled = this.state.paginacao.pagina >= this.state.paginacao.totalPaginas;
    },

    /**
     * Aplica filtros
     */
    aplicarFiltros() {
        this.state.filtros.busca = this.elements.filtroBusca?.value || '';
        this.state.filtros.ativo = this.elements.filtroAtivo?.value || '';
        this.state.paginacao.pagina = 1;
        this.carregarGruposProdutos();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarGruposProdutos();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarGruposProdutos();
        }
    },

    /**
     * Abre modal para novo grupo de produtos
     */
    abrirModalNovo() {
        if (!this.state.permissoes.criar) {
            alert('Você não tem permissão para criar grupos de produtos');
            return;
        }

        this.state.editandoId = null;
        this.elements.modalTitle.textContent = 'Novo Grupo de Produtos';
        this.elements.formGrupoProdutos.reset();
        document.getElementById('grupoProdutosId').value = '';
        document.getElementById('ativo').value = '1';
        this.elements.modalError.style.display = 'none';
        this.elements.modalForm.style.display = 'flex';
    },

    /**
     * Edita um grupo de produtos
     */
    async editar(id) {
        if (!this.state.permissoes.editar) {
            alert('Você não tem permissão para editar grupos de produtos');
            return;
        }

        try {
            this.exibirCarregando();

            const response = await API.get(`/grupos-produtos/${id}`);
            const grupo = response.dados;

            this.state.editandoId = id;
            this.elements.modalTitle.textContent = 'Editar Grupo de Produtos';

            // Preenche o formulário
            document.getElementById('grupoProdutosId').value = grupo.id;
            document.getElementById('externalId').value = grupo.external_id || '';
            document.getElementById('nome').value = grupo.nome;
            document.getElementById('descricao').value = grupo.descricao || '';
            document.getElementById('ativo').value = grupo.ativo;

            this.elements.modalError.style.display = 'none';
            this.elements.modalForm.style.display = 'flex';
            this.esconderCarregando();
        } catch (erro) {
            console.error('Erro ao carregar grupo de produtos:', erro);
            this.exibirErro('Erro ao carregar grupo de produtos. ' + (erro.message || ''));
        }
    },

    /**
     * Salva o grupo de produtos
     */
    async salvar(e) {
        e.preventDefault();

        try {
            const dados = {
                external_id: document.getElementById('externalId').value || null,
                nome: document.getElementById('nome').value,
                descricao: document.getElementById('descricao').value || null,
                ativo: parseInt(document.getElementById('ativo').value)
            };

            if (this.state.editandoId) {
                // Atualizar
                await API.put(`/grupos-produtos/${this.state.editandoId}`, dados);
                this.exibirMensagemSucesso('Grupo de produtos atualizado com sucesso!');
            } else {
                // Criar
                await API.post('/grupos-produtos', dados);
                this.exibirMensagemSucesso('Grupo de produtos cadastrado com sucesso!');
            }

            this.fecharModal();
            await this.carregarGruposProdutos();
        } catch (erro) {
            console.error('Erro ao salvar grupo de produtos:', erro);
            this.exibirErroModal(this.formatarErro(erro));
        }
    },

    /**
     * Confirma a deleção de um grupo de produtos
     */
    confirmarDelecao(id, nome) {
        if (!this.state.permissoes.deletar) {
            alert('Você não tem permissão para deletar grupos de produtos');
            return;
        }

        if (confirm(`Tem certeza que deseja remover o grupo "${nome}"?`)) {
            this.deletar(id);
        }
    },

    /**
     * Deleta um grupo de produtos
     */
    async deletar(id) {
        try {
            this.exibirCarregando();

            await API.delete(`/grupos-produtos/${id}`);

            this.exibirMensagemSucesso('Grupo de produtos removido com sucesso!');
            await this.carregarGruposProdutos();
        } catch (erro) {
            console.error('Erro ao deletar grupo de produtos:', erro);
            this.exibirErro('Erro ao deletar grupo de produtos. ' + (erro.message || ''));
        }
    },

    /**
     * Fecha o modal
     */
    fecharModal() {
        this.elements.modalForm.style.display = 'none';
        this.elements.formGrupoProdutos.reset();
        this.elements.modalError.style.display = 'none';
        this.state.editandoId = null;
    },

    /**
     * Exibe carregando
     */
    exibirCarregando() {
        this.elements.loadingContainer.style.display = 'flex';
        this.elements.errorContainer.style.display = 'none';
    },

    /**
     * Esconde carregando
     */
    esconderCarregando() {
        this.elements.loadingContainer.style.display = 'none';
    },

    /**
     * Exibe erro
     */
    exibirErro(mensagem) {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'block';
        this.elements.errorMessage.textContent = mensagem;
    },

    /**
     * Exibe erro no modal
     */
    exibirErroModal(mensagem) {
        this.elements.modalError.style.display = 'block';
        this.elements.modalErrorMessage.textContent = mensagem;
    },

    /**
     * Exibe mensagem de sucesso
     */
    exibirMensagemSucesso(mensagem) {
        alert(mensagem);
    },

    /**
     * Formata erro da API
     */
    formatarErro(erro) {
        if (erro.data && erro.data.mensagem) {
            return erro.data.mensagem;
        }
        if (erro.message) {
            return erro.message;
        }
        return 'Erro desconhecido';
    },

    /**
     * Formata data
     */
    formatarData(dataString) {
        if (!dataString) return '-';
        const data = new Date(dataString);
        return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR');
    },

    /**
     * Escapa HTML
     */
    escaparHtml(texto) {
        if (texto === null || texto === undefined) return '';
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    },

    /**
     * Logout
     */
    async logout() {
        try {
            await AuthAPI.logout();
            window.location.href = './auth.html';
        } catch (erro) {
            console.error('Erro ao fazer logout:', erro);
            window.location.href = './auth.html';
        }
    }
};

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    GruposProdutosManager.init();
});
