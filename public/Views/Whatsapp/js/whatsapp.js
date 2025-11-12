// ============================================
// WHATSAPP.JS - Sistema de Gerenciamento WhatsApp
// Versão: 2.0.0 - API RESTful
// ============================================

// Configuração da API
const API_BASE = '/api/whatsapp';

// Variáveis Globais
let PODE_ALTERAR = false;
let PODE_DELETAR = false;
let intervalStatusCheck = null;

// ============================================
// INICIALIZAÇÃO
// ============================================

$(document).ready(function() {
    console.log('WhatsApp Manager iniciado - v2.0');

    // Navegação entre tabs
    inicializarNavegacao();

    // Eventos de formulários
    inicializarEventos();

    // Carrega status inicial
    verificarStatusInstancia();

    // Carrega dashboard
    carregarDashboard();
});

// ============================================
// NAVEGAÇÃO
// ============================================

function inicializarNavegacao() {
    $('.list-group-item').click(function(e) {
        e.preventDefault();

        $('.list-group-item').removeClass('active');
        $(this).addClass('active');

        const tab = $(this).data('tab');
        $('.tab-pane-custom').hide();
        $(`#tab-${tab}`).show();

        // Carrega dados da tab
        switch(tab) {
            case 'conexao':
                verificarStatusInstancia();
                break;
            case 'fila':
                carregarFila();
                break;
            case 'historico':
                carregarHistorico();
                break;
            case 'configuracoes':
                carregarConfiguracoes();
                break;
        }
    });
}

// ============================================
// EVENTOS
// ============================================

function inicializarEventos() {
    // Botão desconectar
    $('#btn-desconectar').click(function() {
        desconectarWhatsApp();
    });

    // Formulário de envio de teste
    $('#form-enviar-teste').submit(function(e) {
        e.preventDefault();
        enviarMensagemTeste();
    });

    // Botão processar fila
    $('#btn-processar-fila').click(function() {
        processarFila();
    });

    // Filtro de fila
    $('#filtro-status-fila').change(function() {
        carregarFila();
    });

    // Filtro de histórico
    $('#btn-filtrar-historico').click(function() {
        carregarHistorico();
    });
}

// ============================================
// CONEXÃO
// ============================================

function verificarStatusInstancia() {
    $.ajax({
        url: `${API_BASE}/conexao/status`,
        method: 'GET',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.sucesso) {
            atualizarStatusConexao(response.dados);
        } else {
            mostrarErro(response.mensagem);
        }
    })
    .fail(function(xhr) {
        mostrarErro('Erro ao verificar status da conexão');
    });
}

function atualizarStatusConexao(dados) {
    const statusHtml = dados.conectado ?
        '<span class="status-badge status-conectado"><i class="fas fa-check-circle"></i> Conectado</span>' :
        '<span class="status-badge status-desconectado"><i class="fas fa-times-circle"></i> Desconectado</span>';

    $('#status-conexao').html(statusHtml);

    if (dados.conectado) {
        $('#info-telefone').text(dados.telefone || 'N/A');
        $('#info-nome').text(dados.nome || 'N/A');
        $('#container-qrcode').hide();
        $('#container-info-conectado').show();
        $('#btn-desconectar').show();

        // Para auto-refresh de QR code
        if (intervalStatusCheck) {
            clearInterval(intervalStatusCheck);
            intervalStatusCheck = null;
        }
    } else if (dados.status === 'qrcode' && dados.qr_code) {
        $('#container-qrcode').show();
        $('#container-info-conectado').hide();
        $('#btn-desconectar').hide();
        $('#qrcode-image').attr('src', dados.qr_code);

        // Auto-refresh QR code a cada 5 segundos
        if (!intervalStatusCheck) {
            intervalStatusCheck = setInterval(verificarStatusInstancia, 5000);
        }
    } else {
        $('#container-qrcode').hide();
        $('#container-info-conectado').hide();
        $('#btn-desconectar').hide();

        if (intervalStatusCheck) {
            clearInterval(intervalStatusCheck);
            intervalStatusCheck = null;
        }
    }
}

function desconectarWhatsApp() {
    Swal.fire({
        title: 'Confirmar Desconexão',
        text: 'Deseja realmente desconectar a instância do WhatsApp?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, desconectar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `${API_BASE}/conexao/desconectar`,
                method: 'POST',
                dataType: 'json'
            })
            .done(function(response) {
                if (response.sucesso) {
                    mostrarSucesso('Instância desconectada com sucesso');
                    verificarStatusInstancia();
                } else {
                    mostrarErro(response.mensagem);
                }
            })
            .fail(function() {
                mostrarErro('Erro ao desconectar instância');
            });
        }
    });
}

// ============================================
// ENVIO DE MENSAGENS
// ============================================

function enviarMensagemTeste() {
    const destinatario = $('#destinatario').val();
    const tipoMensagem = $('#tipo-mensagem').val();
    const mensagem = $('#mensagem-teste').val();

    if (!destinatario) {
        mostrarErro('Informe o destinatário');
        return;
    }

    if (tipoMensagem === 'text' && !mensagem) {
        mostrarErro('Informe a mensagem');
        return;
    }

    const dados = {
        destinatario: destinatario,
        tipo: tipoMensagem,
        mensagem: mensagem,
        prioridade: 5
    };

    $.ajax({
        url: `${API_BASE}/enviar`,
        method: 'POST',
        data: JSON.stringify(dados),
        contentType: 'application/json',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.sucesso) {
            mostrarSucesso('Mensagem adicionada à fila com sucesso');
            $('#form-enviar-teste')[0].reset();
        } else {
            mostrarErro(response.mensagem);
        }
    })
    .fail(function() {
        mostrarErro('Erro ao enviar mensagem');
    });
}

// ============================================
// FILA
// ============================================

function carregarFila() {
    const status = $('#filtro-status-fila').val();
    const url = status ? `${API_BASE}/fila?status=${status}` : `${API_BASE}/fila`;

    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.sucesso) {
            renderizarFila(response.dados);
        } else {
            mostrarErro(response.mensagem);
        }
    })
    .fail(function() {
        mostrarErro('Erro ao carregar fila');
    });
}

function renderizarFila(mensagens) {
    if (!mensagens || mensagens.length === 0) {
        $('#tabela-fila tbody').html('<tr><td colspan="6" class="text-center">Nenhuma mensagem na fila</td></tr>');
        return;
    }

    let html = '';
    mensagens.forEach(msg => {
        const statusBadge = obterBadgeStatus(msg.status_code);
        html += `
            <tr>
                <td>${msg.id}</td>
                <td>${msg.destinatario_nome || msg.destinatario}</td>
                <td>${msg.tipo_mensagem}</td>
                <td>${statusBadge}</td>
                <td>${msg.tentativas || 0}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="cancelarMensagem(${msg.id})" ${msg.status_code != 1 ? 'disabled' : ''}>
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    $('#tabela-fila tbody').html(html);
}

function cancelarMensagem(id) {
    Swal.fire({
        title: 'Confirmar Cancelamento',
        text: 'Deseja cancelar esta mensagem?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Não'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `${API_BASE}/fila/${id}`,
                method: 'DELETE',
                dataType: 'json'
            })
            .done(function(response) {
                if (response.sucesso) {
                    mostrarSucesso('Mensagem cancelada');
                    carregarFila();
                } else {
                    mostrarErro(response.mensagem);
                }
            })
            .fail(function() {
                mostrarErro('Erro ao cancelar mensagem');
            });
        }
    });
}

// ============================================
// DASHBOARD
// ============================================

function carregarDashboard() {
    $.ajax({
        url: `${API_BASE}/painel/dashboard`,
        method: 'GET',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.sucesso) {
            atualizarDashboard(response.dados);
        }
    })
    .fail(function() {
        console.error('Erro ao carregar dashboard');
    });
}

function atualizarDashboard(stats) {
    $('#stat-pendentes').text(stats.pendentes || 0);
    $('#stat-enviadas').text(stats.enviado || 0);
    $('#stat-entregues').text(stats.entregue || 0);
    $('#stat-erros').text(stats.erro || 0);
}

// ============================================
// HISTÓRICO
// ============================================

function carregarHistorico() {
    const dataInicio = $('#filtro-data-inicio').val();
    const dataFim = $('#filtro-data-fim').val();

    let url = `${API_BASE}/painel/historico?limit=50`;

    if (dataInicio) url += `&data_inicio=${dataInicio}`;
    if (dataFim) url += `&data_fim=${dataFim}`;

    $.ajax({
        url: url,
        method: 'GET',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.sucesso) {
            renderizarHistorico(response.dados);
        } else {
            mostrarErro(response.mensagem);
        }
    })
    .fail(function() {
        mostrarErro('Erro ao carregar histórico');
    });
}

function renderizarHistorico(eventos) {
    if (!eventos || eventos.length === 0) {
        $('#tabela-historico tbody').html('<tr><td colspan="4" class="text-center">Nenhum evento encontrado</td></tr>');
        return;
    }

    let html = '';
    eventos.forEach(evt => {
        html += `
            <tr>
                <td>${evt.id}</td>
                <td>${evt.tipo_evento}</td>
                <td>${evt.destinatario || '-'}</td>
                <td>${formatarData(evt.criado_em)}</td>
            </tr>
        `;
    });

    $('#tabela-historico tbody').html(html);
}

// ============================================
// CONFIGURAÇÕES
// ============================================

function carregarConfiguracoes() {
    $.ajax({
        url: `${API_BASE}/config`,
        method: 'GET',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.sucesso) {
            renderizarConfiguracoes(response.dados);
        } else {
            mostrarErro(response.mensagem);
        }
    })
    .fail(function() {
        mostrarErro('Erro ao carregar configurações');
    });
}

function renderizarConfiguracoes(configs) {
    let html = '';

    Object.keys(configs).forEach(categoria => {
        html += `<h5 class="mt-4">${categoria}</h5><div class="row">`;

        configs[categoria].forEach(config => {
            html += `
                <div class="col-md-6 mb-3">
                    <label class="form-label">${config.descricao}</label>
                    <input type="text" class="form-control" value="${config.valor}" data-chave="${config.chave}">
                    <small class="text-muted">Padrão: ${config.valor_padrao}</small>
                </div>
            `;
        });

        html += '</div>';
    });

    $('#container-configuracoes').html(html);
}

function processarFila() {
    Swal.fire({
        title: 'Processar Fila',
        text: 'Quantas mensagens deseja processar?',
        input: 'number',
        inputValue: 10,
        showCancelButton: true,
        confirmButtonText: 'Processar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `${API_BASE}/painel/processar`,
                method: 'POST',
                data: JSON.stringify({ limit: parseInt(result.value) }),
                contentType: 'application/json',
                dataType: 'json'
            })
            .done(function(response) {
                if (response.sucesso) {
                    mostrarSucesso(response.mensagem);
                    carregarFila();
                    carregarDashboard();
                } else {
                    mostrarErro(response.mensagem);
                }
            })
            .fail(function() {
                mostrarErro('Erro ao processar fila');
            });
        }
    });
}

// ============================================
// UTILITÁRIOS
// ============================================

function obterBadgeStatus(code) {
    const badges = {
        0: '<span class="badge bg-danger">Erro</span>',
        1: '<span class="badge bg-warning">Pendente</span>',
        2: '<span class="badge bg-info">Enviado</span>',
        3: '<span class="badge bg-primary">Entregue</span>',
        4: '<span class="badge bg-success">Lido</span>'
    };
    return badges[code] || '<span class="badge bg-secondary">Desconhecido</span>';
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleString('pt-BR');
}

function mostrarSucesso(mensagem) {
    Swal.fire({
        icon: 'success',
        title: 'Sucesso!',
        text: mensagem,
        timer: 3000,
        showConfirmButton: false
    });
}

function mostrarErro(mensagem) {
    Swal.fire({
        icon: 'error',
        title: 'Erro',
        text: mensagem
    });
}

// Limpa intervalo ao sair da página
$(window).on('beforeunload', function() {
    if (intervalStatusCheck) {
        clearInterval(intervalStatusCheck);
    }
});
