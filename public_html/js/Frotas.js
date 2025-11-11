/**
 * Gerenciador de Frotas
 * Implementa CRUD completo de veículos com validação de permissões ACL
 */

const FrotasManager = {
    // Estado da aplicação
    state: {
        veiculos: [],
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
            tipo: '',
            status: 'ativo'
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
        filtroBusca: document.getElementById('filtroBusca'),
        filtroTipo: document.getElementById('filtroTipo'),
        filtroStatus: document.getElementById('filtroStatus'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        closeModal: document.getElementById('closeModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        formVeiculo: document.getElementById('formVeiculo'),
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

        await this.carregarVeiculos();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formVeiculo?.addEventListener('submit', (e) => this.salvar(e));
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
     * Carrega veículos
     */
    async carregarVeiculos() {
        this.showLoading();

        try {
            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina
            });

            if (this.state.filtros.busca) {
                params.append('busca', this.state.filtros.busca);
            }

            if (this.state.filtros.tipo) {
                params.append('tipo', this.state.filtros.tipo);
            }

            if (this.state.filtros.status) {
                params.append('status', this.state.filtros.status);
            }

            const response = await API.get(`/frota?${params.toString()}`);

            if (response.sucesso) {
                this.state.veiculos = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            }
        } catch (error) {
            // Formata mensagem de erro considerando validações da API
            const mensagemErro = error.data ?
                this.formatarMensagemErro(error.data) :
                'Erro ao carregar veículos da frota';

            this.showError(mensagemErro);
            console.error('Erro ao carregar veículos:', error);
        }
    },

    /**
     * Renderiza a tabela
     */
    renderizarTabela() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (this.state.veiculos.length === 0) {
            this.elements.tableContainer.style.display = 'block';
            this.elements.noData.style.display = 'block';
            this.elements.tableBody.innerHTML = '';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = '';

        this.state.veiculos.forEach(veiculo => {
            const tr = document.createElement('tr');

            const marcaModelo = [
                veiculo.marca,
                veiculo.modelo
            ].filter(Boolean).join(' ') || '-';

            const ano = veiculo.ano_fabricacao && veiculo.ano_modelo ?
                `${veiculo.ano_fabricacao}/${veiculo.ano_modelo}` :
                veiculo.ano_fabricacao || veiculo.ano_modelo || '-';

            tr.innerHTML = `
                <td>${veiculo.id}</td>
                <td>${this.escapeHtml(veiculo.nome)}</td>
                <td>${this.formatarTipo(veiculo.tipo)}</td>
                <td>${this.escapeHtml(veiculo.placa)}</td>
                <td>${this.escapeHtml(marcaModelo)}</td>
                <td>${ano}</td>
                <td>${veiculo.quilometragem ? this.formatarNumero(veiculo.quilometragem) : '-'}</td>
                <td>
                    <span class="badge ${this.getStatusClass(veiculo.status)}">
                        ${this.formatarStatus(veiculo.status)}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ?
                            `<button class="btn btn-small" onclick="FrotasManager.editar(${veiculo.id})">Editar</button>` :
                            ''}
                        ${this.state.permissoes.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="FrotasManager.deletar(${veiculo.id})">Deletar</button>` :
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
        this.state.filtros.tipo = this.elements.filtroTipo.value;
        this.state.filtros.status = this.elements.filtroStatus.value;
        this.state.paginacao.pagina = 1;
        this.carregarVeiculos();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarVeiculos();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarVeiculos();
        }
    },

    /**
     * Abre modal para novo veículo
     */
    abrirModalNovo() {
        this.state.editandoId = null;
        this.elements.modalTitle.textContent = 'Novo Veículo';
        this.elements.formVeiculo.reset();
        document.getElementById('status').value = 'ativo';

        this.elements.modalError.style.display = 'none';
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Edita veículo
     */
    async editar(id) {
        try {
            const response = await API.get(`/frota/${id}`);

            if (response.sucesso && response.dados) {
                const veiculo = response.dados;
                this.state.editandoId = id;

                this.elements.modalTitle.textContent = 'Editar Veículo';

                document.getElementById('veiculoId').value = veiculo.id;
                document.getElementById('nome').value = veiculo.nome || '';
                document.getElementById('tipo').value = veiculo.tipo || '';
                document.getElementById('placa').value = veiculo.placa || '';
                document.getElementById('status').value = veiculo.status || 'ativo';
                document.getElementById('marca').value = veiculo.marca || '';
                document.getElementById('modelo').value = veiculo.modelo || '';
                document.getElementById('anoFabricacao').value = veiculo.ano_fabricacao || '';
                document.getElementById('anoModelo').value = veiculo.ano_modelo || '';
                document.getElementById('cor').value = veiculo.cor || '';
                document.getElementById('chassi').value = veiculo.chassi || '';
                document.getElementById('renavam').value = veiculo.renavam || '';
                document.getElementById('quilometragem').value = veiculo.quilometragem || '';
                document.getElementById('capacidadeTanque').value = veiculo.capacidade_tanque || '';
                document.getElementById('dataAquisicao').value = veiculo.data_aquisicao || '';
                document.getElementById('valorAquisicao').value = veiculo.valor_aquisicao || '';
                document.getElementById('observacoes').value = veiculo.observacoes || '';

                this.elements.modalError.style.display = 'none';
                this.elements.modalForm.classList.add('show');
            }
        } catch (error) {
            // Formata mensagem de erro considerando validações da API
            const mensagemErro = error.data ?
                this.formatarMensagemErro(error.data) :
                'Erro ao carregar veículo';

            alert(mensagemErro);
            console.error('Erro ao carregar veículo:', error);
        }
    },

    /**
     * Salva veículo
     */
    async salvar(e) {
        e.preventDefault();

        const dados = {
            nome: document.getElementById('nome').value,
            tipo: document.getElementById('tipo').value,
            placa: document.getElementById('placa').value,
            status: document.getElementById('status').value
        };

        // Campos opcionais
        const marca = document.getElementById('marca').value;
        if (marca) dados.marca = marca;

        const modelo = document.getElementById('modelo').value;
        if (modelo) dados.modelo = modelo;

        const anoFabricacao = document.getElementById('anoFabricacao').value;
        if (anoFabricacao) dados.ano_fabricacao = parseInt(anoFabricacao);

        const anoModelo = document.getElementById('anoModelo').value;
        if (anoModelo) dados.ano_modelo = parseInt(anoModelo);

        const cor = document.getElementById('cor').value;
        if (cor) dados.cor = cor;

        const chassi = document.getElementById('chassi').value;
        if (chassi) dados.chassi = chassi;

        const renavam = document.getElementById('renavam').value;
        if (renavam) dados.renavam = renavam;

        const quilometragem = document.getElementById('quilometragem').value;
        if (quilometragem) dados.quilometragem = parseInt(quilometragem);

        const capacidadeTanque = document.getElementById('capacidadeTanque').value;
        if (capacidadeTanque) dados.capacidade_tanque = parseFloat(capacidadeTanque);

        const dataAquisicao = document.getElementById('dataAquisicao').value;
        if (dataAquisicao) dados.data_aquisicao = dataAquisicao;

        const valorAquisicao = document.getElementById('valorAquisicao').value;
        if (valorAquisicao) dados.valor_aquisicao = parseFloat(valorAquisicao);

        const observacoes = document.getElementById('observacoes').value;
        if (observacoes) dados.observacoes = observacoes;

        try {
            let response;

            if (this.state.editandoId) {
                // Atualizar
                response = await API.put(`/frota/${this.state.editandoId}`, dados);
            } else {
                // Criar
                response = await API.post('/frota', dados);
            }

            if (response.sucesso) {
                this.fecharModal();
                this.carregarVeiculos();
                alert(response.mensagem || 'Veículo salvo com sucesso!');
            }
        } catch (error) {
            // Exibe mensagem de erro com detalhes de validação
            this.showModalError(error.data || 'Erro ao salvar veículo');
            console.error('Erro ao salvar veículo:', error);
        }
    },

    /**
     * Deleta veículo
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar este veículo?')) {
            return;
        }

        try {
            const response = await API.delete(`/frota/${id}`);

            if (response.sucesso) {
                this.carregarVeiculos();
                alert(response.mensagem || 'Veículo deletado com sucesso!');
            }
        } catch (error) {
            // Formata mensagem de erro considerando validações da API
            const mensagemErro = error.data ?
                this.formatarMensagemErro(error.data) :
                'Erro ao deletar veículo';

            alert(mensagemErro);
            console.error('Erro ao deletar veículo:', error);
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalForm.classList.remove('show');
        this.elements.formVeiculo.reset();
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
     * Formata erros de validação da API
     */
    formatarErrosValidacao(erros) {
        if (!erros || typeof erros !== 'object') {
            return null;
        }

        const mensagens = [];
        for (const [campo, mensagensArray] of Object.entries(erros)) {
            if (Array.isArray(mensagensArray)) {
                mensagensArray.forEach(msg => {
                    mensagens.push(`• ${msg}`);
                });
            }
        }

        return mensagens.length > 0 ? mensagens.join('\n') : null;
    },

    /**
     * Mostra erro no modal
     */
    showModalError(error) {
        this.elements.modalError.style.display = 'block';
        const mensagemFinal = this.formatarMensagemErro(error);
        this.elements.modalErrorMessage.textContent = mensagemFinal;

        // Aplica estilo para preservar quebras de linha
        this.elements.modalErrorMessage.style.whiteSpace = 'pre-line';
    },

    /**
     * Formata mensagem de erro de forma consistente
     * Suporta erros da API com estrutura: { sucesso: false, mensagem: "...", erros: {...} }
     */
    formatarMensagemErro(error) {
        // Se é uma string simples
        if (typeof error === 'string') {
            return error;
        }

        // Se não é um objeto, retorna mensagem padrão
        if (!error || typeof error !== 'object') {
            return 'Erro ao processar requisição';
        }

        // Verifica se é um objeto de erro da API com validações
        const errosValidacao = this.formatarErrosValidacao(error.erros);

        if (errosValidacao) {
            // Se tem erros de validação, mostra eles formatados
            const titulo = error.mensagem || 'Erro de validação';
            return `${titulo}:\n\n${errosValidacao}`;
        }

        // Se não tem erros detalhados, usa a mensagem geral
        return error.mensagem || error.erro || 'Erro ao processar requisição';
    },

    /**
     * Formata tipo de veículo
     */
    formatarTipo(tipo) {
        const tipos = {
            'motocicleta': 'Motocicleta',
            'automovel': 'Automóvel',
            'caminhonete': 'Caminhonete',
            'caminhao': 'Caminhão',
            'onibus': 'Ônibus',
            'van': 'Van'
        };
        return tipos[tipo] || tipo;
    },

    /**
     * Formata status
     */
    formatarStatus(status) {
        const statusMap = {
            'ativo': 'Ativo',
            'inativo': 'Inativo',
            'manutencao': 'Manutenção',
            'reservado': 'Reservado',
            'vendido': 'Vendido'
        };
        return statusMap[status] || status;
    },

    /**
     * Retorna classe CSS baseada no status
     */
    getStatusClass(status) {
        const classes = {
            'ativo': 'badge-success',
            'inativo': 'badge-danger',
            'manutencao': 'badge-warning',
            'reservado': 'badge-info',
            'vendido': 'badge-secondary'
        };
        return classes[status] || 'badge-secondary';
    },

    /**
     * Formata número
     */
    formatarNumero(numero) {
        return new Intl.NumberFormat('pt-BR').format(numero);
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
    FrotasManager.init();
});

// Expõe globalmente para uso nos event handlers inline
window.FrotasManager = FrotasManager;
