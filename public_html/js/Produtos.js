/**
 * Gerenciador de Produtos - Refatorado (2 tabelas)
 * Implementa CRUD completo de produtos com validação de permissões ACL
 */

const ProdutosManager = {
    // Estado da aplicação
    state: {
        produtos: [],
        gruposProdutos: [],
        fornecedores: [],
        fornecedoresSelecionados: [], // Lista de fornecedores selecionados
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
            grupo_id: '',
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
        filtroGrupo: document.getElementById('filtroGrupo'),
        filtroAtivo: document.getElementById('filtroAtivo'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        closeModal: document.getElementById('closeModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        formProduto: document.getElementById('formProduto'),
        modalError: document.getElementById('modalError'),
        modalErrorMessage: document.getElementById('modalErrorMessage'),
        pagination: document.getElementById('pagination'),
        btnPrevious: document.getElementById('btnPrevious'),
        btnNext: document.getElementById('btnNext'),
        pageInfo: document.getElementById('pageInfo'),
        logoutBtn: document.getElementById('logoutBtn'),
        btnAdicionarFornecedor: document.getElementById('btnAdicionarFornecedor'),
        fornecedoresList: document.getElementById('fornecedoresList')
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
        await this.carregarFornecedores();
        await this.carregarProdutos();
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
        this.elements.formProduto?.addEventListener('submit', (e) => this.salvar(e));

        // Paginação
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());

        // Logout
        this.elements.logoutBtn?.addEventListener('click', () => this.logout());

        // Adicionar fornecedor
        this.elements.btnAdicionarFornecedor?.addEventListener('click', () => this.abrirModalFornecedores());

        // Fechar modal ao clicar fora
        window.addEventListener('click', (e) => {
            if (e.target === this.elements.modalForm) {
                this.fecharModal();
            }
        });

        // Sistema de abas
        document.querySelectorAll('.tab-button').forEach(button => {
            button.addEventListener('click', () => this.trocarAba(button.dataset.tab));
        });
    },

    /**
     * Verifica permissões do usuário
     */
    async verificarPermissoes() {
        try {
            const permissoes = await aguardarPermissoes();

            if (permissoes) {
                this.state.permissoes = {
                    visualizar: permissoes.includes('produtos.visualizar'),
                    criar: permissoes.includes('produtos.criar'),
                    editar: permissoes.includes('produtos.editar'),
                    deletar: permissoes.includes('produtos.deletar')
                };
            }

            // Controla visibilidade do botão novo
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
     * Carrega grupos de produtos para os selects
     */
    async carregarGruposProdutos() {
        try {
            const response = await API.get('/grupos-produtos?ativo=1&por_pagina=1000');
            this.state.gruposProdutos = response.dados.itens;

            // Popula select de filtro
            if (this.elements.filtroGrupo) {
                this.elements.filtroGrupo.innerHTML = '<option value="">Todos os grupos</option>';
                this.state.gruposProdutos.forEach(grupo => {
                    const option = document.createElement('option');
                    option.value = grupo.id;
                    option.textContent = grupo.nome;
                    this.elements.filtroGrupo.appendChild(option);
                });
            }

            // Popula select do formulário
            const grupoIdSelect = document.getElementById('grupoId');
            if (grupoIdSelect) {
                grupoIdSelect.innerHTML = '<option value="">Selecione um grupo (opcional)</option>';
                this.state.gruposProdutos.forEach(grupo => {
                    const option = document.createElement('option');
                    option.value = grupo.id;
                    option.textContent = grupo.nome;
                    grupoIdSelect.appendChild(option);
                });
            }
        } catch (erro) {
            console.error('Erro ao carregar grupos de produtos:', erro);
        }
    },

    /**
     * Carrega fornecedores
     */
    async carregarFornecedores() {
        try {
            const response = await API.get('/fornecedores?ativo=1&por_pagina=1000');
            this.state.fornecedores = response.dados.itens || [];
        } catch (erro) {
            console.error('Erro ao carregar fornecedores:', erro);
            this.state.fornecedores = [];
        }
    },

    /**
     * Carrega a lista de produtos
     */
    async carregarProdutos() {
        try {
            this.exibirCarregando();

            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina,
                ...this.state.filtros
            });

            const response = await API.get(`/produtos?${params}`);

            this.state.produtos = response.dados.itens;
            this.state.paginacao.total = response.dados.total;
            this.state.paginacao.totalPaginas = Math.ceil(response.dados.total / this.state.paginacao.porPagina);

            this.renderizarTabela();
            this.renderizarPaginacao();
            this.esconderCarregando();
        } catch (erro) {
            console.error('Erro ao carregar produtos:', erro);
            this.exibirErro('Erro ao carregar produtos. ' + (erro.message || ''));
        }
    },

    /**
     * Renderiza a tabela de produtos
     */
    renderizarTabela() {
        if (this.state.produtos.length === 0) {
            this.elements.tableContainer.style.display = 'none';
            this.elements.noData.style.display = 'block';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = this.state.produtos.map(produto => `
            <tr>
                <td>${this.escaparHtml(produto.id)}</td>
                <td>${this.escaparHtml(produto.codigo_interno || '-')}</td>
                <td><strong>${this.escaparHtml(produto.nome)}</strong></td>
                <td>${this.escaparHtml(produto.nome_grupo || '-')}</td>
                <td>${this.formatarNumero(produto.estoque)}</td>
                <td>R$ ${this.formatarNumero(produto.valor_venda || 0)}</td>
                <td>
                    <span class="badge ${produto.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${produto.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ? `
                            <button class="btn btn-small" onclick="ProdutosManager.editar(${produto.id})" title="Editar">
                                <i class="fas fa-edit"></i> Editar
                            </button>
                        ` : ''}
                        ${this.state.permissoes.deletar ? `
                            <button class="btn btn-small btn-danger" onclick="ProdutosManager.confirmarDelecao(${produto.id}, '${this.escaparHtml(produto.nome)}')" title="Deletar">
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
        this.state.filtros.grupo_id = this.elements.filtroGrupo?.value || '';
        this.state.filtros.ativo = this.elements.filtroAtivo?.value || '';
        this.state.paginacao.pagina = 1;
        this.carregarProdutos();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarProdutos();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarProdutos();
        }
    },

    /**
     * Troca de aba no modal
     */
    trocarAba(nomeAba) {
        // Remove active de todas as abas
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

        // Adiciona active na aba clicada
        document.querySelector(`[data-tab="${nomeAba}"]`)?.classList.add('active');
        document.getElementById(`tab${nomeAba.charAt(0).toUpperCase() + nomeAba.slice(1)}`)?.classList.add('active');
    },

    /**
     * Abre modal para novo produto
     */
    abrirModalNovo() {
        if (!this.state.permissoes.criar) {
            alert('Você não tem permissão para criar produtos');
            return;
        }

        this.state.editandoId = null;
        this.state.fornecedoresSelecionados = [];
        this.elements.modalTitle.textContent = 'Novo Produto';
        this.elements.formProduto.reset();
        document.getElementById('produtoId').value = '';
        document.getElementById('ativo').value = '1';
        document.getElementById('estoque').value = '0';

        // Limpa lista de fornecedores
        this.renderizarFornecedores();

        // Volta para a primeira aba
        this.trocarAba('gerais');

        this.elements.modalError.style.display = 'none';
        this.elements.modalForm.style.display = 'flex';
    },

    /**
     * Edita um produto
     */
    async editar(id) {
        if (!this.state.permissoes.editar) {
            alert('Você não tem permissão para editar produtos');
            return;
        }

        try {
            this.exibirCarregando();

            const response = await API.get(`/produtos/${id}`);
            const produto = response.dados;

            this.state.editandoId = id;
            this.state.fornecedoresSelecionados = produto.fornecedores || [];
            this.elements.modalTitle.textContent = 'Editar Produto';

            // Preenche formulário
            document.getElementById('produtoId').value = produto.id;
            document.getElementById('externalId').value = produto.external_id || '';
            document.getElementById('nome').value = produto.nome;
            document.getElementById('codigoInterno').value = produto.codigo_interno || '';
            document.getElementById('codigoBarra').value = produto.codigo_barra || '';
            document.getElementById('grupoId').value = produto.grupo_id || '';
            document.getElementById('largura').value = produto.largura || '';
            document.getElementById('altura').value = produto.altura || '';
            document.getElementById('comprimento').value = produto.comprimento || '';
            document.getElementById('estoque').value = produto.estoque || 0;
            document.getElementById('valorCusto').value = produto.valor_custo || '';
            document.getElementById('valorVenda').value = produto.valor_venda || '';
            document.getElementById('descricao').value = produto.descricao || '';
            document.getElementById('ativo').value = produto.ativo;

            // Campos fiscais
            document.getElementById('ncm').value = produto.ncm || '';
            document.getElementById('cest').value = produto.cest || '';
            document.getElementById('pesoLiquido').value = produto.peso_liquido || '';
            document.getElementById('pesoBruto').value = produto.peso_bruto || '';
            document.getElementById('valorAproximadoTributos').value = produto.valor_aproximado_tributos || '';
            document.getElementById('valorFixoPis').value = produto.valor_fixo_pis || '';
            document.getElementById('valorFixoPisSt').value = produto.valor_fixo_pis_st || '';
            document.getElementById('valorFixoConfins').value = produto.valor_fixo_confins || '';
            document.getElementById('valorFixoConfinsSt').value = produto.valor_fixo_confins_st || '';

            // Renderiza fornecedores
            this.renderizarFornecedores();

            // Volta para a primeira aba
            this.trocarAba('gerais');

            this.elements.modalError.style.display = 'none';
            this.elements.modalForm.style.display = 'flex';
            this.esconderCarregando();
        } catch (erro) {
            console.error('Erro ao carregar produto:', erro);
            this.exibirErro('Erro ao carregar produto. ' + (erro.message || ''));
        }
    },

    /**
     * Abre modal para adicionar fornecedores
     */
    abrirModalFornecedores() {
        if (this.state.fornecedores.length === 0) {
            alert('Nenhum fornecedor cadastrado no sistema');
            return;
        }

        // Cria um modal simples com lista de fornecedores
        const fornecedoresDisponiveis = this.state.fornecedores.filter(f =>
            !this.state.fornecedoresSelecionados.find(fs => fs.fornecedor_id == f.id)
        );

        if (fornecedoresDisponiveis.length === 0) {
            alert('Todos os fornecedores já foram adicionados');
            return;
        }

        const fornecedorId = prompt('Digite o ID do fornecedor ou selecione:\n\n' +
            fornecedoresDisponiveis.map(f => `ID: ${f.id} - ${f.nome}`).join('\n')
        );

        if (fornecedorId) {
            const fornecedor = this.state.fornecedores.find(f => f.id == fornecedorId);
            if (fornecedor) {
                this.state.fornecedoresSelecionados.push({
                    fornecedor_id: fornecedor.id,
                    fornecedor_nome: fornecedor.nome
                });
                this.renderizarFornecedores();
            } else {
                alert('Fornecedor não encontrado');
            }
        }
    },

    /**
     * Remove fornecedor da lista
     */
    removerFornecedor(fornecedorId) {
        this.state.fornecedoresSelecionados = this.state.fornecedoresSelecionados.filter(
            f => f.fornecedor_id != fornecedorId
        );
        this.renderizarFornecedores();
    },

    /**
     * Renderiza lista de fornecedores
     */
    renderizarFornecedores() {
        if (this.state.fornecedoresSelecionados.length === 0) {
            this.elements.fornecedoresList.innerHTML = '<p style="color: var(--text-secondary); text-align: center; padding: 20px;">Nenhum fornecedor adicionado.</p>';
            return;
        }

        this.elements.fornecedoresList.innerHTML = this.state.fornecedoresSelecionados.map((fornecedor, index) => `
            <div class="dynamic-list-item">
                <div class="dynamic-list-item-header">
                    <span class="dynamic-list-item-title">
                        ${this.escaparHtml(fornecedor.fornecedor_nome || `Fornecedor ID: ${fornecedor.fornecedor_id}`)}
                    </span>
                    <button type="button" class="btn-remove-item" onclick="ProdutosManager.removerFornecedor(${fornecedor.fornecedor_id})">
                        <i class="fas fa-trash"></i> Remover
                    </button>
                </div>
            </div>
        `).join('');
    },

    /**
     * Salva o produto
     */
    async salvar(e) {
        e.preventDefault();

        try {
            const dados = {
                external_id: document.getElementById('externalId').value || null,
                nome: document.getElementById('nome').value,
                codigo_interno: document.getElementById('codigoInterno').value || null,
                codigo_barra: document.getElementById('codigoBarra').value || null,
                grupo_id: document.getElementById('grupoId').value || null,
                largura: document.getElementById('largura').value || null,
                altura: document.getElementById('altura').value || null,
                comprimento: document.getElementById('comprimento').value || null,
                estoque: parseFloat(document.getElementById('estoque').value) || 0,
                valor_custo: document.getElementById('valorCusto').value || null,
                valor_venda: document.getElementById('valorVenda').value || null,
                descricao: document.getElementById('descricao').value || null,
                ativo: parseInt(document.getElementById('ativo').value),
                // Campos fiscais
                ncm: document.getElementById('ncm').value || null,
                cest: document.getElementById('cest').value || null,
                peso_liquido: document.getElementById('pesoLiquido').value || null,
                peso_bruto: document.getElementById('pesoBruto').value || null,
                valor_aproximado_tributos: document.getElementById('valorAproximadoTributos').value || null,
                valor_fixo_pis: document.getElementById('valorFixoPis').value || null,
                valor_fixo_pis_st: document.getElementById('valorFixoPisSt').value || null,
                valor_fixo_confins: document.getElementById('valorFixoConfins').value || null,
                valor_fixo_confins_st: document.getElementById('valorFixoConfinsSt').value || null,
                // Fornecedores
                fornecedores: this.state.fornecedoresSelecionados.map(f => ({
                    fornecedor_id: f.fornecedor_id
                }))
            };

            if (this.state.editandoId) {
                // Atualizar
                await API.put(`/produtos/${this.state.editandoId}`, dados);
                this.exibirMensagemSucesso('Produto atualizado com sucesso!');
            } else {
                // Criar
                await API.post('/produtos', dados);
                this.exibirMensagemSucesso('Produto cadastrado com sucesso!');
            }

            this.fecharModal();
            await this.carregarProdutos();
        } catch (erro) {
            console.error('Erro ao salvar produto:', erro);
            this.exibirErroModal(this.formatarErro(erro));
        }
    },

    /**
     * Confirma a deleção de um produto
     */
    confirmarDelecao(id, nome) {
        if (!this.state.permissoes.deletar) {
            alert('Você não tem permissão para deletar produtos');
            return;
        }

        if (confirm(`Tem certeza que deseja remover o produto "${nome}"?`)) {
            this.deletar(id);
        }
    },

    /**
     * Deleta um produto
     */
    async deletar(id) {
        try {
            this.exibirCarregando();

            await API.delete(`/produtos/${id}`);

            this.exibirMensagemSucesso('Produto removido com sucesso!');
            await this.carregarProdutos();
        } catch (erro) {
            console.error('Erro ao deletar produto:', erro);
            this.exibirErro('Erro ao deletar produto. ' + (erro.message || ''));
        }
    },

    /**
     * Fecha o modal
     */
    fecharModal() {
        this.elements.modalForm.style.display = 'none';
        this.elements.formProduto.reset();
        this.elements.modalError.style.display = 'none';
        this.state.editandoId = null;
        this.state.fornecedoresSelecionados = [];
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
     * Formata número
     */
    formatarNumero(numero) {
        if (!numero && numero !== 0) return '-';
        return parseFloat(numero).toFixed(2).replace('.', ',');
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
    ProdutosManager.init();
});
