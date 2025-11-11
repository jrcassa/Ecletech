/**
 * Auditoria - Sistema de logs e auditoria
 */

// Variáveis globais
let paginaAtual = 1;
let totalPaginas = 1;
let registros = [];

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    // Verifica autenticação
    if (!API.isAuthenticated()) {
        window.location.href = 'auth.html';
        return;
    }

    // Inicializa menu toggle
    initMenuToggle();

    // Carrega auditoria
    carregarAuditoria();

    // Fecha modal ao clicar fora
    document.getElementById('detalhesModal').addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModal();
        }
    });
});

// Menu toggle
function initMenuToggle() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const menuToggle = document.getElementById('menuToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');

    function toggleSidebar() {
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            sidebar.classList.toggle('open');
            sidebarOverlay.classList.toggle('show');
        } else {
            sidebar.classList.toggle('closed');
            mainContent.classList.toggle('expanded');
        }
    }

    menuToggle.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', toggleSidebar);

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar.classList.remove('open');
            sidebarOverlay.classList.remove('show');
        }
    });
}

// Carrega auditoria
async function carregarAuditoria(pagina = 1) {
    const loadingContainer = document.getElementById('loadingContainer');
    const errorContainer = document.getElementById('errorContainer');
    const tableContainer = document.getElementById('tableContainer');

    try {
        loadingContainer.style.display = 'block';
        errorContainer.style.display = 'none';
        tableContainer.style.display = 'none';

        // Constrói query string com filtros
        const params = new URLSearchParams({
            pagina: pagina,
            por_pagina: 20
        });

        const acao = document.getElementById('filtroAcao').value;
        const tabela = document.getElementById('filtroTabela').value;
        const usuarioId = document.getElementById('filtroUsuarioId').value;
        const dataInicio = document.getElementById('filtroDataInicio').value;
        const dataFim = document.getElementById('filtroDataFim').value;

        if (acao) params.append('acao', acao);
        if (tabela) params.append('tabela', tabela);
        if (usuarioId) params.append('usuario_id', usuarioId);
        if (dataInicio) params.append('data_inicio', dataInicio + ' 00:00:00');
        if (dataFim) params.append('data_fim', dataFim + ' 23:59:59');

        const response = await fetch(`${API.baseURL}/auditoria?${params.toString()}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.mensagem || 'Erro ao carregar auditoria');
        }

        // A resposta paginada usa 'itens' ao invés de 'dados'
        registros = data.dados?.itens || data.itens || [];
        paginaAtual = data.dados?.paginacao?.pagina_atual || data.paginacao?.pagina_atual || 1;
        totalPaginas = data.dados?.paginacao?.total_paginas || data.paginacao?.total_paginas || 1;

        renderizarTabela();
        atualizarPaginacao();

        loadingContainer.style.display = 'none';
        tableContainer.style.display = 'block';

    } catch (error) {
        console.error('Erro:', error);
        loadingContainer.style.display = 'none';
        errorContainer.style.display = 'block';
        document.getElementById('errorMessage').textContent = error.message;
    }
}

// Renderiza tabela
function renderizarTabela() {
    const tbody = document.getElementById('auditoriaTableBody');
    tbody.innerHTML = '';

    if (registros.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px;">Nenhum registro encontrado</td></tr>';
        return;
    }

    registros.forEach(registro => {
        const tr = document.createElement('tr');
        tr.onclick = () => mostrarDetalhes(registro);

        const dataCriacao = new Date(registro.criado_em);
        const dataFormatada = dataCriacao.toLocaleString('pt-BR');

        const acaoBadge = getAcaoBadge(registro.acao);
        const usuarioInfo = formatarUsuario(registro);

        tr.innerHTML = `
            <td>${registro.id}</td>
            <td>${dataFormatada}</td>
            <td>${usuarioInfo}</td>
            <td>${acaoBadge}</td>
            <td>${registro.tabela}</td>
            <td>${registro.registro_id || '-'}</td>
            <td>${registro.ip || '-'}</td>
        `;

        tbody.appendChild(tr);
    });
}

// Badge de ação
function getAcaoBadge(acao) {
    const badges = {
        'criar': '<span class="badge badge-criar">Criar</span>',
        'atualizar': '<span class="badge badge-atualizar">Atualizar</span>',
        'deletar': '<span class="badge badge-deletar">Deletar</span>',
        'restaurar': '<span class="badge badge-restaurar">Restaurar</span>'
    };
    return badges[acao] || `<span class="badge">${acao}</span>`;
}

// Formata informações do usuário
function formatarUsuario(registro) {
    if (!registro.usuario_id) {
        return 'Sistema';
    }

    if (registro.usuario_nome) {
        return `#${registro.usuario_id} ${registro.usuario_nome}`;
    }

    return `#${registro.usuario_id}`;
}

// Mostra detalhes
function mostrarDetalhes(registro) {
    const modal = document.getElementById('detalhesModal');
    const content = document.getElementById('detalhesContent');

    const dataCriacao = new Date(registro.criado_em);
    const dataFormatada = dataCriacao.toLocaleString('pt-BR');

    let html = `
        <div class="detail-section">
            <h4>Informações Gerais</h4>
            <div class="detail-grid">
                <div class="detail-item">
                    <label>ID</label>
                    <div class="value">${registro.id}</div>
                </div>
                <div class="detail-item">
                    <label>Data/Hora</label>
                    <div class="value">${dataFormatada}</div>
                </div>
                <div class="detail-item">
                    <label>Usuário</label>
                    <div class="value">${formatarUsuario(registro)}</div>
                </div>
                <div class="detail-item">
                    <label>Ação</label>
                    <div class="value">${getAcaoBadge(registro.acao)}</div>
                </div>
                <div class="detail-item">
                    <label>Tabela</label>
                    <div class="value">${registro.tabela}</div>
                </div>
                <div class="detail-item">
                    <label>Registro ID</label>
                    <div class="value">${registro.registro_id || '-'}</div>
                </div>
                <div class="detail-item">
                    <label>IP</label>
                    <div class="value">${registro.ip || '-'}</div>
                </div>
                <div class="detail-item">
                    <label>User Agent</label>
                    <div class="value" style="word-break: break-all;">${registro.user_agent || '-'}</div>
                </div>
            </div>
        </div>
    `;

    if (registro.dados_antigos) {
        html += `
            <div class="detail-section">
                <h4>Dados Antigos</h4>
                <div class="json-viewer">
                    <pre>${JSON.stringify(registro.dados_antigos, null, 2)}</pre>
                </div>
            </div>
        `;
    }

    if (registro.dados_novos) {
        html += `
            <div class="detail-section">
                <h4>Dados Novos</h4>
                <div class="json-viewer">
                    <pre>${JSON.stringify(registro.dados_novos, null, 2)}</pre>
                </div>
            </div>
        `;
    }

    content.innerHTML = html;
    modal.classList.add('show');
}

// Fecha modal
function fecharModal() {
    document.getElementById('detalhesModal').classList.remove('show');
}

// Atualiza paginação
function atualizarPaginacao() {
    document.getElementById('pageInfo').textContent = `Página ${paginaAtual} de ${totalPaginas}`;
    document.getElementById('btnPrevPage').disabled = paginaAtual === 1;
    document.getElementById('btnNextPage').disabled = paginaAtual === totalPaginas;
}

// Próxima página
function proximaPagina() {
    if (paginaAtual < totalPaginas) {
        carregarAuditoria(paginaAtual + 1);
    }
}

// Página anterior
function anteriorPagina() {
    if (paginaAtual > 1) {
        carregarAuditoria(paginaAtual - 1);
    }
}

// Aplica filtros
function aplicarFiltros() {
    carregarAuditoria(1);
}

// Limpa filtros
function limparFiltros() {
    document.getElementById('filtroAcao').value = '';
    document.getElementById('filtroTabela').value = '';
    document.getElementById('filtroUsuarioId').value = '';
    document.getElementById('filtroDataInicio').value = '';
    document.getElementById('filtroDataFim').value = '';
    carregarAuditoria(1);
}

// Carrega estatísticas
async function carregarEstatisticas() {
    const statsCard = document.getElementById('statsCard');
    const statsGrid = document.getElementById('statsGrid');

    try {
        const response = await fetch(`${API.baseURL}/auditoria/estatisticas`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'include'
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.mensagem || 'Erro ao carregar estatísticas');
        }

        const stats = data.dados || {};

        statsGrid.innerHTML = `
            <div class="stat-card">
                <h3>Total de Registros</h3>
                <div class="value">${stats.total || 0}</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <h3>Criações</h3>
                <div class="value">${stats.por_acao?.criar || 0}</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                <h3>Atualizações</h3>
                <div class="value">${stats.por_acao?.atualizar || 0}</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                <h3>Exclusões</h3>
                <div class="value">${stats.por_acao?.deletar || 0}</div>
            </div>
        `;

        statsCard.style.display = 'block';

    } catch (error) {
        console.error('Erro ao carregar estatísticas:', error);
        API.showError('Erro ao carregar estatísticas: ' + error.message);
    }
}
