/**
 * Gerenciador de Vendas
 * Implementa listagem e gerenciamento de vendas com validação de permissões ACL
 */

const VendasManager = {
    // Estado da aplicação
    state: {
        vendas: [],
        situacoesVendas: [],
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
            situacao_venda_id: '',
            situacao_financeiro: '',
            data_inicial: '',
            data_final: '',
            ativo: '1'
        }
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
        filtroSituacao: document.getElementById('filtroSituacao'),
        filtroSituacaoFinanceiro: document.getElementById('filtroSituacaoFinanceiro'),
        filtroDataInicial: document.getElementById('filtroDataInicial'),
        filtroDataFinal: document.getElementById('filtroDataFinal'),
        filtroAtivo: document.getElementById('filtroAtivo'),
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

        await this.carregarSituacoesVendas();
        await this.carregarVendas();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => {
            window.location.href = './venda-form.html';
        });
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
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
                    visualizar: permissoes.includes('venda.visualizar'),
                    criar: permissoes.includes('venda.criar'),
                    editar: permissoes.includes('venda.editar'),
                    deletar: permissoes.includes('venda.deletar')
                };
            }

            // Mostra/esconde botão novo baseado em permissões
            if (this.state.permissoes.criar && this.elements.btnNovo) {
                this.elements.btnNovo.style.display = 'inline-flex';
            }
        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
        }
    },

    /**
     * Carrega situações de vendas para o filtro
     */
    async carregarSituacoesVendas() {
        try {
            const response = await API.get('/situacoes-vendas?ativo=1&limite=100');

            if (response.sucesso && response.dados) {
                const itens = response.dados.itens || response.dados;
                this.state.situacoesVendas = Array.isArray(itens) ? itens : [];

                // Popula select de situações
                this.elements.filtroSituacao.innerHTML = '<option value="">Todas</option>';
                this.state.situacoesVendas.forEach(situacao => {
                    const option = document.createElement('option');
                    option.value = situacao.id;
                    option.textContent = situacao.nome;
                    this.elements.filtroSituacao.appendChild(option);
                });
            }
        } catch (erro) {
            console.error('Erro ao carregar situações de vendas:', erro);
        }
    },

    /**
     * Carrega vendas
     */
    async carregarVendas() {
        this.showLoading();

        try {
            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina
            });

            if (this.state.filtros.busca) {
                params.append('busca', this.state.filtros.busca);
            }

            if (this.state.filtros.situacao_venda_id) {
                params.append('situacao_venda_id', this.state.filtros.situacao_venda_id);
            }

            if (this.state.filtros.situacao_financeiro !== '') {
                params.append('situacao_financeiro', this.state.filtros.situacao_financeiro);
            }

            if (this.state.filtros.data_inicial) {
                params.append('data_inicial', this.state.filtros.data_inicial);
            }

            if (this.state.filtros.data_final) {
                params.append('data_final', this.state.filtros.data_final);
            }

            if (this.state.filtros.ativo !== '') {
                params.append('ativo', this.state.filtros.ativo);
            }

            const response = await API.get(`/venda?${params.toString()}`);

            if (response.sucesso) {
                this.state.vendas = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            }
        } catch (error) {
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar vendas';

            this.showError(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar vendas:', error);
        }
    },

    /**
     * Renderiza a tabela
     */
    renderizarTabela() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (this.state.vendas.length === 0) {
            this.elements.tableContainer.style.display = 'block';
            this.elements.noData.style.display = 'block';
            this.elements.tableBody.innerHTML = '';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = '';

        this.state.vendas.forEach(venda => {
            const tr = document.createElement('tr');

            // Situação Financeira
            const situacaoFinanceiro = this.getSituacaoFinanceiroLabel(venda.situacao_financeiro);

            tr.innerHTML = `
                <td><strong>${venda.codigo || '-'}</strong></td>
                <td>${this.formatarData(venda.data_venda)}</td>
                <td>${venda.nome_cliente || '-'}</td>
                <td>${venda.nome_vendedor || '-'}</td>
                <td>${venda.nome_situacao || '-'}</td>
                <td><span class="badge ${situacaoFinanceiro.class}">${situacaoFinanceiro.text}</span></td>
                <td><strong>${this.formatarMoeda(venda.valor_total)}</strong></td>
                <td>
                    <div class="actions">
                        <button class="btn btn-small btn-secondary" title="Visualizar" data-id="${venda.id}">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${this.state.permissoes.editar ? `
                            <button class="btn btn-small btn-warning" title="Editar" data-id="${venda.id}" data-action="editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${this.state.permissoes.deletar ? `
                            <button class="btn btn-small btn-danger" title="Deletar" data-id="${venda.id}" data-action="deletar">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            `;

            // Event listeners para botões de ação
            const btnVisualizar = tr.querySelector('[title="Visualizar"]');
            const btnEditar = tr.querySelector('[data-action="editar"]');
            const btnDeletar = tr.querySelector('[data-action="deletar"]');

            btnVisualizar?.addEventListener('click', () => {
                window.location.href = `vendas-detalhes.html?id=${venda.id}`;
            });

            btnEditar?.addEventListener('click', () => {
                window.location.href = `venda-form.html?id=${venda.id}`;
            });

            btnDeletar?.addEventListener('click', () => this.deletar(venda.id));

            this.elements.tableBody.appendChild(tr);
        });
    },

    /**
     * Deleta uma venda
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar esta venda?')) {
            return;
        }

        try {
            const response = await API.delete(`/venda/${id}`);

            if (response.sucesso) {
                Utils.Notificacao.sucesso('Venda deletada com sucesso!');
                await this.carregarVendas();
            }
        } catch (error) {
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao deletar venda';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao deletar venda:', error);
        }
    },

    /**
     * Aplica filtros
     */
    aplicarFiltros() {
        this.state.filtros.busca = this.elements.filtroBusca.value;
        this.state.filtros.situacao_venda_id = this.elements.filtroSituacao.value;
        this.state.filtros.situacao_financeiro = this.elements.filtroSituacaoFinanceiro.value;
        this.state.filtros.data_inicial = this.elements.filtroDataInicial.value;
        this.state.filtros.data_final = this.elements.filtroDataFinal.value;
        this.state.filtros.ativo = this.elements.filtroAtivo.value;

        this.state.paginacao.pagina = 1;
        this.carregarVendas();
    },

    /**
     * Navegação de paginação
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarVendas();
        }
    },

    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarVendas();
        }
    },

    /**
     * Atualiza informações de paginação
     */
    atualizarPaginacao() {
        const inicio = (this.state.paginacao.pagina - 1) * this.state.paginacao.porPagina + 1;
        const fim = Math.min(
            this.state.paginacao.pagina * this.state.paginacao.porPagina,
            this.state.paginacao.total
        );

        this.elements.pageInfo.textContent =
            `${inicio}-${fim} de ${this.state.paginacao.total}`;

        this.elements.btnPrevious.disabled = this.state.paginacao.pagina === 1;
        this.elements.btnNext.disabled =
            this.state.paginacao.pagina >= this.state.paginacao.totalPaginas;
    },

    /**
     * Helpers de formatação
     */
    getSituacaoFinanceiroLabel(situacao) {
        switch (parseInt(situacao)) {
            case 0:
                return { text: 'Pendente', class: 'badge-warning' };
            case 1:
                return { text: 'Pago', class: 'badge-success' };
            case 2:
                return { text: 'Parcial', class: 'badge-info' };
            default:
                return { text: '-', class: 'badge-secondary' };
        }
    },

    formatarMoeda(valor) {
        return new Intl.NumberFormat('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        }).format(valor || 0);
    },

    formatarData(data) {
        if (!data) return '-';
        const d = new Date(data + 'T00:00:00');
        return d.toLocaleDateString('pt-BR');
    },

    /**
     * UI helpers
     */
    showLoading() {
        this.elements.loadingContainer.style.display = 'flex';
        this.elements.errorContainer.style.display = 'none';
        this.elements.tableContainer.style.display = 'none';
    },

    showError(mensagem) {
        this.elements.errorMessage.textContent = mensagem;
        this.elements.errorContainer.style.display = 'block';
        this.elements.loadingContainer.style.display = 'none';
        this.elements.tableContainer.style.display = 'none';
    }
};

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    VendasManager.init();
});
