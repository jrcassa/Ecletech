/**
 * Gerenciador de Forma de Pagamento
 * Implementa CRUD completo de formas de pagamento com validação de permissões ACL
 */

const FormaDePagamentoManager = {
    // Estado da aplicação
    state: {
        formas: [],
        contasBancarias: [],
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
        editandoId: null,
        formaParaDeletar: null
    },

    // Elementos DOM
    elements: {
        tableBody: document.getElementById('tableBody'),
        btnNovo: document.getElementById('btnNovo'),
        inputBusca: document.getElementById('inputBusca'),
        selectAtivo: document.getElementById('selectAtivo'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        btnCloseModal: document.getElementById('btnCloseModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        btnSalvar: document.getElementById('btnSalvar'),
        formFormaDePagamento: document.getElementById('formFormaDePagamento'),
        selectContaBancaria: document.getElementById('selectContaBancaria'),
        modalConfirm: document.getElementById('modalConfirm'),
        btnConfirmDelete: document.getElementById('btnConfirmDelete'),
        btnFiltrar: document.getElementById('btnFiltrar'),
        btnPrevious: document.getElementById('btnPrevious'),
        btnNext: document.getElementById('btnNext'),
        pageInfo: document.getElementById('pageInfo'),
        loadingContainer: document.getElementById('loadingContainer'),
        errorContainer: document.getElementById('errorContainer'),
        errorMessage: document.getElementById('errorMessage'),
        tableContainer: document.getElementById('tableContainer'),
        noData: document.getElementById('noData'),
        pagination: document.getElementById('pagination')
    },

    /**
     * Inicializa o gerenciador
     */
    async init() {
        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            return;
        }

        // Configura event listeners
        this.setupEventListeners();

        // Verifica permissões
        await this.verificarPermissoes();

        // Se não tem permissão de visualizar, exibe mensagem
        if (!this.state.permissoes.visualizar) {
            document.getElementById('permissionDenied').style.display = 'block';
            API.showError('Você não tem permissão para visualizar formas de pagamento');
            return;
        }

        // Mostra o conteúdo da página
        document.getElementById('pageContent').style.display = 'block';

        // Mostra botão de novo se tiver permissão
        if (this.state.permissoes.criar && this.elements.btnNovo) {
            this.elements.btnNovo.style.display = 'inline-flex';
        }

        // Carrega contas bancárias para o SELECT
        await this.carregarContasBancarias();

        // Carrega dados iniciais
        await this.carregarDados();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        // Botão novo
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());

        // Filtros
        this.elements.inputBusca?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                this.aplicarFiltros();
            }
        });
        this.elements.selectAtivo?.addEventListener('change', () => this.aplicarFiltros());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());

        // Modal
        this.elements.btnCloseModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.btnSalvar?.addEventListener('click', () => this.salvar());

        // Confirmação de exclusão
        this.elements.btnConfirmDelete?.addEventListener('click', () => this.confirmarDeletar());

        // Paginação
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());

        // Fecha modal ao clicar no backdrop
        this.elements.modalForm?.querySelector('.modal-backdrop')?.addEventListener('click', () => {
            this.fecharModal();
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
                    visualizar: permissoes.includes('forma_de_pagamento.visualizar'),
                    criar: permissoes.includes('forma_de_pagamento.criar'),
                    editar: permissoes.includes('forma_de_pagamento.editar'),
                    deletar: permissoes.includes('forma_de_pagamento.deletar')
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
     * Carrega as contas bancárias disponíveis
     */
    async carregarContasBancarias() {
        try {
            const response = await API.get('/conta-bancaria?ativo=1&por_pagina=1000');

            if (response.sucesso && response.dados && response.dados.itens) {
                this.state.contasBancarias = response.dados.itens;
                this.popularSelectContaBancaria();
            } else {
                console.warn('Não foi possível carregar contas bancárias');
            }
        } catch (error) {
            console.error('Erro ao carregar contas bancárias:', error);
        }
    },

    /**
     * Popula o SELECT de contas bancárias
     */
    popularSelectContaBancaria() {
        if (!this.elements.selectContaBancaria) return;

        // Limpa opções existentes (exceto a primeira)
        this.elements.selectContaBancaria.innerHTML = '<option value="">Selecione uma conta bancária</option>';

        // Adiciona as contas bancárias
        this.state.contasBancarias.forEach(conta => {
            const option = document.createElement('option');
            option.value = conta.id;
            option.textContent = conta.nome;
            this.elements.selectContaBancaria.appendChild(option);
        });
    },

    /**
     * Carrega os dados das formas de pagamento
     */
    async carregarDados() {
        try {
            // Mostra loading
            this.elements.loadingContainer.style.display = 'block';
            this.elements.errorContainer.style.display = 'none';
            this.elements.tableContainer.style.display = 'none';

            // Monta query string com filtros e paginação
            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina,
                ...this.state.filtros
            });

            // Remove parâmetros vazios
            for (const [key, value] of [...params.entries()]) {
                if (!value) {
                    params.delete(key);
                }
            }

            const response = await API.get(`/forma-de-pagamento?${params.toString()}`);

            if (response.sucesso) {
                this.state.formas = response.dados.itens || [];
                this.state.paginacao.total = response.dados.total || 0;
                this.state.paginacao.totalPaginas = response.dados.total_paginas || 1;

                // Esconde loading e mostra tabela
                this.elements.loadingContainer.style.display = 'none';
                this.elements.tableContainer.style.display = 'block';

                this.renderizarTabela();
                this.atualizarPaginacao();
            } else {
                this.elements.loadingContainer.style.display = 'none';
                this.elements.errorContainer.style.display = 'block';
                this.elements.errorMessage.textContent = response.mensagem || 'Erro ao carregar formas de pagamento';
            }
        } catch (error) {
            console.error('Erro ao carregar dados:', error);
            this.elements.loadingContainer.style.display = 'none';
            this.elements.errorContainer.style.display = 'block';
            this.elements.errorMessage.textContent = 'Erro ao carregar formas de pagamento';
        }
    },

    /**
     * Renderiza a tabela de formas de pagamento
     */
    renderizarTabela() {
        if (!this.elements.tableBody) return;

        if (this.state.formas.length === 0) {
            this.elements.tableBody.innerHTML = `
                <tr>
                    <td colspan="6" style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc; margin-bottom: 16px;"></i>
                        <p style="color: #666;">Nenhuma forma de pagamento encontrada</p>
                    </td>
                </tr>
            `;
            return;
        }

        this.elements.tableBody.innerHTML = this.state.formas.map(forma => `
            <tr>
                <td>${forma.id}</td>
                <td><strong>${this.escapeHtml(forma.nome)}</strong></td>
                <td>${this.escapeHtml(forma.nome_conta_bancaria || '-')}</td>
                <td style="text-align: center;">${forma.maximo_parcelas || 1}x</td>
                <td style="text-align: center;">
                    <span class="badge ${forma.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${forma.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td style="text-align: center;">
                    <div class="btn-group">
                        ${this.state.permissoes.editar ? `
                            <button class="btn btn-sm btn-primary" onclick="FormaDePagamentoManager.editar(${forma.id})" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                        ` : ''}
                        ${this.state.permissoes.deletar ? `
                            <button class="btn btn-sm btn-danger" onclick="FormaDePagamentoManager.deletar(${forma.id})" title="Excluir">
                                <i class="fas fa-trash"></i>
                            </button>
                        ` : ''}
                    </div>
                </td>
            </tr>
        `).join('');
    },

    /**
     * Atualiza informações de paginação
     */
    atualizarPaginacao() {
        if (this.elements.pageInfo) {
            this.elements.pageInfo.textContent = `Página ${this.state.paginacao.pagina} de ${this.state.paginacao.totalPaginas}`;
        }

        // Controla botões de navegação
        if (this.elements.btnPrevious) {
            this.elements.btnPrevious.disabled = this.state.paginacao.pagina === 1;
        }

        if (this.elements.btnNext) {
            this.elements.btnNext.disabled = this.state.paginacao.pagina >= this.state.paginacao.totalPaginas;
        }
    },

    /**
     * Aplicar filtros
     */
    aplicarFiltros() {
        this.state.filtros.busca = this.elements.inputBusca?.value || '';
        this.state.filtros.ativo = this.elements.selectAtivo?.value || '';
        this.state.paginacao.pagina = 1;
        this.carregarDados();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarDados();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarDados();
        }
    },

    /**
     * Abre modal para nova forma de pagamento
     */
    abrirModalNovo() {
        if (!this.state.permissoes.criar) {
            API.showError('Você não tem permissão para criar formas de pagamento');
            return;
        }

        this.state.editandoId = null;
        if (this.elements.modalTitle) {
            this.elements.modalTitle.textContent = 'Nova Forma de Pagamento';
        }
        this.limparFormulario();
        this.abrirModal();
    },

    /**
     * Edita uma forma de pagamento
     */
    async editar(id) {
        if (!this.state.permissoes.editar) {
            API.showError('Você não tem permissão para editar formas de pagamento');
            return;
        }

        try {
            const response = await API.get(`/forma-de-pagamento/${id}`);

            if (response.sucesso && response.dados) {
                this.state.editandoId = id;
                if (this.elements.modalTitle) {
                    this.elements.modalTitle.textContent = 'Editar Forma de Pagamento';
                }
                this.preencherFormulario(response.dados);
                this.abrirModal();
            } else {
                API.showError(response.mensagem || 'Erro ao carregar forma de pagamento');
            }
        } catch (error) {
            console.error('Erro ao editar:', error);
            API.showError('Erro ao carregar forma de pagamento');
        }
    },

    /**
     * Deleta uma forma de pagamento
     */
    deletar(id) {
        if (!this.state.permissoes.deletar) {
            API.showError('Você não tem permissão para deletar formas de pagamento');
            return;
        }

        this.state.formaParaDeletar = id;
        this.abrirModalConfirm();
    },

    /**
     * Confirma a exclusão
     */
    async confirmarDeletar() {
        if (!this.state.formaParaDeletar) return;

        try {
            const response = await API.delete(`/forma-de-pagamento/${this.state.formaParaDeletar}`);

            if (response.sucesso) {
                API.showSuccess('Forma de pagamento excluída com sucesso');
                this.fecharModalConfirm();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao excluir forma de pagamento');
            }
        } catch (error) {
            console.error('Erro ao deletar:', error);
            API.showError('Erro ao excluir forma de pagamento');
        }
    },

    /**
     * Salva a forma de pagamento (cria ou atualiza)
     */
    async salvar() {
        try {
            const dados = this.obterDadosFormulario();

            // Validação básica
            if (!dados.nome) {
                API.showError('Nome é obrigatório');
                return;
            }

            if (!dados.conta_bancaria_id) {
                API.showError('Conta bancária é obrigatória');
                return;
            }

            if (!dados.maximo_parcelas || dados.maximo_parcelas < 1) {
                API.showError('Máximo de parcelas deve ser no mínimo 1');
                return;
            }

            let response;
            if (this.state.editandoId) {
                response = await API.put(`/forma-de-pagamento/${this.state.editandoId}`, dados);
            } else {
                response = await API.post('/forma-de-pagamento', dados);
            }

            if (response.sucesso) {
                API.showSuccess(
                    this.state.editandoId ?
                    'Forma de pagamento atualizada com sucesso' :
                    'Forma de pagamento cadastrada com sucesso'
                );
                this.fecharModal();
                this.carregarDados();
            } else {
                API.showError(response.mensagem || 'Erro ao salvar forma de pagamento');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            API.showError('Erro ao salvar forma de pagamento');
        }
    },

    /**
     * Obtém dados do formulário
     */
    obterDadosFormulario() {
        const form = this.elements.formFormaDePagamento;
        if (!form) return {};

        return {
            nome: form.querySelector('#inputNome')?.value,
            conta_bancaria_id: form.querySelector('#selectContaBancaria')?.value || null,
            maximo_parcelas: parseInt(form.querySelector('#inputMaximoParcelas')?.value) || 1,
            intervalo_parcelas: parseInt(form.querySelector('#inputIntervaloParcelas')?.value) || 0,
            intervalo_primeira_parcela: parseInt(form.querySelector('#inputIntervaloPrimeira')?.value) || 0,
            external_id: form.querySelector('#inputExternalId')?.value || null,
            observacoes: form.querySelector('#inputObservacoes')?.value || null,
            ativo: form.querySelector('#checkAtivo')?.checked ? 1 : 0
        };
    },

    /**
     * Preenche o formulário com dados da forma de pagamento
     */
    preencherFormulario(forma) {
        const form = this.elements.formFormaDePagamento;
        if (!form) return;

        form.querySelector('#formaId').value = forma.id || '';
        form.querySelector('#inputNome').value = forma.nome || '';
        form.querySelector('#selectContaBancaria').value = forma.conta_bancaria_id || '';
        form.querySelector('#inputMaximoParcelas').value = forma.maximo_parcelas || 1;
        form.querySelector('#inputIntervaloParcelas').value = forma.intervalo_parcelas || 0;
        form.querySelector('#inputIntervaloPrimeira').value = forma.intervalo_primeira_parcela || 0;
        form.querySelector('#inputExternalId').value = forma.external_id || '';
        form.querySelector('#inputObservacoes').value = forma.observacoes || '';
        form.querySelector('#checkAtivo').checked = forma.ativo == 1;
    },

    /**
     * Limpa o formulário
     */
    limparFormulario() {
        const form = this.elements.formFormaDePagamento;
        if (!form) return;

        form.querySelector('#formaId').value = '';
        form.querySelector('#inputNome').value = '';
        form.querySelector('#selectContaBancaria').value = '';
        form.querySelector('#inputMaximoParcelas').value = '1';
        form.querySelector('#inputIntervaloParcelas').value = '0';
        form.querySelector('#inputIntervaloPrimeira').value = '0';
        form.querySelector('#inputExternalId').value = '';
        form.querySelector('#inputObservacoes').value = '';
        form.querySelector('#checkAtivo').checked = true;
    },

    /**
     * Abre o modal
     */
    abrirModal() {
        if (this.elements.modalForm) {
            this.elements.modalForm.classList.add('show');
        }
    },

    /**
     * Fecha o modal
     */
    fecharModal() {
        if (this.elements.modalForm) {
            this.elements.modalForm.classList.remove('show');
        }
        this.state.editandoId = null;
    },

    /**
     * Abre modal de confirmação
     */
    abrirModalConfirm() {
        if (this.elements.modalConfirm) {
            this.elements.modalConfirm.classList.add('show');
        }
    },

    /**
     * Fecha modal de confirmação
     */
    fecharModalConfirm() {
        if (this.elements.modalConfirm) {
            this.elements.modalConfirm.classList.remove('show');
        }
        this.state.formaParaDeletar = null;
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
