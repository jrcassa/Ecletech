// ============================================
// WHATSAPP.JS - Sistema de Gerenciamento WhatsApp
// Versão: 1.0.0
// ============================================

// Variáveis Globais
let PODE_ALTERAR = false;
let PODE_DELETAR = false;
let USUARIO_NOME = '';
let intervalStatusCheck = null;

// ============================================
// INICIALIZAÇÃO
// ============================================

$(document).ready(function() {
    console.log('WhatsApp Manager iniciado');

    // Verifica sessão e carrega dados iniciais
    verificarSessao();

    // Navegação entre tabs
    inicializarNavegacao();

    // Eventos de formulários
    inicializarEventos();

    // Carrega status inicial
    verificarStatusInstancia();
});

/**
 * Verifica sessão do usuário
 */
function verificarSessao() {
    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Conexao.php',
        method: 'GET',
        data: 'op=verificar-sessao',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.sessao === false) {
            window.location.href = '../../index.php';
            return;
        }

        // Define permissões
        PODE_ALTERAR = response.pode_alterar || false;
        PODE_DELETAR = false; // Sempre false
        USUARIO_NOME = response.nome || 'Usuário';

        // Atualiza interface
        $('#nome-usuario').text(USUARIO_NOME);
        atualizarInterfacePermissoes();
    })
    .fail(function() {
        Swal.fire({
            icon: 'error',
            title: 'Erro de Conexão',
            text: 'Não foi possível verificar sua sessão'
        }).then(() => {
            window.location.href = '../../index.php';
        });
    });
}

/**
 * Atualiza interface conforme permissões
 */
function atualizarInterfacePermissoes() {
    // Atualiza lista de permissões
    let htmlPermissoes = `
        <li class="mb-2">
            <i class="fas fa-check text-success"></i> Acessar
        </li>
        <li class="mb-2">
            ${PODE_ALTERAR ?
                '<i class="fas fa-check text-success"></i> Alterar' :
                '<i class="fas fa-times text-danger"></i> Alterar'}
        </li>
        <li>
            <i class="fas fa-times text-danger"></i> Deletar
        </li>
    `;
    $('#lista-permissoes').html(htmlPermissoes);

    // Bloqueia menu de configurações se não pode alterar
    if (!PODE_ALTERAR) {
        $('#menu-configuracoes').addClass('permissao-negada');
        $('#lock-configuracoes').show();
    }
}

/**
 * Inicializa navegação entre tabs
 */
function inicializarNavegacao() {
    $('.list-group-item').click(function(e) {
        e.preventDefault();

        if ($(this).hasClass('permissao-negada')) {
            Swal.fire({
                icon: 'error',
                title: 'Acesso Negado',
                text: 'Você não tem permissão para acessar esta seção.'
            });
            return;
        }

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
            case 'teste':
                // Nada a carregar
                break;
            case 'fila':
                carregarFila();
                break;
            case 'historico':
                carregarHistorico();
                break;
            case 'configuracoes':
                if (PODE_ALTERAR) {
                    carregarConfiguracoes();
                }
                break;
        }
    });
}

/**
 * Inicializa eventos de formulários
 */
function inicializarEventos() {
    // Mudança de tipo de destinatário
    $('#tipo-destinatario').change(function() {
        carregarDestinatarios($(this).val());
    });

    // Mudança de tipo de mensagem
    $('#tipo-mensagem').change(function() {
        alternarCamposMensagem($(this).val());
    });

    // Submit do formulário de teste
    $('#form-teste-envio').submit(function(e) {
        e.preventDefault();
        enviarMensagemTeste();
    });
}

// ============================================
// CONEXÃO - Gerenciamento da Instância
// ============================================

/**
 * HTML de carregamento
 */
const htmlCarregando = `
    <div class="text-center py-5">
        <div class="d-flex justify-content-center align-items-center rounded-circle bg-warning bg-opacity-10 shadow-sm pulse-bg mx-auto"
            style="width: 160px; height: 160px;">
            <span class="text-warning fas fa-spinner fa-spin" style="font-size: 64px;"></span>
        </div>
        <h4 class="mt-4 text-warning">Inicializando</h4>
        <p class="text-muted">Sincronizando com o WhatsApp, aguarde...</p>
    </div>
`;

/**
 * Verifica status da instância WhatsApp
 */
function verificarStatusInstancia() {
    $('#status-instancia-container').html(htmlCarregando);

    // Limpa interval anterior se existir
    if (intervalStatusCheck) {
        clearInterval(intervalStatusCheck);
        intervalStatusCheck = null;
    }

    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Conexao.php',
        method: 'GET',
        data: 'op=status-whatsapp',
        dataType: 'json',
        timeout: 30000
    })
    .done(function(response) {
        console.log('Status Response:', response);

        if (response.sessao === false) {
            window.location.href = '../../index.php';
            return;
        }

        if (response.status === 'sucesso') {
            renderizarStatusInstancia(response);
        } else if (response.status === 'permissao') {
            mostrarErro('Você não tem permissão para acessar este recurso');
        } else {
            mostrarErro('Erro ao verificar status: ' + (response.mensagem || 'Erro desconhecido'));
        }
    })
    .fail(function(xhr, status, error) {
        console.error('Erro:', error);
        mostrarErro('Erro na comunicação com o servidor');
    });
}

/**
 * Renderiza o status da instância
 */
function renderizarStatusInstancia(response) {
    let html = '';

    // STATUS: CONECTADO
    if (response.status_instancia === 'conectado') {
        const info = response.instance_data?.instance_data || {};
        const user = info.user || {};
        const numero = user.id ? user.id.split(':')[0] : 'N/A';
        const numeroFormatado = formatarNumeroTelefone(numero);

        html = `
            <div class="info-box">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5><i class="fas fa-check-circle"></i> Instância Conectada</h5>
                        <p class="mb-2"><strong>Número:</strong> ${numeroFormatado}</p>
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
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-phone fa-3x text-success mb-3"></i>
                            <h6>WhatsApp Ativo</h6>
                            <p class="text-muted small mb-0">Pronto para enviar mensagens</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card">
                        <div class="card-body text-center">
                            <i class="fas fa-shield-alt fa-3x text-primary mb-3"></i>
                            <h6>Webhook Configurado</h6>
                            <p class="text-muted small mb-0">Recebendo atualizações</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-3">
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
    // STATUS: QR CODE
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
                        Escaneie o QR Code abaixo com seu WhatsApp
                    </p>

                    <div class="qr-container mb-4">
                        <img src="${response.instancia.qrcode}"
                             alt="QR Code WhatsApp"
                             class="img-fluid">
                    </div>

                    <div class="alert alert-info d-inline-flex align-items-center">
                        <i class="fas fa-info-circle me-2"></i>
                        <small>O QR Code expira em 60 segundos e será atualizado automaticamente.</small>
                    </div>

                    <div class="mt-3">
                        <button class="btn btn-outline-secondary" onclick="verificarStatusInstancia()">
                            <i class="fas fa-sync-alt"></i> Atualizar QR Code
                        </button>
                    </div>
                </div>
            `;

            // Auto-atualiza a cada 5 segundos
            intervalStatusCheck = setTimeout(verificarStatusInstancia, 5000);
        } else {
            html = `
                <div class="text-center">
                    <h4 class="mb-4">
                        <span class="status-badge status-qrcode">
                            <i class="fas fa-spinner fa-spin"></i> Gerando QR Code
                        </span>
                    </h4>
                    <p class="text-muted">Aguarde enquanto geramos o QR Code...</p>
                </div>
            `;

            // Tenta novamente em 2 segundos
            intervalStatusCheck = setTimeout(verificarStatusInstancia, 2000);
        }
    }
    // STATUS: DESCONECTADO
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
            // Limpa interval
            if (intervalStatusCheck) {
                clearInterval(intervalStatusCheck);
                intervalStatusCheck = null;
            }

            $.ajax({
                url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Conexao.php',
                method: 'GET',
                data: 'op=desconectar-whatsapp',
                dataType: 'json',
                beforeSend: function() {
                    Swal.fire({
                        title: 'Desconectando...',
                        text: 'Aguarde...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                }
            })
            .done(function(response) {
                if (response.status === 'sucesso') {
                    Swal.fire('Desconectado!', 'Instância desconectada com sucesso.', 'success');
                    $('#status-instancia-container').html(htmlCarregando);
                    setTimeout(verificarStatusInstancia, 3000);
                } else if (response.status === 'permissao') {
                    Swal.fire('Acesso Negado', response.mensagem, 'error');
                } else {
                    Swal.fire('Erro', response.mensagem || 'Erro ao desconectar', 'error');
                }
            })
            .fail(function() {
                Swal.fire('Erro', 'Erro na comunicação com o servidor', 'error');
            });
        }
    });
}

// ============================================
// TESTE DE ENVIO
// ============================================

/**
 * Carrega destinatários conforme tipo
 */
function carregarDestinatarios(tipo) {
    $('#select-entidade, #input-numero').hide();

    if (tipo === 'numero') {
        $('#input-numero').show().prop('required', true);
        $('#select-entidade').prop('required', false);
    } else if (tipo !== '') {
        $('#select-entidade').show().prop('required', true).html('<option value="">Carregando...</option>');
        $('#input-numero').prop('required', false);

        $.ajax({
            url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Envio.php',
            method: 'GET',
            data: `op=listar-entidades&tipo=${tipo}`,
            dataType: 'json'
        })
        .done(function(response) {
            if (response.status === 'sucesso') {
                let options = '<option value="">Selecione...</option>';

                if (response.entidades && response.entidades.length > 0) {
                    response.entidades.forEach(function(ent) {
                        options += `<option value="${ent.entidade_id}">
                            ${ent.nome} - ${ent.numero_formatado || ent.numero_whatsapp}
                        </option>`;
                    });
                } else {
                    options = '<option value="">Nenhum registro encontrado</option>';
                }

                $('#select-entidade').html(options);
            } else {
                $('#select-entidade').html('<option value="">Erro ao carregar</option>');
                console.error('Erro:', response.mensagem);
            }
        })
        .fail(function() {
            $('#select-entidade').html('<option value="">Erro ao carregar</option>');
        });
    } else {
        $('#select-entidade').prop('required', false);
        $('#input-numero').prop('required', false);
    }
}

/**
 * Alterna campos conforme tipo de mensagem
 */
function alternarCamposMensagem(tipo) {
    if (tipo === 'text') {
        $('#campo-texto').show();
        $('#mensagem-texto').prop('required', true);
        $('#campo-url, #campo-caption').hide();
        $('#arquivo-url').prop('required', false);
    } else {
        $('#campo-texto').hide();
        $('#mensagem-texto').prop('required', false);
        $('#campo-url, #campo-caption').show();
        $('#arquivo-url').prop('required', true);
    }
}

/**
 * Envia mensagem de teste
 */
function enviarMensagemTeste() {
    const tipoDestinatario = $('#tipo-destinatario').val();
    const tipo = $('#tipo-mensagem').val();
    let destinatario;

    // Valida destinatário
    if (!tipoDestinatario) {
        Swal.fire('Atenção', 'Selecione o tipo de destinatário', 'warning');
        return;
    }

    // Monta destinatário
    if (tipoDestinatario === 'numero') {
        destinatario = $('#input-numero').val().trim();
        if (!destinatario) {
            Swal.fire('Atenção', 'Digite o número do destinatário', 'warning');
            return;
        }
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
        prioridade: $('#prioridade').val(),
        agendado_para: $('#agendado-para').val() || null
    };

    // Adiciona conteúdo conforme tipo
    if (tipo === 'text') {
        dados.mensagem = $('#mensagem-texto').val().trim();
        if (!dados.mensagem) {
            Swal.fire('Atenção', 'Digite a mensagem', 'warning');
            return;
        }
    } else {
        dados.url = $('#arquivo-url').val().trim();
        dados.caption = $('#arquivo-caption').val().trim();
        if (!dados.url) {
            Swal.fire('Atenção', 'Digite a URL do arquivo', 'warning');
            return;
        }
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
                text: 'Aguarde enquanto a mensagem é processada',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            Swal.fire({
                icon: 'success',
                title: 'Sucesso!',
                text: 'Mensagem adicionada na fila com sucesso!',
                timer: 3000
            });
            $('#form-teste-envio')[0].reset();
            $('#select-entidade, #input-numero').hide();
        } else if (response.status === 'permissao') {
            Swal.fire('Acesso Negado', response.mensagem, 'error');
        } else {
            Swal.fire('Erro', response.mensagem || 'Erro ao enviar mensagem', 'error');
        }
    })
    .fail(function(xhr, status, error) {
        console.error('Erro:', error);
        Swal.fire('Erro', 'Erro na comunicação com o servidor', 'error');
    });
}

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
    })
    .fail(function() {
        $('#stat-pendentes, #stat-processando, #stat-enviados, #stat-erros').html('<i class="fas fa-exclamation-triangle"></i>');
    });

    // Carrega lista
    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Painel.php',
        method: 'GET',
        data: 'op=listar-fila&limite=50',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            renderizarTabelaFila(response.mensagens);
        } else {
            $('#tabela-fila').html('<tr><td colspan="7" class="text-center text-danger">Erro ao carregar fila</td></tr>');
        }
    })
    .fail(function() {
        $('#tabela-fila').html('<tr><td colspan="7" class="text-center text-danger">Erro na comunicação</td></tr>');
    });
}

/**
 * Renderiza tabela da fila
 */
function renderizarTabelaFila(mensagens) {
    if (!mensagens || mensagens.length === 0) {
        $('#tabela-fila').html('<tr><td colspan="7" class="text-center text-muted">Nenhuma mensagem na fila</td></tr>');
        return;
    }

    let html = '';
    mensagens.forEach(function(msg) {
        const statusClass = {
            'pendente': 'warning text-dark',
            'processando': 'info',
            'enviado': 'success',
            'erro': 'danger',
            'cancelado': 'secondary'
        }[msg.status] || 'secondary';

        const destinatario = msg.entidade_nome || msg.destinatario || 'N/A';
        const data = formatarData(msg.criado_em);

        html += `
            <tr>
                <td><strong>#${msg.id}</strong></td>
                <td>${destinatario}</td>
                <td><span class="badge bg-secondary">${msg.tipo_mensagem}</span></td>
                <td><span class="badge bg-${statusClass}">${msg.status.toUpperCase()}</span></td>
                <td>${msg.tentativas}/${msg.max_tentativas}</td>
                <td><small>${data}</small></td>
                <td>
                    ${msg.status === 'pendente' && PODE_ALTERAR ? `
                        <button class="btn btn-sm btn-danger" onclick="cancelarMensagem(${msg.id})" title="Cancelar">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : '<span class="text-muted">-</span>'}
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
                } else {
                    Swal.fire('Erro', response.mensagem || 'Erro ao cancelar', 'error');
                }
            })
            .fail(function() {
                Swal.fire('Erro', 'Erro na comunicação', 'error');
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
        status: $('#filtro-status').val(),
        limite: 50
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
        } else {
            $('#tabela-historico').html('<tr><td colspan="6" class="text-center text-danger">Erro ao carregar histórico</td></tr>');
        }
    })
    .fail(function() {
        $('#tabela-historico').html('<tr><td colspan="6" class="text-center text-danger">Erro na comunicação</td></tr>');
    });
}

/**
 * Renderiza tabela de histórico
 */
function renderizarTabelaHistorico(historico) {
    if (!historico || historico.length === 0) {
        $('#tabela-historico').html('<tr><td colspan="6" class="text-center text-muted">Nenhum registro encontrado</td></tr>');
        return;
    }

    let html = '';
    historico.forEach(function(msg) {
        const statusBadge = getStatusBadge(msg.status_code || 0);
        const destinatario = msg.entidade_nome || msg.destinatario || 'N/A';
        const data = formatarData(msg.criado_em);
        const tempo = msg.tempo_envio ? parseFloat(msg.tempo_envio).toFixed(2) + 's' : '-';
        const dataLeitura = msg.data_leitura ? formatarData(msg.data_leitura) : '-';

        html += `
            <tr>
                <td><small>${data}</small></td>
                <td>${destinatario}</td>
                <td><span class="badge bg-secondary">${msg.tipo_mensagem}</span></td>
                <td>${statusBadge}</td>
                <td><small>${tempo}</small></td>
                <td><small>${dataLeitura}</small></td>
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
    if (!PODE_ALTERAR) {
        $('#container-configuracoes').html(`
            <div class="alert alert-warning text-center" role="alert">
                <i class="fas fa-lock fa-3x mb-3"></i>
                <h5>Acesso Restrito</h5>
                <p class="mb-0">Você não tem permissão para alterar as configurações do sistema.</p>
            </div>
        `);
        return;
    }

    $.ajax({
        url: '../../src/Controllers/Whatsapp/Controller_Whatsapp_Configuracao.php',
        method: 'GET',
        data: 'op=listar-todas',
        dataType: 'json'
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            renderizarFormConfiguracoes(response.configuracoes);
        } else {
            $('#container-configuracoes').html('<div class="alert alert-danger">Erro ao carregar configurações</div>');
        }
    })
    .fail(function() {
        $('#container-configuracoes').html('<div class="alert alert-danger">Erro na comunicação</div>');
    });
}

/**
 * Renderiza formulário de configurações
 */
function renderizarFormConfiguracoes(configs) {
    const html = `
        <div class="accordion" id="accordionConfig">

            <!-- Fila -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFila">
                        <i class="fas fa-list me-2"></i> Fila e Processamento
                    </button>
                </h2>
                <div id="collapseFila" class="accordion-collapse collapse show">
                    <div class="accordion-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Modo de Envio</label>
                                <select class="form-control" id="modo_envio">
                                    <option value="direto">Direto</option>
                                    <option value="queue">Fila</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Mensagens por Ciclo</label>
                                <input type="number" class="form-control" id="fila_mensagens_por_ciclo" min="1" max="100">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Intervalo entre Mensagens (seg)</label>
                                <input type="number" class="form-control" id="fila_intervalo_entre_mensagens" min="1" max="60">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Anti-Ban -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAntiBan">
                        <i class="fas fa-shield-alt me-2"></i> Anti-Ban e Limites
                    </button>
                </h2>
                <div id="collapseAntiBan" class="accordion-collapse collapse">
                    <div class="accordion-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Limite por Hora</label>
                                <input type="number" class="form-control" id="limite_mensagens_por_hora" min="0">
                                <small class="text-muted">0 = ilimitado</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Limite por Dia</label>
                                <input type="number" class="form-control" id="limite_mensagens_por_dia" min="0">
                                <small class="text-muted">0 = ilimitado</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="mt-4 text-end">
            <button class="btn btn-secondary" onclick="carregarConfiguracoes()">
                <i class="fas fa-undo"></i> Cancelar
            </button>
            <button class="btn btn-whatsapp" onclick="salvarConfiguracoes()">
                <i class="fas fa-save"></i> Salvar Configurações
            </button>
        </div>
    `;

    $('#container-configuracoes').html(html);

    // Preenche valores
    Object.keys(configs).forEach(chave => {
        const elemento = document.getElementById(chave);
        if (elemento) {
            elemento.value = configs[chave];
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
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({
                title: 'Salvando...',
                text: 'Aguarde...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        }
    })
    .done(function(response) {
        if (response.status === 'sucesso') {
            Swal.fire('Salvo!', 'Configurações salvas com sucesso.', 'success');
        } else {
            Swal.fire('Erro', response.mensagem || 'Erro ao salvar', 'error');
        }
    })
    .fail(function() {
        Swal.fire('Erro', 'Erro na comunicação', 'error');
    });
}

// ============================================
// FUNÇÕES AUXILIARES
// ============================================

/**
 * Mostra erro na interface
 */
function mostrarErro(mensagem) {
    $('#status-instancia-container').html(`
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${mensagem}
        </div>
    `);
}

/**
 * Formata data para exibição
 */
function formatarData(data) {
    if (!data) return '-';
    const d = new Date(data);
    if (isNaN(d.getTime())) return '-';
    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

/**
 * Formata número de telefone
 */
function formatarNumeroTelefone(numero) {
    if (!numero) return 'N/A';
    // Remove caracteres não numéricos
    numero = numero.replace(/\D/g, '');
    // Formato: +55 (15) 99999-9999
    if (numero.length >= 13) {
        return `+${numero.substr(0, 2)} (${numero.substr(2, 2)}) ${numero.substr(4, 5)}-${numero.substr(9)}`;
    }
    return numero;
}

/**
 * Retorna badge HTML conforme status code
 */
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

// ============================================
// LIMPEZA AO SAIR
// ============================================

window.addEventListener('beforeunload', function() {
    if (intervalStatusCheck) {
        clearInterval(intervalStatusCheck);
    }
});
