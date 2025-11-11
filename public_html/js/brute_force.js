// Configuração da API
const API_URL = '/api';
let currentUser = null;

// Estado da aplicação
const state = {
    tentativas: {
        page: 1,
        perPage: 20,
        filters: {}
    },
    bloqueios: {
        page: 1,
        perPage: 20,
        filters: {}
    }
};

// ==================== INICIALIZAÇÃO ====================

document.addEventListener('DOMContentLoaded', async () => {
    await verificarAutenticacao();
    await carregarEstatisticas();
});

// ==================== AUTENTICAÇÃO ====================

async function verificarAutenticacao() {
    try {
        const response = await fetch(`${API_URL}/auth/me`, {
            credentials: 'include'
        });

        if (!response.ok) {
            window.location.href = 'auth.html';
            return;
        }

        const data = await response.json();
        currentUser = data.dados;
        document.getElementById('userName').textContent = currentUser.nome;
    } catch (error) {
        console.error('Erro ao verificar autenticação:', error);
        window.location.href = 'auth.html';
    }
}

async function logout() {
    try {
        await fetch(`${API_URL}/auth/logout`, {
            method: 'POST',
            credentials: 'include'
        });
    } catch (error) {
        console.error('Erro ao fazer logout:', error);
    } finally {
        window.location.href = 'auth.html';
    }
}

// ==================== NAVEGAÇÃO ====================

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
            carregarEstatisticas();
            break;
        case 'tentativas':
            carregarTentativas();
            break;
        case 'bloqueios':
            carregarBloqueios();
            break;
    }
}

// ==================== ESTATÍSTICAS ====================

async function carregarEstatisticas() {
    try {
        const response = await fetch(`${API_URL}/login-attempts/estatisticas`, {
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error('Erro ao carregar estatísticas');
        }

        const data = await response.json();
        const stats = data.dados;

        // Atualiza cards de estatísticas
        document.getElementById('stat-tentativas').textContent = stats.tentativas_24h || 0;
        document.getElementById('stat-sucesso').textContent = stats.sucesso_24h || 0;
        document.getElementById('stat-falhas').textContent = stats.falhas_24h || 0;
        document.getElementById('stat-bloqueios').textContent = stats.bloqueios_ativos || 0;
        document.getElementById('stat-ips').textContent = stats.ips_bloqueados || 0;
        document.getElementById('stat-emails').textContent = stats.emails_bloqueados || 0;
        document.getElementById('stat-taxa').textContent = (stats.taxa_sucesso || 0) + '%';

        // Atualiza top IPs
        const topIpsList = document.getElementById('topIpsList');
        if (stats.top_ips && stats.top_ips.length > 0) {
            topIpsList.innerHTML = stats.top_ips.map(item => `
                <li>
                    <span>${item.ip_address}</span>
                    <span class="count">${item.total} tentativas</span>
                </li>
            `).join('');
        } else {
            topIpsList.innerHTML = '<li class="empty-state">Nenhum dado disponível</li>';
        }

        // Atualiza top emails
        const topEmailsList = document.getElementById('topEmailsList');
        if (stats.top_emails && stats.top_emails.length > 0) {
            topEmailsList.innerHTML = stats.top_emails.map(item => `
                <li>
                    <span>${item.email}</span>
                    <span class="count">${item.total} tentativas</span>
                </li>
            `).join('');
        } else {
            topEmailsList.innerHTML = '<li class="empty-state">Nenhum dado disponível</li>';
        }

    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
        mostrarAlerta('Erro ao carregar estatísticas', 'danger');
    }
}

// ==================== TENTATIVAS DE LOGIN ====================

async function carregarTentativas(page = 1) {
    try {
        state.tentativas.page = page;

        // Monta query string com filtros
        const params = new URLSearchParams({
            pagina: page,
            por_pagina: state.tentativas.perPage,
            ...state.tentativas.filters
        });

        const response = await fetch(`${API_URL}/login-attempts?${params}`, {
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error('Erro ao carregar tentativas');
        }

        const data = await response.json();
        renderizarTentativas(data.dados.itens || []);
        renderizarPaginacaoTentativas(data.dados.paginacao);

    } catch (error) {
        console.error('Erro ao carregar tentativas:', error);
        document.getElementById('tentativasTableBody').innerHTML = `
            <tr><td colspan="5" class="empty-state">Erro ao carregar tentativas</td></tr>
        `;
    }
}

function renderizarTentativas(tentativas) {
    const tbody = document.getElementById('tentativasTableBody');

    if (tentativas.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="empty-state">Nenhuma tentativa encontrada</td></tr>';
        return;
    }

    tbody.innerHTML = tentativas.map(t => {
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
}

function renderizarPaginacaoTentativas(paginacao) {
    if (!paginacao) return;

    const container = document.getElementById('tentativasPagination');
    const { pagina_atual, total_paginas } = paginacao;

    let html = `
        <button onclick="carregarTentativas(${pagina_atual - 1})" ${pagina_atual <= 1 ? 'disabled' : ''}>
            Anterior
        </button>
        <span>Página ${pagina_atual} de ${total_paginas}</span>
        <button onclick="carregarTentativas(${pagina_atual + 1})" ${pagina_atual >= total_paginas ? 'disabled' : ''}>
            Próxima
        </button>
    `;

    container.innerHTML = html;
}

function aplicarFiltrosTentativas() {
    state.tentativas.filters = {};

    const email = document.getElementById('filter-email').value;
    const ip = document.getElementById('filter-ip').value;
    const status = document.getElementById('filter-status').value;

    if (email) state.tentativas.filters.email = email;
    if (ip) state.tentativas.filters.ip_address = ip;
    if (status !== '') state.tentativas.filters.sucesso = status;

    carregarTentativas(1);
}

function limparFiltrosTentativas() {
    document.getElementById('filter-email').value = '';
    document.getElementById('filter-ip').value = '';
    document.getElementById('filter-status').value = '';
    state.tentativas.filters = {};
    carregarTentativas(1);
}

// ==================== BLOQUEIOS ====================

async function carregarBloqueios(page = 1) {
    try {
        state.bloqueios.page = page;

        const params = new URLSearchParams({
            pagina: page,
            por_pagina: state.bloqueios.perPage,
            ...state.bloqueios.filters
        });

        const response = await fetch(`${API_URL}/login-bloqueios?${params}`, {
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error('Erro ao carregar bloqueios');
        }

        const data = await response.json();
        renderizarBloqueios(data.dados.itens || []);
        renderizarPaginacaoBloqueios(data.dados.paginacao);

    } catch (error) {
        console.error('Erro ao carregar bloqueios:', error);
        document.getElementById('bloqueiosTableBody').innerHTML = `
            <tr><td colspan="8" class="empty-state">Erro ao carregar bloqueios</td></tr>
        `;
    }
}

function renderizarBloqueios(bloqueios) {
    const tbody = document.getElementById('bloqueiosTableBody');

    if (bloqueios.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Nenhum bloqueio ativo</td></tr>';
        return;
    }

    tbody.innerHTML = bloqueios.map(b => {
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
                    <button class="btn btn-success btn-sm" onclick="desbloquear(${b.id})">
                        Desbloquear
                    </button>
                </td>
            </tr>
        `;
    }).join('');
}

function renderizarPaginacaoBloqueios(paginacao) {
    if (!paginacao) return;

    const container = document.getElementById('bloqueiosPagination');
    const { pagina_atual, total_paginas } = paginacao;

    let html = `
        <button onclick="carregarBloqueios(${pagina_atual - 1})" ${pagina_atual <= 1 ? 'disabled' : ''}>
            Anterior
        </button>
        <span>Página ${pagina_atual} de ${total_paginas}</span>
        <button onclick="carregarBloqueios(${pagina_atual + 1})" ${pagina_atual >= total_paginas ? 'disabled' : ''}>
            Próxima
        </button>
    `;

    container.innerHTML = html;
}

function aplicarFiltrosBloqueios() {
    state.bloqueios.filters = {};

    const tipo = document.getElementById('filter-tipo-bloqueio').value;
    const email = document.getElementById('filter-bloqueio-email').value;
    const ip = document.getElementById('filter-bloqueio-ip').value;

    if (tipo) state.bloqueios.filters.tipo = tipo;
    if (email) state.bloqueios.filters.email = email;
    if (ip) state.bloqueios.filters.ip_address = ip;

    carregarBloqueios(1);
}

function limparFiltrosBloqueios() {
    document.getElementById('filter-tipo-bloqueio').value = '';
    document.getElementById('filter-bloqueio-email').value = '';
    document.getElementById('filter-bloqueio-ip').value = '';
    state.bloqueios.filters = {};
    carregarBloqueios(1);
}

async function desbloquear(id) {
    if (!confirm('Deseja realmente desbloquear este item?')) {
        return;
    }

    try {
        const response = await fetch(`${API_URL}/login-bloqueios/${id}`, {
            method: 'DELETE',
            credentials: 'include'
        });

        if (!response.ok) {
            throw new Error('Erro ao desbloquear');
        }

        mostrarAlerta('Bloqueio removido com sucesso!', 'success');
        await carregarBloqueios(state.bloqueios.page);
        await carregarEstatisticas();

    } catch (error) {
        console.error('Erro ao desbloquear:', error);
        mostrarAlerta('Erro ao remover bloqueio', 'danger');
    }
}

// ==================== MODAL NOVO BLOQUEIO ====================

function abrirModalNovoBloqueio() {
    document.getElementById('modalNovoBloqueio').classList.add('show');
    limparFormNovoBloqueio();
}

function fecharModalNovoBloqueio() {
    document.getElementById('modalNovoBloqueio').classList.remove('show');
    limparFormNovoBloqueio();
}

function limparFormNovoBloqueio() {
    document.getElementById('novo-tipo-bloqueio').value = '';
    document.getElementById('novo-email-bloqueio').value = '';
    document.getElementById('novo-ip-bloqueio').value = '';
    document.getElementById('novo-permanente').checked = false;
    document.getElementById('novo-motivo').value = '';
    ajustarCamposBloqueio();
}

function ajustarCamposBloqueio() {
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
}

async function criarBloqueio() {
    const tipo = document.getElementById('novo-tipo-bloqueio').value;
    const email = document.getElementById('novo-email-bloqueio').value;
    const ip = document.getElementById('novo-ip-bloqueio').value;
    const permanente = document.getElementById('novo-permanente').checked;
    const motivo = document.getElementById('novo-motivo').value;

    // Validações
    if (!tipo) {
        mostrarAlerta('Selecione o tipo de bloqueio', 'danger');
        return;
    }

    if ((tipo === 'email' || tipo === 'ambos') && !email) {
        mostrarAlerta('Email é obrigatório para este tipo de bloqueio', 'danger');
        return;
    }

    if ((tipo === 'ip' || tipo === 'ambos') && !ip) {
        mostrarAlerta('IP é obrigatório para este tipo de bloqueio', 'danger');
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
        const response = await fetch(`${API_URL}/login-bloqueios`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include',
            body: JSON.stringify(dados)
        });

        if (!response.ok) {
            const error = await response.json();
            throw new Error(error.mensagem || 'Erro ao criar bloqueio');
        }

        mostrarAlerta('Bloqueio criado com sucesso!', 'success');
        fecharModalNovoBloqueio();
        await carregarBloqueios(1);
        await carregarEstatisticas();

    } catch (error) {
        console.error('Erro ao criar bloqueio:', error);
        mostrarAlerta(error.message || 'Erro ao criar bloqueio', 'danger');
    }
}

// ==================== UTILITÁRIOS ====================

function mostrarAlerta(mensagem, tipo = 'info') {
    // Cria o elemento de alerta
    const alerta = document.createElement('div');
    alerta.className = `alert alert-${tipo}`;
    alerta.textContent = mensagem;

    // Adiciona no topo do container
    const container = document.querySelector('.container');
    container.insertBefore(alerta, container.firstChild);

    // Remove após 5 segundos
    setTimeout(() => {
        alerta.remove();
    }, 5000);
}

// Fecha modal ao clicar fora
document.addEventListener('click', (e) => {
    const modal = document.getElementById('modalNovoBloqueio');
    if (e.target === modal) {
        fecharModalNovoBloqueio();
    }
});
