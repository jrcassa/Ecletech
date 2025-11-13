/**
 * Gerenciador de Email
 * Implementa gerenciamento completo de envio, fila, histórico e configurações de Email
 */

const EmailManager = {
    // Estado da aplicação
    state: {
        conectado: false,
        statusSistema: null,
        permissoes: {
            visualizar: true,
            criar: true,
            editar: false,
            deletar: false
        },
        tabAtual: 'status',
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
        tabStatus: document.getElementById('tab-status'),
        tabTeste: document.getElementById('tab-teste'),
        tabFila: document.getElementById('tab-fila'),
        tabHistorico: document.getElementById('tab-historico'),
        tabConfiguracoes: document.getElementById('tab-configuracoes'),

        // Status do sistema
        statusSistemaContainer: document.getElementById('status-sistema-container'),

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
        inputEmail: document.getElementById('input-email'),
        assunto: document.getElementById('assunto'),
        mensagemCorpo: document.getElementById('mensagem-corpo')
    },

    /**
     * Inicializa o gerenciador
     */
    async init() {
        console.log('Email Manager iniciado - v1.0');

        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            return;
        }

        // Configura event listeners
        this.setupEventListeners();

        // Carrega dados iniciais
        await this.carregarNomeUsuario();
        await this.carregarPermissoes();
        await this.verificarStatusSistema();
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
                this.enviarEmailTeste();
            });
        }

        // Tipo de destinatário
        if (this.elements.tipoDestinatario) {
            this.elements.tipoDestinatario.addEventListener('change', () => {
                this.alterarTipoDestinatario();
            });
        }

        // Formato do email (texto/html)
        const formatoRadios = document.querySelectorAll('input[name="formato-email"]');
        formatoRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                this.alterarFormatoEmail();
            });
        });

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
                    visualizar: permissoes.includes('email.acessar'),
                    criar: permissoes.includes('email.alterar'),
                    editar: permissoes.includes('email.alterar'),
                    deletar: permissoes.includes('email.deletar')
                };

                // Renderiza as permissões na UI
                this.renderizarPermissoes(permissoes);
            } else {
                this.elements.listaPermissoes.innerHTML = '<li class="text-danger"><i class="fas fa-exclamation-circle"></i> Erro ao carregar</li>';
            }

            // Verifica se não tem permissão de visualizar
            if (!this.state.permissoes.visualizar) {
                API.showError('Você não tem permissão para acessar o Email');
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
        if (permissoesArray.includes('email.acessar')) {
            permissoes.push({
                icone: 'fa-eye',
                texto: 'Acessar Email',
                classe: 'text-info'
            });
        }

        if (permissoesArray.includes('email.alterar')) {
            permissoes.push({
                icone: 'fa-edit',
                texto: 'Alterar Configurações',
                classe: 'text-success'
            });
        }

        if (permissoesArray.includes('email.deletar')) {
            permissoes.push({
                icone: 'fa-trash',
                texto: 'Deletar Emails',
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
            case 'status':
                this.verificarStatusSistema();
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
     * Verifica status do sistema
     */
    async verificarStatusSistema() {
        if (!this.elements.statusSistemaContainer) return;

        try {
            const response = await API.get('/email/status');

            if (response.sucesso) {
                this.atualizarStatusSistema(response.dados);
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao verificar status');
            }
        } catch (error) {
            console.error('Erro ao verificar status:', error);
            this.mostrarErro('Erro ao verificar status do sistema');
        }
    },

    /**
     * Atualiza UI do status do sistema
     */
    atualizarStatusSistema(dados) {
        if (!this.elements.statusSistemaContainer) return;

        const configurado = dados.configurado || false;
        const info = dados.info || {};
        const smtp_host = info.host || 'Não configurado';
        const smtp_user = info.username || 'Não configurado';
        const smtp_from = info.from_email || 'Não configurado';
        const smtp_port = info.port || '';
        const smtp_secure = info.secure || '';

        let html = '';

        if (configurado) {
            // Sistema configurado
            html = `
                <div class="alert alert-success" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-check-circle"></i> Sistema de Email Configurado</h5>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Servidor SMTP:</strong> ${Utils.DOM.escapeHtml(smtp_host)}${smtp_port ? ':' + smtp_port : ''}</p>
                            <p class="mb-1"><strong>Segurança:</strong> ${Utils.DOM.escapeHtml(smtp_secure || 'none')}</p>
                            <p class="mb-1"><strong>Remetente Padrão:</strong> ${Utils.DOM.escapeHtml(smtp_from)}</p>
                        </div>
                        <div class="col-md-6">
                            <button id="btn-testar-conexao" class="btn btn-primary">
                                <i class="fas fa-vial"></i> Testar Conexão
                            </button>
                        </div>
                    </div>
                </div>
            `;
        } else {
            // Sistema não configurado
            const erros = dados.erros || [];
            const mensagemErros = erros.length > 0 ? `<p class="mb-2"><strong>Problemas encontrados:</strong></p><ul class="mb-0">${erros.map(e => '<li>' + Utils.DOM.escapeHtml(e) + '</li>').join('')}</ul>` : '';

            html = `
                <div class="alert alert-warning" role="alert">
                    <h5 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> Sistema não configurado</h5>
                    <p>O sistema de email ainda não foi configurado. Configure o servidor SMTP nas configurações.</p>
                    ${mensagemErros}
                    <button id="btn-ir-configuracoes" class="btn btn-primary mt-2">
                        <i class="fas fa-cog"></i> Ir para Configurações
                    </button>
                </div>
            `;
        }

        this.elements.statusSistemaContainer.innerHTML = html;

        // Re-bind eventos dos botões
        const btnTestarConexao = document.getElementById('btn-testar-conexao');
        if (btnTestarConexao) {
            btnTestarConexao.addEventListener('click', () => this.testarConexaoSMTP());
        }

        const btnIrConfiguracoes = document.getElementById('btn-ir-configuracoes');
        if (btnIrConfiguracoes) {
            btnIrConfiguracoes.addEventListener('click', () => this.trocarTab('configuracoes'));
        }
    },

    /**
     * Testa conexão SMTP
     */
    async testarConexaoSMTP() {
        try {
            this.mostrarSucesso('Testando conexão SMTP...');
            const response = await API.post('/email/testar-conexao', {});

            if (response.sucesso) {
                this.mostrarSucesso('Conexão SMTP testada com sucesso!');
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao testar conexão');
            }
        } catch (error) {
            console.error('Erro ao testar conexão:', error);
            this.mostrarErro('Erro ao testar conexão SMTP');
        }
    },

    /**
     * Carrega dashboard/estatísticas
     */
    async carregarDashboard() {
        try {
            const response = await API.get('/email/painel/dashboard');

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
     * Carrega fila de emails
     */
    async carregarFila() {
        if (!this.elements.tabelaFila) return;

        this.elements.tabelaFila.innerHTML = '<tr><td colspan="7" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> <span class="ms-2">Carregando...</span></td></tr>';

        try {
            const response = await API.get('/email/fila');

            if (response.sucesso) {
                const emails = response.dados.itens || response.dados || [];
                this.renderizarFila(emails);
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
    renderizarFila(emails) {
        if (!this.elements.tabelaFila) return;

        if (!emails || emails.length === 0) {
            this.elements.tabelaFila.innerHTML = '<tr><td colspan="7" class="text-center">Nenhum email na fila</td></tr>';
            return;
        }

        let html = '';
        emails.forEach(email => {
            const statusBadge = this.obterBadgeStatus(email.status_code);
            const destinatario = Utils.DOM.escapeHtml(email.destinatario || '-');
            const assunto = Utils.DOM.escapeHtml(email.assunto || '-');
            const tentativas = email.tentativas || 0;
            const dataCriacao = Utils.Format.dataHora(email.criado_em || email.created_at);

            html += `
                <tr>
                    <td>${email.id}</td>
                    <td>${destinatario}</td>
                    <td>${assunto}</td>
                    <td>${statusBadge}</td>
                    <td>${tentativas}</td>
                    <td>${dataCriacao}</td>
                    <td>
                        <button class="btn btn-sm btn-danger" onclick="EmailManager.cancelarEmail(${email.id})" ${email.status_code != 1 ? 'disabled' : ''}>
                            <i class="fas fa-times"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        this.elements.tabelaFila.innerHTML = html;
    },

    /**
     * Cancela email da fila
     */
    async cancelarEmail(id) {
        // Verifica permissão
        if (!this.state.permissoes.editar) {
            this.mostrarErro('Você não tem permissão para cancelar emails');
            return;
        }

        if (!confirm('Deseja cancelar este email?')) {
            return;
        }

        try {
            const response = await API.delete(`/email/fila/${id}`);

            if (response.sucesso) {
                this.mostrarSucesso('Email cancelado');
                await this.carregarFila();
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao cancelar email');
            }
        } catch (error) {
            console.error('Erro ao cancelar email:', error);
            this.mostrarErro('Erro ao cancelar email');
        }
    },

    /**
     * Carrega histórico
     */
    async carregarHistorico() {
        if (!this.elements.tabelaHistorico) return;

        this.elements.tabelaHistorico.innerHTML = '<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm"></div> <span class="ms-2">Carregando...</span></td></tr>';

        try {
            const dataInicio = this.elements.filtroDataInicio?.value || '';
            const dataFim = this.elements.filtroDataFim?.value || '';
            const status = this.elements.filtroStatus?.value || '';

            let url = '/email/painel/historico?limit=50';
            if (dataInicio) url += `&data_inicio=${dataInicio}`;
            if (dataFim) url += `&data_fim=${dataFim}`;
            if (status) url += `&status=${status}`;

            const response = await API.get(url);

            if (response.sucesso) {
                const eventos = response.dados.itens || response.dados || [];
                this.renderizarHistorico(eventos);
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao carregar histórico');
                this.elements.tabelaHistorico.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Erro ao carregar histórico</td></tr>';
            }
        } catch (error) {
            console.error('Erro ao carregar histórico:', error);
            this.mostrarErro('Erro ao carregar histórico');
            this.elements.tabelaHistorico.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Erro ao carregar histórico</td></tr>';
        }
    },

    /**
     * Renderiza tabela do histórico
     */
    renderizarHistorico(eventos) {
        if (!this.elements.tabelaHistorico) return;

        if (!eventos || eventos.length === 0) {
            this.elements.tabelaHistorico.innerHTML = '<tr><td colspan="5" class="text-center">Nenhum evento encontrado</td></tr>';
            return;
        }

        let html = '';
        eventos.forEach(evt => {
            const destinatario = Utils.DOM.escapeHtml(evt.destinatario || '-');
            const assunto = Utils.DOM.escapeHtml(evt.assunto || '-');
            const statusBadge = this.obterBadgeStatus(evt.status_code);
            const dataEnviado = evt.data_enviado ? Utils.Format.dataHora(evt.data_enviado) : '-';
            const resposta = Utils.DOM.escapeHtml(evt.resposta_servidor || '-');

            html += `
                <tr>
                    <td>${destinatario}</td>
                    <td>${assunto}</td>
                    <td>${statusBadge}</td>
                    <td>${dataEnviado}</td>
                    <td><small>${resposta}</small></td>
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
                    Você não tem permissão para visualizar ou alterar as configurações de Email.
                </div>
            `;
            return;
        }

        this.elements.containerConfiguracoes.innerHTML = '<div class="text-center py-5"><div class="spinner-border text-primary"></div><p class="mt-3 text-muted">Carregando configurações...</p></div>';

        try {
            const response = await API.get('/email/config');

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
    renderizarConfiguracoes(dados) {
        if (!this.elements.containerConfiguracoes) return;

        // Se dados é um array, agrupa por categoria
        let configs = dados;
        if (Array.isArray(dados)) {
            configs = {};
            dados.forEach(config => {
                const cat = config.categoria || 'outros';
                if (!configs[cat]) {
                    configs[cat] = [];
                }
                configs[cat].push(config);
            });
        }

        const categorias = {
            'smtp': '📧 Servidor SMTP',
            'fila': '📋 Fila de Envio',
            'retry': '🔄 Sistema de Retry',
            'limite': '⚠️ Limites de Envio',
            'validar': '✅ Validações',
            'entidade': '👥 Entidades',
            'cron': '⏰ Tarefas Agendadas',
            'limpeza': '🧹 Limpeza Automática',
            'log': '📝 Logs',
            'sistema': '⚙️ Sistema',
            'modo': '🚀 Modo de Envio',
            'outros': '⚙️ Outras Configurações'
        };

        let html = '<form id="form-configuracoes">';

        Object.keys(configs).forEach(categoria => {
            if (!configs[categoria] || configs[categoria].length === 0) return;

            const tituloCategoria = categorias[categoria] || Utils.String.capitalize(categoria);

            html += `
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">${tituloCategoria}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
            `;

            configs[categoria].forEach(config => {
                html += this.renderizarCampoConfiguracao(config);
            });

            html += `
                        </div>
                    </div>
                </div>
            `;
        });

        html += `
            <div class="text-end">
                <button type="button" class="btn btn-secondary me-2" onclick="EmailManager.carregarConfiguracoes()">
                    <i class="fas fa-undo"></i> Cancelar
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Salvar Configurações
                </button>
            </div>
        </form>
        `;

        this.elements.containerConfiguracoes.innerHTML = html;

        // Adiciona event listener para salvar
        document.getElementById('form-configuracoes')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.salvarConfiguracoes();
        });
    },

    /**
     * Renderiza campo individual de configuração
     */
    renderizarCampoConfiguracao(config) {
        const valor = config.valor || '';
        const chave = config.chave;
        const descricao = config.descricao || chave;
        const tipo = config.tipo || 'string';

        let inputHtml = '';

        // Campo de acordo com o tipo
        if (tipo === 'bool') {
            const checked = (valor === 'true' || valor === '1' || valor === 1) ? 'checked' : '';
            inputHtml = `
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="config_${chave}"
                           data-chave="${chave}" ${checked}>
                    <label class="form-check-label" for="config_${chave}">
                        ${Utils.DOM.escapeHtml(descricao)}
                    </label>
                </div>
            `;
        } else if (tipo === 'int') {
            inputHtml = `
                <label class="form-label" for="config_${chave}">${Utils.DOM.escapeHtml(descricao)}</label>
                <input type="number" class="form-control" id="config_${chave}"
                       data-chave="${chave}" value="${Utils.DOM.escapeHtml(valor)}">
            `;
        } else if (chave.includes('password') || chave.includes('senha')) {
            // Campo de senha
            inputHtml = `
                <label class="form-label" for="config_${chave}">${Utils.DOM.escapeHtml(descricao)}</label>
                <input type="password" class="form-control" id="config_${chave}"
                       data-chave="${chave}" value="${Utils.DOM.escapeHtml(valor)}">
            `;
        } else {
            // Input de texto padrão
            inputHtml = `
                <label class="form-label" for="config_${chave}">${Utils.DOM.escapeHtml(descricao)}</label>
                <input type="text" class="form-control" id="config_${chave}"
                       data-chave="${chave}" value="${Utils.DOM.escapeHtml(valor)}">
            `;
        }

        // Largura do campo baseado no tipo
        const colClass = tipo === 'bool' ? 'col-12' : 'col-md-6';

        return `
            <div class="${colClass} mb-3">
                ${inputHtml}
            </div>
        `;
    },

    /**
     * Salva configurações
     */
    async salvarConfiguracoes() {
        const campos = document.querySelectorAll('[data-chave]');
        const configuracoes = [];

        campos.forEach(campo => {
            let valor;

            if (campo.type === 'checkbox') {
                valor = campo.checked ? 'true' : 'false';
            } else {
                valor = campo.value;
            }

            configuracoes.push({
                chave: campo.dataset.chave,
                valor: valor
            });
        });

        try {
            let sucessos = 0;
            let erros = 0;

            for (const config of configuracoes) {
                try {
                    const response = await API.post('/email/config/salvar', config);
                    if (response.sucesso) {
                        sucessos++;
                    } else {
                        erros++;
                    }
                } catch (error) {
                    erros++;
                }
            }

            if (erros === 0) {
                this.mostrarSucesso(`${sucessos} configurações salvas com sucesso!`);
            } else {
                this.mostrarAlerta(`${sucessos} salvas, ${erros} com erro`);
            }

            // Recarrega configurações
            setTimeout(() => this.carregarConfiguracoes(), 1500);

        } catch (error) {
            console.error('Erro ao salvar configurações:', error);
            this.mostrarErro('Erro ao salvar configurações');
        }
    },

    /**
     * Altera tipo de destinatário
     */
    alterarTipoDestinatario() {
        const tipo = this.elements.tipoDestinatario?.value;

        if (tipo === 'email') {
            if (this.elements.selectEntidade) {
                this.elements.selectEntidade.style.display = 'none';
            }
            if (this.elements.inputEmail) {
                this.elements.inputEmail.style.display = 'block';
            }
        } else if (tipo) {
            if (this.elements.selectEntidade) {
                this.elements.selectEntidade.style.display = 'block';
            }
            if (this.elements.inputEmail) {
                this.elements.inputEmail.style.display = 'none';
            }
            // Carrega entidades do tipo selecionado
            this.carregarEntidades(tipo);
        } else {
            if (this.elements.selectEntidade) {
                this.elements.selectEntidade.style.display = 'none';
            }
            if (this.elements.inputEmail) {
                this.elements.inputEmail.style.display = 'none';
            }
        }
    },

    /**
     * Carrega entidades (clientes, colaboradores, etc)
     */
    async carregarEntidades(tipo) {
        if (!this.elements.selectEntidade) return;

        // Mostra loading
        this.elements.selectEntidade.innerHTML = '<option value="">Carregando...</option>';

        try {
            // Mapeia tipo para endpoint da API (singular, exceto colaboradores)
            const endpoints = {
                'cliente': '/cliente',
                'colaborador': '/colaboradores',
                'fornecedor': '/fornecedor',
                'transportadora': '/transportadora'
            };

            const endpoint = endpoints[tipo];
            if (!endpoint) {
                this.elements.selectEntidade.innerHTML = '<option value="">Tipo inválido</option>';
                return;
            }

            // Busca entidades da API
            const response = await API.get(`${endpoint}?ativo=1&por_pagina=1000`);

            if (response.sucesso && response.dados) {
                const entidades = response.dados.itens || response.dados || [];
                this.popularSelectEntidades(entidades);
            } else {
                this.elements.selectEntidade.innerHTML = '<option value="">Erro ao carregar</option>';
            }

        } catch (error) {
            console.error(`Erro ao carregar ${tipo}s:`, error);
            this.elements.selectEntidade.innerHTML = '<option value="">Erro ao carregar</option>';
        }
    },

    /**
     * Popula select com entidades
     */
    popularSelectEntidades(entidades) {
        if (!this.elements.selectEntidade) return;

        if (!entidades || entidades.length === 0) {
            this.elements.selectEntidade.innerHTML = '<option value="">Nenhum registro encontrado</option>';
            return;
        }

        let html = '<option value="">Selecione...</option>';
        entidades.forEach(entidade => {
            const nome = Utils.DOM.escapeHtml(entidade.nome || entidade.razao_social || 'Sem nome');
            const email = entidade.email || '';
            const display = email ? `${nome} - ${email}` : nome;

            html += `<option value="${entidade.id}" data-email="${Utils.DOM.escapeHtml(email)}">${display}</option>`;
        });

        this.elements.selectEntidade.innerHTML = html;
    },

    /**
     * Altera formato do email
     */
    alterarFormatoEmail() {
        const formato = document.querySelector('input[name="formato-email"]:checked')?.value;

        const hintTexto = document.getElementById('hint-texto');
        const hintHtml = document.getElementById('hint-html');

        if (formato === 'html') {
            if (hintTexto) hintTexto.style.display = 'none';
            if (hintHtml) hintHtml.style.display = 'inline';
        } else {
            if (hintTexto) hintTexto.style.display = 'inline';
            if (hintHtml) hintHtml.style.display = 'none';
        }
    },

    /**
     * Envia email de teste
     */
    async enviarEmailTeste() {
        // Verifica permissão
        if (!this.state.permissoes.editar) {
            this.mostrarErro('Você não tem permissão para enviar emails');
            return;
        }

        try {
            // Coleta dados do formulário
            const tipoDestinatario = this.elements.tipoDestinatario?.value;
            const assunto = this.elements.assunto?.value;
            const mensagem = this.elements.mensagemCorpo?.value;
            const formato = document.querySelector('input[name="formato-email"]:checked')?.value || 'texto';
            const modoEnvio = document.querySelector('input[name="modo-envio"]:checked')?.value || 'fila';
            const prioridade = document.getElementById('prioridade')?.value || 'normal';
            const agendadoPara = document.getElementById('agendado-para')?.value || null;

            // Validação básica
            if (!tipoDestinatario) {
                this.mostrarErro('Selecione o tipo de destinatário');
                return;
            }

            if (!assunto) {
                this.mostrarErro('Digite o assunto do email');
                return;
            }

            if (!mensagem) {
                this.mostrarErro('Digite a mensagem do email');
                return;
            }

            // Obtém destinatário (email direto ou entidade)
            let email = '';
            if (tipoDestinatario === 'email') {
                email = this.elements.inputEmail?.value;
                if (!email) {
                    this.mostrarErro('Digite o email do destinatário');
                    return;
                }
            } else {
                const entidadeId = this.elements.selectEntidade?.value;
                if (!entidadeId) {
                    this.mostrarErro('Selecione um destinatário');
                    return;
                }

                // Busca email da opção selecionada
                const optionSelecionada = this.elements.selectEntidade.selectedOptions[0];
                email = optionSelecionada.getAttribute('data-email');

                if (!email) {
                    this.mostrarErro('Destinatário selecionado não possui email cadastrado');
                    return;
                }
            }

            // Monta payload
            const dados = {
                destinatario: email,
                assunto: assunto,
                modo_envio: modoEnvio,
                prioridade: prioridade
            };

            // Adiciona corpo no formato correto
            if (formato === 'html') {
                dados.corpo_html = mensagem;
            } else {
                dados.corpo_texto = mensagem;
            }

            // Adiciona agendamento se foi definido
            if (agendadoPara) {
                dados.agendado_para = agendadoPara;
            }

            // Envia para API
            const response = await API.post('/email/enviar', dados);

            if (response.sucesso) {
                // Mensagem de sucesso dinâmica conforme o modo
                let mensagemSucesso = '';
                if (response.dados.modo === 'fila') {
                    mensagemSucesso = `Email adicionado à fila! ID: ${response.dados.queue_id}`;
                } else {
                    mensagemSucesso = `Email enviado diretamente!`;
                }

                this.mostrarSucesso(mensagemSucesso);

                // Limpa formulário
                this.limparFormularioTeste();

                // Atualiza fila se estiver na aba de fila
                if (this.state.tabAtual === 'fila') {
                    await this.carregarFila();
                }
            } else {
                this.mostrarErro(response.mensagem || 'Erro ao enviar email');
            }

        } catch (error) {
            console.error('Erro ao enviar email de teste:', error);
            this.mostrarErro('Erro ao enviar email');
        }
    },

    /**
     * Limpa formulário de teste
     */
    limparFormularioTeste() {
        if (this.elements.tipoDestinatario) {
            this.elements.tipoDestinatario.value = '';
        }
        if (this.elements.selectEntidade) {
            this.elements.selectEntidade.innerHTML = '<option value="">Selecione...</option>';
            this.elements.selectEntidade.style.display = 'none';
        }
        if (this.elements.inputEmail) {
            this.elements.inputEmail.value = '';
            this.elements.inputEmail.style.display = 'none';
        }
        if (this.elements.assunto) {
            this.elements.assunto.value = '';
        }
        if (this.elements.mensagemCorpo) {
            this.elements.mensagemCorpo.value = '';
        }
    },

    /**
     * Obtém badge de status
     */
    obterBadgeStatus(code) {
        const badges = {
            0: '<span class="badge bg-danger">Erro</span>',
            1: '<span class="badge bg-warning">Pendente</span>',
            2: '<span class="badge bg-success">Enviado</span>'
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
     * Mostra mensagem de alerta
     */
    mostrarAlerta(mensagem) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: mensagem,
                timer: 3000,
                showConfirmButton: true
            });
        } else {
            alert(mensagem);
        }
    }
};

// Inicializa quando o DOM estiver pronto
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => EmailManager.init());
} else {
    EmailManager.init();
}

// Expõe globalmente para uso em onclick
window.EmailManager = EmailManager;
