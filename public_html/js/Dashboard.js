/**
 * Dashboard.js
 * Sistema de Dashboards Customizáveis
 * Ecletech - 2025
 */

const Dashboard = {
    // Estado
    dashboardAtual: null,
    dashboards: [],
    widgetsTipos: [],
    templates: [],
    grid: null,
    widgets: new Map(), // widgetId => {dados, chart, interval}

    /**
     * Inicializa o sistema de dashboards
     */
    async inicializar() {
        try {
            // Carrega dados iniciais
            await this.carregarWidgetsTipos();
            await this.carregarTemplates();
            await this.carregarDashboards();

            // Inicializa GridStack
            this.inicializarGrid();

            // Configura event listeners
            this.configurarEventos();

            // Carrega dashboard padrão
            await this.carregarDashboardPadrao();

        } catch (error) {
            console.error('Erro ao inicializar dashboard:', error);
            API.showError('Erro ao carregar dashboard');
        }
    },

    /**
     * Carrega tipos de widgets disponíveis
     */
    async carregarWidgetsTipos() {
        try {
            const response = await API.get('/dashboard/widget-tipos');
            if (response.sucesso) {
                this.widgetsTipos = response.dados;
            }
        } catch (error) {
            console.error('Erro ao carregar widgets:', error);
        }
    },

    /**
     * Carrega templates disponíveis
     */
    async carregarTemplates() {
        try {
            const response = await API.get('/dashboard/templates');
            if (response.sucesso) {
                this.templates = response.dados;
                this.renderizarTemplates();
            }
        } catch (error) {
            console.error('Erro ao carregar templates:', error);
        }
    },

    /**
     * Carrega lista de dashboards do usuário
     */
    async carregarDashboards() {
        try {
            const response = await API.get('/dashboard');
            if (response.sucesso) {
                this.dashboards = response.dados;
                this.atualizarSeletorDashboards();
            }
        } catch (error) {
            console.error('Erro ao carregar dashboards:', error);
        }
    },

    /**
     * Carrega dashboard padrão
     */
    async carregarDashboardPadrao() {
        try {
            const response = await API.get('/dashboard/padrao');
            if (response.sucesso) {
                await this.carregarDashboard(response.dados);
            }
        } catch (error) {
            console.error('Erro ao carregar dashboard padrão:', error);
        }
    },

    /**
     * Carrega um dashboard específico
     */
    async carregarDashboard(dashboard) {
        try {
            // Limpa grid
            this.limparGrid();

            // Define dashboard atual
            this.dashboardAtual = dashboard;

            // Atualiza título
            document.getElementById('dashboard-nome').textContent = dashboard.nome;
            document.getElementById('select-dashboard').value = dashboard.id;

            // Carrega widgets
            if (dashboard.widgets && dashboard.widgets.length > 0) {
                for (const widget of dashboard.widgets) {
                    await this.adicionarWidgetAoGrid(widget);
                }
            }

        } catch (error) {
            console.error('Erro ao carregar dashboard:', error);
            API.showError('Erro ao carregar dashboard');
        }
    },

    /**
     * Inicializa GridStack
     */
    inicializarGrid() {
        this.grid = GridStack.init({
            column: 12,
            cellHeight: 80,
            float: true,
            animate: true,
            draggable: {
                handle: '.widget-header'
            },
            resizable: {
                handles: 'e,se,s,sw,w'
            }
        });

        // Evento ao mover/redimensionar
        this.grid.on('change', (event, items) => {
            this.salvarPosicoes(items);
        });
    },

    /**
     * Adiciona widget ao grid
     */
    async adicionarWidgetAoGrid(widget) {
        // Cria HTML do widget
        const widgetHtml = this.criarWidgetHTML(widget);

        // Adiciona ao grid
        this.grid.addWidget(widgetHtml, {
            x: widget.posicao_x,
            y: widget.posicao_y,
            w: widget.largura,
            h: widget.altura,
            id: widget.id
        });

        // Carrega dados do widget
        await this.carregarDadosWidget(widget.id);

        // Inicia atualização automática
        this.iniciarAtualizacaoAutomatica(widget.id, widget.intervalo_atualizacao || 300);
    },

    /**
     * Cria HTML do widget
     */
    criarWidgetHTML(widget) {
        const titulo = widget.titulo || widget.widget_tipo_nome;
        const icone = widget.icone || 'fa-chart-line';

        const div = document.createElement('div');
        div.classList.add('grid-stack-item');
        div.setAttribute('data-widget-id', widget.id);
        div.setAttribute('data-gs-id', widget.id);

        div.innerHTML = `
            <div class="grid-stack-item-content">
                <div class="widget">
                    <div class="widget-header">
                        <h3>
                            <i class="fas ${icone}"></i>
                            ${titulo}
                        </h3>
                        <div class="widget-actions">
                            <button class="btn-icon" onclick="Dashboard.atualizarWidget(${widget.id})" title="Atualizar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                            <button class="btn-icon" onclick="Dashboard.removerWidget(${widget.id})" title="Remover">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="widget-body">
                        <div class="widget-loading">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <div class="widget-content hidden" id="widget-content-${widget.id}">
                            <!-- Conteúdo renderizado aqui -->
                        </div>
                        <div class="widget-error hidden" id="widget-error-${widget.id}">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Erro ao carregar dados</span>
                        </div>
                    </div>
                    <div class="widget-footer">
                        <small>Atualizado há <span id="widget-time-${widget.id}">-</span></small>
                    </div>
                </div>
            </div>
        `;

        return div;
    },

    /**
     * Carrega dados do widget
     */
    async carregarDadosWidget(widgetId) {
        try {
            const loadingEl = document.querySelector(`[data-widget-id="${widgetId}"] .widget-loading`);
            const contentEl = document.getElementById(`widget-content-${widgetId}`);
            const errorEl = document.getElementById(`widget-error-${widgetId}`);

            if (loadingEl) loadingEl.classList.remove('hidden');
            if (contentEl) contentEl.classList.add('hidden');
            if (errorEl) errorEl.classList.add('hidden');

            const response = await API.get(`/dashboard/widgets/${widgetId}/dados`);

            if (response.sucesso) {
                // Encontra widget
                const widget = this.dashboardAtual.widgets.find(w => w.id == widgetId);
                if (!widget) return;

                // Renderiza dados
                this.renderizarWidget(widgetId, widget.tipo_visual, response.dados);

                // Atualiza timestamp
                this.atualizarTimestamp(widgetId);

                if (loadingEl) loadingEl.classList.add('hidden');
                if (contentEl) contentEl.classList.remove('hidden');
            } else {
                throw new Error(response.mensagem);
            }

        } catch (error) {
            console.error('Erro ao carregar dados do widget:', error);
            const loadingEl = document.querySelector(`[data-widget-id="${widgetId}"] .widget-loading`);
            const errorEl = document.getElementById(`widget-error-${widgetId}`);

            if (loadingEl) loadingEl.classList.add('hidden');
            if (errorEl) errorEl.classList.remove('hidden');
        }
    },

    /**
     * Renderiza widget baseado no tipo visual
     */
    renderizarWidget(widgetId, tipoVisual, dados) {
        const contentEl = document.getElementById(`widget-content-${widgetId}`);
        if (!contentEl) return;

        // Destroi chart anterior se existir
        const widgetData = this.widgets.get(widgetId);
        if (widgetData && widgetData.chart) {
            widgetData.chart.destroy();
        }

        // Renderiza baseado no tipo
        switch (tipoVisual) {
            case 'grafico_linha':
            case 'grafico_barra':
            case 'grafico_area':
                this.renderizarGrafico(widgetId, tipoVisual, dados, contentEl);
                break;

            case 'grafico_pizza':
            case 'grafico_donut':
                this.renderizarGraficoPizza(widgetId, tipoVisual, dados, contentEl);
                break;

            case 'card':
                this.renderizarCard(dados, contentEl);
                break;

            case 'contador':
                this.renderizarContador(dados, contentEl);
                break;

            case 'lista':
                this.renderizarLista(dados, contentEl);
                break;

            case 'tabela':
                this.renderizarTabela(dados, contentEl);
                break;

            case 'cards_multiplos':
                this.renderizarCardsMultiplos(dados, contentEl);
                break;

            default:
                contentEl.innerHTML = '<p>Tipo de widget não suportado</p>';
        }
    },

    /**
     * Renderiza gráfico (linha/barra/área)
     */
    renderizarGrafico(widgetId, tipo, dados, container) {
        const canvas = document.createElement('canvas');
        container.innerHTML = '';
        container.appendChild(canvas);

        const chartType = tipo === 'grafico_linha' ? 'line' :
                         tipo === 'grafico_barra' ? 'bar' : 'line';

        let datasets = [];

        if (dados.datasets) {
            // Múltiplos datasets (ex: fluxo de caixa)
            datasets = dados.datasets;
        } else {
            // Dataset único
            datasets = [{
                label: 'Valores',
                data: dados.values || [],
                borderColor: '#3498db',
                backgroundColor: tipo === 'grafico_area' ?
                    'rgba(52, 152, 219, 0.2)' : 'rgba(52, 152, 219, 0.8)',
                tension: 0.4,
                fill: tipo === 'grafico_area'
            }];
        }

        const chart = new Chart(canvas, {
            type: chartType,
            data: {
                labels: dados.labels || [],
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: datasets.length > 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Salva referência
        this.widgets.set(widgetId, { ...this.widgets.get(widgetId), chart });
    },

    /**
     * Renderiza gráfico pizza/donut
     */
    renderizarGraficoPizza(widgetId, tipo, dados, container) {
        const canvas = document.createElement('canvas');
        container.innerHTML = '';
        container.appendChild(canvas);

        const chart = new Chart(canvas, {
            type: tipo === 'grafico_donut' ? 'doughnut' : 'pie',
            data: {
                labels: dados.labels || [],
                datasets: [{
                    data: dados.values || [],
                    backgroundColor: [
                        '#3498db', '#e74c3c', '#2ecc71', '#f39c12', '#9b59b6',
                        '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#16a085'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        this.widgets.set(widgetId, { ...this.widgets.get(widgetId), chart });
    },

    /**
     * Renderiza card simples
     */
    renderizarCard(dados, container) {
        const valor = typeof dados.valor === 'number' && dados.formato === 'moeda' ?
            'R$ ' + dados.valor.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) :
            dados.valor;

        container.innerHTML = `
            <div style="text-align: center; padding: 2rem;">
                ${dados.icone ? `<i class="fas ${dados.icone}" style="font-size: 3rem; color: ${dados.cor || '#3498db'}; margin-bottom: 1rem;"></i>` : ''}
                <h2 style="margin: 0; font-size: 2.5rem; color: ${dados.cor || '#333'};">${valor}</h2>
                ${dados.label ? `<p style="margin: 0.5rem 0 0 0; color: #666;">${dados.label}</p>` : ''}
                ${dados.subtitulo ? `<p style="margin: 0.25rem 0 0 0; font-size: 0.9rem; color: #999;">${dados.subtitulo}</p>` : ''}
            </div>
        `;
    },

    /**
     * Renderiza contador
     */
    renderizarContador(dados, container) {
        this.renderizarCard(dados, container);
    },

    /**
     * Renderiza lista
     */
    renderizarLista(dados, container) {
        if (!dados.items || dados.items.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">Nenhum item encontrado</p>';
            return;
        }

        let html = '<ul style="list-style: none; padding: 0; margin: 0;">';

        dados.items.forEach(item => {
            html += `
                <li style="padding: 0.75rem; border-bottom: 1px solid #e0e0e0;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong>${item.titulo}</strong>
                            ${item.subtitulo ? `<div style="font-size: 0.9rem; color: #666;">${item.subtitulo}</div>` : ''}
                        </div>
                        <div style="text-align: right;">
                            ${item.valor ? `<div style="font-weight: bold;">${item.valor}</div>` : ''}
                            ${item.data ? `<div style="font-size: 0.85rem; color: #999;">${item.data}</div>` : ''}
                        </div>
                    </div>
                </li>
            `;
        });

        html += '</ul>';
        container.innerHTML = html;
    },

    /**
     * Renderiza tabela
     */
    renderizarTabela(dados, container) {
        if (!dados.linhas || dados.linhas.length === 0) {
            container.innerHTML = '<p style="text-align: center; color: #999; padding: 2rem;">Nenhum dado encontrado</p>';
            return;
        }

        let html = '<table style="width: 100%; border-collapse: collapse;">';

        // Cabeçalho
        if (dados.colunas) {
            html += '<thead><tr>';
            dados.colunas.forEach(col => {
                html += `<th style="padding: 0.75rem; border-bottom: 2px solid #e0e0e0; text-align: left; font-weight: 600;">${col}</th>`;
            });
            html += '</tr></thead>';
        }

        // Corpo
        html += '<tbody>';
        dados.linhas.forEach(linha => {
            html += '<tr>';
            linha.forEach(celula => {
                html += `<td style="padding: 0.75rem; border-bottom: 1px solid #e0e0e0;">${celula}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table>';

        container.innerHTML = html;
    },

    /**
     * Renderiza múltiplos cards
     */
    renderizarCardsMultiplos(dados, container) {
        if (!dados.cards || dados.cards.length === 0) {
            container.innerHTML = '<p>Nenhum card disponível</p>';
            return;
        }

        let html = '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">';

        dados.cards.forEach(card => {
            html += `
                <div style="text-align: center; padding: 1rem; background: #f5f5f5; border-radius: 8px;">
                    <i class="fas ${card.icone}" style="font-size: 2rem; color: ${card.cor}; margin-bottom: 0.5rem;"></i>
                    <h3 style="margin: 0.5rem 0; font-size: 1.5rem;">${card.valor}</h3>
                    <p style="margin: 0; font-size: 0.9rem; color: #666;">${card.titulo}</p>
                    ${card.subtitulo ? `<p style="margin: 0.25rem 0 0 0; font-size: 0.8rem; color: #999;">${card.subtitulo}</p>` : ''}
                </div>
            `;
        });

        html += '</div>';
        container.innerHTML = html;
    },

    /**
     * Atualiza timestamp do widget
     */
    atualizarTimestamp(widgetId) {
        const timeEl = document.getElementById(`widget-time-${widgetId}`);
        if (timeEl) {
            timeEl.textContent = 'agora';
        }
    },

    /**
     * Inicia atualização automática
     */
    iniciarAtualizacaoAutomatica(widgetId, intervalo) {
        // Limpa intervalo anterior se existir
        const widgetData = this.widgets.get(widgetId);
        if (widgetData && widgetData.interval) {
            clearInterval(widgetData.interval);
        }

        // Cria novo intervalo
        const interval = setInterval(() => {
            this.carregarDadosWidget(widgetId);
        }, intervalo * 1000);

        this.widgets.set(widgetId, { ...this.widgets.get(widgetId), interval });
    },

    /**
     * Para atualização automática
     */
    pararAtualizacaoAutomatica(widgetId) {
        const widgetData = this.widgets.get(widgetId);
        if (widgetData && widgetData.interval) {
            clearInterval(widgetData.interval);
        }
    },

    /**
     * Atualiza widget manualmente
     */
    async atualizarWidget(widgetId) {
        await this.carregarDadosWidget(widgetId);
        API.showSuccess('Widget atualizado');
    },

    /**
     * Remove widget
     */
    async removerWidget(widgetId) {
        if (!confirm('Deseja remover este widget?')) return;

        try {
            const response = await API.delete(`/dashboard/widgets/${widgetId}`);

            if (response.sucesso) {
                // Para atualização automática
                this.pararAtualizacaoAutomatica(widgetId);

                // Remove do grid
                const elemento = document.querySelector(`[data-widget-id="${widgetId}"]`);
                if (elemento) {
                    this.grid.removeWidget(elemento);
                }

                // Remove do Map
                this.widgets.delete(widgetId);

                API.showSuccess('Widget removido');
            }

        } catch (error) {
            console.error('Erro ao remover widget:', error);
            API.showError('Erro ao remover widget');
        }
    },

    /**
     * Salva posições dos widgets
     */
    async salvarPosicoes(items) {
        if (!this.dashboardAtual) return;

        const widgets = items.map(item => ({
            id: parseInt(item.id),
            x: item.x,
            y: item.y,
            w: item.w,
            h: item.h
        }));

        try {
            await API.put(`/dashboard/${this.dashboardAtual.id}/widgets/posicoes`, { widgets });
        } catch (error) {
            console.error('Erro ao salvar posições:', error);
        }
    },

    /**
     * Limpa grid
     */
    limparGrid() {
        if (this.grid) {
            // Para todas as atualizações automáticas
            this.widgets.forEach((data, widgetId) => {
                this.pararAtualizacaoAutomatica(widgetId);
            });

            this.grid.removeAll();
            this.widgets.clear();
        }
    },

    /**
     * Atualiza seletor de dashboards
     */
    atualizarSeletorDashboards() {
        const select = document.getElementById('select-dashboard');
        select.innerHTML = '';

        this.dashboards.forEach(dashboard => {
            const option = document.createElement('option');
            option.value = dashboard.id;
            option.textContent = dashboard.nome;
            if (dashboard.is_padrao) {
                option.textContent += ' ⭐';
            }
            select.appendChild(option);
        });
    },

    /**
     * Renderiza templates no modal
     */
    renderizarTemplates() {
        const container = document.getElementById('templates-grid');
        if (!container) return;

        container.innerHTML = '';

        this.templates.forEach(template => {
            const card = document.createElement('div');
            card.classList.add('template-card');
            card.setAttribute('data-template-id', template.id);
            card.setAttribute('data-template-codigo', template.codigo);

            card.innerHTML = `
                <i class="fas ${template.icone || 'fa-th-large'}"></i>
                <h4>${template.nome}</h4>
                <p>${template.descricao || ''}</p>
            `;

            card.addEventListener('click', () => {
                document.querySelectorAll('.template-card').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
            });

            container.appendChild(card);
        });
    },

    /**
     * Renderiza catálogo de widgets
     */
    renderizarCatalogo(categoria = 'todas') {
        const container = document.getElementById('lista-widgets');
        if (!container) return;

        container.innerHTML = '';

        const widgets = categoria === 'todas' ?
            this.widgetsTipos :
            this.widgetsTipos.filter(w => w.categoria === categoria);

        widgets.forEach(widget => {
            const item = document.createElement('div');
            item.classList.add('widget-item');

            item.innerHTML = `
                <h4>
                    <i class="fas ${widget.icone || 'fa-cube'}"></i>
                    ${widget.nome}
                </h4>
                <p>${widget.descricao || ''}</p>
                <div class="widget-meta">
                    <span><i class="fas fa-tag"></i> ${widget.categoria}</span>
                    <span><i class="fas fa-chart-${widget.tipo_visual.split('_')[1] || 'line'}"></i> ${widget.tipo_visual}</span>
                </div>
            `;

            item.addEventListener('click', () => {
                this.adicionarNovoWidget(widget);
            });

            container.appendChild(item);
        });
    },

    /**
     * Adiciona novo widget ao dashboard
     */
    async adicionarNovoWidget(widgetTipo) {
        if (!this.dashboardAtual) {
            API.showWarning('Selecione um dashboard primeiro');
            return;
        }

        try {
            const response = await API.post(`/dashboard/${this.dashboardAtual.id}/widgets`, {
                widget_tipo_id: widgetTipo.id,
                posicao_x: 0,
                posicao_y: 0,
                largura: widgetTipo.largura_padrao,
                altura: widgetTipo.altura_padrao
            });

            if (response.sucesso) {
                // Adiciona widget ao array
                this.dashboardAtual.widgets.push(response.dados);

                // Adiciona ao grid
                await this.adicionarWidgetAoGrid(response.dados);

                // Fecha catálogo
                document.getElementById('catalogo-widgets').classList.add('hidden');

                API.showSuccess('Widget adicionado');
            }

        } catch (error) {
            console.error('Erro ao adicionar widget:', error);
            API.showError('Erro ao adicionar widget');
        }
    },

    /**
     * Cria novo dashboard
     */
    async criarDashboard(dados, templateCodigo = null) {
        try {
            let response;

            if (templateCodigo) {
                // Cria de template
                response = await API.post('/dashboard/from-template', {
                    nome: dados.nome,
                    template_codigo: templateCodigo
                });
            } else {
                // Cria em branco
                response = await API.post('/dashboard', dados);
            }

            if (response.sucesso) {
                // Atualiza lista
                await this.carregarDashboards();

                // Carrega novo dashboard
                await this.carregarDashboard(response.dados);

                API.showSuccess('Dashboard criado com sucesso');

                // Fecha modal
                document.getElementById('modal-novo-dashboard').classList.add('hidden');
            }

        } catch (error) {
            console.error('Erro ao criar dashboard:', error);
            API.showError('Erro ao criar dashboard');
        }
    },

    /**
     * Deleta dashboard
     */
    async deletarDashboard() {
        if (!this.dashboardAtual) return;

        if (!confirm(`Deseja excluir o dashboard "${this.dashboardAtual.nome}"?`)) return;

        try {
            const response = await API.delete(`/dashboard/${this.dashboardAtual.id}`);

            if (response.sucesso) {
                API.showSuccess('Dashboard excluído');

                // Recarrega dashboards e carrega o padrão
                await this.carregarDashboards();
                await this.carregarDashboardPadrao();
            }

        } catch (error) {
            console.error('Erro ao deletar dashboard:', error);
            API.showError('Erro ao deletar dashboard');
        }
    },

    /**
     * Define dashboard como padrão
     */
    async definirComoPadrao() {
        if (!this.dashboardAtual) return;

        try {
            const response = await API.post(`/dashboard/${this.dashboardAtual.id}/padrao`);

            if (response.sucesso) {
                API.showSuccess('Dashboard definido como padrão');
                await this.carregarDashboards();
            }

        } catch (error) {
            console.error('Erro ao definir padrão:', error);
            API.showError('Erro ao definir como padrão');
        }
    },

    /**
     * Duplica dashboard
     */
    async duplicarDashboard() {
        if (!this.dashboardAtual) return;

        const novoNome = prompt('Nome do novo dashboard:', this.dashboardAtual.nome + ' (cópia)');
        if (!novoNome) return;

        try {
            const response = await API.post(`/dashboard/${this.dashboardAtual.id}/duplicar`, {
                nome: novoNome
            });

            if (response.sucesso) {
                API.showSuccess('Dashboard duplicado');
                await this.carregarDashboards();
                await this.carregarDashboard(response.dados);
            }

        } catch (error) {
            console.error('Erro ao duplicar dashboard:', error);
            API.showError('Erro ao duplicar dashboard');
        }
    },

    /**
     * Configura event listeners
     */
    configurarEventos() {
        // Seletor de dashboard
        document.getElementById('select-dashboard').addEventListener('change', async (e) => {
            const dashboardId = e.target.value;
            const dashboard = this.dashboards.find(d => d.id == dashboardId);
            if (dashboard) {
                const response = await API.get(`/dashboard/${dashboardId}`);
                if (response.sucesso) {
                    await this.carregarDashboard(response.dados);
                }
            }
        });

        // Botão adicionar widget
        document.getElementById('btn-adicionar-widget').addEventListener('click', () => {
            document.getElementById('catalogo-widgets').classList.toggle('hidden');
            this.renderizarCatalogo('todas');
        });

        // Botão fechar catálogo
        document.getElementById('btn-fechar-catalogo').addEventListener('click', () => {
            document.getElementById('catalogo-widgets').classList.add('hidden');
        });

        // Filtros de categoria
        document.querySelectorAll('.filtro-categoria').forEach(btn => {
            btn.addEventListener('click', (e) => {
                document.querySelectorAll('.filtro-categoria').forEach(b => b.classList.remove('active'));
                e.currentTarget.classList.add('active');
                const categoria = e.currentTarget.getAttribute('data-categoria');
                this.renderizarCatalogo(categoria);
            });
        });

        // Busca de widgets
        document.getElementById('input-busca-widget').addEventListener('input', (e) => {
            const termo = e.target.value.toLowerCase();
            document.querySelectorAll('.widget-item').forEach(item => {
                const texto = item.textContent.toLowerCase();
                item.style.display = texto.includes(termo) ? 'block' : 'none';
            });
        });

        // Botão novo dashboard
        document.getElementById('btn-novo-dashboard').addEventListener('click', () => {
            document.getElementById('modal-novo-dashboard').classList.remove('hidden');
        });

        // Fechar modal
        document.querySelectorAll('.btn-close, [data-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.modal').classList.add('hidden');
            });
        });

        // Tabs
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const tab = e.currentTarget.getAttribute('data-tab');
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                e.currentTarget.classList.add('active');
                document.getElementById(`tab-${tab}`).classList.add('active');
            });
        });

        // Botão criar dashboard
        document.getElementById('btn-criar-dashboard').addEventListener('click', () => {
            const tabAtiva = document.querySelector('.tab-btn.active').getAttribute('data-tab');

            if (tabAtiva === 'em-branco') {
                const form = document.getElementById('form-dashboard-branco');
                const formData = new FormData(form);
                const dados = Object.fromEntries(formData);
                this.criarDashboard(dados);
            } else {
                const nome = document.getElementById('nome-dashboard-template').value;
                const templateSelecionado = document.querySelector('.template-card.selected');

                if (!nome) {
                    API.showWarning('Digite um nome para o dashboard');
                    return;
                }

                if (!templateSelecionado) {
                    API.showWarning('Selecione um template');
                    return;
                }

                const templateCodigo = templateSelecionado.getAttribute('data-template-codigo');
                this.criarDashboard({ nome }, templateCodigo);
            }
        });

        // Dropdown opções
        document.getElementById('btn-opcoes').addEventListener('click', () => {
            document.getElementById('dropdown-opcoes').classList.toggle('hidden');
        });

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.dropdown')) {
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.classList.add('hidden');
                });
            }
        });

        // Opções do dropdown
        document.getElementById('opt-definir-padrao').addEventListener('click', (e) => {
            e.preventDefault();
            this.definirComoPadrao();
        });

        document.getElementById('opt-duplicar').addEventListener('click', (e) => {
            e.preventDefault();
            this.duplicarDashboard();
        });

        document.getElementById('opt-deletar').addEventListener('click', (e) => {
            e.preventDefault();
            this.deletarDashboard();
        });

        // Atualizar todos
        document.getElementById('btn-atualizar-todos').addEventListener('click', () => {
            this.dashboardAtual.widgets.forEach(widget => {
                this.carregarDadosWidget(widget.id);
            });
            API.showSuccess('Todos os widgets atualizados');
        });
    }
};
