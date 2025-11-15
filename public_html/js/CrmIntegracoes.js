/**
 * Gerenciador de Integrações CRM
 * Implementa CRUD de integrações CRM com sincronização e monitoramento
 */

const CrmManager = {
    // Estado da aplicação
    state: {
        integracoes: [],
        integracaoAtual: null,
        stats: null,
        logs: [],
        editando: false
    },

    // Elementos DOM
    elements: {
        loading: document.getElementById('loading'),
        logsLoading: document.getElementById('logsLoading'),
        integracoesContainer: document.getElementById('integracoesContainer'),
        logsContainer: document.getElementById('logsContainer'),
        emptyState: document.getElementById('emptyState'),
        successAlert: document.getElementById('successAlert'),
        errorAlert: document.getElementById('errorAlert'),
        modalIntegracao: document.getElementById('modalIntegracao'),
        formIntegracao: document.getElementById('formIntegracao'),
        modalTitle: document.getElementById('modalTitle'),
        connectionTest: document.getElementById('connectionTest'),
        statsGrid: document.getElementById('statsGrid'),
        statPendentes: document.getElementById('statPendentes'),
        statProcessadosHoje: document.getElementById('statProcessadosHoje'),
        statErros24h: document.getElementById('statErros24h'),
        statTaxaSucesso: document.getElementById('statTaxaSucesso')
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
        this.elements.formIntegracao.addEventListener('submit', (e) => {
            e.preventDefault();
            this.salvar();
        });

        // Carrega dados
        await this.carregar();
        await this.carregarEstatisticas();
        await this.carregarLogs();

        // Auto-refresh stats a cada 30 segundos
        setInterval(() => this.carregarEstatisticas(), 30000);
    },

    /**
     * Carrega as integrações
     */
    async carregar() {
        this.showLoading();

        try {
            const response = await API.get('/crm/integracoes');

            if (response.sucesso) {
                this.state.integracoes = response.dados || [];
                this.renderizar();
            } else {
                throw new Error(response.mensagem || 'Erro ao carregar integrações');
            }
        } catch (error) {
            console.error('Erro ao carregar integrações:', error);
            const mensagem = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar integrações';
            this.showError(mensagem);
        } finally {
            this.hideLoading();
        }
    },

    /**
     * Carrega estatísticas da fila
     */
    async carregarEstatisticas() {
        try {
            const response = await API.get('/crm/estatisticas');

            if (response.sucesso) {
                this.state.stats = response.dados;
                this.renderizarEstatisticas();
            }
        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
        }
    },

    /**
     * Carrega logs recentes
     */
    async carregarLogs() {
        this.elements.logsLoading.style.display = 'block';
        this.elements.logsContainer.innerHTML = '<div class="loading" id="logsLoading">Carregando logs...</div>';

        try {
            const response = await API.get('/crm/logs?limit=20');

            if (response.sucesso) {
                this.state.logs = response.dados || [];
                this.renderizarLogs();
            }
        } catch (error) {
            console.error('Erro ao carregar logs:', error);
            this.elements.logsContainer.innerHTML = '<div class="empty-state"><p>Erro ao carregar logs</p></div>';
        }
    },

    /**
     * Renderiza estatísticas
     */
    renderizarEstatisticas() {
        if (!this.state.stats) return;

        const stats = this.state.stats;
        this.elements.statPendentes.textContent = stats.pendentes || 0;
        this.elements.statProcessadosHoje.textContent = stats.processados_hoje || 0;
        this.elements.statErros24h.textContent = stats.erros_24h || 0;
        this.elements.statTaxaSucesso.textContent = (stats.taxa_sucesso || 0) + '%';
    },

    /**
     * Renderiza lista de integrações
     */
    renderizar() {
        if (this.state.integracoes.length === 0) {
            this.elements.integracoesContainer.style.display = 'none';
            this.elements.emptyState.style.display = 'block';
            return;
        }

        this.elements.emptyState.style.display = 'none';
        this.elements.integracoesContainer.style.display = 'block';

        const html = `
            <table class="table">
                <thead>
                    <tr>
                        <th>Provider</th>
                        <th>Status</th>
                        <th>Última Atualização</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    ${this.state.integracoes.map(integracao => this.renderizarLinha(integracao)).join('')}
                </tbody>
            </table>
        `;

        this.elements.integracoesContainer.innerHTML = html;
    },

    /**
     * Renderiza uma linha da tabela
     */
    renderizarLinha(integracao) {
        const providerNome = this.formatarProvider(integracao.provider);
        const status = integracao.ativo ? 'ativo' : 'inativo';
        const statusIcon = integracao.ativo ? 'check-circle' : 'times-circle';
        const dataAtualizacao = integracao.atualizado_em ?
            new Date(integracao.atualizado_em).toLocaleString('pt-BR') : '-';

        return `
            <tr>
                <td>
                    <span class="provider-badge ${integracao.provider}">${providerNome}</span>
                </td>
                <td>
                    <span class="status-badge ${status}">
                        <i class="fas fa-${statusIcon}"></i>
                        ${status === 'ativo' ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>${dataAtualizacao}</td>
                <td>
                    <div class="action-buttons">
                        <button class="btn btn-warning btn-sm" onclick="CrmManager.testarConexaoIntegracao(${integracao.id})" title="Testar Conexão">
                            <i class="fas fa-plug"></i>
                        </button>
                        <button class="btn btn-info btn-sm" onclick="CrmManager.sincronizarManual(${integracao.id})" title="Sincronizar Agora">
                            <i class="fas fa-sync"></i>
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="CrmManager.editar(${integracao.id})" title="Editar">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="CrmManager.deletar(${integracao.id})" title="Excluir">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    },

    /**
     * Renderiza logs
     */
    renderizarLogs() {
        if (this.state.logs.length === 0) {
            this.elements.logsContainer.innerHTML = `
                <div class="empty-state">
                    <i class="fas fa-file-alt"></i>
                    <p>Nenhum log encontrado</p>
                </div>
            `;
            return;
        }

        const html = this.state.logs.map(log => {
            const data = new Date(log.criado_em);
            const tempo = this.formatarTempoRelativo(data);

            return `
                <div class="log-item">
                    <div class="log-header">
                        <span class="log-status ${log.status}">${log.status.toUpperCase()}</span>
                        <span class="log-time">${tempo}</span>
                    </div>
                    <div class="log-message">
                        <strong>${log.entidade}</strong> #${log.id_registro}: ${log.mensagem}
                    </div>
                </div>
            `;
        }).join('');

        this.elements.logsContainer.innerHTML = html;
    },

    /**
     * Abre modal para nova integração
     */
    abrirModal() {
        this.state.editando = false;
        this.state.integracaoAtual = null;
        this.elements.modalTitle.textContent = 'Nova Integração CRM';
        this.elements.formIntegracao.reset();
        this.elements.formIntegracao.ativo.checked = true;
        this.elements.connectionTest.style.display = 'none';

        // Esconde campos de credenciais inicialmente
        document.getElementById('credenciaisGestaoClick').style.display = 'none';
        document.getElementById('credenciaisGenericas').style.display = 'none';

        // Limpa valores dos campos de credenciais
        document.getElementById('access_token').value = '';
        document.getElementById('secret_access_token').value = '';
        document.getElementById('api_token').value = '';

        this.elements.modalIntegracao.classList.add('show');
    },

    /**
     * Fecha modal
     */
    fecharModal() {
        this.elements.modalIntegracao.classList.remove('show');
        this.elements.formIntegracao.reset();
        this.elements.connectionTest.style.display = 'none';
        // Reset dos campos de credenciais
        document.getElementById('credenciaisGestaoClick').style.display = 'none';
        document.getElementById('credenciaisGenericas').style.display = 'none';
    },

    /**
     * Manipula mudança de provider - mostra campos de credenciais corretos
     */
    onProviderChange() {
        const form = this.elements.formIntegracao;
        const provider = form.provider.value;

        const camposGestaoClick = document.getElementById('credenciaisGestaoClick');
        const camposGenericos = document.getElementById('credenciaisGenericas');

        // Mostra campos apropriados (NÃO limpa valores para preservar o que foi digitado)
        if (provider === 'gestao_click') {
            camposGestaoClick.style.display = 'block';
            camposGenericos.style.display = 'none';
            // Torna campos do GestãoClick obrigatórios
            document.getElementById('access_token').required = true;
            document.getElementById('secret_access_token').required = true;
            document.getElementById('api_token').required = false;
        } else if (provider) {
            camposGestaoClick.style.display = 'none';
            camposGenericos.style.display = 'block';
            // Torna campo genérico obrigatório
            document.getElementById('access_token').required = false;
            document.getElementById('secret_access_token').required = false;
            document.getElementById('api_token').required = true;
        } else {
            // Nenhum provider selecionado
            camposGestaoClick.style.display = 'none';
            camposGenericos.style.display = 'none';
        }
    },

    /**
     * Editar integração
     */
    async editar(id) {
        const integracao = this.state.integracoes.find(i => i.id === id);
        if (!integracao) return;

        this.state.editando = true;
        this.state.integracaoAtual = integracao;
        this.elements.modalTitle.textContent = 'Editar Integração CRM';

        // Preenche o formulário
        const form = this.elements.formIntegracao;
        form.provider.value = integracao.provider;
        form.ativo.checked = integracao.ativo == 1;

        // Mostra campos de credenciais corretos baseado no provider
        this.onProviderChange();

        // Configura placeholders para edição (não mostra tokens por segurança)
        if (integracao.provider === 'gestao_click') {
            document.getElementById('access_token').placeholder = 'Digite novo access-token ou deixe vazio para manter';
            document.getElementById('secret_access_token').placeholder = 'Digite novo secret-access-token ou deixe vazio para manter';
        } else {
            document.getElementById('api_token').placeholder = 'Digite novo token ou deixe vazio para manter';
        }

        // Preenche configurações se existirem
        if (integracao.configuracoes) {
            try {
                const config = typeof integracao.configuracoes === 'string' ?
                    JSON.parse(integracao.configuracoes) : integracao.configuracoes;
                form.webhook_url.value = config.webhook_url || '';
            } catch (e) {
                console.error('Erro ao parsear configurações:', e);
            }
        }

        this.elements.connectionTest.style.display = 'none';
        this.elements.modalIntegracao.classList.add('show');
    },

    /**
     * Salvar integração
     */
    async salvar() {
        this.hideAlerts();

        const form = this.elements.formIntegracao;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Coleta dados
        const dados = {
            provider: form.provider.value,
            ativo: form.ativo.checked ? 1 : 0,
            configuracoes: {
                webhook_url: form.webhook_url.value || null
            }
        };

        // Coleta credenciais baseado no provider
        if (form.provider.value === 'gestao_click') {
            // GestãoClick usa dois tokens
            const accessToken = document.getElementById('access_token').value.trim();
            const secretToken = document.getElementById('secret_access_token').value.trim();

            // Na criação, tokens são SEMPRE obrigatórios
            if (!this.state.editando && (!accessToken || !secretToken)) {
                this.showError('Preencha os dois tokens para o GestãoClick');
                return;
            }

            // Só envia credenciais se ambos estiverem preenchidos
            if (accessToken && secretToken) {
                dados.credenciais = {
                    access_token: accessToken,
                    secret_access_token: secretToken
                };
            }
        } else {
            // Outros providers usam token único
            const apiToken = document.getElementById('api_token').value.trim();

            // Na criação, token é SEMPRE obrigatório
            if (!this.state.editando && !apiToken) {
                this.showError('Preencha o token da API');
                return;
            }

            // Só envia se preenchido
            if (apiToken) {
                dados.credenciais = {
                    api_token: apiToken
                };
            }
        }

        // DEBUG: Log dos dados que serão enviados
        console.log('=== DEBUG SALVAR INTEGRAÇÃO ===');
        console.log('Editando:', this.state.editando);
        console.log('Provider:', dados.provider);
        console.log('Credenciais:', dados.credenciais);
        console.log('Dados completos:', JSON.stringify(dados, null, 2));
        console.log('==============================');

        const btnSalvar = document.getElementById('btnSalvar');
        btnSalvar.disabled = true;
        btnSalvar.textContent = 'Salvando...';

        try {
            let response;
            if (this.state.editando) {
                response = await API.put(`/crm/integracoes/${this.state.integracaoAtual.id}`, dados);
            } else {
                response = await API.post('/crm/integracoes', dados);
            }

            if (response.sucesso) {
                this.showSuccess(
                    this.state.editando ?
                    'Integração atualizada com sucesso!' :
                    'Integração criada com sucesso!'
                );
                this.fecharModal();
                await this.carregar();
            } else {
                throw new Error(response.mensagem || 'Erro ao salvar integração');
            }
        } catch (error) {
            console.error('Erro ao salvar:', error);
            const mensagem = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao salvar integração';
            this.showError(mensagem);
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.textContent = 'Salvar';
        }
    },

    /**
     * Deletar integração
     */
    async deletar(id) {
        if (!confirm('Tem certeza que deseja excluir esta integração? Esta ação não pode ser desfeita.')) {
            return;
        }

        try {
            const response = await API.delete(`/crm/integracoes/${id}`);

            if (response.sucesso) {
                this.showSuccess('Integração excluída com sucesso!');
                await this.carregar();
            } else {
                throw new Error(response.mensagem || 'Erro ao excluir integração');
            }
        } catch (error) {
            console.error('Erro ao excluir:', error);
            const mensagem = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao excluir integração';
            this.showError(mensagem);
        }
    },

    /**
     * Testar conexão (modal)
     */
    async testarConexao() {
        const form = this.elements.formIntegracao;
        const provider = form.provider.value;

        // Prepara dados para teste baseado no provider
        const dadosTeste = {
            provider: provider
        };

        // Valida e coleta credenciais baseado no provider
        if (provider === 'gestao_click') {
            const accessToken = document.getElementById('access_token').value.trim();
            const secretToken = document.getElementById('secret_access_token').value.trim();

            if (!accessToken || !secretToken) {
                this.showError('Preencha os dois tokens do GestãoClick para testar a conexão');
                return;
            }

            dadosTeste.access_token = accessToken;
            dadosTeste.secret_access_token = secretToken;
        } else {
            const apiToken = document.getElementById('api_token').value.trim();

            if (!apiToken) {
                this.showError('Digite o token da API para testar a conexão');
                return;
            }

            dadosTeste.api_token = apiToken;
        }

        const testDiv = this.elements.connectionTest;
        testDiv.style.display = 'block';
        testDiv.className = 'connection-test testing';
        testDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando conexão...';

        try {
            const response = await API.post('/crm/testar-conexao', dadosTeste);

            if (response.sucesso && response.dados.success) {
                testDiv.className = 'connection-test success';
                testDiv.innerHTML = `<i class="fas fa-check-circle"></i> ${response.dados.message}`;
            } else {
                testDiv.className = 'connection-test error';
                testDiv.innerHTML = `<i class="fas fa-times-circle"></i> ${response.dados.message || 'Falha na conexão'}`;
            }
        } catch (error) {
            console.error('Erro ao testar conexão:', error);
            testDiv.className = 'connection-test error';
            testDiv.innerHTML = '<i class="fas fa-times-circle"></i> Erro ao testar conexão';
        }
    },

    /**
     * Testar conexão de integração existente
     */
    async testarConexaoIntegracao(id) {
        try {
            const response = await API.post(`/crm/integracoes/${id}/testar`);

            if (response.sucesso && response.dados.success) {
                this.showSuccess(response.dados.message);
            } else {
                this.showError(response.dados.message || 'Falha na conexão com o CRM');
            }
        } catch (error) {
            console.error('Erro ao testar conexão:', error);
            this.showError('Erro ao testar conexão com o CRM');
        }
    },

    /**
     * Sincronizar manualmente
     */
    async sincronizarManual(id) {
        if (!confirm('Deseja enfileirar uma sincronização completa? Isso pode levar alguns minutos.')) {
            return;
        }

        try {
            const response = await API.post(`/crm/integracoes/${id}/sincronizar`);

            if (response.sucesso) {
                this.showSuccess(response.mensagem || 'Sincronização iniciada! Verifique os logs em alguns minutos.');
                await this.carregarEstatisticas();
            } else {
                throw new Error(response.mensagem || 'Erro ao iniciar sincronização');
            }
        } catch (error) {
            console.error('Erro ao sincronizar:', error);
            this.showError('Erro ao iniciar sincronização');
        }
    },

    /**
     * Sincronizar entidade específica
     */
    async sincronizarEntidade(entidade) {
        const nomes = {
            'cliente': 'Clientes',
            'produto': 'Produtos',
            'venda': 'Vendas'
        };

        const btnId = `btnSync${nomes[entidade].replace(/\s/g, '')}`;
        const btn = document.getElementById(btnId);
        const resultDiv = document.getElementById('syncResult');

        if (!confirm(`Deseja sincronizar todos os ${nomes[entidade].toLowerCase()}?\n\nIsso enfileirá todos os registros ativos para sincronização com o CRM.`)) {
            return;
        }

        // Desabilita botão e mostra loading
        btn.disabled = true;
        btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Sincronizando...`;

        resultDiv.style.display = 'block';
        resultDiv.className = 'connection-test testing';
        resultDiv.innerHTML = `<i class="fas fa-spinner fa-spin"></i> Buscando ${nomes[entidade].toLowerCase()} para enfileirar...`;

        try {
            const response = await API.post(`/crm/sincronizar/${entidade}`);

            if (response.sucesso) {
                resultDiv.className = 'connection-test success';
                resultDiv.innerHTML = `
                    <i class="fas fa-check-circle"></i>
                    <strong>✅ ${nomes[entidade]} enfileirados com sucesso!</strong><br>
                    <span style="font-size: 0.875rem;">
                        Total: ${response.dados.total || 0} registros<br>
                        Os itens serão processados automaticamente pelo cron.
                    </span>
                `;

                // Atualiza estatísticas
                await this.carregarEstatisticas();

                // Esconde após 10 segundos
                setTimeout(() => {
                    resultDiv.style.display = 'none';
                }, 10000);
            } else {
                throw new Error(response.mensagem || 'Erro ao sincronizar');
            }
        } catch (error) {
            console.error(`Erro ao sincronizar ${entidade}:`, error);
            resultDiv.className = 'connection-test error';
            resultDiv.innerHTML = `
                <i class="fas fa-times-circle"></i>
                <strong>Erro ao sincronizar ${nomes[entidade].toLowerCase()}</strong><br>
                <span style="font-size: 0.875rem;">${error.message || 'Erro desconhecido'}</span>
            `;
        } finally {
            // Reabilita botão
            btn.disabled = false;
            const icons = {
                'cliente': 'users',
                'produto': 'box',
                'venda': 'shopping-cart'
            };
            btn.innerHTML = `<i class="fas fa-${icons[entidade]}"></i> Sincronizar ${nomes[entidade]}`;
        }
    },

    /**
     * Formata nome do provider
     */
    formatarProvider(provider) {
        const nomes = {
            'gestao_click': 'Gestão Click',
            'pipedrive': 'Pipedrive',
            'bling': 'Bling'
        };
        return nomes[provider] || provider;
    },

    /**
     * Formata tempo relativo
     */
    formatarTempoRelativo(data) {
        const agora = new Date();
        const diff = Math.floor((agora - data) / 1000); // segundos

        if (diff < 60) return 'agora mesmo';
        if (diff < 3600) return `há ${Math.floor(diff / 60)} min`;
        if (diff < 86400) return `há ${Math.floor(diff / 3600)}h`;
        return data.toLocaleDateString('pt-BR');
    },

    /**
     * Mostra loading
     */
    showLoading() {
        this.elements.loading.style.display = 'block';
        this.elements.integracoesContainer.style.display = 'none';
        this.elements.emptyState.style.display = 'none';
    },

    /**
     * Esconde loading
     */
    hideLoading() {
        this.elements.loading.style.display = 'none';
    },

    /**
     * Mostra mensagem de sucesso
     */
    showSuccess(mensagem) {
        this.hideAlerts();
        this.elements.successAlert.textContent = mensagem;
        this.elements.successAlert.classList.add('show');
        setTimeout(() => this.elements.successAlert.classList.remove('show'), 5000);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    /**
     * Mostra mensagem de erro
     */
    showError(mensagem) {
        this.hideAlerts();
        this.elements.errorAlert.textContent = mensagem;
        this.elements.errorAlert.classList.add('show');
        setTimeout(() => this.elements.errorAlert.classList.remove('show'), 8000);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    /**
     * Esconde todos os alertas
     */
    hideAlerts() {
        this.elements.successAlert.classList.remove('show');
        this.elements.errorAlert.classList.remove('show');
    }
};

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    CrmManager.init();
});

// Exporta para uso global
window.CrmManager = CrmManager;
