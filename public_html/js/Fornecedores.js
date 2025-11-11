/**
 * Gerenciador de Fornecedores
 * Implementa CRUD completo de fornecedores com validação de permissões ACL
 */

const FornecedoresManager = {
    // Estado da aplicação
    state: {
        fornecedores: [],
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
            status: 'ativo'
        },
        editandoId: null,
        contatos: [],
        enderecos: []
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
        btnLimparFiltros: document.getElementById('btnLimparFiltros'),
        filtroBusca: document.getElementById('filtroBusca'),
        filtroTipoPessoa: document.getElementById('filtroTipoPessoa'),
        filtroStatus: document.getElementById('filtroStatus'),
        modalForm: document.getElementById('modalForm'),
        modalTitle: document.getElementById('modalTitle'),
        closeModal: document.getElementById('closeModal'),
        btnCancelar: document.getElementById('btnCancelar'),
        formFornecedor: document.getElementById('formFornecedor'),
        modalError: document.getElementById('modalError'),
        modalErrorMessage: document.getElementById('modalErrorMessage'),
        pagination: document.getElementById('pagination'),
        btnPrevious: document.getElementById('btnPrevious'),
        btnNext: document.getElementById('btnNext'),
        pageInfo: document.getElementById('pageInfo'),
        logoutBtn: document.getElementById('logoutBtn'),
        contatosList: document.getElementById('contatosList'),
        btnAdicionarContato: document.getElementById('btnAdicionarContato'),
        enderecosList: document.getElementById('enderecosList'),
        btnAdicionarEndereco: document.getElementById('btnAdicionarEndereco'),
        tipoPessoa: document.getElementById('tipoPessoa'),
        camposPF: document.getElementById('camposPF'),
        camposPJ: document.getElementById('camposPJ')
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

        await this.carregarFornecedores();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        this.elements.btnNovo?.addEventListener('click', () => this.abrirModalCriar());
        this.elements.btnFiltrar?.addEventListener('click', () => this.aplicarFiltros());
        this.elements.btnLimparFiltros?.addEventListener('click', () => this.limparFiltros());
        this.elements.closeModal?.addEventListener('click', () => this.fecharModal());
        this.elements.btnCancelar?.addEventListener('click', () => this.fecharModal());
        this.elements.formFornecedor?.addEventListener('submit', (e) => this.salvarFornecedor(e));
        this.elements.btnPrevious?.addEventListener('click', () => this.paginaAnterior());
        this.elements.btnNext?.addEventListener('click', () => this.proximaPagina());
        this.elements.btnAdicionarContato?.addEventListener('click', () => this.adicionarContato());
        this.elements.btnAdicionarEndereco?.addEventListener('click', () => this.adicionarEndereco());

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
     * Carrega fornecedores
     */
    async carregarFornecedores() {
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

            if (this.state.filtros.status) {
                params.append('status', this.state.filtros.status);
            }

            const response = await API.get(`/fornecedor?${params.toString()}`);

            if (response.sucesso) {
                this.state.fornecedores = response.dados?.itens || [];
                this.state.paginacao.total = response.dados?.paginacao?.total || 0;
                this.state.paginacao.totalPaginas = response.dados?.paginacao?.total_paginas || 0;

                this.renderizarTabela(this.state.fornecedores);
                this.renderizarPaginacao(
                    this.state.paginacao.total,
                    this.state.paginacao.pagina,
                    this.state.paginacao.porPagina
                );
            }
        } catch (error) {
            // Usa Utils para formatar mensagem de erro
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar fornecedores';

            this.showError(mensagemErro);
            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar fornecedores:', error);
        }
    },

    /**
     * Renderiza a tabela
     */
    renderizarTabela(dados) {
        this.elements.loadingContainer.style.display = 'none';
        this.elements.errorContainer.style.display = 'none';

        if (dados.length === 0) {
            this.elements.tableContainer.style.display = 'block';
            this.elements.noData.style.display = 'block';
            this.elements.tableBody.innerHTML = '';
            return;
        }

        this.elements.tableContainer.style.display = 'block';
        this.elements.noData.style.display = 'none';

        this.elements.tableBody.innerHTML = '';

        dados.forEach(fornecedor => {
            const tr = document.createElement('tr');

            const documento = fornecedor.tipo_pessoa === 'PF' ?
                this.formatarCPF(fornecedor.cpf) :
                this.formatarCNPJ(fornecedor.cnpj);

            tr.innerHTML = `
                <td>${fornecedor.id}</td>
                <td>${this.escapeHtml(fornecedor.nome)}</td>
                <td>${this.formatarTipoPessoa(fornecedor.tipo_pessoa)}</td>
                <td>${documento || '-'}</td>
                <td>${this.escapeHtml(fornecedor.email) || '-'}</td>
                <td>${this.formatarTelefone(fornecedor.telefone) || '-'}</td>
                <td>
                    <span class="badge ${this.getStatusClass(fornecedor.status)}">
                        ${this.formatarStatus(fornecedor.status)}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.editar ?
                            `<button class="btn btn-small" onclick="FornecedoresManager.abrirModalEditar(${fornecedor.id})">Editar</button>` :
                            ''}
                        ${this.state.permissoes.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="FornecedoresManager.deletarFornecedor(${fornecedor.id})">Deletar</button>` :
                            ''}
                    </div>
                </td>
            `;

            this.elements.tableBody.appendChild(tr);
        });
    },

    /**
     * Renderiza paginação
     */
    renderizarPaginacao(total, paginaAtual, porPagina) {
        const inicio = (paginaAtual - 1) * porPagina + 1;
        const fim = Math.min(paginaAtual * porPagina, total);

        this.elements.pageInfo.textContent = `${inicio}-${fim} de ${total}`;

        this.elements.btnPrevious.disabled = paginaAtual <= 1;
        this.elements.btnNext.disabled = paginaAtual >= this.state.paginacao.totalPaginas;
    },

    /**
     * Aplica filtros
     */
    aplicarFiltros() {
        this.state.filtros.busca = this.elements.filtroBusca.value;
        this.state.filtros.tipo_pessoa = this.elements.filtroTipoPessoa.value;
        this.state.filtros.status = this.elements.filtroStatus.value;
        this.state.paginacao.pagina = 1;
        this.carregarFornecedores();
    },

    /**
     * Limpa filtros
     */
    limparFiltros() {
        this.elements.filtroBusca.value = '';
        this.elements.filtroTipoPessoa.value = '';
        this.elements.filtroStatus.value = 'ativo';
        this.state.filtros.busca = '';
        this.state.filtros.tipo_pessoa = '';
        this.state.filtros.status = 'ativo';
        this.state.paginacao.pagina = 1;
        this.carregarFornecedores();
    },

    /**
     * Página anterior
     */
    paginaAnterior() {
        if (this.state.paginacao.pagina > 1) {
            this.state.paginacao.pagina--;
            this.carregarFornecedores();
        }
    },

    /**
     * Próxima página
     */
    proximaPagina() {
        if (this.state.paginacao.pagina < this.state.paginacao.totalPaginas) {
            this.state.paginacao.pagina++;
            this.carregarFornecedores();
        }
    },

    /**
     * Abre modal para criar novo fornecedor
     */
    abrirModalCriar() {
        this.state.editandoId = null;
        this.state.contatos = [];
        this.state.enderecos = [];

        this.elements.modalTitle.textContent = 'Novo Fornecedor';
        this.elements.formFornecedor.reset();
        document.getElementById('status').value = 'ativo';
        document.getElementById('tipoPessoa').value = 'PF';

        this.elements.contatosList.innerHTML = '';
        this.elements.enderecosList.innerHTML = '';

        this.controlarCamposCondicionais();
        this.aplicarMascaras();

        this.elements.modalError.style.display = 'none';
        Utils.Errors.limparCampos();
        this.elements.modalForm.classList.add('show');
    },

    /**
     * Abre modal para editar fornecedor
     */
    async abrirModalEditar(id) {
        try {
            const response = await API.get(`/fornecedor/${id}`);

            if (response.sucesso && response.dados) {
                const fornecedor = response.dados;
                this.state.editandoId = id;

                this.elements.modalTitle.textContent = 'Editar Fornecedor';

                // Dados básicos
                document.getElementById('fornecedorId').value = fornecedor.id;
                document.getElementById('tipoPessoa').value = fornecedor.tipo_pessoa || 'PF';
                document.getElementById('nome').value = fornecedor.nome || '';
                document.getElementById('cpf').value = fornecedor.cpf || '';
                document.getElementById('cnpj').value = fornecedor.cnpj || '';
                document.getElementById('razaoSocial').value = fornecedor.razao_social || '';
                document.getElementById('inscricaoEstadual').value = fornecedor.inscricao_estadual || '';
                document.getElementById('inscricaoMunicipal').value = fornecedor.inscricao_municipal || '';
                document.getElementById('email').value = fornecedor.email || '';
                document.getElementById('telefone').value = fornecedor.telefone || '';
                document.getElementById('celular').value = fornecedor.celular || '';
                document.getElementById('site').value = fornecedor.site || '';
                document.getElementById('observacoes').value = fornecedor.observacoes || '';
                document.getElementById('status').value = fornecedor.status || 'ativo';

                // Controla campos condicionais
                this.controlarCamposCondicionais();

                // Carrega contatos
                this.state.contatos = fornecedor.contatos || [];
                this.renderizarContatos();

                // Carrega endereços
                this.state.enderecos = fornecedor.enderecos || [];
                this.renderizarEnderecos();

                this.aplicarMascaras();

                this.elements.modalError.style.display = 'none';
                Utils.Errors.limparCampos();
                this.elements.modalForm.classList.add('show');
            }
        } catch (error) {
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar fornecedor';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao carregar fornecedor:', error);
        }
    },

    /**
     * Salva fornecedor (criar ou editar)
     */
    async salvarFornecedor(e) {
        e.preventDefault();

        const tipoPessoa = document.getElementById('tipoPessoa').value;

        const dados = {
            tipo_pessoa: tipoPessoa,
            nome: document.getElementById('nome').value,
            status: document.getElementById('status').value
        };

        // Campos específicos por tipo de pessoa
        if (tipoPessoa === 'PF') {
            const cpf = document.getElementById('cpf').value;
            if (cpf) {
                if (!this.validarCPF(cpf)) {
                    Utils.Notificacao.erro('CPF inválido');
                    return;
                }
                dados.cpf = cpf.replace(/\D/g, '');
            }
        } else if (tipoPessoa === 'PJ') {
            const cnpj = document.getElementById('cnpj').value;
            if (cnpj) {
                if (!this.validarCNPJ(cnpj)) {
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
        }

        // Campos opcionais
        const email = document.getElementById('email').value;
        if (email) {
            if (!this.validarEmail(email)) {
                Utils.Notificacao.erro('Email inválido');
                return;
            }
            dados.email = email;
        }

        const telefone = document.getElementById('telefone').value;
        if (telefone) dados.telefone = telefone.replace(/\D/g, '');

        const celular = document.getElementById('celular').value;
        if (celular) dados.celular = celular.replace(/\D/g, '');

        const site = document.getElementById('site').value;
        if (site) dados.site = site;

        const observacoes = document.getElementById('observacoes').value;
        if (observacoes) dados.observacoes = observacoes;

        // Adiciona contatos e endereços
        dados.contatos = this.obterContatos();
        dados.enderecos = this.obterEnderecos();

        try {
            let response;

            if (this.state.editandoId) {
                // Atualizar
                response = await API.put(`/fornecedor/${this.state.editandoId}`, dados);
            } else {
                // Criar
                response = await API.post('/fornecedor', dados);
            }

            if (response.sucesso) {
                this.fecharModal();
                this.carregarFornecedores();
                Utils.Notificacao.sucesso(response.mensagem || 'Fornecedor salvo com sucesso!');
            }
        } catch (error) {
            this.showModalError(error.data || 'Erro ao salvar fornecedor');
            console.error('Erro ao salvar fornecedor:', error);
        }
    },

    /**
     * Deleta fornecedor
     */
    async deletarFornecedor(id) {
        if (!confirm('Tem certeza que deseja deletar este fornecedor?')) {
            return;
        }

        try {
            const response = await API.delete(`/fornecedor/${id}`);

            if (response.sucesso) {
                this.carregarFornecedores();
                Utils.Notificacao.sucesso(response.mensagem || 'Fornecedor deletado com sucesso!');
            }
        } catch (error) {
            const mensagemErro = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao deletar fornecedor';

            Utils.Notificacao.erro(mensagemErro);
            console.error('Erro ao deletar fornecedor:', error);
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
        this.elements.contatosList.innerHTML = '';

        this.state.contatos.forEach((contato, index) => {
            const div = document.createElement('div');
            div.className = 'contato-item';
            div.innerHTML = `
                <div class="form-grid">
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
                               value="${this.formatarTelefone(contato.telefone || '')}"
                               placeholder="(00) 00000-0000">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email"
                               class="contato-email"
                               value="${this.escapeHtml(contato.email || '')}"
                               placeholder="email@exemplo.com">
                    </div>
                </div>
                <button type="button"
                        class="btn btn-small btn-danger"
                        onclick="FornecedoresManager.removerContato(${index})">
                    Remover
                </button>
            `;
            this.elements.contatosList.appendChild(div);
        });

        // Reaplica máscaras
        this.aplicarMascaras();
    },

    /**
     * Obtém contatos do formulário
     */
    obterContatos() {
        const contatos = [];
        const items = this.elements.contatosList.querySelectorAll('.contato-item');

        items.forEach(item => {
            const nome = item.querySelector('.contato-nome').value.trim();
            const cargo = item.querySelector('.contato-cargo').value.trim();
            const telefone = item.querySelector('.contato-telefone').value.replace(/\D/g, '');
            const email = item.querySelector('.contato-email').value.trim();

            if (nome || cargo || telefone || email) {
                contatos.push({ nome, cargo, telefone, email });
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
            tipo: 'comercial',
            cep: '',
            logradouro: '',
            numero: '',
            complemento: '',
            bairro: '',
            cidade: '',
            estado: '',
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
        this.elements.enderecosList.innerHTML = '';

        this.state.enderecos.forEach((endereco, index) => {
            const div = document.createElement('div');
            div.className = 'endereco-item';
            div.innerHTML = `
                <div class="form-grid">
                    <div class="form-group">
                        <label>Tipo</label>
                        <select class="endereco-tipo">
                            <option value="comercial" ${endereco.tipo === 'comercial' ? 'selected' : ''}>Comercial</option>
                            <option value="residencial" ${endereco.tipo === 'residencial' ? 'selected' : ''}>Residencial</option>
                            <option value="entrega" ${endereco.tipo === 'entrega' ? 'selected' : ''}>Entrega</option>
                            <option value="cobranca" ${endereco.tipo === 'cobranca' ? 'selected' : ''}>Cobrança</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>CEP</label>
                        <input type="text"
                               class="endereco-cep mask-cep"
                               value="${endereco.cep || ''}"
                               placeholder="00000-000"
                               onblur="FornecedoresManager.buscarCEP(this.value, ${index})">
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
                        <input type="text"
                               class="endereco-cidade"
                               value="${this.escapeHtml(endereco.cidade || '')}"
                               placeholder="Cidade">
                    </div>
                    <div class="form-group">
                        <label>Estado</label>
                        <input type="text"
                               class="endereco-estado"
                               value="${this.escapeHtml(endereco.estado || '')}"
                               placeholder="UF"
                               maxlength="2">
                    </div>
                </div>
                <button type="button"
                        class="btn btn-small btn-danger"
                        onclick="FornecedoresManager.removerEndereco(${index})">
                    Remover
                </button>
            `;
            this.elements.enderecosList.appendChild(div);
        });

        // Reaplica máscaras
        this.aplicarMascaras();
    },

    /**
     * Obtém endereços do formulário
     */
    obterEnderecos() {
        const enderecos = [];
        const items = this.elements.enderecosList.querySelectorAll('.endereco-item');

        items.forEach(item => {
            const tipo = item.querySelector('.endereco-tipo').value;
            const cep = item.querySelector('.endereco-cep').value.replace(/\D/g, '');
            const logradouro = item.querySelector('.endereco-logradouro').value.trim();
            const numero = item.querySelector('.endereco-numero').value.trim();
            const complemento = item.querySelector('.endereco-complemento').value.trim();
            const bairro = item.querySelector('.endereco-bairro').value.trim();
            const cidade = item.querySelector('.endereco-cidade').value.trim();
            const estado = item.querySelector('.endereco-estado').value.trim().toUpperCase();

            if (cep || logradouro || cidade || estado) {
                enderecos.push({
                    tipo,
                    cep,
                    logradouro,
                    numero,
                    complemento,
                    bairro,
                    cidade,
                    estado,
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
                cidade: data.localidade || '',
                estado: data.uf || '',
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
     * Valida CPF
     */
    validarCPF(cpf) {
        return Utils.Validation.cpf(cpf);
    },

    /**
     * Valida CNPJ
     */
    validarCNPJ(cnpj) {
        return Utils.Validation.cnpj(cnpj);
    },

    /**
     * Valida Email
     */
    validarEmail(email) {
        return Utils.Validation.email(email);
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

        if (tipoPessoa === 'PF') {
            // Mostra campos de PF
            if (this.elements.camposPF) {
                this.elements.camposPF.style.display = 'block';
            }
            // Esconde campos de PJ
            if (this.elements.camposPJ) {
                this.elements.camposPJ.style.display = 'none';
            }
        } else if (tipoPessoa === 'PJ') {
            // Esconde campos de PF
            if (this.elements.camposPF) {
                this.elements.camposPF.style.display = 'none';
            }
            // Mostra campos de PJ
            if (this.elements.camposPJ) {
                this.elements.camposPJ.style.display = 'block';
            }
        }
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalForm.classList.remove('show');
        this.elements.formFornecedor.reset();
        this.state.editandoId = null;
        this.state.contatos = [];
        this.state.enderecos = [];
        this.elements.contatosList.innerHTML = '';
        this.elements.enderecosList.innerHTML = '';
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
                'razao_social': 'razaoSocial',
                'inscricao_estadual': 'inscricaoEstadual',
                'inscricao_municipal': 'inscricaoMunicipal',
                'email': 'email',
                'telefone': 'telefone',
                'celular': 'celular',
                'site': 'site',
                'observacoes': 'observacoes',
                'status': 'status'
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
     * Formata status
     */
    formatarStatus(status) {
        const statusMap = {
            'ativo': 'Ativo',
            'inativo': 'Inativo',
            'bloqueado': 'Bloqueado'
        };
        return statusMap[status] || status;
    },

    /**
     * Retorna classe CSS baseada no status
     */
    getStatusClass(status) {
        const classes = {
            'ativo': 'badge-success',
            'inativo': 'badge-secondary',
            'bloqueado': 'badge-danger'
        };
        return classes[status] || 'badge-secondary';
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
    FornecedoresManager.init();
});

// Expõe globalmente para uso nos event handlers inline
window.FornecedoresManager = FornecedoresManager;
