/**
 * Gerenciador de WhatsApp
 * Implementa gerenciamento completo de conexão, fila, histórico e configurações do WhatsApp
 */

const WhatsAppManager = {
    // Estado da aplicação
    state: {
        conectado: false,
        statusInstancia: null,
        qrCode: null,
        permissoes: {
            visualizar: true,
            criar: true,
            editar: false,
            deletar: false
        },
        intervalStatusCheck: null,
        tabAtual: 'conexao',
        estatisticas: {
            pendentes: 0,
            processando: 0,
            enviados: 0,
            erros: 0
        }
    },

    // Elementos DOM
    elements: {
        // Menu lateral
        menuItems: document.querySelectorAll('.list-group-item[data-tab]'),

        // Nome do usuário
        nomeUsuario: document.getElementById('nome-usuario'),
        listaPermissoes: document.getElementById('lista-permissoes'),
        menuConfiguracoes: document.getElementById('menu-configuracoes'),
        lockConfiguracoes: document.getElementById('lock-configuracoes'),

        // Tabs
        tabConexao: document.getElementById('tab-conexao'),
        tabTeste: document.getElementById('tab-teste'),
        tabFila: document.getElementById('tab-fila'),
        tabHistorico: document.getElementById('tab-historico'),
        tabConfiguracoes: document.getElementById('tab-configuracoes'),

        // Status da instância
        statusInstanciaContainer: document.getElementById('status-instancia-container'),

        // Fila
        tabelaFila: document.getElementById('tabela-fila'),
        statPendentes: document.getElementById('stat-pendentes'),
        statProcessando: document.getElementById('stat-processando'),
        statEnviados: document.getElementById('stat-enviados'),
        statErros: document.getElementById('stat-erros'),

        // Histórico
        tabelaHistorico: document.getElementById('tabela-historico'),
        filtroDataInicio: document.getElementById('filtro-data-inicio'),
        filtroDataFim: document.getElementById('filtro-data-fim'),
        filtroStatus: document.getElementById('filtro-status'),

        // Configurações
        containerConfiguracoes: document.getElementById('container-configuracoes'),

        // Formulário de teste
        formTesteEnvio: document.getElementById('form-teste-envio'),
        tipoDestinatario: document.getElementById('tipo-destinatario'),
        selectEntidade: document.getElementById('select-entidade'),
        inputNumero: document.getElementById('input-numero'),
        tipoMensagem: document.getElementById('tipo-mensagem'),
        campoTexto: document.getElementById('campo-texto'),
        campoUrl: document.getElementById('campo-url'),
        campoCaption: document.getElementById('campo-caption')
    },

    /**
     * Inicializa o gerenciador
     */
    async init() {
        console.log('WhatsApp Manager iniciado - v3.0');

        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            return;
        }

        // Configura event listeners
        this.setupEventListeners();

        // Carrega dados iniciais
        await this.carregarNomeUsuario();
        await this.carregarPermissoes();
        await this.verificarStatusInstancia();
        await this.carregarDashboard();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        // Navegação entre tabs
        this.elements.menuItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const tab = item.getAttribute('data-tab');
                this.trocarTab(tab);
            });
        });

        // Formulário de teste de envio
        if (this.elements.formTesteEnvio) {
            this.elements.formTesteEnvio.addEventListener('submit', (e) => {
                e.preventDefault();
                this.enviarMensagemTeste();
            });
        }

        // Tipo de destinatário
        if (this.elements.tipoDestinatario) {
            this.elements.tipoDestinatario.addEventListener('change', () => {
                this.alterarTipoDestinatario();
            });
        }

        // Tipo de mensagem
        if (this.elements.tipoMensagem) {
            this.elements.tipoMensagem.addEventListener('change', () => {
                this.alterarTipoMensagem();
            });
        }

        // Filtros de histórico
        const btnFiltrarHistorico = document.querySelector('#tab-historico .btn-primary');
        if (btnFiltrarHistorico) {
            btnFiltrarHistorico.addEventListener('click', () => {
                this.carregarHistorico();
            });
        }

        // Botão atualizar fila
        const btnAtualizarFila = document.querySelector('#tab-fila .btn-outline-primary');
        if (btnAtualizarFila) {
            btnAtualizarFila.addEventListener('click', () => {
                this.carregarFila();
            });
        }
    },

    /**
     * Carrega nome do usuário
     */
    async carregarNomeUsuario() {
        const usuario = API.getUser();

        if (usuario && usuario.nome) {
            if (this.elements.nomeUsuario) {
                this.elements.nomeUsuario.textContent = usuario.nome;
            }
        } else {
            try {
                const response = await API.get('/me');

                if (response.sucesso) {
                    const user = response.dados?.usuario || response.dados?.user || response.dados;

                    if (user && user.nome && this.elements.nomeUsuario) {
                        this.elements.nomeUsuario.textContent = user.nome;
                        API.setUser(user);
                    }
                }
            } catch (error) {
                console.error('Erro ao carregar nome do usuário:', error);
                if (this.elements.nomeUsuario) {
                    this.elements.nomeUsuario.textContent = 'Usuário';
                }
            }
        }
    },

    /**
     * Carrega permissões do usuário
     */
    async carregarPermissoes() {
        if (!this.elements.listaPermissoes) return;

        this.elements.listaPermissoes.innerHTML = '<li class="mb-2"><i class="fas fa-spinner fa-spin"></i> Carregando...</li>';

        try {
            // Aguarda as permissões serem carregadas pelo sidebar
            const permissoes = await aguardarPermissoes();

            if (permissoes) {
                // Atualiza state com permissões do ACL
                this.state.permissoes = {
                    visualizar: permissoes.includes('whatsapp.acessar'),
                    criar: permissoes.includes('whatsapp.alterar'),
                    editar: permissoes.includes('whatsapp.alterar'),
                    deletar: permissoes.includes('whatsapp.deletar')
                };

                // Renderiza as permissões na UI
                this.renderizarPermissoes(permissoes);
            } else {
                this.elements.listaPermissoes.innerHTML = '<li class="text-danger"><i class="fas fa-exclamation-circle"></i> Erro ao carregar</li>';
            }

            // Verifica se não tem permissão de visualizar
            if (!this.state.permissoes.visualizar) {
                API.showError('Você não tem permissão para acessar o WhatsApp');
                window.location.href = './home.html';
                return;
            }

        } catch (error) {
            console.error('Erro ao carregar permissões:', error);
            this.elements.listaPermissoes.innerHTML = '<li class="text-danger"><i class="fas fa-exclamation-circle"></i> Erro ao carregar</li>';
        }
    },

    /**
     * Renderiza permissões do usuário
     */
    renderizarPermissoes(permissoesArray) {
        if (!this.elements.listaPermissoes) return;

        const permissoes = [];

        // Perfil do usuário (carrega do API.getUser())
        const usuario = API.getUser();
        if (usuario && (usuario.tipo_usuario || usuario.role)) {
            const tipo = usuario.tipo_usuario || usuario.role;
            permissoes.push({
                icone: 'fa-user-shield',
                texto: `Perfil: ${Utils.String.capitalize(tipo)}`,
                classe: 'text-primary'
            });
        }

        // Permissões baseadas no ACL
        if (permissoesArray.includes('whatsapp.acessar')) {
            permissoes.push({
                icone: 'fa-eye',
                texto: 'Acessar WhatsApp',
                classe: 'text-info'
            });
        }

        if (permissoesArray.includes('whatsapp.alterar')) {
            permissoes.push({
                icone: 'fa-edit',
                texto: 'Alterar Configurações',
                classe: 'text-success'
            });
        }

        if (permissoesArray.includes('whatsapp.deletar')) {
            permissoes.push({
                icone: 'fa-trash',
                texto: 'Deletar Mensagens',
                classe: 'text-warning'
            });
        }

        // Renderiza
        let html = '';
        if (permissoes.length > 0) {
            permissoes.forEach(perm => {
                html += `
                    <li class="mb-2 ${perm.classe}">
                        <i class="fas ${perm.icone}"></i> ${perm.texto}
                    </li>
                `;
            });
        } else {
            html = '<li class="text-muted"><i class="fas fa-info-circle"></i> Nenhuma permissão específica</li>';
        }

        this.elements.listaPermissoes.innerHTML = html;

        // Desabilita menu de configurações se não tiver permissão de alterar
        if (!this.state.permissoes.editar) {
            if (this.elements.menuConfiguracoes) {
                this.elements.menuConfiguracoes.classList.add('permissao-negada');
            }
            if (this.elements.lockConfiguracoes) {
                this.elements.lockConfiguracoes.style.display = 'block';
            }
        }
    },

    /**
     * Troca de tab
     */
    trocarTab(tab) {
        // Remove active de todos os itens do menu
        this.elements.menuItems.forEach(item => {
            item.classList.remove('active');
        });

        // Adiciona active no item clicado
        const itemAtivo = document.querySelector(`.list-group-item[data-tab="${tab}"]`);
        if (itemAtivo) {
            itemAtivo.classList.add('active');
        }

        // Esconde todas as tabs
        const allTabs = document.querySelectorAll('.tab-pane-custom');
        allTabs.forEach(t => {
            t.style.display = 'none';
        });

        // Mostra a tab selecionada
        const tabElement = document.getElementById(`tab-${tab}`);
        if (tabElement) {
            tabElement.style.display = 'block';
        }

        this.state.tabAtual = tab;

        // Carrega dados da tab
        switch(tab) {
            case 'conexao':
                this.verificarStatusInstancia();
                break;
            case 'fila':
                this.carregarFila();
                break;
            case 'historico':
                this.carregarHistorico();
                break;
            case 'configuracoes':
                this.carregarConfiguracoes();
                break;
        }
    },

    /**
     * Verifica status da instância
     */
    async verificarStatusInstancia() {
        if (!this.elements.statusInstanciaContainer) return;

        try {
            const response = await API.get('/whatsapp/conexao/status');

            if (response.sucesso) {
                this.atualizarStatusConexao(response.dados);
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao verificar status');
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
            this.mostrarErro('Erro ao verificar status da conexão');
        }
    },

    /**
     * Atualiza UI do status da conexão
     */
    atualizarStatusConexao(dados) {
        if (!this.elements.statusInstanciaContainer) return;

        let html = '';

        if (dados.conectado) {
            // Conectado
            html = `
                <div class="alert alert-success" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-check-circle"></i> WhatsApp Conectado</h5>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Telefone:</strong> ${Utils.DOM.escapeHtml(dados.telefone || 'N/A')}</p>
                            <p class="mb-1"><strong>Nome:</strong> ${Utils.DOM.escapeHtml(dados.nome || 'N/A')}</p>
                        </div>
                        <div class="col-md-6 text-end">
                            <button id="btn-desconectar" class="btn btn-danger">
                                <i class="fas fa-power-off"></i> Desconectar
                            </button>
                        </div>
                    </div>
                </div>
            `;

            // Para auto-refresh de QR code
            if (this.state.intervalStatusCheck) {
                clearInterval(this.state.intervalStatusCheck);
                this.state.intervalStatusCheck = null;
            }

        } else if (dados.status === 'qrcode' && dados.qr_code) {
            // Aguardando QR Code
            html = `
                <div class="alert alert-warning" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-qrcode"></i> Aguardando Conexão</h5>
                    <p>Escaneie o QR Code abaixo com seu WhatsApp para conectar:</p>
                    <div class="text-center">
                        <div class="qr-container">
                            <img src="${dados.qr_code}" alt="QR Code" class="img-fluid" style="max-width: 300px;">
                        </div>
                        <p class="mt-3 text-muted"><small>O QR Code é atualizado automaticamente a cada 5 segundos</small></p>
                    </div>
                </div>
            `;

            // Auto-refresh QR code
            if (!this.state.intervalStatusCheck) {
                this.state.intervalStatusCheck = setInterval(() => {
                    this.verificarStatusInstancia();
                }, 5000);
            }

        } else {
            // Desconectado
            html = `
                <div class="alert alert-danger" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-times-circle"></i> WhatsApp Desconectado</h5>
                    <p>A instância do WhatsApp não está conectada.</p>
                    <button id="btn-criar-instancia" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Criar Instância
                    </button>
                </div>
            `;

            if (this.state.intervalStatusCheck) {
                clearInterval(this.state.intervalStatusCheck);
                this.state.intervalStatusCheck = null;
            }
        }

        this.elements.statusInstanciaContainer.innerHTML = html;

        // Re-bind eventos dos botões
        const btnDesconectar = document.getElementById('btn-desconectar');
        if (btnDesconectar) {
            btnDesconectar.addEventListener('click', () => this.desconectarWhatsApp());
        }

        const btnCriarInstancia = document.getElementById('btn-criar-instancia');
        if (btnCriarInstancia) {
            btnCriarInstancia.addEventListener('click', () => this.criarInstancia());
        }
    },

    /**
     * Cria instância do WhatsApp
     */
    async criarInstancia() {
        // Verifica permissão
        if (!this.state.permissoes.editar) {
            this.mostrarErro('Você não tem permissão para criar instância do WhatsApp');
            return;
        }

        if (!confirm('Deseja criar uma nova instância do WhatsApp?')) {
            return;
        }

        try {
            const response = await API.post('/whatsapp/conexao/criar', {});

            if (response.sucesso) {
                this.mostrarSucesso('Instância criada com sucesso. Aguardando QR Code...');
                await this.verificarStatusInstancia();
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao criar instância');
            }
        } catch (error) {
            console.error('Erro ao criar instância:', error);
            this.mostrarErro('Erro ao criar instância');
        }
    },

    /**
     * Desconecta WhatsApp
     */
    async desconectarWhatsApp() {
        // Verifica permissão
        if (!this.state.permissoes.editar) {
            this.mostrarErro('Você não tem permissão para desconectar o WhatsApp');
            return;
        }

        if (!confirm('Deseja realmente desconectar a instância do WhatsApp?')) {
            return;
        }

        try {
            const response = await API.post('/whatsapp/conexao/desconectar', {});

            if (response.sucesso) {
                this.mostrarSucesso('Instância desconectada com sucesso');
                await this.verificarStatusInstancia();
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao desconectar');
            }
        } catch (error) {
            console.error('Erro ao desconectar:', error);
            this.mostrarErro('Erro ao desconectar instância');
        }
    },

    /**
     * Carrega dashboard/estatísticas
     */
    async carregarDashboard() {
        try {
            const response = await API.get('/whatsapp/painel/dashboard');

            if (response.sucesso) {
                this.atualizarDashboard(response.dados);
            }
        } catch (error) {
            console.error('Erro ao carregar dashboard:', error);
        }
    },

    /**
     * Atualiza estatísticas do dashboard
     */
    atualizarDashboard(stats) {
        if (this.elements.statPendentes) {
            this.elements.statPendentes.textContent = stats.pendentes || 0;
        }
        if (this.elements.statProcessando) {
            this.elements.statProcessando.textContent = stats.processando || 0;
        }
        if (this.elements.statEnviados) {
            this.elements.statEnviados.textContent = stats.enviado || stats.enviados || 0;
        }
        if (this.elements.statErros) {
            this.elements.statErros.textContent = stats.erro || stats.erros || 0;
        }

        this.state.estatisticas = {
            pendentes: stats.pendentes || 0,
            processando: stats.processando || 0,
            enviados: stats.enviado || stats.enviados || 0,
            erros: stats.erro || stats.erros || 0
        };
    },

    /**
     * Carrega fila de mensagens
     */
    async carregarFila() {
        if (!this.elements.tabelaFila) return;

        this.elements.tabelaFila.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> <span class="ms-2">Carregando...</span></td></tr>';

        try {
            const response = await API.get('/whatsapp/fila');

            if (response.sucesso) {
                const mensagens = response.dados.itens || response.dados || [];
                this.renderizarFila(mensagens);
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao carregar fila');
                this.elements.tabelaFila.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erro ao carregar fila</td></tr>';
            }
        } catch (error) {
            console.error('Erro ao carregar fila:', error);
            this.mostrarErro('Erro ao carregar fila');
            this.elements.tabelaFila.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Erro ao carregar fila</td></tr>';
        }
    },

    /**
     * Renderiza tabela da fila
     */
    renderizarFila(mensagens) {
        if (!this.elements.tabelaFila) return;

        if (!mensagens || mensagens.length === 0) {
            this.elements.tabelaFila.innerHTML = '<tr><td colspan="7" class="text-center">Nenhuma mensagem na fila</td></tr>';
            return;
        }

        let html = '';
        mensagens.forEach(msg => {
            const statusBadge = this.obterBadgeStatus(msg.status_code);
            const destinatario = Utils.DOM.escapeHtml(msg.destinatario_nome || msg.destinatario || '-');
            const tipo = Utils.String.capitalize(msg.tipo_mensagem || 'text');
            const tentativas = msg.tentativas || 0;
            const dataCriacao = Utils.Format.dataHora(msg.criado_em || msg.created_at);

            html += `
                <tr>
                    <td>${msg.id}</td>
                    <td>${destinatario}</td>
                    <td>${tipo}</td>
                    <td>${statusBadge}</td>
                    <td>${tentativas}</td>
                    <td>${dataCriacao}</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="WhatsAppManager.cancelarMensagem(${msg.id})" ${msg.status_code != 1 ? 'disabled' : ''}>
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        this.elements.tabelaFila.innerHTML = html;
    },

    /**
     * Cancela mensagem da fila
     */
    async cancelarMensagem(id) {
        // Verifica permissão
        if (!this.state.permissoes.editar) {
            this.mostrarErro('Você não tem permissão para cancelar mensagens');
            return;
        }

        if (!confirm('Deseja cancelar esta mensagem?')) {
            return;
        }

        try {
            const response = await API.delete(`/whatsapp/fila/${id}`);

            if (response.sucesso) {
                this.mostrarSucesso('Mensagem cancelada');
                await this.carregarFila();
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao cancelar mensagem');
            }
        } catch (error) {
            console.error('Erro ao cancelar mensagem:', error);
            this.mostrarErro('Erro ao cancelar mensagem');
        }
    },

    /**
     * Carrega histórico
     */
    async carregarHistorico() {
        if (!this.elements.tabelaHistorico) return;

        this.elements.tabelaHistorico.innerHTML = '<tr><td colspan="6" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> <span class="ms-2">Carregando...</span></td></tr>';

        try {
            const dataInicio = this.elements.filtroDataInicio?.value || '';
            const dataFim = this.elements.filtroDataFim?.value || '';
            const status = this.elements.filtroStatus?.value || '';

            let url = '/whatsapp/painel/historico?limit=50';
            if (dataInicio) url += `&data_inicio=${dataInicio}`;
            if (dataFim) url += `&data_fim=${dataFim}`;
            if (status) url += `&status=${status}`;

            const response = await API.get(url);

            if (response.sucesso) {
                const eventos = response.dados.itens || response.dados || [];
                this.renderizarHistorico(eventos);
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao carregar histórico');
                this.elements.tabelaHistorico.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar histórico</td></tr>';
            }
        } catch (error) {
            console.error('Erro ao carregar histórico:', error);
            this.mostrarErro('Erro ao carregar histórico');
            this.elements.tabelaHistorico.innerHTML = '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar histórico</td></tr>';
        }
    },

    /**
     * Renderiza tabela do histórico
     */
    renderizarHistorico(eventos) {
        if (!this.elements.tabelaHistorico) return;

        if (!eventos || eventos.length === 0) {
            this.elements.tabelaHistorico.innerHTML = '<tr><td colspan="6" class="text-center">Nenhum evento encontrado</td></tr>';
            return;
        }

        let html = '';
        eventos.forEach(evt => {
            const dataCriacao = Utils.Format.dataHora(evt.criado_em || evt.created_at);
            const destinatario = Utils.DOM.escapeHtml(evt.destinatario_nome || evt.destinatario || '-');
            const tipo = Utils.String.capitalize(evt.tipo_mensagem || evt.tipo_evento || '-');
            const statusBadge = this.obterBadgeStatus(evt.status_code);
            const tempoProcessamento = evt.tempo_processamento ? `${evt.tempo_processamento}s` : '-';
            const dataLeitura = evt.lido_em ? Utils.Format.dataHora(evt.lido_em) : '-';

            html += `
                <tr>
                    <td>${dataCriacao}</td>
                    <td>${destinatario}</td>
                    <td>${tipo}</td>
                    <td>${statusBadge}</td>
                    <td>${tempoProcessamento}</td>
                    <td>${dataLeitura}</td>
                </tr>
            `;
        });

        this.elements.tabelaHistorico.innerHTML = html;
    },

    /**
     * Carrega configurações
     */
    async carregarConfiguracoes() {
        if (!this.elements.containerConfiguracoes) return;

        // Verifica permissão
        if (!this.state.permissoes.editar) {
            this.elements.containerConfiguracoes.innerHTML = `
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle"></i>
                    Você não tem permissão para visualizar ou alterar as configurações do WhatsApp.
                </div>
            `;
            return;
        }

        this.elements.containerConfiguracoes.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3 text-muted">Carregando configurações...</p></div>';

        try {
            const response = await API.get('/whatsapp/config');

            if (response.sucesso) {
                this.renderizarConfiguracoes(response.dados);
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao carregar configurações');
            }
        } catch (error) {
            console.error('Erro ao carregar configurações:', error);
            this.mostrarErro('Erro ao carregar configurações');
        }
    },

    /**
     * Renderiza configurações
     */
    renderizarConfiguracoes(configs) {
        if (!this.elements.containerConfiguracoes) return;

        let html = '';

        Object.keys(configs).forEach(categoria => {
            html += `<h5 class="mt-4">${Utils.String.capitalize(categoria)}</h5><div class="row">`;

            configs[categoria].forEach(config => {
                html += `
                    <div class="col-md-6 mb-3">
                        <label class="form-label">${Utils.DOM.escapeHtml(config.descricao)}</label>
                        <input type="text" class="form-control" value="${Utils.DOM.escapeHtml(config.valor)}" data-chave="${config.chave}">
                        <small class="text-muted">Padrão: ${Utils.DOM.escapeHtml(config.valor_padrao)}</small>
                    </div>
                `;
            });

            html += '</div>';
        });

        this.elements.containerConfiguracoes.innerHTML = html;
    },

    /**
     * Altera tipo de destinatário
     */
    alterarTipoDestinatario() {
        const tipo = this.elements.tipoDestinatario?.value;

        if (tipo === 'numero') {
            if (this.elements.selectEntidade) {
                this.elements.selectEntidade.style.display = 'none';
            }
            if (this.elements.inputNumero) {
                this.elements.inputNumero.style.display = 'block';
            }
        } else if (tipo) {
            if (this.elements.selectEntidade) {
                this.elements.selectEntidade.style.display = 'block';
            }
            if (this.elements.inputNumero) {
                this.elements.inputNumero.style.display = 'none';
            }
            // TODO: Carregar entidades do tipo selecionado
        } else {
            if (this.elements.selectEntidade) {
                this.elements.selectEntidade.style.display = 'none';
            }
            if (this.elements.inputNumero) {
                this.elements.inputNumero.style.display = 'none';
            }
        }
    },

    /**
     * Altera tipo de mensagem
     */
    alterarTipoMensagem() {
        const tipo = this.elements.tipoMensagem?.value;

        if (tipo === 'text') {
            if (this.elements.campoTexto) {
                this.elements.campoTexto.style.display = 'block';
            }
            if (this.elements.campoUrl) {
                this.elements.campoUrl.style.display = 'none';
            }
            if (this.elements.campoCaption) {
                this.elements.campoCaption.style.display = 'none';
            }
        } else {
            if (this.elements.campoTexto) {
                this.elements.campoTexto.style.display = 'none';
            }
            if (this.elements.campoUrl) {
                this.elements.campoUrl.style.display = 'block';
            }
            if (this.elements.campoCaption) {
                this.elements.campoCaption.style.display = 'block';
            }
        }
    },

    /**
     * Envia mensagem de teste
     */
    async enviarMensagemTeste() {
        // TODO: Implementar envio de mensagem de teste
        this.mostrarErro('Função de envio de teste ainda não implementada');
    },

    /**
     * Obtém badge de status
     */
    obterBadgeStatus(code) {
        const badges = {
            0: '<span class="badge bg-danger">Erro</span>',
            1: '<span class="badge bg-warning">Pendente</span>',
            2: '<span class="badge bg-info">Enviado</span>',
            3: '<span class="badge bg-primary">Entregue</span>',
            4: '<span class="badge bg-success">Lido</span>'
        };
        return badges[code] || '<span class="badge bg-secondary">Desconhecido</span>';
    },

    /**
     * Mostra mensagem de sucesso
     */
    mostrarSucesso(mensagem) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: mensagem,
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            alert(mensagem);
        }
    },

    /**
     * Mostra mensagem de erro
     */
    mostrarErro(mensagem) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Erro',
                text: mensagem
            });
        } else {
            alert(mensagem);
        }
    },

    /**
     * Cleanup ao sair da página
     */
    cleanup() {
        if (this.state.intervalStatusCheck) {
            clearInterval(this.state.intervalStatusCheck);
            this.state.intervalStatusCheck = null;
        }
    }
};

// Inicializa quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => WhatsAppManager.init());
} else {
    WhatsAppManager.init();
}

// Cleanup ao sair da página
window.addEventListener('beforeunload', () => WhatsAppManager.cleanup());

// Expõe globalmente para uso em onclick
window.WhatsAppManager = WhatsAppManager;
