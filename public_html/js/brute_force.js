/**
 * Gerenciador de Proteção Brute Force
 * Gerencia tentativas de login, bloqueios e estatísticas
 */

const BruteForceManager = {
    // Estado da aplicação
    state: {
        tentativas: {
            page: 1,
            perPage: 20,
            filters: {}
        },
        bloqueios: {
            page: 1,
            perPage: 20,
            filters: {}
        },
        permissoes: {
            visualizar: false,
            editar: false
        }
    },

    // Elementos DOM
    elements: {
        userName: document.getElementById('userName'),
        sidebar: document.getElementById('sidebar'),
        mainContent: document.getElementById('mainContent'),
        permissionDenied: document.getElementById('permissionDenied'),
        pageContent: document.getElementById('pageContent'),
        // Bloqueios
        bloqueiosTableBody: document.getElementById('bloqueiosTableBody'),
        bloqueiosPagination: document.getElementById('bloqueiosPagination'),
        // Modal
        modalNovoBloqueio: document.getElementById('modalNovoBloqueio')
    },

    /**
     * Inicializa o gerenciador
     */
    async init() {
        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            window.location.href = 'auth.html';
            return;
        }

        // Carrega dados do usuário
        await this.carregarUsuario();

        // Verifica permissões do usuário
        await this.verificarPermissoes();

        // Se não tem permissão de visualizar, exibe mensagem
        if (!this.state.permissoes.visualizar) {
            if (this.elements.permissionDenied) {
                this.elements.permissionDenied.style.display = 'block';
            }
            if (this.elements.pageContent) {
                this.elements.pageContent.style.display = 'none';
            }
            return;
        }

        // Tem permissão - mostra conteúdo
        if (this.elements.permissionDenied) {
            this.elements.permissionDenied.style.display = 'none';
        }
        if (this.elements.pageContent) {
            this.elements.pageContent.style.display = 'block';
        }

        // Carrega dados iniciais
        this.mostrarConfiguracoesPadrao(); // Mostra configurações com valores padrão
        await this.carregarBloqueios();
    },

    /**
     * Verifica permissões do usuário
     */
    async verificarPermissoes() {
        try {
            // Aguarda as permissões serem carregadas pelo SidebarManager
            const permissoes = await aguardarPermissoes();

            console.log('Permissões carregadas:', permissoes);

            // Verifica cada tipo de permissão (apenas visualizar e editar existem)
            this.state.permissoes.visualizar = permissoes.includes('config.visualizar');
            this.state.permissoes.editar = permissoes.includes('config.editar');

            console.log('Permissões de config:', this.state.permissoes);

            // Atualiza botões de acordo com permissões
            if (this.state.permissoes.editar) {
                // Botão Salvar Config
                const btnSalvar = document.getElementById('btnSalvarConfig');
                if (btnSalvar) {
                    btnSalvar.style.display = 'inline-flex';
                    console.log('Botão Salvar Config exibido');
                }

                // Botão Novo Bloqueio (também requer permissão de editar)
                const btnNovo = document.getElementById('btnNovoBloqueio');
                if (btnNovo) {
                    btnNovo.style.display = 'inline-flex';
                    console.log('Botão Novo Bloqueio exibido');
                } else {
                    console.error('Botão Novo Bloqueio não encontrado no DOM');
                }
            } else {
                console.log('Usuário não tem permissão config.editar');
            }

            // Se não tem permissão de editar, mostra aviso
            if (!this.state.permissoes.editar) {
                console.info('Usuário tem apenas permissão de visualização');
            }

        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
            // Em caso de erro, assume sem permissões
            this.state.permissoes.visualizar = false;
        }
    },

    /**
     * Mostra configurações com valores padrão
     */
    mostrarConfiguracoesPadrao() {
        const loadingConfig = document.getElementById('loadingConfig');
        const configContent = document.getElementById('configContent');

        // Esconde loading
        if (loadingConfig) loadingConfig.style.display = 'none';

        // Preenche com valores padrão
        const maxTentativas = document.getElementById('config-max-tentativas');
        const tempoBloqueio = document.getElementById('config-tempo-bloqueio');
        const janelaTempo = document.getElementById('config-janela-tempo');

        if (maxTentativas) maxTentativas.value = 5;
        if (tempoBloqueio) tempoBloqueio.value = 30;
        if (janelaTempo) janelaTempo.value = 15;

        // Mostra conteúdo
        if (configContent) configContent.style.display = 'block';
    },

    /**
     * Salva configurações de brute force
     */
    async salvarConfiguracoes() {
        try {
            const maxTentativas = document.getElementById('config-max-tentativas');
            const tempoBloqueio = document.getElementById('config-tempo-bloqueio');
            const janelaTempo = document.getElementById('config-janela-tempo');

            // Validações
            if (!maxTentativas || !maxTentativas.value) {
                API.showError('Tentativas máximas é obrigatório');
                return;
            }

            if (!tempoBloqueio || !tempoBloqueio.value) {
                API.showError('Tempo de bloqueio é obrigatório');
                return;
            }

            if (!janelaTempo || !janelaTempo.value) {
                API.showError('Janela de tempo é obrigatória');
                return;
            }

            const dados = {
                max_tentativas: parseInt(maxTentativas.value),
                tempo_bloqueio: parseInt(tempoBloqueio.value),
                janela_tempo: parseInt(janelaTempo.value)
            };

            // Validações de valores
            if (dados.max_tentativas < 1 || dados.max_tentativas > 100) {
                API.showError('Tentativas máximas deve estar entre 1 e 100');
                return;
            }

            if (dados.tempo_bloqueio < 1 || dados.tempo_bloqueio > 1440) {
                API.showError('Tempo de bloqueio deve estar entre 1 e 1440 minutos');
                return;
            }

            if (dados.janela_tempo < 1 || dados.janela_tempo > 60) {
                API.showError('Janela de tempo deve estar entre 1 e 60 minutos');
                return;
            }

            const response = await API.put('/configuracoes/brute-force', dados);

            if (!response.sucesso) {
                throw new Error(response.mensagem || 'Erro ao salvar configurações');
            }

            API.showSuccess('Configurações salvas com sucesso!');

        } catch (error) {
            console.error('Erro ao salvar configurações:', error);
            API.showError(error.message || 'Erro ao salvar configurações');
        }
    },

    /**
     * Carrega configurações de brute force (para uso futuro quando a rota existir)
     */
    async carregarConfiguracoes() {
        try {
            const loadingConfig = document.getElementById('loadingConfig');
            const configContent = document.getElementById('configContent');

            // Mostra loading
            if (loadingConfig) loadingConfig.style.display = 'block';
            if (configContent) configContent.style.display = 'none';

            const response = await API.get('/configuracoes/brute-force');

            if (response.sucesso && response.dados) {
                const config = response.dados;

                // Preenche os campos
                const maxTentativas = document.getElementById('config-max-tentativas');
                const tempoBloqueio = document.getElementById('config-tempo-bloqueio');
                const janelaTempo = document.getElementById('config-janela-tempo');

                if (maxTentativas) maxTentativas.value = config.max_tentativas || 5;
                if (tempoBloqueio) tempoBloqueio.value = config.tempo_bloqueio || 30;
                if (janelaTempo) janelaTempo.value = config.janela_tempo || 15;
            }

            // Esconde loading e mostra conteúdo
            if (loadingConfig) loadingConfig.style.display = 'none';
            if (configContent) configContent.style.display = 'block';

        } catch (error) {
            console.error('Erro ao carregar configurações:', error);
            const loadingConfig = document.getElementById('loadingConfig');
            const configContent = document.getElementById('configContent');

            if (loadingConfig) loadingConfig.style.display = 'none';
            if (configContent) configContent.style.display = 'block';
        }
    },

    /**
     * Carrega informações do usuário
     */
    async carregarUsuario() {
        try {
            const user = await AuthAPI.getMe();
            if (user && user.nome && this.elements.userName) {
                this.elements.userName.textContent = user.nome;
            }
        } catch (error) {
            console.error('Erro ao carregar usuário:', error);
            // Não redireciona - deixa a verificação de permissões decidir o que mostrar
        }
    },

    /**
     * Carrega estatísticas
     */
    async carregarEstatisticas() {
        try {
            const response = await API.get('/login-attempts/estatisticas');

            if (!response.sucesso) {
                throw new Error(response.mensagem || 'Erro ao carregar estatísticas');
            }

            const stats = response.dados;

            // Atualiza cards de estatísticas
            this.elements.statTentativas.textContent = stats.tentativas_24h || 0;
            this.elements.statSucesso.textContent = stats.sucesso_24h || 0;
            this.elements.statFalhas.textContent = stats.falhas_24h || 0;
            this.elements.statBloqueios.textContent = stats.bloqueios_ativos || 0;
            this.elements.statIps.textContent = stats.ips_bloqueados || 0;
            this.elements.statEmails.textContent = stats.emails_bloqueados || 0;
            this.elements.statTaxa.textContent = (stats.taxa_sucesso || 0) + '%';

            // Atualiza top IPs
            if (stats.top_ips && stats.top_ips.length > 0) {
                this.elements.topIpsList.innerHTML = stats.top_ips.map(item => `
                    <li>
                        <span>${item.ip_address}</span>
                        <span class="count">${item.total} tentativas</span>
                    </li>
                `).join('');
            } else {
                this.elements.topIpsList.innerHTML = '<li class="empty-state">Nenhum dado disponível</li>';
            }

            // Atualiza top emails
            if (stats.top_emails && stats.top_emails.length > 0) {
                this.elements.topEmailsList.innerHTML = stats.top_emails.map(item => `
                    <li>
                        <span>${item.email}</span>
                        <span class="count">${item.total} tentativas</span>
                    </li>
                `).join('');
            } else {
                this.elements.topEmailsList.innerHTML = '<li class="empty-state">Nenhum dado disponível</li>';
            }

        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
            API.showError('Erro ao carregar estatísticas');
        }
    },

    /**
     * Carrega tentativas de login
     */
    async carregarTentativas(page = 1) {
        try {
            this.state.tentativas.page = page;

            // Monta query string com filtros
            const params = {
                pagina: page,
                por_pagina: this.state.tentativas.perPage,
                ...this.state.tentativas.filters
            };

            const response = await API.get('/login-attempts', params);

            if (!response.sucesso) {
                throw new Error(response.mensagem || 'Erro ao carregar tentativas');
            }

            this.renderizarTentativas(response.dados.itens || []);
            this.renderizarPaginacaoTentativas(response.dados.paginacao);

        } catch (error) {
            console.error('Erro ao carregar tentativas:', error);
            this.elements.tentativasTableBody.innerHTML = `
                <tr><td colspan="5" class="empty-state">Erro ao carregar tentativas</td></tr>
            `;
        }
    },

    /**
     * Renderiza tabela de tentativas
     */
    renderizarTentativas(tentativas) {
        if (tentativas.length === 0) {
            this.elements.tentativasTableBody.innerHTML = '<tr><td colspan="5" class="empty-state">Nenhuma tentativa encontrada</td></tr>';
            return;
        }

        this.elements.tentativasTableBody.innerHTML = tentativas.map(t => {
            const data = new Date(t.criado_em).toLocaleString('pt-BR');
            const status = t.tentativa_sucesso == 1
                ? '<span class="badge badge-success">Sucesso</span>'
                : '<span class="badge badge-danger">Falha</span>';

            const motivo = t.motivo_falha || '-';

            return `
                <tr>
                    <td>${data}</td>
                    <td>${t.email}</td>
                    <td>${t.ip_address}</td>
                    <td>${status}</td>
                    <td>${motivo}</td>
                </tr>
            `;
        }).join('');
    },

    /**
     * Renderiza paginação de tentativas
     */
    renderizarPaginacaoTentativas(paginacao) {
        if (!paginacao) return;

        const { pagina_atual, total_paginas } = paginacao;

        this.elements.tentativasPagination.innerHTML = `
            <button onclick="BruteForceManager.carregarTentativas(${pagina_atual - 1})" ${pagina_atual <= 1 ? 'disabled' : ''}>
                Anterior
            </button>
            <span>Página ${pagina_atual} de ${total_paginas}</span>
            <button onclick="BruteForceManager.carregarTentativas(${pagina_atual + 1})" ${pagina_atual >= total_paginas ? 'disabled' : ''}>
                Próxima
            </button>
        `;
    },

    /**
     * Aplica filtros nas tentativas
     */
    aplicarFiltrosTentativas() {
        this.state.tentativas.filters = {};

        const email = document.getElementById('filter-email').value;
        const ip = document.getElementById('filter-ip').value;
        const status = document.getElementById('filter-status').value;

        if (email) this.state.tentativas.filters.email = email;
        if (ip) this.state.tentativas.filters.ip_address = ip;
        if (status !== '') this.state.tentativas.filters.sucesso = status;

        this.carregarTentativas(1);
    },

    /**
     * Limpa filtros das tentativas
     */
    limparFiltrosTentativas() {
        document.getElementById('filter-email').value = '';
        document.getElementById('filter-ip').value = '';
        document.getElementById('filter-status').value = '';
        this.state.tentativas.filters = {};
        this.carregarTentativas(1);
    },

    /**
     * Carrega bloqueios ativos
     */
    async carregarBloqueios(page = 1) {
        const loadingBloqueios = document.getElementById('loadingBloqueios');
        const tableContainer = document.getElementById('tableContainer');
        const errorContainer = document.getElementById('errorContainer');
        const errorMessage = document.getElementById('errorMessage');
        const noData = document.getElementById('noData');

        try {
            // Mostra loading
            if (loadingBloqueios) loadingBloqueios.style.display = 'flex';
            if (tableContainer) tableContainer.style.display = 'none';
            if (errorContainer) errorContainer.style.display = 'none';

            this.state.bloqueios.page = page;

            const params = {
                pagina: page,
                por_pagina: this.state.bloqueios.perPage,
                ...this.state.bloqueios.filters
            };

            const response = await API.get('/login-bloqueios', params);

            if (!response.sucesso) {
                throw new Error(response.mensagem || 'Erro ao carregar bloqueios');
            }

            const bloqueios = response.dados.itens || [];

            // Esconde loading
            if (loadingBloqueios) loadingBloqueios.style.display = 'none';

            // Se tem dados, renderiza e mostra tabela
            if (bloqueios.length > 0) {
                this.renderizarBloqueios(bloqueios);
                this.renderizarPaginacaoBloqueios(response.dados.paginacao);
                if (tableContainer) tableContainer.style.display = 'block';
                if (noData) noData.style.display = 'none';
            } else {
                // Sem dados, mostra mensagem
                if (tableContainer) tableContainer.style.display = 'block';
                if (this.elements.bloqueiosTableBody) {
                    this.elements.bloqueiosTableBody.innerHTML = '';
                }
                if (noData) noData.style.display = 'block';
            }

        } catch (error) {
            console.error('Erro ao carregar bloqueios:', error);

            // Esconde loading
            if (loadingBloqueios) loadingBloqueios.style.display = 'none';

            // Mostra erro
            if (errorContainer) errorContainer.style.display = 'block';
            if (errorMessage) errorMessage.textContent = error.message || 'Erro ao carregar bloqueios';
            if (tableContainer) tableContainer.style.display = 'none';
        }
    },

    /**
     * Renderiza tabela de bloqueios
     */
    renderizarBloqueios(bloqueios) {
        if (bloqueios.length === 0) {
            this.elements.bloqueiosTableBody.innerHTML = '<tr><td colspan="8" class="empty-state">Nenhum bloqueio ativo</td></tr>';
            return;
        }

        this.elements.bloqueiosTableBody.innerHTML = bloqueios.map(b => {
            const bloqueadoAte = new Date(b.bloqueado_ate).toLocaleString('pt-BR');
            const permanente = b.bloqueado_permanente == 1
                ? '<span class="badge badge-danger">Sim</span>'
                : '<span class="badge badge-success">Não</span>';

            const tipo = b.tipo_bloqueio.toUpperCase();

            return `
                <tr>
                    <td><span class="badge badge-warning">${tipo}</span></td>
                    <td>${b.email || '-'}</td>
                    <td>${b.ip_address || '-'}</td>
                    <td>${b.tentativas_falhadas}</td>
                    <td>${bloqueadoAte}</td>
                    <td>${permanente}</td>
                    <td>${b.motivo || '-'}</td>
                    <td>
                        <button class="btn btn-success btn-sm" onclick="BruteForceManager.desbloquear(${b.id})">
                            Desbloquear
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    },

    /**
     * Renderiza paginação de bloqueios
     */
    renderizarPaginacaoBloqueios(paginacao) {
        if (!paginacao) return;

        const { pagina_atual, total_paginas } = paginacao;

        this.elements.bloqueiosPagination.innerHTML = `
            <button onclick="BruteForceManager.carregarBloqueios(${pagina_atual - 1})" ${pagina_atual <= 1 ? 'disabled' : ''}>
                Anterior
            </button>
            <span>Página ${pagina_atual} de ${total_paginas}</span>
            <button onclick="BruteForceManager.carregarBloqueios(${pagina_atual + 1})" ${pagina_atual >= total_paginas ? 'disabled' : ''}>
                Próxima
            </button>
        `;
    },

    /**
     * Aplica filtros nos bloqueios
     */
    aplicarFiltrosBloqueios() {
        this.state.bloqueios.filters = {};

        const tipo = document.getElementById('filter-tipo-bloqueio').value;
        const email = document.getElementById('filter-bloqueio-email').value;
        const ip = document.getElementById('filter-bloqueio-ip').value;

        if (tipo) this.state.bloqueios.filters.tipo = tipo;
        if (email) this.state.bloqueios.filters.email = email;
        if (ip) this.state.bloqueios.filters.ip_address = ip;

        this.carregarBloqueios(1);
    },

    /**
     * Limpa filtros dos bloqueios
     */
    limparFiltrosBloqueios() {
        document.getElementById('filter-tipo-bloqueio').value = '';
        document.getElementById('filter-bloqueio-email').value = '';
        document.getElementById('filter-bloqueio-ip').value = '';
        this.state.bloqueios.filters = {};
        this.carregarBloqueios(1);
    },

    /**
     * Remove um bloqueio
     */
    async desbloquear(id) {
        if (!confirm('Deseja realmente desbloquear este item?')) {
            return;
        }

        try {
            const response = await API.delete(`/login-bloqueios/${id}`);

            if (!response.sucesso) {
                throw new Error(response.mensagem || 'Erro ao desbloquear');
            }

            API.showSuccess('Bloqueio removido com sucesso!');
            await this.carregarBloqueios(this.state.bloqueios.page);
            // await this.carregarEstatisticas(); // Comentado: elementos DOM não existem na página

        } catch (error) {
            console.error('Erro ao desbloquear:', error);
            API.showError('Erro ao remover bloqueio');
        }
    },

    /**
     * Abre modal de novo bloqueio
     */
    abrirModalNovoBloqueio() {
        this.elements.modalNovoBloqueio.classList.add('show');
        this.limparFormNovoBloqueio();
    },

    /**
     * Fecha modal de novo bloqueio
     */
    fecharModalNovoBloqueio() {
        this.elements.modalNovoBloqueio.classList.remove('show');
        this.limparFormNovoBloqueio();
    },

    /**
     * Limpa formulário de novo bloqueio
     */
    limparFormNovoBloqueio() {
        document.getElementById('novo-tipo-bloqueio').value = '';
        document.getElementById('novo-email-bloqueio').value = '';
        document.getElementById('novo-ip-bloqueio').value = '';
        document.getElementById('novo-permanente').checked = false;
        document.getElementById('novo-motivo').value = '';
        this.ajustarCamposBloqueio();
    },

    /**
     * Ajusta visibilidade dos campos do formulário de bloqueio
     */
    ajustarCamposBloqueio() {
        const tipo = document.getElementById('novo-tipo-bloqueio').value;
        const campoEmail = document.getElementById('campo-email-bloqueio');
        const campoIp = document.getElementById('campo-ip-bloqueio');

        // Mostra/oculta campos baseado no tipo
        if (tipo === 'email') {
            campoEmail.style.display = 'flex';
            campoIp.style.display = 'none';
            document.getElementById('novo-email-bloqueio').required = true;
            document.getElementById('novo-ip-bloqueio').required = false;
        } else if (tipo === 'ip') {
            campoEmail.style.display = 'none';
            campoIp.style.display = 'flex';
            document.getElementById('novo-email-bloqueio').required = false;
            document.getElementById('novo-ip-bloqueio').required = true;
        } else if (tipo === 'ambos') {
            campoEmail.style.display = 'flex';
            campoIp.style.display = 'flex';
            document.getElementById('novo-email-bloqueio').required = true;
            document.getElementById('novo-ip-bloqueio').required = true;
        } else {
            campoEmail.style.display = 'flex';
            campoIp.style.display = 'flex';
            document.getElementById('novo-email-bloqueio').required = false;
            document.getElementById('novo-ip-bloqueio').required = false;
        }
    },

    /**
     * Cria um novo bloqueio
     */
    async criarBloqueio() {
        const tipo = document.getElementById('novo-tipo-bloqueio').value;
        const email = document.getElementById('novo-email-bloqueio').value;
        const ip = document.getElementById('novo-ip-bloqueio').value;
        const permanente = document.getElementById('novo-permanente').checked;
        const motivo = document.getElementById('novo-motivo').value;

        // Validações
        if (!tipo) {
            API.showError('Selecione o tipo de bloqueio');
            return;
        }

        if ((tipo === 'email' || tipo === 'ambos') && !email) {
            API.showError('Email é obrigatório para este tipo de bloqueio');
            return;
        }

        if ((tipo === 'ip' || tipo === 'ambos') && !ip) {
            API.showError('IP é obrigatório para este tipo de bloqueio');
            return;
        }

        const dados = {
            tipo,
            permanente,
            motivo: motivo || 'Bloqueio manual por administrador'
        };

        if (email) dados.email = email;
        if (ip) dados.ip_address = ip;

        try {
            const response = await API.post('/login-bloqueios', dados);

            if (!response.sucesso) {
                throw new Error(response.mensagem || 'Erro ao criar bloqueio');
            }

            API.showSuccess('Bloqueio criado com sucesso!');
            this.fecharModalNovoBloqueio();
            await this.carregarBloqueios(1);
            // await this.carregarEstatisticas(); // Comentado: elementos DOM não existem na página

        } catch (error) {
            console.error('Erro ao criar bloqueio:', error);
            API.showError(error.message || 'Erro ao criar bloqueio');
        }
    }
};

// ==================== FUNÇÕES GLOBAIS ====================

// Navegação
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');

    sidebar.classList.toggle('open');
    sidebar.classList.toggle('closed');
    mainContent.classList.toggle('expanded');
}

function switchTab(tabName) {
    // Remove active de todos os botões e conteúdos
    document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

    // Adiciona active ao botão e conteúdo selecionado
    event.target.classList.add('active');
    document.getElementById(`tab-${tabName}`).classList.add('active');

    // Carrega dados da tab
    switch(tabName) {
        case 'estatisticas':
            // BruteForceManager.carregarEstatisticas(); // Comentado: elementos DOM não existem na página
            console.warn('Tab de estatísticas não disponível - elementos DOM não implementados');
            break;
        case 'tentativas':
            BruteForceManager.carregarTentativas();
            break;
        case 'bloqueios':
            BruteForceManager.carregarBloqueios();
            break;
    }
}

// Autenticação
async function logout() {
    await AuthAPI.logout();
}

// Modal
function abrirModalNovoBloqueio() {
    BruteForceManager.abrirModalNovoBloqueio();
}

function fecharModalNovoBloqueio() {
    BruteForceManager.fecharModalNovoBloqueio();
}

function ajustarCamposBloqueio() {
    BruteForceManager.ajustarCamposBloqueio();
}

function criarBloqueio() {
    BruteForceManager.criarBloqueio();
}

// Filtros
function aplicarFiltrosTentativas() {
    BruteForceManager.aplicarFiltrosTentativas();
}

function limparFiltrosTentativas() {
    BruteForceManager.limparFiltrosTentativas();
}

function aplicarFiltrosBloqueios() {
    BruteForceManager.aplicarFiltrosBloqueios();
}

function limparFiltrosBloqueios() {
    BruteForceManager.limparFiltrosBloqueios();
}

// Fecha modal ao clicar fora
document.addEventListener('click', (e) => {
    const modal = document.getElementById('modalNovoBloqueio');
    if (e.target === modal) {
        fecharModalNovoBloqueio();
    }
});

// Inicialização
document.addEventListener('DOMContentLoaded', async () => {
    await BruteForceManager.init();

    // Event listener para botão de salvar configurações
    const btnSalvarConfig = document.getElementById('btnSalvarConfig');
    if (btnSalvarConfig) {
        btnSalvarConfig.addEventListener('click', () => {
            BruteForceManager.salvarConfiguracoes();
        });
    }

    // Event listeners para botões de filtro
    const btnFiltrarBloqueios = document.getElementById('btnFiltrarBloqueios');
    if (btnFiltrarBloqueios) {
        btnFiltrarBloqueios.addEventListener('click', () => {
            BruteForceManager.aplicarFiltrosBloqueios();
        });
    }

    // Event listener para botão de novo bloqueio
    const btnNovoBloqueio = document.getElementById('btnNovoBloqueio');
    if (btnNovoBloqueio) {
        btnNovoBloqueio.addEventListener('click', () => {
            BruteForceManager.abrirModalNovoBloqueio();
        });
    }

    // Event listeners do modal
    const closeModalBloqueio = document.getElementById('closeModalBloqueio');
    if (closeModalBloqueio) {
        closeModalBloqueio.addEventListener('click', () => {
            BruteForceManager.fecharModalNovoBloqueio();
        });
    }

    const btnCancelarBloqueio = document.getElementById('btnCancelarBloqueio');
    if (btnCancelarBloqueio) {
        btnCancelarBloqueio.addEventListener('click', () => {
            BruteForceManager.fecharModalNovoBloqueio();
        });
    }

    const formNovoBloqueio = document.getElementById('formNovoBloqueio');
    if (formNovoBloqueio) {
        formNovoBloqueio.addEventListener('submit', (e) => {
            e.preventDefault();
            BruteForceManager.criarBloqueio();
        });
    }

    const novoTipoBloqueio = document.getElementById('novo-tipo-bloqueio');
    if (novoTipoBloqueio) {
        novoTipoBloqueio.addEventListener('change', () => {
            BruteForceManager.ajustarCamposBloqueio();
        });
    }
});
