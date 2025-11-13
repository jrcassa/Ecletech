/**
 * Gerenciador de Abastecimentos para Motoristas
 * Interface para visualizar e finalizar ordens de abastecimento
 */

const FrotaAbastecimentoMotoristaManager = {
    // Estado da aplicação
    state: {
        ordensPendentes: [],
        estatisticas: {
            pendentes: 0,
            finalizados: 0,
            cancelados: 0
        },
        editandoId: null
    },

    // Elementos DOM
    elements: {
        loadingContainer: document.getElementById('loadingContainer'),
        errorContainer: document.getElementById('errorContainer'),
        errorMessage: document.getElementById('errorMessage'),
        ordersContainer: document.getElementById('ordersContainer'),
        noData: document.getElementById('noData'),
        statsGrid: document.getElementById('statsGrid'),
        statPendentes: document.getElementById('statPendentes'),
        statFinalizados: document.getElementById('statFinalizados'),
        statCancelados: document.getElementById('statCancelados'),
        modalFinalizar: document.getElementById('modalFinalizar'),
        closeModal: document.getElementById('closeModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        formFinalizar: document.getElementById('formFinalizar'),
        modalError: document.getElementById('modalError'),
        modalErrorMessage: document.getElementById('modalErrorMessage'),
        btnAtualizar: document.getElementById('btnAtualizar')
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

        // Carrega dados
        await this.carregarDados();

        // Auto-refresh a cada 30 segundos
        setInterval(() => this.carregarDados(), 30000);
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formFinalizar?.addEventListener('submit', (e) => this.finalizar(e));
        this.elements.btnAtualizar?.addEventListener('click', () => this.carregarDados());

        // Define data/hora atual por padrão
        const agora = new Date();
        agora.setMinutes(agora.getMinutes() - agora.getTimezoneOffset());
        document.getElementById('dataAbastecimento').value = agora.toISOString().slice(0, 16);
    },

    /**
     * Carrega todos os dados
     */
    async carregarDados() {
        await Promise.all([
            this.carregarOrdensPendentes(),
            this.carregarEstatisticas()
        ]);
    },

    /**
     * Carrega ordens pendentes do motorista
     */
    async carregarOrdensPendentes() {
        this.showLoading();

        try {
            const response = await API.get('/frota-abastecimento/meus-pendentes');

            if (response.sucesso) {
                this.state.ordensPendentes = response.dados || [];
                this.renderizarOrdens();
            }
        } catch (error) {
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar ordens pendentes';

            this.showError(mensagemErro);
            console.error('Erro ao carregar ordens:', error);
        }
    },

    /**
     * Carrega estatísticas do motorista
     */
    async carregarEstatisticas() {
        try {
            // Busca histórico para calcular estatísticas
            const response = await API.get('/frota-abastecimento/meu-historico?limite=100');

            if (response.sucesso) {
                const historico = response.dados || [];

                // Calcula estatísticas
                this.state.estatisticas = {
                    pendentes: this.state.ordensPendentes.length,
                    finalizados: historico.filter(o => o.status === 'abastecido').length,
                    cancelados: historico.filter(o => o.status === 'cancelado').length
                };

                this.atualizarEstatisticas();
            }
        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
        }
    },

    /**
     * Atualiza exibição de estatísticas
     */
    atualizarEstatisticas() {
        if (this.elements.statPendentes) {
            this.elements.statPendentes.textContent = this.state.estatisticas.pendentes;
        }
        if (this.elements.statFinalizados) {
            this.elements.statFinalizados.textContent = this.state.estatisticas.finalizados;
        }
        if (this.elements.statCancelados) {
            this.elements.statCancelados.textContent = this.state.estatisticas.cancelados;
        }
    },

    /**
     * Renderiza as ordens pendentes
     */
    renderizarOrdens() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (this.state.ordensPendentes.length === 0) {
            this.elements.ordersContainer.style.display = 'none';
            this.elements.noData.style.display = 'block';
            return;
        }

        this.elements.ordersContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.ordersContainer.innerHTML = '';

        this.state.ordensPendentes.forEach(ordem => {
            const card = this.criarCardOrdem(ordem);
            this.elements.ordersContainer.appendChild(card);
        });
    },

    /**
     * Cria card de uma ordem
     */
    criarCardOrdem(ordem) {
        const div = document.createElement('div');
        div.className = 'order-card';

        const dataLimite = ordem.data_limite ?
            this.formatarData(ordem.data_limite) :
            'Sem prazo definido';

        const dataCriacao = ordem.criado_em ?
            this.formatarDataHora(ordem.criado_em) :
            '-';

        div.innerHTML = `
            <div class="order-header">
                <div class="order-vehicle">
                    <i class="fas fa-car"></i>
                    ${this.escapeHtml(ordem.frota_placa || 'Veículo')} - ${this.escapeHtml(ordem.frota_modelo || '')}
                </div>
                <span class="badge badge-warning">
                    <i class="fas fa-clock"></i> Pendente
                </span>
            </div>

            <div class="order-info">
                <div class="info-item">
                    <div class="info-label">Data Limite</div>
                    <div class="info-value">${dataLimite}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Criado em</div>
                    <div class="info-value">${dataCriacao}</div>
                </div>
                <div class="info-item">
                    <div class="info-label">Criado por</div>
                    <div class="info-value">${this.escapeHtml(ordem.criado_por_nome || '-')}</div>
                </div>
            </div>

            ${ordem.observacao_admin ? `
                <div class="info-item" style="margin-top: 12px;">
                    <div class="info-label">Observações do Administrador</div>
                    <div class="info-value">${this.escapeHtml(ordem.observacao_admin)}</div>
                </div>
            ` : ''}

            <div class="order-actions">
                <button class="btn btn-success" onclick="FrotaAbastecimentoMotoristaManager.abrirModalFinalizar(${ordem.id})">
                    <i class="fas fa-gas-pump"></i> Finalizar Abastecimento
                </button>
            </div>
        `;

        return div;
    },

    /**
     * Abre modal para finalizar
     */
    async abrirModalFinalizar(id) {
        try {
            // Busca detalhes da ordem
            const response = await API.get(`/frota-abastecimento/${id}`);

            if (response.sucesso && response.dados) {
                const ordem = response.dados;
                this.state.editandoId = id;

                // Limpa formulário
                this.elements.formFinalizar.reset();
                document.getElementById('abastecimentoId').value = id;

                // Define data/hora atual
                const agora = new Date();
                agora.setMinutes(agora.getMinutes() - agora.getTimezoneOffset());
                document.getElementById('dataAbastecimento').value = agora.toISOString().slice(0, 16);

                this.elements.modalError.style.display = 'none';
                Utils.Errors.limparCampos();
                this.elements.modalFinalizar.classList.add('show');
            }
        } catch (error) {
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar ordem';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar ordem:', error);
        }
    },

    /**
     * Finaliza abastecimento
     */
    async finalizar(e) {
        e.preventDefault();

        const dados = {
            km: parseInt(document.getElementById('km').value),
            combustivel: document.getElementById('combustivel').value,
            litros: parseFloat(document.getElementById('litros').value),
            valor: parseFloat(document.getElementById('valor').value),
            data_abastecimento: document.getElementById('dataAbastecimento').value
        };

        const observacao = document.getElementById('observacaoMotorista').value;
        if (observacao) dados.observacao_motorista = observacao;

        try {
            const response = await API.patch(
                `/frota-abastecimento/${this.state.editandoId}/finalizar`,
                dados
            );

            if (response.sucesso) {
                this.fecharModal();
                await this.carregarDados();
                Utils.Notificacao.sucesso('Abastecimento finalizado com sucesso!');
            }
        } catch (error) {
            this.showModalError(error.data || 'Erro ao finalizar abastecimento');
            console.error('Erro ao finalizar:', error);
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalFinalizar.classList.remove('show');
        this.elements.formFinalizar.reset();
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
        this.elements.ordersContainer.style.display = 'none';
        this.elements.noData.style.display = 'none';
    },

    /**
     * Mostra erro
     */
    showError(message) {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'block';
        this.elements.errorMessage.textContent = message;
        this.elements.ordersContainer.style.display = 'none';
        this.elements.noData.style.display = 'none';
    },

    /**
     * Mostra erro no modal
     */
    showModalError(error) {
        Utils.Errors.limparCampos();

        Utils.Errors.exibir(
            this.elements.modalError,
            this.elements.modalErrorMessage,
            error
        );

        if (error && error.erros && typeof error.erros === 'object') {
            const mapeamentoCampos = {
                'km': 'km',
                'combustivel': 'combustivel',
                'litros': 'litros',
                'valor': 'valor',
                'data_abastecimento': 'dataAbastecimento',
                'observacao_motorista': 'observacaoMotorista'
            };

            Utils.Errors.destacarCampos(error.erros, mapeamentoCampos);
        }
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
     * Formata data e hora
     */
    formatarDataHora(data) {
        if (!data) return '-';
        const date = new Date(data);
        return date.toLocaleString('pt-BR');
    },

    /**
     * Escape HTML
     */
    escapeHtml(text) {
        return Utils.DOM.escapeHtml(text);
    }
};

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    FrotaAbastecimentoMotoristaManager.init();
});

// Expõe globalmente
window.FrotaAbastecimentoMotoristaManager = FrotaAbastecimentoMotoristaManager;
