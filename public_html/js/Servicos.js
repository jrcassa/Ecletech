/**
 * Gerenciador de Serviços
 * Implementa CRUD completo de serviços com validação de permissões ACL
 */

const ServicosManager = {
    // Estado da aplicação
    state: {
        servicos: [],
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
            ativo: ''
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
        formServico: document.getElementById('formServico'),
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

        await this.carregarServicos();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formServico?.addEventListener('submit', (e) => this.salvar(e));
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
            // Aguarda as permissões serem carregadas pelo sidebar
            const permissoes = await aguardarPermissoes();

            if (permissoes) {
                this.state.permissoes = {
                    visualizar: permissoes.includes('servicos.visualizar'),
                    criar: permissoes.includes('servicos.criar'),
                    editar: permissoes.includes('servicos.editar'),
                    deletar: permissoes.includes('servicos.deletar')
                };
            }

            // Esconde botão novo se não tem permissão de criar
            if (!this.state.permissoes.criar && this.elements.btnNovo) {
                this.elements.btnNovo.style.display = 'none';
            } else if (this.elements.btnNovo) {
                this.elements.btnNovo.style.display = 'inline-flex';
            }
        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
        }
    },

    /**
     * Carrega serviços
     */
    async carregarServicos() {
        try {
            this.mostrarLoading();
            this.esconderErro();

            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina
            });

            // Adiciona filtros
            if (this.state.filtros.busca) {
                params.append('busca', this.state.filtros.busca);
            }
            if (this.state.filtros.ativo !== '') {
                params.append('ativo', this.state.filtros.ativo);
            }

            const response = await API.get(`/servicos?${params.toString()}`);

            if (response.sucesso) {
                this.state.servicos = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao carregar serviços');
            }
        } catch (error) {
            console.error('Erro ao carregar serviços:', error);
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar serviços. Tente novamente.';
            this.mostrarErro(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
        } finally {
            this.esconderLoading();
        }
    },

    /**
     * Renderiza tabela de serviços
     */
    renderizarTabela() {
        if (!this.state.servicos || this.state.servicos.length === 0) {
            this.elements.tableContainer.style.display = 'none';
            this.elements.noData.style.display = 'block';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = this.state.servicos.map(servico => `
            <tr>
                <td>${servico.id}</td>
                <td><strong>${this.escapeHtml(servico.codigo)}</strong></td>
                <td>${this.escapeHtml(servico.nome)}</td>
                <td>R$ ${parseFloat(servico.valor_venda).toFixed(2)}</td>
                <td>
                    <span class="badge badge-${servico.ativo == 1 ? 'success' : 'secondary'}">
                        ${servico.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-action btn-view" onclick="ServicosManager.visualizar(${servico.id})" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${this.state.permissoes.editar ? `
                            <button class="btn-action btn-edit" onclick="ServicosManager.editar(${servico.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${this.state.permissoes.deletar ? `
                            <button class="btn-action btn-delete" onclick="ServicosManager.deletar(${servico.id})" title="Deletar">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    },

    /**
     * Aplica filtros
     */
    aplicarFiltros() {
        this.state.filtros.busca = this.elements.filtroBusca.value;
        this.state.filtros.ativo = this.elements.filtroAtivo.value;
        this.state.paginacao.pagina = 1; // Reseta para primeira página
        this.carregarServicos();
    },

    /**
     * Abre modal para novo serviço
     */
    abrirModalNovo() {
        this.state.editandoId = null;
        this.elements.modalTitle.textContent = 'Novo Serviço';
        this.elements.formServico.reset();
        document.getElementById('servicoId').value = '';
        this.esconderErroModal();
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Visualiza serviço (modo readonly)
     */
    async visualizar(id) {
        try {
            const response = await API.get(`/servicos/${id}`);

            if (response.sucesso) {
                const servico = response.dados;
                this.state.editandoId = id;
                this.elements.modalTitle.textContent = 'Visualizar Serviço';
                this.preencherFormulario(servico);

                // Desabilita todos os campos
                const inputs = this.elements.formServico.querySelectorAll('input, select, textarea, button[type="submit"]');
                inputs.forEach(input => input.disabled = true);

                this.esconderErroModal();
                this.elements.modalForm.classList.add('show');
            } else {
                Utils.Notificacao.erro(response.mensagem || 'Erro ao carregar serviço');
            }
        } catch (error) {
            console.error('Erro ao visualizar serviço:', error);
            Utils.Notificacao.erro('Erro ao carregar serviço');
        }
    },

    /**
     * Edita serviço
     */
    async editar(id) {
        try {
            const response = await API.get(`/servicos/${id}`);

            if (response.sucesso) {
                const servico = response.dados;
                this.state.editandoId = id;
                this.elements.modalTitle.textContent = 'Editar Serviço';
                this.preencherFormulario(servico);

                // Habilita todos os campos
                const inputs = this.elements.formServico.querySelectorAll('input, select, textarea, button');
                inputs.forEach(input => input.disabled = false);

                this.esconderErroModal();
                this.elements.modalForm.classList.add('show');
            } else {
                Utils.Notificacao.erro(response.mensagem || 'Erro ao carregar serviço');
            }
        } catch (error) {
            console.error('Erro ao editar serviço:', error);
            Utils.Notificacao.erro('Erro ao carregar serviço');
        }
    },

    /**
     * Preenche formulário com dados do serviço
     */
    preencherFormulario(servico) {
        document.getElementById('servicoId').value = servico.id || '';
        document.getElementById('codigo').value = servico.codigo || '';
        document.getElementById('nome').value = servico.nome || '';
        document.getElementById('valorVenda').value = servico.valor_venda || '';
        document.getElementById('ativo').value = servico.ativo || '1';
        document.getElementById('externalId').value = servico.external_id || '';
        document.getElementById('externalCodigo').value = servico.external_codigo || '';
        document.getElementById('observacoes').value = servico.observacoes || '';
    },

    /**
     * Salva serviço (criar ou editar)
     */
    async salvar(event) {
        event.preventDefault();
        this.esconderErroModal();

        try {
            const servicoId = document.getElementById('servicoId').value;
            const dados = {
                codigo: document.getElementById('codigo').value,
                nome: document.getElementById('nome').value,
                valor_venda: document.getElementById('valorVenda').value,
                ativo: document.getElementById('ativo').value == '1',
                external_id: document.getElementById('externalId').value || null,
                external_codigo: document.getElementById('externalCodigo').value || null,
                observacoes: document.getElementById('observacoes').value || null
            };

            let response;
            if (servicoId) {
                // Atualizar
                response = await API.put(`/servicos/${servicoId}`, dados);
            } else {
                // Criar
                response = await API.post('/servicos', dados);
            }

            if (response.sucesso) {
                Utils.Notificacao.sucesso(response.mensagem || 'Serviço salvo com sucesso!');
                this.fecharModal();
                await this.carregarServicos();
            } else {
                this.mostrarErroModal(response.mensagem || 'Erro ao salvar serviço');
            }
        } catch (error) {
            console.error('Erro ao salvar serviço:', error);
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao salvar serviço. Tente novamente.';
            this.mostrarErroModal(mensagemErro);
        }
    },

    /**
     * Deleta serviço
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar este serviço?')) {
            return;
        }

        try {
            const response = await API.delete(`/servicos/${id}`);

            if (response.sucesso) {
                Utils.Notificacao.sucesso(response.mensagem || 'Serviço deletado com sucesso!');
                await this.carregarServicos();
            } else {
                Utils.Notificacao.erro(response.mensagem || 'Erro ao deletar serviço');
            }
        } catch (error) {
            console.error('Erro ao deletar serviço:', error);
            Utils.Notificacao.erro('Erro ao deletar serviço');
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalForm.classList.remove('show');
        this.elements.formServico.reset();
        this.state.editandoId = null;

        // Habilita todos os campos novamente
        const inputs = this.elements.formServico.querySelectorAll('input, select, textarea, button');
        inputs.forEach(input => input.disabled = false);

        this.esconderErroModal();
    },

    /**
     * Paginação - Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarServicos();
        }
    },

    /**
     * Paginação - Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarServicos();
        }
    },

    /**
     * Atualiza informações de paginação
     */
    atualizarPaginacao() {
        const inicio = (this.state.paginacao.pagina - 1) * this.state.paginacao.porPagina + 1;
        const fim = Math.min(this.state.paginacao.pagina * this.state.paginacao.porPagina, this.state.paginacao.total);

        this.elements.pageInfo.textContent = `Página ${this.state.paginacao.pagina} de ${this.state.paginacao.totalPaginas} (${inicio}-${fim} de ${this.state.paginacao.total} serviços)`;

        this.elements.btnPrevious.disabled = this.state.paginacao.pagina === 1;
        this.elements.btnNext.disabled = this.state.paginacao.pagina >= this.state.paginacao.totalPaginas;
    },

    /**
     * Utilitários de UI
     */
    mostrarLoading() {
        this.elements.loadingContainer.style.display = 'flex';
        this.elements.tableContainer.style.display = 'none';
        this.elements.noData.style.display = 'none';
    },

    esconderLoading() {
        this.elements.loadingContainer.style.display = 'none';
    },

    mostrarErro(mensagem) {
        this.elements.errorMessage.textContent = mensagem;
        this.elements.errorContainer.style.display = 'block';
        this.elements.tableContainer.style.display = 'none';
        this.elements.noData.style.display = 'none';
    },

    esconderErro() {
        this.elements.errorContainer.style.display = 'none';
    },

    mostrarErroModal(mensagem) {
        this.elements.modalErrorMessage.textContent = mensagem;
        this.elements.modalError.style.display = 'block';
    },

    esconderErroModal() {
        this.elements.modalError.style.display = 'none';
    },

    /**
     * Escape HTML para prevenir XSS
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
    ServicosManager.init();
});
