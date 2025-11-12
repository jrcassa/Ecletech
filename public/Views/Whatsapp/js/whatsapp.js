// ============================================
// WHATSAPP.JS - Sistema de Gerenciamento WhatsApp
// ============================================

// HTML Templates
const whatsapp_carregando = `
    <div class="card border-0 shadow-sm p-4 mb-4">
        <div class="text-center">
            <h1 class="display-5 fw-bold text-warning mb-3">
                <i class="fas fa-circle-notch fa-spin me-2"></i>Inicializando
            </h1>
            <p class="lead text-muted mb-4">
                Sincronizando com o WhatsApp, aguarde um momento...
            </p>
            <div class="d-flex justify-content-center mb-4">
                <div class="d-flex justify-content-center align-items-center rounded-circle bg-warning bg-opacity-10 shadow-sm pulse-bg"
                    style="width: 160px; height: 160px;">
                    <span class="text-warning fas fa-spinner fa-spin" style="font-size: 64px;"></span>
                </div>
            </div>
        </div>
    </div>
`;

// ============================================
// CONEXÃO - Gerenciamento da Instância
// ============================================

/**
 * Verifica status da instância WhatsApp
 */
function verificarStatusInstancia() {
    $('#status-instancia-container').html(whatsapp_carregando);

    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Conexao.php',
        method: 'GET',
        data: 'op=status-whatsapp',
        dataType: 'json'
    })
    .done(function(response) {
        console.log('Status Response:', response);

        if (response.sessao === false) {
            window.location.href = '../../index.php';
            return;
        }

        if (response.status === 'sucesso') {
            renderizarStatusInstancia(response);
        } else {
            mostrarErro('Erro ao verificar status: ' + (response.mensagem || 'Erro desconhecido'));
        }
    })
    .fail(function(xhr, status, error) {
        mostrarErro('Erro na comunicação: ' + error);
    });
}

/**
 * Renderiza o status da instância conforme resposta
 */
function renderizarStatusInstancia(response) {
    let html = '';

    // Status: Conectado
    if (response.status_instancia === 'conectado') {
        const info = response.instance_data?.instance_data || {};
        const user = info.user || {};

        html = `
            <div class="info-box">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5><i class="fas fa-check-circle"></i> Instância Conectada</h5>
                        <p class="mb-2"><strong>Número:</strong> ${user.id || 'N/A'}</p>
                        <p class="mb-0"><strong>Nome:</strong> ${user.name || 'N/A'}</p>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="status-badge status-conectado">
                            <i class="fas fa-check-circle"></i> Conectado
                        </span>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-phone fa-3x text-success mb-3"></i>
                            <h6>WhatsApp Ativo</h6>
                            <p class="text-muted small mb-0">Pronto para enviar mensagens</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                            <h6>Webhook Configurado</h6>
                            <p class="text-muted small mb-0">Recebendo atualizações</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-4 text-center">
                <button class="btn btn-outline-primary" onclick="verificarStatusInstancia()">
                    <i class="fas fa-sync-alt"></i> Atualizar Status
                </button>
                ${PODE_ALTERAR ? `
                <button class="btn btn-outline-danger" onclick="desconectarWhatsApp()">
                    <i class="fas fa-sign-out-alt"></i> Desconectar
                </button>
                ` : ''}
            </div>
        `;
    }
    // Status: QR Code
    else if (response.status_instancia === 'qrcode') {
        if (response.instancia?.qrcode) {
            html = `
                <div class="text-center">
                    <h4 class="mb-4">
                        <span class="status-badge status-qrcode">
                            <i class="fas fa-qrcode"></i> Aguardando Conexão
                        </span>
                    </h4>
                    <p class="lead text-muted mb-4">
                        Escaneie o QR Code abaixo com seu WhatsApp para conectar
                    </p>

                    <div class="qr-container mb-4">
                        <img src="${response.instancia.qrcode}"
                             alt="QR Code WhatsApp"
                             class="img-fluid">
                    </div>

                    <div class="alert alert-info d-inline-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>O QR Code expira em 60 segundos. Será atualizado automaticamente.</small>
                    </div>

                    <div class="mt-3">
                        <button class="btn btn-outline-secondary" onclick="verificarStatusInstancia()">
                            <i class="fas fa-sync-alt"></i> Atualizar QR Code
                        </button>
                    </div>
                </div>
            `;

            // Auto-atualiza a cada 5 segundos
            setTimeout(verificarStatusInstancia, 5000);
        }
    }
    // Status: Desconectado
    else {
        html = `
            <div class="text-center">
                <h4 class="mb-4">
                    <span class="status-badge status-desconectado">
                        <i class="fas fa-times-circle"></i> Desconectado
                    </span>
                </h4>
                <p class="lead text-muted mb-4">
                    A instância do WhatsApp não está conectada.
                </p>

                <div class="alert alert-warning d-inline-block">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Conecte sua conta do WhatsApp para começar a enviar mensagens.
                </div>

                <div class="mt-4">
                    <button class="btn btn-whatsapp btn-lg" onclick="verificarStatusInstancia()">
                        <i class="fas fa-plug"></i> Iniciar Conexão
                    </button>
                </div>
            </div>
        `;
    }

    $('#status-instancia-container').html(html);
}

/**
 * Desconecta a instância WhatsApp
 */
function desconectarWhatsApp() {
    if (!PODE_ALTERAR) {
        Swal.fire('Acesso Negado', 'Você não tem permissão para desconectar.', 'error');
        return;
    }

    Swal.fire({
        title: 'Desconectar WhatsApp?',
        text: "Você precisará escanear o QR Code novamente para reconectar.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sim, desconectar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Conexao.php',
                method: 'GET',
                data: 'op=desconectar-whatsapp',
                dataType: 'json'
            })
            .done(function(response) {
                if (response.status === 'sucesso') {
                    Swal.fire('Desconectado!', 'Instância desconectada com sucesso.', 'success');
                    $('#status-instancia-container').html(whatsapp_carregando);
                    setTimeout(verificarStatusInstancia, 3000);
                } else {
                    mostrarErro(response.mensagem || 'Erro ao desconectar');
                }
            })
            .fail(function() {
                mostrarErro('Erro na comunicação com o servidor');
            });
        }
    });
}

// ============================================
// TESTE DE ENVIO
// ============================================

/**
 * Carrega entidades ao selecionar tipo
 */
$('#tipo-destinatario').change(function() {
    const tipo = $(this).val();

    $('#select-entidade, #input-numero').hide();

    if (tipo === 'numero') {
        $('#input-numero').show();
    } else if (tipo !== '') {
        $('#select-entidade').show().html('<option value="">Carregando...</option>');

        $.ajax({
            url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Envio.php',
            method: 'GET',
            data: `op=listar-entidades&tipo=${tipo}`,
            dataType: 'json'
        })
        .done(function(response) {
            if (response.status === 'sucesso') {
                let options = '<option value="">Selecione...</option>';
                response.entidades.forEach(function(ent) {
                    options += `<option value="${ent.entidade_id}">
                        ${ent.nome} - ${ent.numero_formatado}
                    </option>`;
                });
                $('#select-entidade').html(options);
            }
        });
    }
});

/**
 * Alterna campos conforme tipo de mensagem
 */
$('#tipo-mensagem').change(function() {
    const tipo = $(this).val();

    if (tipo === 'text') {
        $('#campo-texto').show();
        $('#campo-url, #campo-caption').hide();
    } else {
        $('#campo-texto').hide();
        $('#campo-url, #campo-caption').show();
    }
});

/**
 * Envia mensagem de teste
 */
$('#form-teste-envio').submit(function(e) {
    e.preventDefault();

    const tipoDestinatario = $('#tipo-destinatario').val();
    const tipo = $('#tipo-mensagem').val();
    let destinatario;

    // Monta destinatário
    if (tipoDestinatario === 'numero') {
        destinatario = $('#input-numero').val();
    } else {
        const id = $('#select-entidade').val();
        if (!id) {
            Swal.fire('Atenção', 'Selecione um destinatário', 'warning');
            return;
        }
        destinatario = `${tipoDestinatario}:${id}`;
    }

    // Monta dados
    let dados = {
        op: 'enviar',
        destinatario: destinatario,
        tipo: tipo,
        prioridade: $('#prioridade').val()
    };

    if (tipo === 'text') {
        dados.mensagem = $('#mensagem-texto').val();
    } else {
        dados.url = $('#arquivo-url').val();
        dados.caption = $('#arquivo-caption').val();
    }

    // Envia
    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Envio.php',
        method: 'POST',
        data: dados,
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({
                title: 'Enviando...',
                text: 'Aguarde enquanto a mensagem é enviada',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            Swal.fire('Sucesso!', 'Mensagem enviada com sucesso!', 'success');
            $('#form-teste-envio')[0].reset();
        } else {
            Swal.fire('Erro', response.mensagem || 'Erro ao enviar mensagem', 'error');
        }
    })
    .fail(function() {
        Swal.fire('Erro', 'Erro na comunicação com o servidor', 'error');
    });
});

// ============================================
// FILA
// ============================================

/**
 * Carrega fila de mensagens
 */
function carregarFila() {
    // Carrega estatísticas
    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Painel.php',
        method: 'GET',
        data: 'op=estatisticas-fila',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            const stats = response.estatisticas;
            $('#stat-pendentes').text(stats.pendentes || 0);
            $('#stat-processando').text(stats.processando || 0);
            $('#stat-enviados').text(stats.enviados_hoje || 0);
            $('#stat-erros').text(stats.erros || 0);
        }
    });

    // Carrega lista
    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Painel.php',
        method: 'GET',
        data: 'op=listar-fila&limite=20',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            renderizarTabelaFila(response.mensagens);
        }
    });
}

/**
 * Renderiza tabela da fila
 */
function renderizarTabelaFila(mensagens) {
    if (!mensagens || mensagens.length === 0) {
        $('#tabela-fila').html('<tr><td colspan="7" class="text-center">Nenhuma mensagem na fila</td></tr>');
        return;
    }

    let html = '';
    mensagens.forEach(function(msg) {
        const statusClass = {
            'pendente': 'warning',
            'processando': 'info',
            'enviado': 'success',
            'erro': 'danger',
            'cancelado': 'secondary'
        }[msg.status] || 'secondary';

        html += `
            <tr>
                <td>${msg.id}</td>
                <td>${msg.entidade_nome || msg.destinatario}</td>
                <td><span class="badge bg-secondary">${msg.tipo_mensagem}</span></td>
                <td><span class="badge bg-${statusClass}">${msg.status}</span></td>
                <td>${msg.tentativas}/${msg.max_tentativas}</td>
                <td>${formatarData(msg.criado_em)}</td>
                <td>
                    ${msg.status === 'pendente' ? `
                        <button class="btn btn-sm btn-danger" onclick="cancelarMensagem(${msg.id})">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : '-'}
                </td>
            </tr>
        `;
    });

    $('#tabela-fila').html(html);
}

/**
 * Cancela mensagem da fila
 */
function cancelarMensagem(id) {
    if (!PODE_ALTERAR) {
        Swal.fire('Acesso Negado', 'Você não tem permissão para cancelar mensagens.', 'error');
        return;
    }

    Swal.fire({
        title: 'Cancelar mensagem?',
        text: "Esta ação não pode ser desfeita.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sim, cancelar',
        cancelButtonText: 'Não'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Painel.php',
                method: 'POST',
                data: { op: 'cancelar-mensagem', id: id },
                dataType: 'json'
            })
            .done(function(response) {
                if (response.status === 'sucesso') {
                    Swal.fire('Cancelado!', 'Mensagem cancelada com sucesso.', 'success');
                    carregarFila();
                }
            });
        }
    });
}

// ============================================
// HISTÓRICO
// ============================================

/**
 * Carrega histórico de envios
 */
function carregarHistorico() {
    const filtros = {
        op: 'listar-historico',
        data_inicio: $('#filtro-data-inicio').val(),
        data_fim: $('#filtro-data-fim').val(),
        status: $('#filtro-status').val()
    };

    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Painel.php',
        method: 'GET',
        data: filtros,
        dataType: 'json'
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            renderizarTabelaHistorico(response.historico);
        }
    });
}

/**
 * Renderiza tabela de histórico
 */
function renderizarTabelaHistorico(historico) {
    if (!historico || historico.length === 0) {
        $('#tabela-historico').html('<tr><td colspan="6" class="text-center">Nenhum registro encontrado</td></tr>');
        return;
    }

    let html = '';
    historico.forEach(function(msg) {
        const statusBadge = getStatusBadge(msg.status_code);

        html += `
            <tr>
                <td>${formatarData(msg.criado_em)}</td>
                <td>${msg.entidade_nome || msg.destinatario}</td>
                <td><span class="badge bg-secondary">${msg.tipo_mensagem}</span></td>
                <td>${statusBadge}</td>
                <td>${msg.tempo_envio ? msg.tempo_envio.toFixed(2) + 's' : '-'}</td>
                <td>${msg.data_leitura ? formatarData(msg.data_leitura) : '-'}</td>
            </tr>
        `;
    });

    $('#tabela-historico').html(html);
}

// ============================================
// CONFIGURAÇÕES
// ============================================

/**
 * Carrega configurações
 */
function carregarConfiguracoes() {
    if (!PODE_ALTERAR) return;

    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Configuracao.php',
        method: 'GET',
        data: 'op=listar-todas',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            const configs = response.configuracoes;

            // Preenche campos
            Object.keys(configs).forEach(chave => {
                const elemento = document.getElementById(chave);
                if (elemento) {
                    if (elemento.type === 'checkbox') {
                        elemento.checked = configs[chave] === true || configs[chave] === 'true';
                    } else {
                        elemento.value = configs[chave];
                    }
                }
            });
        }
    });
}

/**
 * Salva configurações
 */
function salvarConfiguracoes() {
    if (!PODE_ALTERAR) {
        Swal.fire('Acesso Negado', 'Você não tem permissão para alterar configurações.', 'error');
        return;
    }

    const configs = {
        modo_envio: $('#modo_envio').val(),
        fila_mensagens_por_ciclo: $('#fila_mensagens_por_ciclo').val(),
        fila_intervalo_entre_mensagens: $('#fila_intervalo_entre_mensagens').val(),
        limite_mensagens_por_hora: $('#limite_mensagens_por_hora').val(),
        limite_mensagens_por_dia: $('#limite_mensagens_por_dia').val()
    };

    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Configuracao.php',
        method: 'POST',
        data: {
            op: 'salvar-multiplas',
            configs: configs
        },
        dataType: 'json'
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            Swal.fire('Salvo!', 'Configurações salvas com sucesso.', 'success');
        } else {
            Swal.fire('Erro', response.mensagem || 'Erro ao salvar', 'error');
        }
    });
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

function mostrarErro(mensagem) {
    $('#status-instancia-container').html(`
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${mensagem}
        </div>
    `);
}

function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    return d.toLocaleString('pt-BR');
}

function getStatusBadge(statusCode) {
    const badges = {
        0: '<span class="badge bg-danger"><i class="fas fa-exclamation-circle"></i> Erro</span>',
        1: '<span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Pendente</span>',
        2: '<span class="badge bg-info"><i class="fas fa-check"></i> Enviado</span>',
        3: '<span class="badge bg-primary"><i class="fas fa-check-double"></i> Entregue</span>',
        4: '<span class="badge bg-success"><i class="fas fa-check-double"></i> Lido</span>'
    };
    return badges[statusCode] || badges[0];
}
