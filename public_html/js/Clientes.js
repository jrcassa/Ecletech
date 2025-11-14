/**
 * Gerenciador de Clientes
 * Implementa CRUD completo de clientes com validação de permissões ACL
 */

const ClienteesManager = {
    // Estado da aplicação
    state: {
        clientes: [],
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
            tipo_pessoa: '',
            ativo: '1'
        },
        editandoId: null,
        contatos: [],
        enderecos: [],
        tiposEnderecos: []
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
        filtroTipoPessoa: document.getElementById('filtroTipoPessoa'),
        filtroAtivo: document.getElementById('filtroAtivo'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        closeModal: document.getElementById('closeModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        formCliente: document.getElementById('formCliente'),
        modalError: document.getElementById('modalError'),
        modalErrorMessage: document.getElementById('modalErrorMessage'),
        pagination: document.getElementById('pagination'),
        btnPrevious: document.getElementById('btnPrevious'),
        btnNext: document.getElementById('btnNext'),
        pageInfo: document.getElementById('pageInfo'),
        logoutBtn: document.getElementById('logoutBtn'),
        listaContatos: document.getElementById('listaContatos'),
        btnAddContato: document.getElementById('btnAddContato'),
        listaEnderecos: document.getElementById('listaEnderecos'),
        btnAddEndereco: document.getElementById('btnAddEndereco'),
        tipoPessoa: document.getElementById('tipoPessoa')
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

        await this.carregarTiposEnderecos();
        await this.carregarClientes();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalNovo());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formCliente?.addEventListener('submit', (e) => this.salvar(e));
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());
        this.elements.btnAddContato?.addEventListener('click', () => this.adicionarContato());
        this.elements.btnAddEndereco?.addEventListener('click', () => this.adicionarEndereco());
        this.elements.logoutBtn?.addEventListener('click', async () => {
            if (confirm('Tem certeza que deseja sair?')) {
                await AuthAPI.logout();
            }
        });

        // Controle de campos condicionais baseado no tipo_pessoa
        this.elements.tipoPessoa?.addEventListener('change', () => this.controlarCamposCondicionais());

        // Filtro em tempo real
        this.elements.filtroBusca?.addEventListener('keyup', (e) => {
            if (e.key === 'Enter') {
                this.aplicarFiltros();
            }
        });

        // Aplica máscaras quando o modal é aberto
        this.aplicarMascaras();
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
                    visualizar: permissoes.includes('cliente.visualizar'),
                    criar: permissoes.includes('cliente.criar'),
                    editar: permissoes.includes('cliente.editar'),
                    deletar: permissoes.includes('cliente.deletar')
                };
            }

            // Controla visibilidade do botão novo baseado em permissões
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
     * Carrega tipos de endereços
     */
    async carregarTiposEnderecos() {
        try {
            const response = await API.get('/tipos-enderecos');

            if (response.sucesso && response.dados) {
                // A resposta vem paginada: response.dados.itens
                const itens = response.dados.itens || response.dados;

                if (Array.isArray(itens)) {
                    this.state.tiposEnderecos = itens;
                } else {
                    console.error('Resposta de tipos-enderecos não contém array de itens:', response.dados);
                    this.state.tiposEnderecos = [];
                }
            } else {
                console.warn('Resposta de tipos-enderecos sem sucesso ou sem dados:', response);
                this.state.tiposEnderecos = [];
            }
        } catch (erro) {
            console.error('Erro ao carregar tipos de endereços:', erro);
            this.state.tiposEnderecos = [];
        }
    },

    /**
     * Busca cidades por nome (autocomplete)
     */
    async buscarCidades(termo) {
        try {
            if (!termo || termo.length < 2) {
                return [];
            }

            const params = new URLSearchParams({
                busca: termo,
                por_pagina: 20
            });

            const response = await API.get(`/cidades?${params.toString()}`);

            if (response.sucesso && response.dados) {
                const itens = response.dados.itens || response.dados;
                return Array.isArray(itens) ? itens : [];
            }

            return [];
        } catch (erro) {
            console.error('Erro ao buscar cidades:', erro);
            return [];
        }
    },

    /**
     * Busca cidade por ID
     */
    async buscarCidadePorId(id) {
        try {
            const response = await API.get(`/cidades/${id}`);
            if (response.sucesso && response.dados) {
                return response.dados;
            }
            return null;
        } catch (erro) {
            console.error('Erro ao buscar cidade por ID:', erro);
            return null;
        }
    },

    /**
     * Carrega clientes
     */
    async carregarClientes() {
        this.showLoading();

        try {
            const params = new URLSearchParams({
                pagina: this.state.paginacao.pagina,
                por_pagina: this.state.paginacao.porPagina
            });

            if (this.state.filtros.busca) {
                params.append('busca', this.state.filtros.busca);
            }

            if (this.state.filtros.tipo_pessoa) {
                params.append('tipo_pessoa', this.state.filtros.tipo_pessoa);
            }

            if (this.state.filtros.ativo !== '') {
                params.append('ativo', this.state.filtros.ativo);
            }

            const response = await API.get(`/cliente?${params.toString()}`);

            if (response.sucesso) {
                this.state.clientes = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela();
                this.atualizarPaginacao();
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar clientes';

            this.showError(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar clientes:', error);
        }
    },

    /**
     * Renderiza a tabela
     */
    renderizarTabela() {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (this.state.clientes.length === 0) {
            this.elements.tableContainer.style.display = 'block';
            this.elements.noData.style.display = 'block';
            this.elements.tableBody.innerHTML = '';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = '';

        this.state.clientes.forEach(cliente => {
            const tr = document.createElement('tr');

            const documento = cliente.tipo_pessoa === 'PF' ?
                this.formatarCPF(cliente.cpf) :
                this.formatarCNPJ(cliente.cnpj);

            const ativo = cliente.ativo == 1 || cliente.ativo === true;

            tr.innerHTML = `
                <td>${cliente.id}</td>
                <td>${this.escapeHtml(cliente.nome)}</td>
                <td>${this.formatarTipoPessoa(cliente.tipo_pessoa)}</td>
                <td>${documento || '-'}</td>
                <td>${this.escapeHtml(cliente.email) || '-'}</td>
                <td>${this.formatarTelefone(cliente.telefone) || '-'}</td>
                <td>
                    <span class="badge ${this.getStatusClass(ativo)}">
                        ${ativo ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ?
                            `<button class="btn btn-small" onclick="ClienteesManager.editar(${cliente.id})">Editar</button>` :
                            ''}
                        ${this.state.permissoes.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="ClienteesManager.deletar(${cliente.id})">Deletar</button>` :
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
        this.state.filtros.tipo_pessoa = this.elements.filtroTipoPessoa.value;
        this.state.filtros.ativo = this.elements.filtroAtivo.value;
        this.state.paginacao.pagina = 1;
        this.carregarClientes();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarClientes();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarClientes();
        }
    },

    /**
     * Abre modal para novo cliente
     */
    abrirModalNovo() {
        this.state.editandoId = null;
        this.state.contatos = [];
        this.state.enderecos = [];

        this.elements.modalTitle.textContent = 'Novo Cliente';
        this.elements.formCliente.reset();
        document.getElementById('ativo').value = '1';
        document.getElementById('tipoPessoa').value = '';

        this.elements.listaContatos.innerHTML = '';
        this.elements.listaEnderecos.innerHTML = '';

        this.controlarCamposCondicionais();
        this.aplicarMascaras();

        this.elements.modalError.style.display = 'none';
        Utils.Errors.limparCampos();
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Edita cliente
     */
    async editar(id) {
        try {
            const response = await API.get(`/cliente/${id}`);

            if (response.sucesso && response.dados) {
                const cliente = response.dados;
                this.state.editandoId = id;

                this.elements.modalTitle.textContent = 'Editar Cliente';

                // Dados básicos
                document.getElementById('clienteId').value = cliente.id;
                document.getElementById('tipoPessoa').value = cliente.tipo_pessoa || 'PF';
                document.getElementById('nome').value = cliente.nome || '';
                document.getElementById('ativo').value = cliente.ativo == 1 ? '1' : '0';

                // Campos específicos
                if (cliente.tipo_pessoa === 'PF') {
                    document.getElementById('cpf').value = this.formatarCPF(cliente.cpf) || '';
                    document.getElementById('rg').value = cliente.rg || '';
                    document.getElementById('dataNascimento').value = cliente.data_nascimento || '';
                } else {
                    document.getElementById('cnpj').value = this.formatarCNPJ(cliente.cnpj) || '';
                    document.getElementById('razaoSocial').value = cliente.razao_social || '';
                    document.getElementById('inscricaoEstadual').value = cliente.inscricao_estadual || '';
                    document.getElementById('inscricaoMunicipal').value = cliente.inscricao_municipal || '';
                    document.getElementById('tipoContribuinte').value = cliente.tipo_contribuinte || '';
                }

                document.getElementById('email').value = cliente.email || '';
                document.getElementById('telefone').value = this.formatarTelefone(cliente.telefone) || '';
                document.getElementById('celular').value = this.formatarTelefone(cliente.celular) || '';
                document.getElementById('observacoes').value = cliente.observacoes || '';

                // Controla campos condicionais
                this.controlarCamposCondicionais();

                // Carrega contatos
                this.state.contatos = cliente.contatos || [];
                this.renderizarContatos();

                // Carrega endereços
                this.state.enderecos = cliente.enderecos || [];
                this.renderizarEnderecos();

                this.aplicarMascaras();

                this.elements.modalError.style.display = 'none';
                Utils.Errors.limparCampos();
                this.elements.modalForm.classList.add('show');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar cliente';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar cliente:', error);
        }
    },

    /**
     * Salva cliente
     */
    async salvar(e) {
        e.preventDefault();

        const tipoPessoa = document.getElementById('tipoPessoa').value;

        if (!tipoPessoa) {
            Utils.Notificacao.erro('Selecione o tipo de pessoa');
            return;
        }

        const dados = {
            tipo_pessoa: tipoPessoa,
            nome: document.getElementById('nome').value,
            ativo: parseInt(document.getElementById('ativo').value)
        };

        // Campos específicos por tipo de pessoa
        if (tipoPessoa === 'PF') {
            const cpf = document.getElementById('cpf').value;
            if (cpf) {
                if (!Utils.Validation.cpf(cpf)) {
                    Utils.Notificacao.erro('CPF inválido');
                    return;
                }
                dados.cpf = cpf.replace(/\D/g, '');
            }

            const rg = document.getElementById('rg').value;
            if (rg) dados.rg = rg;

            const dataNascimento = document.getElementById('dataNascimento').value;
            if (dataNascimento) dados.data_nascimento = dataNascimento;
        } else if (tipoPessoa === 'PJ') {
            const cnpj = document.getElementById('cnpj').value;
            if (cnpj) {
                if (!Utils.Validation.cnpj(cnpj)) {
                    Utils.Notificacao.erro('CNPJ inválido');
                    return;
                }
                dados.cnpj = cnpj.replace(/\D/g, '');
            }

            const razaoSocial = document.getElementById('razaoSocial').value;
            if (razaoSocial) dados.razao_social = razaoSocial;

            const inscricaoEstadual = document.getElementById('inscricaoEstadual').value;
            if (inscricaoEstadual) dados.inscricao_estadual = inscricaoEstadual;

            const inscricaoMunicipal = document.getElementById('inscricaoMunicipal').value;
            if (inscricaoMunicipal) dados.inscricao_municipal = inscricaoMunicipal;

            const tipoContribuinte = document.getElementById('tipoContribuinte').value;
            if (tipoContribuinte) dados.tipo_contribuinte = tipoContribuinte;
        }

        // Campos opcionais
        const email = document.getElementById('email').value;
        if (email) {
            if (!Utils.Validation.email(email)) {
                Utils.Notificacao.erro('Email inválido');
                return;
            }
            dados.email = email;
        }

        const telefone = document.getElementById('telefone').value;
        if (telefone) dados.telefone = telefone.replace(/\D/g, '');

        const celular = document.getElementById('celular').value;
        if (celular) dados.celular = celular.replace(/\D/g, '');

        const observacoes = document.getElementById('observacoes').value;
        if (observacoes) dados.observacoes = observacoes;

        // Adiciona contatos e endereços
        dados.contatos = this.obterContatos();
        dados.enderecos = this.obterEnderecos();

        try {
            let response;

            if (this.state.editandoId) {
                // Atualizar
                response = await API.put(`/cliente/${this.state.editandoId}`, dados);
            } else {
                // Criar
                response = await API.post('/cliente', dados);
            }

            if (response.sucesso) {
                this.fecharModal();
                this.carregarClientes();
                Utils.Notificacao.sucesso(response.mensagem || 'Cliente salvo com sucesso!');
            }
        } catch (error) {
            // Exibe mensagem de erro com detalhes de validação
            this.showModalError(error.data || 'Erro ao salvar cliente');
            Utils.Notificacao.erro(error.data || 'Erro ao salvar cliente');
            console.error('Erro ao salvar cliente:', error);
        }
    },

    /**
     * Deleta cliente
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja deletar este cliente?')) {
            return;
        }

        try {
            const response = await API.delete(`/cliente/${id}`);

            if (response.sucesso) {
                this.carregarClientes();
                Utils.Notificacao.sucesso(response.mensagem || 'Cliente deletado com sucesso!');
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao deletar cliente';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao deletar cliente:', error);
        }
    },

    /**
     * Adiciona linha de contato
     */
    adicionarContato() {
        const index = this.state.contatos.length;
        this.state.contatos.push({
            nome: '',
            cargo: '',
            telefone: '',
            email: ''
        });
        this.renderizarContatos();
    },

    /**
     * Remove linha de contato
     */
    removerContato(index) {
        this.state.contatos.splice(index, 1);
        this.renderizarContatos();
    },

    /**
     * Renderiza lista de contatos
     */
    renderizarContatos() {
        this.elements.listaContatos.innerHTML = '';

        this.state.contatos.forEach((contato, index) => {
            // Backend retorna 'contato' mas frontend usa 'telefone'
            const telefone = contato.contato || contato.telefone || '';

            // Extrai email da observacao se existir
            let email = contato.email || '';
            if (!email && contato.observacao && contato.observacao.startsWith('Email: ')) {
                email = contato.observacao.replace('Email: ', '');
            }

            const div = document.createElement('div');
            div.className = 'dynamic-list-item';
            div.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label>Nome do Contato</label>
                        <input type="text"
                               class="contato-nome"
                               value="${this.escapeHtml(contato.nome || '')}"
                               placeholder="Nome do contato">
                    </div>
                    <div class="form-group">
                        <label>Cargo</label>
                        <input type="text"
                               class="contato-cargo"
                               value="${this.escapeHtml(contato.cargo || '')}"
                               placeholder="Cargo">
                    </div>
                    <div class="form-group">
                        <label>Telefone</label>
                        <input type="text"
                               class="contato-telefone mask-telefone"
                               value="${this.formatarTelefone(telefone)}"
                               placeholder="(00) 00000-0000">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email"
                               class="contato-email"
                               value="${this.escapeHtml(email)}"
                               placeholder="email@exemplo.com">
                    </div>
                </div>
                <button type="button"
                        class="btn btn-small btn-danger"
                        onclick="ClienteesManager.removerContato(${index})">
                    <i class="fas fa-trash"></i> Remover
                </button>
            `;
            this.elements.listaContatos.appendChild(div);
        });

        // Reaplica máscaras
        this.aplicarMascaras();
    },

    /**
     * Obtém contatos do formulário
     */
    obterContatos() {
        const contatos = [];
        const items = this.elements.listaContatos.querySelectorAll('.dynamic-list-item');

        items.forEach(item => {
            const nome = item.querySelector('.contato-nome').value.trim();
            const cargo = item.querySelector('.contato-cargo').value.trim();
            const telefone = item.querySelector('.contato-telefone').value.replace(/\D/g, '');
            const email = item.querySelector('.contato-email').value.trim();

            // Precisa ter pelo menos nome E telefone para salvar
            if (nome && telefone) {
                const contato = {
                    nome,
                    contato: telefone,  // Mapeia telefone → contato
                    cargo: cargo || null
                };

                // Se tiver email, adiciona em observacao
                if (email) {
                    contato.observacao = `Email: ${email}`;
                }

                contatos.push(contato);
            }
        });

        return contatos;
    },

    /**
     * Adiciona linha de endereço
     */
    adicionarEndereco() {
        const index = this.state.enderecos.length;
        this.state.enderecos.push({
            tipo_endereco_id: null,
            cep: '',
            logradouro: '',
            numero: '',
            complemento: '',
            bairro: '',
            cidade_id: null,
            pais: 'Brasil'
        });
        this.renderizarEnderecos();
    },

    /**
     * Remove linha de endereço
     */
    removerEndereco(index) {
        this.state.enderecos.splice(index, 1);
        this.renderizarEnderecos();
    },

    /**
     * Renderiza lista de endereços
     */
    renderizarEnderecos() {
        this.elements.listaEnderecos.innerHTML = '';

        this.state.enderecos.forEach((endereco, index) => {
            const div = document.createElement('div');
            div.className = 'dynamic-list-item';
            div.innerHTML = `
                <div class="form-row">
                    <div class="form-group">
                        <label>Tipo de Endereço</label>
                        <select class="endereco-tipo"></select>
                    </div>
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text"
                               class="endereco-cep mask-cep"
                               value="${endereco.cep || ''}"
                               placeholder="00000-000"
                               onblur="ClienteesManager.buscarCEP(this.value, ${index})">
                    </div>
                    <div class="form-group">
                        <label>Logradouro</label>
                        <input type="text"
                               class="endereco-logradouro"
                               value="${this.escapeHtml(endereco.logradouro || '')}"
                               placeholder="Rua, Avenida, etc">
                    </div>
                    <div class="form-group">
                        <label>Número</label>
                        <input type="text"
                               class="endereco-numero"
                               value="${this.escapeHtml(endereco.numero || '')}"
                               placeholder="Número">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Complemento</label>
                        <input type="text"
                               class="endereco-complemento"
                               value="${this.escapeHtml(endereco.complemento || '')}"
                               placeholder="Apto, Sala, etc">
                    </div>
                    <div class="form-group">
                        <label>Bairro</label>
                        <input type="text"
                               class="endereco-bairro"
                               value="${this.escapeHtml(endereco.bairro || '')}"
                               placeholder="Bairro">
                    </div>
                    <div class="form-group">
                        <label>Cidade</label>
                        <div class="autocomplete-wrapper" style="position: relative;">
                            <input type="text"
                                   class="endereco-cidade-nome"
                                   placeholder="Digite para buscar..."
                                   autocomplete="off"
                                   value="">
                            <input type="hidden" class="endereco-cidade-id" value="${endereco.cidade_id || ''}">
                            <div class="autocomplete-list" style="display: none; position: absolute; z-index: 1000; background: white; border: 1px solid #ddd; max-height: 200px; overflow-y: auto; width: 100%;"></div>
                        </div>
                    </div>
                </div>
                <button type="button"
                        class="btn btn-small btn-danger"
                        onclick="ClienteesManager.removerEndereco(${index})">
                    <i class="fas fa-trash"></i> Remover
                </button>
            `;
            this.elements.listaEnderecos.appendChild(div);

            // Popular select de tipo de endereço
            const selectTipo = div.querySelector('.endereco-tipo');
            this.popularSelectTipoEndereco(selectTipo, endereco.tipo_endereco_id);

            // Configurar autocomplete de cidade
            const inputCidadeNome = div.querySelector('.endereco-cidade-nome');
            const inputCidadeId = div.querySelector('.endereco-cidade-id');
            const autocompleteList = div.querySelector('.autocomplete-list');

            this.configurarAutocompleteCidade(inputCidadeNome, inputCidadeId, autocompleteList);

            // Se já tem cidade_id, carregar o nome da cidade
            if (endereco.cidade_id) {
                this.carregarNomeCidade(endereco.cidade_id, inputCidadeNome);
            } else if (endereco.nome_cidade) {
                inputCidadeNome.value = endereco.nome_cidade;
            }
        });

        // Reaplica máscaras
        this.aplicarMascaras();
    },

    /**
     * Popular select de tipo de endereço
     */
    popularSelectTipoEndereco(select, valorSelecionado) {
        select.innerHTML = '<option value="">Selecione o tipo</option>';

        // Verifica se tiposEnderecos é um array válido
        if (!Array.isArray(this.state.tiposEnderecos)) {
            console.error('tiposEnderecos não é um array:', this.state.tiposEnderecos);
            this.state.tiposEnderecos = [];
            return;
        }

        this.state.tiposEnderecos.forEach(tipo => {
            const option = document.createElement('option');
            option.value = tipo.id;
            option.textContent = Utils.DOM.escapeHtml(tipo.nome);
            if (tipo.id == valorSelecionado) {
                option.selected = true;
            }
            select.appendChild(option);
        });
    },

    /**
     * Configura autocomplete de cidade
     */
    configurarAutocompleteCidade(inputNome, inputId, listElement) {
        let timeoutId = null;

        // Evento de digitação
        inputNome.addEventListener('input', async (e) => {
            const termo = e.target.value.trim();

            // Limpa timeout anterior
            if (timeoutId) {
                clearTimeout(timeoutId);
            }

            // Se termo for muito curto, esconde a lista
            if (termo.length < 2) {
                listElement.style.display = 'none';
                inputId.value = '';
                return;
            }

            // Debounce: aguarda 300ms após parar de digitar
            timeoutId = setTimeout(async () => {
                const cidades = await this.buscarCidades(termo);

                if (cidades.length > 0) {
                    listElement.innerHTML = '';
                    listElement.style.display = 'block';

                    cidades.forEach(cidade => {
                        const item = document.createElement('div');
                        item.style.cssText = 'padding: 8px; cursor: pointer; border-bottom: 1px solid #eee;';
                        item.textContent = `${cidade.nome} - ${cidade.estado || ''}`;
                        item.dataset.cidadeId = cidade.id;
                        item.dataset.cidadeNome = cidade.nome;

                        // Hover
                        item.addEventListener('mouseenter', () => {
                            item.style.backgroundColor = '#f0f0f0';
                        });
                        item.addEventListener('mouseleave', () => {
                            item.style.backgroundColor = 'white';
                        });

                        // Click
                        item.addEventListener('click', () => {
                            inputNome.value = cidade.nome;
                            inputId.value = cidade.id;
                            listElement.style.display = 'none';
                        });

                        listElement.appendChild(item);
                    });
                } else {
                    listElement.innerHTML = '<div style="padding: 8px; color: #999;">Nenhuma cidade encontrada</div>';
                    listElement.style.display = 'block';
                }
            }, 300);
        });

        // Fecha a lista ao clicar fora
        document.addEventListener('click', (e) => {
            if (!inputNome.contains(e.target) && !listElement.contains(e.target)) {
                listElement.style.display = 'none';
            }
        });

        // Fecha a lista ao perder o foco
        inputNome.addEventListener('blur', () => {
            setTimeout(() => {
                listElement.style.display = 'none';
            }, 200);
        });
    },

    /**
     * Carrega nome da cidade por ID
     */
    async carregarNomeCidade(cidadeId, inputNome) {
        const cidade = await this.buscarCidadePorId(cidadeId);
        if (cidade && cidade.nome) {
            inputNome.value = cidade.nome;
        }
    },

    /**
     * Obtém endereços do formulário
     */
    obterEnderecos() {
        const enderecos = [];
        const items = this.elements.listaEnderecos.querySelectorAll('.dynamic-list-item');

        items.forEach(item => {
            const cep = item.querySelector('.endereco-cep').value.replace(/\D/g, '');
            const logradouro = item.querySelector('.endereco-logradouro').value.trim();
            const numero = item.querySelector('.endereco-numero').value.trim();
            const complemento = item.querySelector('.endereco-complemento').value.trim();
            const bairro = item.querySelector('.endereco-bairro').value.trim();
            const cidadeId = item.querySelector('.endereco-cidade-id').value; // Mudado para campo oculto
            const tipoEnderecoId = item.querySelector('.endereco-tipo').value;

            if (cep || logradouro || cidadeId) {
                enderecos.push({
                    tipo_endereco_id: tipoEnderecoId ? parseInt(tipoEnderecoId) : null,
                    cep,
                    logradouro,
                    numero,
                    complemento,
                    bairro,
                    cidade_id: cidadeId ? parseInt(cidadeId) : null,
                    pais: 'Brasil'
                });
            }
        });

        return enderecos;
    },

    /**
     * Busca CEP via ViaCEP API
     */
    async buscarCEP(cep, index) {
        cep = cep.replace(/\D/g, '');

        if (cep.length !== 8) {
            return;
        }

        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await response.json();

            if (data.erro) {
                Utils.Notificacao.aviso('CEP não encontrado');
                return;
            }

            // Atualiza o endereço no state
            this.state.enderecos[index] = {
                ...this.state.enderecos[index],
                cep: cep,
                logradouro: data.logradouro || '',
                bairro: data.bairro || '',
                pais: 'Brasil'
            };

            // Re-renderiza os endereços
            this.renderizarEnderecos();

            Utils.Notificacao.sucesso('CEP encontrado!');
        } catch (error) {
            console.error('Erro ao buscar CEP:', error);
            Utils.Notificacao.erro('Erro ao buscar CEP');
        }
    },

    /**
     * Formata CPF
     */
    formatarCPF(cpf) {
        if (!cpf) return '';
        cpf = cpf.replace(/\D/g, '');
        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    },

    /**
     * Formata CNPJ
     */
    formatarCNPJ(cnpj) {
        if (!cnpj) return '';
        cnpj = cnpj.replace(/\D/g, '');
        return cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    },

    /**
     * Formata Telefone
     */
    formatarTelefone(telefone) {
        if (!telefone) return '';
        telefone = telefone.replace(/\D/g, '');

        if (telefone.length === 11) {
            return telefone.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        } else if (telefone.length === 10) {
            return telefone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        }

        return telefone;
    },

    /**
     * Aplica máscaras nos inputs
     */
    aplicarMascaras() {
        // Máscara de CPF
        document.querySelectorAll('.mask-cpf, #cpf').forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                value = value.substring(0, 11);
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            });
        });

        // Máscara de CNPJ
        document.querySelectorAll('.mask-cnpj, #cnpj').forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                value = value.substring(0, 14);
                value = value.replace(/(\d{2})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1/$2');
                value = value.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            });
        });

        // Máscara de Telefone
        document.querySelectorAll('.mask-telefone, #telefone, #celular, .contato-telefone').forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                value = value.substring(0, 11);

                if (value.length <= 10) {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{4})(\d)/, '$1-$2');
                } else {
                    value = value.replace(/(\d{2})(\d)/, '($1) $2');
                    value = value.replace(/(\d{5})(\d)/, '$1-$2');
                }

                e.target.value = value;
            });
        });

        // Máscara de CEP
        document.querySelectorAll('.mask-cep, .endereco-cep').forEach(input => {
            input.addEventListener('input', (e) => {
                let value = e.target.value.replace(/\D/g, '');
                value = value.substring(0, 8);
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            });
        });
    },

    /**
     * Controla campos condicionais baseado no tipo_pessoa
     */
    controlarCamposCondicionais() {
        const tipoPessoa = this.elements.tipoPessoa.value;
        const camposPF = document.querySelectorAll('.campo-pf');
        const camposPJ = document.querySelectorAll('.campo-pj');

        // Esconde todos primeiro
        camposPF.forEach(campo => {
            campo.classList.remove('show');
            const input = campo.querySelector('input, select');
            if (input) input.removeAttribute('required');
        });
        camposPJ.forEach(campo => {
            campo.classList.remove('show');
            const input = campo.querySelector('input, select');
            if (input) input.removeAttribute('required');
        });

        // Mostra os campos apropriados
        if (tipoPessoa === 'PF') {
            camposPF.forEach(campo => {
                campo.classList.add('show');
            });
            const cpfInput = document.getElementById('cpf');
            if (cpfInput) cpfInput.setAttribute('required', 'required');
        } else if (tipoPessoa === 'PJ') {
            camposPJ.forEach(campo => {
                campo.classList.add('show');
            });
            const cnpjInput = document.getElementById('cnpj');
            if (cnpjInput) cnpjInput.setAttribute('required', 'required');
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalForm.classList.remove('show');
        this.elements.formCliente.reset();
        this.state.editandoId = null;
        this.state.contatos = [];
        this.state.enderecos = [];
        this.elements.listaContatos.innerHTML = '';
        this.elements.listaEnderecos.innerHTML = '';
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
                'tipo_pessoa': 'tipoPessoa',
                'nome': 'nome',
                'cpf': 'cpf',
                'cnpj': 'cnpj',
                'rg': 'rg',
                'data_nascimento': 'dataNascimento',
                'razao_social': 'razaoSocial',
                'inscricao_estadual': 'inscricaoEstadual',
                'inscricao_municipal': 'inscricaoMunicipal',
                'tipo_contribuinte': 'tipoContribuinte',
                'email': 'email',
                'telefone': 'telefone',
                'celular': 'celular',
                'observacoes': 'observacoes',
                'ativo': 'ativo'
            };

            Utils.Errors.destacarCampos(error.erros, mapeamentoCampos);
        }
    },

    /**
     * Formata tipo de pessoa
     */
    formatarTipoPessoa(tipo) {
        const tipos = {
            'PF': 'Pessoa Física',
            'PJ': 'Pessoa Jurídica'
        };
        return tipos[tipo] || tipo;
    },

    /**
     * Retorna classe CSS baseada no status
     */
    getStatusClass(ativo) {
        return ativo ? 'badge-success' : 'badge-secondary';
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
    ClienteesManager.init();
});

// Expõe globalmente para uso nos event handlers inline
window.ClienteesManager = ClienteesManager;
