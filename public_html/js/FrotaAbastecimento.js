/**
 * Gerenciador de Abastecimentos da Frota
 * Implementa CRUD completo de abastecimentos com validação de permissões ACL
 */

const FrotaAbastecimentoManager = {
    // Estado da aplicação
    state: {
        abastecimentos: [],
        frotas: [],
        colaboradores: [],
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
            status: '',
            frota_id: '',
            data_inicio: '',
            data_fim: ''
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
        filtroStatus: document.getElementById('filtroStatus'),
        filtroFrota: document.getElementById('filtroFrota'),
        filtroDataInicio: document.getElementById('filtroDataInicio'),
        filtroDataFim: document.getElementById('filtroDataFim'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        closeModal: document.getElementById('closeModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        formAbastecimento: document.getElementById('formAbastecimento'),
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

        // Carrega dados auxiliares
        await Promise.all([
            this.carregarFrotas(),
            this.carregarColaboradores()
        ]);

        await this.carregarAbastecimentos();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formAbastecimento?.addEventListener('submit', (e) => this.salvar(e));
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
                    visualizar: permissoes.includes('frota_abastecimento.visualizar'),
                    criar: permissoes.includes('frota_abastecimento.criar'),
                    editar: permissoes.includes('frota_abastecimento.editar'),
                    deletar: permissoes.includes('frota_abastecimento.deletar')
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
     * Carrega frotas para os selects
     */
    async carregarFrotas() {
        try {
            const response = await API.get('/frota?status=ativo&por_pagina=999');

            if (response.sucesso) {
                this.state.frotas = response.dados?.itens || [];
                this.popularSelectFrotas();
            }
        } catch (error) {
            console.error('Erro ao carregar frotas:', error);
        }
    },

    /**
     * Popula selects de frotas
     */
    popularSelectFrotas() {
        const selectFrota = document.getElementById('frotaId');
        const filtroFrota = this.elements.filtroFrota;

        // Limpa opções existentes (exceto a primeira)
        if (selectFrota) {
            selectFrota.innerHTML = '<option value="">Selecione...</option>';
        }

        if (filtroFrota) {
            filtroFrota.innerHTML = '<option value="">Todas</option>';
        }

        // Adiciona frotas
        this.state.frotas.forEach(frota => {
            const optionModal = document.createElement('option');
            optionModal.value = frota.id;
            optionModal.textContent = `${frota.nome} - ${frota.placa}`;

            const optionFiltro = document.createElement('option');
            optionFiltro.value = frota.id;
            optionFiltro.textContent = `${frota.nome} - ${frota.placa}`;

            if (selectFrota) selectFrota.appendChild(optionModal);
            if (filtroFrota) filtroFrota.appendChild(optionFiltro);
        });
    },

    /**
     * Carrega colaboradores para os selects
     */
    async carregarColaboradores() {
        try {
            const response = await API.get('/colaboradores?ativo=1&por_pagina=999');

            if (response.sucesso) {
                this.state.colaboradores = response.dados?.itens || [];
                this.popularSelectColaboradores();
            }
        } catch (error) {
            console.error('Erro ao carregar colaboradores:', error);
        }
    },

    /**
     * Popula select de colaboradores
     */
    popularSelectColaboradores() {
        const selectColaborador = document.getElementById('colaboradorId');

        if (selectColaborador) {
            selectColaborador.innerHTML = '<option value="">Selecione...</option>';

            this.state.colaboradores.forEach(colaborador => {
                const option = document.createElement('option');
                option.value = colaborador.id;
                option.textContent = colaborador.nome;
                selectColaborador.appendChild(option);
            });
        }
    },

    /**
     * Carrega abastecimentos
     */
    async carregarAbastecimentos() {
        this.showLoading();

        try {
            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina
            });

            if (this.state.filtros.busca) {
                params.append('busca', this.state.filtros.busca);
            }

            if (this.state.filtros.status) {
                params.append('status', this.state.filtros.status);
            }

            if (this.state.filtros.frota_id) {
                params.append('frota_id', this.state.filtros.frota_id);
            }

            if (this.state.filtros.data_inicio) {
                params.append('data_inicio', this.state.filtros.data_inicio);
            }

            if (this.state.filtros.data_fim) {
                params.append('data_fim', this.state.filtros.data_fim);
            }

            const response = await API.get(`/frota-abastecimento?${params.toString()}`);

            if (response.sucesso) {
                this.state.abastecimentos = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar abastecimentos';

            this.showError(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar abastecimentos:', error);
        }
    },

    /**
     * Renderiza a tabela
     */
    renderizarTabela() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (this.state.abastecimentos.length === 0) {
            this.elements.tableContainer.style.display = 'block';
            this.elements.noData.style.display = 'block';
            this.elements.tableBody.innerHTML = '';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = '';

        this.state.abastecimentos.forEach(abastecimento => {
            const tr = document.createElement('tr');

            const frotaInfo = abastecimento.frota_placa || '-';
            const motoristaInfo = abastecimento.motorista_nome || '-';
            const dataLimite = abastecimento.data_limite ?
                this.formatarData(abastecimento.data_limite) : '-';
            const km = abastecimento.km ? this.formatarNumero(abastecimento.km) : '-';
            const combustivel = abastecimento.combustivel ?
                this.formatarCombustivel(abastecimento.combustivel) : '-';
            const litros = abastecimento.litros ?
                `${this.formatarNumero(abastecimento.litros, 2)} L` : '-';
            const valor = abastecimento.valor ?
                this.formatarMoeda(abastecimento.valor) : '-';

            tr.innerHTML = `
                <td>${abastecimento.id}</td>
                <td>${this.escapeHtml(frotaInfo)}</td>
                <td>${this.escapeHtml(motoristaInfo)}</td>
                <td>${dataLimite}</td>
                <td>${km}</td>
                <td>${combustivel}</td>
                <td>${litros}</td>
                <td>${valor}</td>
                <td>
                    <span class="badge ${this.getStatusClass(abastecimento.status)}">
                        ${this.formatarStatus(abastecimento.status)}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ?
                            `<button class="btn btn-small" onclick="FrotaAbastecimentoManager.editar(${abastecimento.id})">
                                <i class="fas fa-edit"></i>
                            </button>` :
                            ''}
                        ${this.state.permissoes.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="FrotaAbastecimentoManager.deletar(${abastecimento.id})">
                                <i class="fas fa-trash"></i>
                            </button>` :
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
        this.state.filtros.status = this.elements.filtroStatus.value;
        this.state.filtros.frota_id = this.elements.filtroFrota.value;
        this.state.filtros.data_inicio = this.elements.filtroDataInicio.value;
        this.state.filtros.data_fim = this.elements.filtroDataFim.value;
        this.state.paginacao.pagina = 1;
        this.carregarAbastecimentos();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarAbastecimentos();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarAbastecimentos();
        }
    },

    /**
     * Abre modal para nova ordem
     */
    abrirModalNovo() {
        this.state.editandoId = null;
        this.elements.modalTitle.textContent = 'Nova Ordem de Abastecimento';
        this.elements.formAbastecimento.reset();

        this.elements.modalError.style.display = 'none';
        Utils.Errors.limparCampos();
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Edita abastecimento
     */
    async editar(id) {
        try {
            const response = await API.get(`/frota-abastecimento/${id}`);

            if (response.sucesso && response.dados) {
                const abastecimento = response.dados;
                this.state.editandoId = id;

                this.elements.modalTitle.textContent = 'Editar Ordem de Abastecimento';

                document.getElementById('abastecimentoId').value = abastecimento.id;
                document.getElementById('frotaId').value = abastecimento.frota_id || '';
                document.getElementById('colaboradorId').value = abastecimento.colaborador_id || '';
                document.getElementById('dataLimite').value = abastecimento.data_limite || '';
                document.getElementById('observacaoAdmin').value = abastecimento.observacao_admin || '';

                this.elements.modalError.style.display = 'none';
                Utils.Errors.limparCampos();
                this.elements.modalForm.classList.add('show');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar abastecimento';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar abastecimento:', error);
        }
    },

    /**
     * Salva abastecimento
     */
    async salvar(e) {
        e.preventDefault();

        const dados = {
            frota_id: parseInt(document.getElementById('frotaId').value),
            colaborador_id: parseInt(document.getElementById('colaboradorId').value)
        };

        // Campos permitidos apenas para criar/editar ordem
        const dataLimite = document.getElementById('dataLimite').value;
        if (dataLimite) dados.data_limite = dataLimite;

        const observacaoAdmin = document.getElementById('observacaoAdmin').value;
        if (observacaoAdmin) dados.observacao_admin = observacaoAdmin;

        try {
            let response;

            if (this.state.editandoId) {
                // Atualizar ordem (apenas data_limite e observacao_admin)
                const dadosAtualizacao = {
                    data_limite: dados.data_limite,
                    observacao_admin: dados.observacao_admin
                };
                response = await API.put(`/frota-abastecimento/${this.state.editandoId}`, dadosAtualizacao);
            } else {
                // Criar nova ordem
                response = await API.post('/frota-abastecimento', dados);
            }

            if (response.sucesso) {
                this.fecharModal();
                this.carregarAbastecimentos();
                Utils.Notificacao.sucesso(response.mensagem || 'Ordem salva com sucesso!');
            }
        } catch (error) {
            // Exibe mensagem de erro com detalhes de validação
            this.showModalError(error.data || 'Erro ao salvar ordem');
            Utils.Notificacao.erro(error.data || 'Erro ao salvar ordem');
            console.error('Erro ao salvar ordem:', error);
        }
    },

    /**
     * Deleta abastecimento
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar este abastecimento?')) {
            return;
        }

        try {
            const response = await API.delete(`/frota-abastecimento/${id}`);

            if (response.sucesso) {
                this.carregarAbastecimentos();
                Utils.Notificacao.sucesso(response.mensagem || 'Abastecimento deletado com sucesso!');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao deletar abastecimento';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao deletar abastecimento:', error);
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalForm.classList.remove('show');
        this.elements.formAbastecimento.reset();
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
                'frota_id': 'frotaId',
                'colaborador_id': 'colaboradorId',
                'data_limite': 'dataLimite',
                'observacao_admin': 'observacaoAdmin'
            };

            Utils.Errors.destacarCampos(error.erros, mapeamentoCampos);
        }
    },

    /**
     * Formata combustível
     */
    formatarCombustivel(combustivel) {
        const combustiveis = {
            'gasolina': 'Gasolina',
            'etanol': 'Etanol',
            'diesel': 'Diesel',
            'gnv': 'GNV'
        };
        return combustiveis[combustivel] || combustivel;
    },

    /**
     * Formata status
     */
    formatarStatus(status) {
        const statusMap = {
            'aguardando': 'Aguardando',
            'abastecido': 'Abastecido',
            'cancelado': 'Cancelado',
            'expirado': 'Expirado'
        };
        return statusMap[status] || status;
    },

    /**
     * Retorna classe CSS baseada no status
     */
    getStatusClass(status) {
        const classes = {
            'aguardando': 'badge-warning',
            'abastecido': 'badge-success',
            'cancelado': 'badge-danger',
            'expirado': 'badge-secondary'
        };
        return classes[status] || 'badge-secondary';
    },

    /**
     * Formata número
     */
    formatarNumero(numero, decimais = 0) {
        return new Intl.NumberFormat('pt-BR', {
            minimumFractionDigits: decimais,
            maximumFractionDigits: decimais
        }).format(numero);
    },

    /**
     * Formata moeda
     */
    formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor);
    },

    /**
     * Formata data
     */
    formatarData(data) {
        if (!data) return '-';
        const date = new Date(data + 'T00:00:00');
        return date.toLocaleDateString('pt-BR');
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
    FrotaAbastecimentoManager.init();
});

// Expõe globalmente para uso nos event handlers inline
window.FrotaAbastecimentoManager = FrotaAbastecimentoManager;
