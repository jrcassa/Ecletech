/**
 * ============================================================================
 * FROTA_ABASTECIMENTO_RELATORIOS.JS
 * ============================================================================
 * Gerenciamento de relatórios automáticos de abastecimento
 * ============================================================================
 */

// Estado da aplicação
const AppState = {
    configuracoes: [],
    historico: [],
    snapshots: [],
    paginaAtual: 1,
    itensPorPagina: 10,
    totalItens: 0,
    filtrosHistorico: {},
    filtrosSnapshots: {},
    configEdicao: null
};

// ============================================================================
// INICIALIZAÇÃO
// ============================================================================

document.addEventListener('DOMContentLoaded', async () => {
    // Verificar autenticação
    if (!API.isAuthenticated()) {
        window.location.href = './login.html';
        return;
    }

    // Inicializar tabs
    inicializarTabs();

    // Inicializar eventos do formulário
    inicializarFormularios();

    // Carregar dados iniciais
    await carregarConfiguracoes();

    // Listeners para mudança de tipo de relatório
    document.getElementById('tipo_relatorio').addEventListener('change', function() {
        alterarTipoRelatorio(this.value);
    });
});

// ============================================================================
// TABS
// ============================================================================

function inicializarTabs() {
    const tabButtons = document.querySelectorAll('.tab-button');

    tabButtons.forEach(button => {
        button.addEventListener('click', () => {
            const tabName = button.dataset.tab;

            // Atualizar botões
            tabButtons.forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');

            // Atualizar conteúdo
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });
            document.getElementById(`tab-${tabName}`).classList.add('active');

            // Carregar dados da tab
            if (tabName === 'historico' && AppState.historico.length === 0) {
                carregarHistorico();
            } else if (tabName === 'snapshots' && AppState.snapshots.length === 0) {
                carregarSnapshots();
            }
        });
    });
}

// ============================================================================
// FORMULÁRIOS
// ============================================================================

function inicializarFormularios() {
    // Form: Configuração
    document.getElementById('form-configuracao').addEventListener('submit', async (e) => {
        e.preventDefault();
        await salvarConfiguracao();
    });

    // Form: Geração Manual
    document.getElementById('form-geracao-manual').addEventListener('submit', async (e) => {
        e.preventDefault();
        await gerarEEnviar();
    });
}

function alterarTipoRelatorio(tipo) {
    const groupSemanal = document.getElementById('group-dia-semanal');
    const groupMensal = document.getElementById('group-dia-mensal');
    const inputSemanal = document.getElementById('dia_envio_semanal');
    const inputMensal = document.getElementById('dia_envio_mensal');

    if (tipo === 'semanal') {
        groupSemanal.style.display = 'block';
        groupMensal.style.display = 'none';
        inputSemanal.required = true;
        inputMensal.required = false;
    } else if (tipo === 'mensal') {
        groupSemanal.style.display = 'none';
        groupMensal.style.display = 'block';
        inputSemanal.required = false;
        inputMensal.required = true;
    } else {
        groupSemanal.style.display = 'none';
        groupMensal.style.display = 'none';
        inputSemanal.required = false;
        inputMensal.required = false;
    }
}

// ============================================================================
// CONFIGURAÇÕES
// ============================================================================

async function carregarConfiguracoes() {
    const loading = document.getElementById('loading-configuracoes');
    const error = document.getElementById('error-configuracoes');
    const lista = document.getElementById('lista-configuracoes');

    loading.style.display = 'block';
    error.style.display = 'none';
    lista.style.display = 'none';

    try {
        const response = await API.get('/frota-abastecimento-relatorios/minhas-configuracoes');

        if (response.sucesso) {
            AppState.configuracoes = response.dados || [];
            renderizarConfiguracoes();
        } else {
            throw new Error(response.mensagem || 'Erro ao carregar configurações');
        }
    } catch (err) {
        console.error('Erro:', err);
        error.textContent = err.message;
        error.style.display = 'block';
    } finally {
        loading.style.display = 'none';
    }
}

function renderizarConfiguracoes() {
    const lista = document.getElementById('lista-configuracoes');

    if (AppState.configuracoes.length === 0) {
        lista.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Nenhuma configuração encontrada</p>
                <p>Crie sua primeira configuração acima!</p>
            </div>
        `;
    } else {
        lista.innerHTML = AppState.configuracoes.map(config => `
            <div class="config-item">
                <div class="config-item-header">
                    <div class="config-item-title">
                        <i class="fas fa-${config.tipo_relatorio === 'semanal' ? 'calendar-week' : 'calendar-alt'}"></i>
                        Relatório ${config.tipo_relatorio === 'semanal' ? 'Semanal' : 'Mensal'}
                        ${config.ativo ? '<span class="badge badge-success">Ativo</span>' : '<span class="badge badge-danger">Inativo</span>'}
                    </div>
                    <div class="config-item-actions">
                        <button class="btn btn-sm btn-secondary" onclick="editarConfiguracao(${config.id})">
                            <i class="fas fa-edit"></i> Editar
                        </button>
                        <button class="btn btn-sm ${config.ativo ? 'btn-danger' : 'btn-success'}"
                                onclick="toggleConfiguracao(${config.id}, ${!config.ativo})">
                            <i class="fas fa-${config.ativo ? 'pause' : 'play'}"></i>
                            ${config.ativo ? 'Desativar' : 'Ativar'}
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="deletarConfiguracao(${config.id})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="config-item-details">
                    <div class="config-detail">
                        <strong>Dia de Envio</strong>
                        ${config.tipo_relatorio === 'semanal'
                            ? formatarDiaSemana(config.dia_envio_semanal)
                            : `Dia ${config.dia_envio_mensal}`}
                    </div>
                    <div class="config-detail">
                        <strong>Hora</strong>
                        ${config.hora_envio || '08:00'}
                    </div>
                    <div class="config-detail">
                        <strong>Formato</strong>
                        ${formatarFormato(config.formato_relatorio)}
                    </div>
                    <div class="config-detail">
                        <strong>Criado em</strong>
                        ${formatarDataHora(config.criado_em)}
                    </div>
                </div>
            </div>
        `).join('');
    }

    lista.style.display = 'block';
}

async function salvarConfiguracao() {
    const id = document.getElementById('config-id').value;
    const tipo = document.getElementById('tipo_relatorio').value;
    const diaSemanal = document.getElementById('dia_envio_semanal').value;
    const diaMensal = document.getElementById('dia_envio_mensal').value;
    const hora = document.getElementById('hora_envio').value;
    const formato = document.getElementById('formato_relatorio').value;
    const ativo = document.getElementById('ativo').checked;

    const dados = {
        tipo_relatorio: tipo,
        hora_envio: hora,
        formato_relatorio: formato,
        ativo: ativo ? 1 : 0
    };

    if (tipo === 'semanal') {
        dados.dia_envio_semanal = diaSemanal;
    } else if (tipo === 'mensal') {
        dados.dia_envio_mensal = parseInt(diaMensal);
    }

    if (id) {
        dados.id = parseInt(id);
    }

    try {
        const response = await API.post('/frota-abastecimento-relatorios/configurar', dados);

        if (response.sucesso) {
            showNotification('Configuração salva com sucesso!', 'success');
            limparFormulario();
            await carregarConfiguracoes();
        } else {
            throw new Error(response.mensagem || 'Erro ao salvar configuração');
        }
    } catch (err) {
        console.error('Erro:', err);
        showNotification(err.message, 'error');
    }
}

function editarConfiguracao(id) {
    const config = AppState.configuracoes.find(c => c.id === id);
    if (!config) return;

    AppState.configEdicao = config;

    document.getElementById('config-id').value = config.id;
    document.getElementById('tipo_relatorio').value = config.tipo_relatorio;
    document.getElementById('hora_envio').value = config.hora_envio || '08:00';
    document.getElementById('formato_relatorio').value = config.formato_relatorio || 'detalhado';
    document.getElementById('ativo').checked = config.ativo;

    alterarTipoRelatorio(config.tipo_relatorio);

    if (config.tipo_relatorio === 'semanal') {
        document.getElementById('dia_envio_semanal').value = config.dia_envio_semanal;
    } else if (config.tipo_relatorio === 'mensal') {
        document.getElementById('dia_envio_mensal').value = config.dia_envio_mensal;
    }

    document.getElementById('form-titulo').textContent = 'Editar Configuração';
    document.getElementById('btn-cancelar').style.display = 'inline-block';

    // Scroll para o formulário
    document.getElementById('form-configuracao').scrollIntoView({ behavior: 'smooth' });
}

async function toggleConfiguracao(id, ativar) {
    try {
        const endpoint = ativar
            ? `/frota-abastecimento-relatorios/configuracao/${id}/ativar`
            : `/frota-abastecimento-relatorios/configuracao/${id}/desativar`;

        const response = await API.patch(endpoint);

        if (response.sucesso) {
            showNotification(`Configuração ${ativar ? 'ativada' : 'desativada'} com sucesso!`, 'success');
            await carregarConfiguracoes();
        } else {
            throw new Error(response.mensagem);
        }
    } catch (err) {
        console.error('Erro:', err);
        showNotification(err.message, 'error');
    }
}

async function deletarConfiguracao(id) {
    if (!confirm('Tem certeza que deseja excluir esta configuração?')) {
        return;
    }

    try {
        const response = await API.delete(`/frota-abastecimento-relatorios/configuracao/${id}`);

        if (response.sucesso) {
            showNotification('Configuração excluída com sucesso!', 'success');
            await carregarConfiguracoes();
        } else {
            throw new Error(response.mensagem);
        }
    } catch (err) {
        console.error('Erro:', err);
        showNotification(err.message, 'error');
    }
}

function limparFormulario() {
    document.getElementById('form-configuracao').reset();
    document.getElementById('config-id').value = '';
    document.getElementById('form-titulo').textContent = 'Nova Configuração';
    document.getElementById('btn-cancelar').style.display = 'none';
    document.getElementById('hora_envio').value = '08:00';
    document.getElementById('formato_relatorio').value = 'detalhado';
    document.getElementById('ativo').checked = true;
    alterarTipoRelatorio('');
    AppState.configEdicao = null;
}

// ============================================================================
// HISTÓRICO
// ============================================================================

async function carregarHistorico(pagina = 1) {
    const loading = document.getElementById('loading-historico');
    const error = document.getElementById('error-historico');
    const table = document.getElementById('table-historico');

    loading.style.display = 'block';
    error.style.display = 'none';
    table.style.display = 'none';

    try {
        const params = new URLSearchParams({
            pagina: pagina,
            por_pagina: AppState.itensPorPagina,
            ...AppState.filtrosHistorico
        });

        const response = await API.get(`/frota-abastecimento-relatorios/historico?${params}`);

        if (response.sucesso) {
            AppState.historico = response.dados || [];
            AppState.paginaAtual = response.paginacao?.pagina_atual || 1;
            AppState.totalItens = response.paginacao?.total || 0;
            renderizarHistorico();
        } else {
            throw new Error(response.mensagem || 'Erro ao carregar histórico');
        }
    } catch (err) {
        console.error('Erro:', err);
        error.textContent = err.message;
        error.style.display = 'block';
    } finally {
        loading.style.display = 'none';
    }
}

function renderizarHistorico() {
    const tbody = document.getElementById('historico-tbody');
    const table = document.getElementById('table-historico');

    if (AppState.historico.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align: center; padding: 40px;">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.3; display: block; margin-bottom: 10px;"></i>
                    Nenhum envio encontrado
                </td>
            </tr>
        `;
    } else {
        tbody.innerHTML = AppState.historico.map(log => `
            <tr>
                <td>${log.id}</td>
                <td><span class="badge badge-info">${log.tipo_relatorio}</span></td>
                <td>
                    ${formatarData(log.periodo_inicio)} a ${formatarData(log.periodo_fim)}
                </td>
                <td>${log.destinatario_nome || 'N/A'}</td>
                <td>${formatarFormato(log.formato)}</td>
                <td>${renderizarStatusBadge(log.status_envio)}</td>
                <td>${formatarDataHora(log.criado_em)}</td>
                <td>
                    <button class="btn btn-sm btn-secondary" onclick="verDetalhesLog(${log.id})">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
        `).join('');
    }

    renderizarPaginacao();
    table.style.display = 'block';
}

function renderizarPaginacao() {
    const container = document.getElementById('pagination-historico');
    const totalPaginas = Math.ceil(AppState.totalItens / AppState.itensPorPagina);

    if (totalPaginas <= 1) {
        container.innerHTML = '';
        return;
    }

    container.innerHTML = `
        <button ${AppState.paginaAtual === 1 ? 'disabled' : ''} onclick="carregarHistorico(${AppState.paginaAtual - 1})">
            <i class="fas fa-chevron-left"></i> Anterior
        </button>
        <span>Página ${AppState.paginaAtual} de ${totalPaginas}</span>
        <button ${AppState.paginaAtual === totalPaginas ? 'disabled' : ''} onclick="carregarHistorico(${AppState.paginaAtual + 1})">
            Próxima <i class="fas fa-chevron-right"></i>
        </button>
    `;
}

function aplicarFiltrosHistorico() {
    AppState.filtrosHistorico = {
        tipo_relatorio: document.getElementById('filtro-tipo').value,
        status: document.getElementById('filtro-status').value,
        data_inicio: document.getElementById('filtro-data-inicio').value,
        data_fim: document.getElementById('filtro-data-fim').value
    };

    // Remover filtros vazios
    Object.keys(AppState.filtrosHistorico).forEach(key => {
        if (!AppState.filtrosHistorico[key]) {
            delete AppState.filtrosHistorico[key];
        }
    });

    carregarHistorico(1);
}

function limparFiltrosHistorico() {
    document.getElementById('filtro-tipo').value = '';
    document.getElementById('filtro-status').value = '';
    document.getElementById('filtro-data-inicio').value = '';
    document.getElementById('filtro-data-fim').value = '';
    AppState.filtrosHistorico = {};
    carregarHistorico(1);
}

async function verDetalhesLog(id) {
    const modal = document.getElementById('modal-detalhes');
    const conteudo = document.getElementById('detalhes-conteudo');

    modal.classList.add('active');
    conteudo.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Carregando...</p></div>';

    try {
        const response = await API.get(`/frota-abastecimento-relatorios/log/${id}`);

        if (response.sucesso) {
            const log = response.dados;
            conteudo.innerHTML = `
                <div class="detail-section">
                    <h4>Informações Gerais</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">ID</div>
                            <div class="detail-value">${log.id}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tipo</div>
                            <div class="detail-value">${log.tipo_relatorio}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Status</div>
                            <div class="detail-value">${renderizarStatusBadge(log.status_envio)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Formato</div>
                            <div class="detail-value">${formatarFormato(log.formato)}</div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>Período</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Data Início</div>
                            <div class="detail-value">${formatarData(log.periodo_inicio)}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Data Fim</div>
                            <div class="detail-value">${formatarData(log.periodo_fim)}</div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>Destinatário</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Nome</div>
                            <div class="detail-value">${log.destinatario_nome || 'N/A'}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Telefone</div>
                            <div class="detail-value">${log.telefone || 'N/A'}</div>
                        </div>
                    </div>
                </div>

                <div class="detail-section">
                    <h4>Estatísticas</h4>
                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-label">Tentativas</div>
                            <div class="detail-value">${log.tentativas || 0}</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tamanho Mensagem</div>
                            <div class="detail-value">${log.tamanho_mensagem || 0} caracteres</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Tempo Processamento</div>
                            <div class="detail-value">${log.tempo_processamento || 0}s</div>
                        </div>
                        <div class="detail-item">
                            <div class="detail-label">Data Envio</div>
                            <div class="detail-value">${formatarDataHora(log.criado_em)}</div>
                        </div>
                    </div>
                </div>

                ${log.erro_mensagem ? `
                    <div class="detail-section">
                        <h4>Erro</h4>
                        <div class="error-message">${log.erro_mensagem}</div>
                    </div>
                ` : ''}

                ${log.mensagem ? `
                    <div class="detail-section">
                        <h4>Mensagem Enviada</h4>
                        <div class="message-box">${log.mensagem}</div>
                    </div>
                ` : ''}
            `;
        } else {
            throw new Error(response.mensagem);
        }
    } catch (err) {
        console.error('Erro:', err);
        conteudo.innerHTML = `<div class="error-message">${err.message}</div>`;
    }
}

function fecharModalDetalhes() {
    document.getElementById('modal-detalhes').classList.remove('active');
}

// ============================================================================
// SNAPSHOTS
// ============================================================================

async function carregarSnapshots() {
    const loading = document.getElementById('loading-snapshots');
    const error = document.getElementById('error-snapshots');
    const lista = document.getElementById('lista-snapshots');

    loading.style.display = 'block';
    error.style.display = 'none';
    lista.style.display = 'none';

    try {
        const params = new URLSearchParams(AppState.filtrosSnapshots);
        const response = await API.get(`/frota-abastecimento-relatorios/snapshots?${params}`);

        if (response.sucesso) {
            AppState.snapshots = response.dados || [];
            renderizarSnapshots();
        } else {
            throw new Error(response.mensagem || 'Erro ao carregar snapshots');
        }
    } catch (err) {
        console.error('Erro:', err);
        error.textContent = err.message;
        error.style.display = 'block';
    } finally {
        loading.style.display = 'none';
    }
}

function renderizarSnapshots() {
    const lista = document.getElementById('lista-snapshots');

    if (AppState.snapshots.length === 0) {
        lista.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-camera"></i>
                <p>Nenhum snapshot encontrado</p>
            </div>
        `;
    } else {
        lista.innerHTML = AppState.snapshots.map(snap => `
            <div class="snapshot-item">
                <div class="snapshot-header">
                    <div>
                        <div class="snapshot-title">
                            <i class="fas fa-${snap.tipo_periodo === 'semanal' ? 'calendar-week' : 'calendar-alt'}"></i>
                            ${snap.tipo_periodo === 'semanal' ? 'Semanal' : 'Mensal'} -
                            ${snap.tipo_periodo === 'semanal' ? `Semana ${snap.semana}` : formatarMes(snap.mes)} ${snap.ano}
                        </div>
                        <div class="snapshot-period">${formatarData(snap.periodo_inicio)} a ${formatarData(snap.periodo_fim)}</div>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-secondary" onclick="verDetalhesSnapshot(${snap.id})">
                            <i class="fas fa-eye"></i> Ver Detalhes
                        </button>
                    </div>
                </div>
                <div class="snapshot-stats">
                    <div class="stat-box">
                        <div class="stat-label">Abastecimentos</div>
                        <div class="stat-value">${snap.total_abastecimentos || 0}</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Litros</div>
                        <div class="stat-value">${formatarNumero(snap.total_litros)} L</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Total Valor</div>
                        <div class="stat-value">R$ ${formatarNumero(snap.total_valor)}</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Consumo Médio</div>
                        <div class="stat-value">${formatarNumero(snap.consumo_medio_geral)} km/L</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Custo/km</div>
                        <div class="stat-value">R$ ${formatarNumero(snap.custo_medio_por_km)}</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-label">Alertas</div>
                        <div class="stat-value ${snap.total_alertas > 0 ? 'text-danger' : ''}">${snap.total_alertas || 0}</div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    lista.style.display = 'block';
}

function aplicarFiltrosSnapshots() {
    AppState.filtrosSnapshots = {
        tipo_periodo: document.getElementById('filtro-snapshot-tipo').value,
        ano: document.getElementById('filtro-snapshot-ano').value,
        mes: document.getElementById('filtro-snapshot-mes').value
    };

    // Remover filtros vazios
    Object.keys(AppState.filtrosSnapshots).forEach(key => {
        if (!AppState.filtrosSnapshots[key]) {
            delete AppState.filtrosSnapshots[key];
        }
    });

    carregarSnapshots();
}

function limparFiltrosSnapshots() {
    document.getElementById('filtro-snapshot-tipo').value = '';
    document.getElementById('filtro-snapshot-ano').value = '';
    document.getElementById('filtro-snapshot-mes').value = '';
    AppState.filtrosSnapshots = {};
    carregarSnapshots();
}

async function verDetalhesSnapshot(id) {
    const modal = document.getElementById('modal-snapshot');
    const conteudo = document.getElementById('snapshot-conteudo');

    modal.classList.add('active');
    conteudo.innerHTML = '<div class="loading"><div class="loading-spinner"></div><p>Carregando...</p></div>';

    try {
        const response = await API.get(`/frota-abastecimento-relatorios/snapshot/${id}`);

        if (response.sucesso) {
            const snap = response.dados;
            const dados = snap.dados_detalhados ? JSON.parse(snap.dados_detalhados) : {};

            let html = `
                <div class="detail-section">
                    <h4>Resumo Geral</h4>
                    <div class="snapshot-stats">
                        <div class="stat-box">
                            <div class="stat-label">Total de Abastecimentos</div>
                            <div class="stat-value">${snap.total_abastecimentos || 0}</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Total de Litros</div>
                            <div class="stat-value">${formatarNumero(snap.total_litros)} L</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Valor Total</div>
                            <div class="stat-value">R$ ${formatarNumero(snap.total_valor)}</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">KM Percorridos</div>
                            <div class="stat-value">${formatarNumero(snap.total_km_percorrido)} km</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Consumo Médio</div>
                            <div class="stat-value">${formatarNumero(snap.consumo_medio_geral)} km/L</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Custo por KM</div>
                            <div class="stat-value">R$ ${formatarNumero(snap.custo_medio_por_km)}</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Custo por Litro</div>
                            <div class="stat-value">R$ ${formatarNumero(snap.custo_medio_por_litro)}</div>
                        </div>
                        <div class="stat-box">
                            <div class="stat-label">Total de Alertas</div>
                            <div class="stat-value ${snap.total_alertas > 0 ? 'text-danger' : ''}">${snap.total_alertas || 0}</div>
                        </div>
                    </div>
                </div>
            `;

            // Rankings
            if (dados.ranking_consumo) {
                html += `
                    <div class="detail-section">
                        <h4>🏆 Ranking de Consumo</h4>
                        ${renderizarRanking(dados.ranking_consumo)}
                    </div>
                `;
            }

            conteudo.innerHTML = html;
        } else {
            throw new Error(response.mensagem);
        }
    } catch (err) {
        console.error('Erro:', err);
        conteudo.innerHTML = `<div class="error-message">${err.message}</div>`;
    }
}

function renderizarRanking(ranking) {
    let html = '';

    if (ranking.melhores && ranking.melhores.length > 0) {
        html += '<h5>✅ Melhores</h5><ul>';
        ranking.melhores.forEach((item, index) => {
            html += `<li>${index + 1}. ${item.placa || item.nome} - ${formatarNumero(item.consumo_medio)} km/L</li>`;
        });
        html += '</ul>';
    }

    if (ranking.piores && ranking.piores.length > 0) {
        html += '<h5>⚠️ Piores</h5><ul>';
        ranking.piores.forEach((item, index) => {
            html += `<li>${index + 1}. ${item.placa || item.nome} - ${formatarNumero(item.consumo_medio)} km/L</li>`;
        });
        html += '</ul>';
    }

    return html || '<p>Sem dados de ranking</p>';
}

function fecharModalSnapshot() {
    document.getElementById('modal-snapshot').classList.remove('active');
}

// ============================================================================
// GERAÇÃO MANUAL
// ============================================================================

function abrirModalGeracao() {
    document.getElementById('modal-geracao').classList.add('active');
    document.getElementById('resultado-geracao').style.display = 'none';
}

function fecharModalGeracao() {
    document.getElementById('modal-geracao').classList.remove('active');
    document.getElementById('form-geracao-manual').reset();
    document.getElementById('resultado-geracao').style.display = 'none';
}

async function gerarSemEnviar() {
    const tipo = document.getElementById('geracao-tipo').value;
    const dataInicio = document.getElementById('geracao-data-inicio').value;
    const dataFim = document.getElementById('geracao-data-fim').value;
    const formato = document.getElementById('geracao-formato').value;

    if (!tipo || !dataInicio || !dataFim) {
        showNotification('Preencha todos os campos obrigatórios', 'error');
        return;
    }

    try {
        const response = await API.post('/frota-abastecimento-relatorios/gerar-manual', {
            tipo_relatorio: tipo,
            periodo_inicio: dataInicio,
            periodo_fim: dataFim,
            formato: formato
        });

        if (response.sucesso) {
            const resultado = document.getElementById('resultado-geracao');
            resultado.innerHTML = `
                <div class="success-message">
                    <h4>✅ Relatório Gerado com Sucesso!</h4>
                    <div class="message-box">${response.dados.mensagem}</div>
                </div>
            `;
            resultado.style.display = 'block';
        } else {
            throw new Error(response.mensagem);
        }
    } catch (err) {
        console.error('Erro:', err);
        showNotification(err.message, 'error');
    }
}

async function gerarEEnviar() {
    const tipo = document.getElementById('geracao-tipo').value;
    const dataInicio = document.getElementById('geracao-data-inicio').value;
    const dataFim = document.getElementById('geracao-data-fim').value;
    const formato = document.getElementById('geracao-formato').value;

    if (!tipo || !dataInicio || !dataFim) {
        showNotification('Preencha todos os campos obrigatórios', 'error');
        return;
    }

    try {
        const response = await API.post('/frota-abastecimento-relatorios/enviar-manual', {
            tipo_relatorio: tipo,
            periodo_inicio: dataInicio,
            periodo_fim: dataFim,
            formato: formato
        });

        if (response.sucesso) {
            showNotification('Relatório gerado e enviado com sucesso!', 'success');
            fecharModalGeracao();

            // Atualizar histórico
            if (document.getElementById('tab-historico').classList.contains('active')) {
                await carregarHistorico();
            }
        } else {
            throw new Error(response.mensagem);
        }
    } catch (err) {
        console.error('Erro:', err);
        showNotification(err.message, 'error');
    }
}

// ============================================================================
// UTILITÁRIOS
// ============================================================================

function formatarData(data) {
    if (!data) return 'N/A';
    const d = new Date(data + 'T00:00:00');
    return d.toLocaleDateString('pt-BR');
}

function formatarDataHora(data) {
    if (!data) return 'N/A';
    const d = new Date(data);
    return d.toLocaleString('pt-BR');
}

function formatarNumero(num) {
    if (num === null || num === undefined) return '0,00';
    return parseFloat(num).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function formatarDiaSemana(dia) {
    const dias = {
        'segunda': 'Segunda-feira',
        'terca': 'Terça-feira',
        'quarta': 'Quarta-feira',
        'quinta': 'Quinta-feira',
        'sexta': 'Sexta-feira',
        'sabado': 'Sábado',
        'domingo': 'Domingo'
    };
    return dias[dia] || dia;
}

function formatarFormato(formato) {
    const formatos = {
        'resumido': 'Resumido',
        'detalhado': 'Detalhado',
        'completo': 'Completo'
    };
    return formatos[formato] || formato;
}

function formatarMes(mes) {
    const meses = ['', 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                   'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    return meses[parseInt(mes)] || mes;
}

function renderizarStatusBadge(status) {
    const badges = {
        'enviado': '<span class="badge badge-success">Enviado</span>',
        'erro': '<span class="badge badge-danger">Erro</span>',
        'pendente': '<span class="badge badge-warning">Pendente</span>',
        'cancelado': '<span class="badge badge-info">Cancelado</span>'
    };
    return badges[status] || `<span class="badge">${status}</span>`;
}

function showNotification(message, type = 'info') {
    // Implementação simples de notificação
    // Você pode substituir por uma biblioteca de toast/notification
    alert(message);
}

// Fechar modais ao clicar fora
window.addEventListener('click', (e) => {
    const modais = document.querySelectorAll('.modal');
    modais.forEach(modal => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
