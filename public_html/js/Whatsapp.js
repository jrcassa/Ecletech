// ============================================
// WHATSAPP.JS - Sistema de Gerenciamento WhatsApp
// Versão: 2.0.0 - API RESTful
// ============================================

// Configuração da API (usa o baseURL do API.js)
const API_BASE = window.location.origin + '/public_html/api/whatsapp';

// Configuração global do jQuery AJAX para usar credentials e CSRF
$.ajaxSetup({
    xhrFields: {
        withCredentials: true  // Envia cookies automaticamente
    },
    crossDomain: false,
    beforeSend: function(xhr, settings) {
        // Adiciona CSRF token para requisições que não sejam GET
        if (settings.type !== 'GET' && typeof API !== 'undefined') {
            const csrfToken = API.getCsrfToken();
            if (csrfToken) {
                xhr.setRequestHeader('X-CSRF-Token', csrfToken);
            }
        }
    }
});

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

    // Carrega dados iniciais
    carregarNomeUsuario();
    carregarPermissoes();
    verificarStatusInstancia();
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
// USUÁRIO E PERMISSÕES
// ============================================

function carregarNomeUsuario() {
    const usuario = API.getUser();
    if (usuario && usuario.nome) {
        $('#nome-usuario').text(usuario.nome);
    } else {
        // Tenta carregar do servidor se não estiver no localStorage
        API.get('/me')
            .then(response => {
                if (response.sucesso) {
                    const user = response.dados?.usuario || response.dados?.user || response.dados;
                    if (user && user.nome) {
                        $('#nome-usuario').text(user.nome);
                        API.setUser(user);
                    }
                }
            })
            .catch(error => {
                console.error('Erro ao carregar nome do usuário:', error);
                $('#nome-usuario').text('Usuário');
            });
    }
}

function carregarPermissoes() {
    $('#lista-permissoes').html('<li class="mb-2"><i class="fas fa-spinner fa-spin"></i> Carregando...</li>');

    API.get('/me')
        .then(response => {
            if (response.sucesso) {
                const user = response.dados?.usuario || response.dados?.user || response.dados;
                renderizarPermissoes(user);
            } else {
                $('#lista-permissoes').html('<li class="text-danger"><i class="fas fa-exclamation-circle"></i> Erro ao carregar</li>');
            }
        })
        .catch(error => {
            console.error('Erro ao carregar permissões:', error);
            $('#lista-permissoes').html('<li class="text-danger"><i class="fas fa-exclamation-circle"></i> Erro ao carregar</li>');
        });
}

function renderizarPermissoes(user) {
    let html = '';

    // Verifica permissões básicas do usuário
    const permissoes = [];

    // Adiciona permissões baseadas no perfil/role
    if (user.tipo_usuario || user.role) {
        const tipo = user.tipo_usuario || user.role;
        permissoes.push({
            icone: 'fa-user-shield',
            texto: `Perfil: ${Utils.String.capitalize(tipo)}`,
            classe: 'text-primary'
        });
    }

    // Verifica se pode alterar configurações
    if (user.pode_alterar_whatsapp || user.is_admin || user.tipo_usuario === 'admin') {
        PODE_ALTERAR = true;
        permissoes.push({
            icone: 'fa-edit',
            texto: 'Alterar Configurações',
            classe: 'text-success'
        });
    }

    // Verifica se pode deletar
    if (user.pode_deletar_whatsapp || user.is_admin || user.tipo_usuario === 'admin') {
        PODE_DELETAR = true;
        permissoes.push({
            icone: 'fa-trash',
            texto: 'Deletar Mensagens',
            classe: 'text-warning'
        });
    }

    // Permissão de enviar mensagens (todos têm por padrão)
    permissoes.push({
        icone: 'fa-paper-plane',
        texto: 'Enviar Mensagens',
        classe: 'text-info'
    });

    // Permissão de visualizar fila
    permissoes.push({
        icone: 'fa-list',
        texto: 'Visualizar Fila',
        classe: 'text-info'
    });

    // Renderiza as permissões
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

    $('#lista-permissoes').html(html);

    // Desabilita menu de configurações se não tiver permissão
    if (!PODE_ALTERAR) {
        $('#menu-configuracoes').addClass('permissao-negada');
        $('#lock-configuracoes').show();
    }
}

// ============================================
// EVENTOS
// ============================================

function inicializarEventos() {
    // Nota: evento de btn-desconectar e btn-criar-instancia são vinculados dinamicamente
    // em atualizarStatusConexao() pois os botões são criados dinamicamente

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
    let html = '';

    if (dados.conectado) {
        // Conectado
        html = `
            <div class="alert alert-success" role="alert">
                <h5 class="alert-heading"><i class="fas fa-check-circle"></i> WhatsApp Conectado</h5>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Telefone:</strong> ${dados.telefone || 'N/A'}</p>
                        <p class="mb-1"><strong>Nome:</strong> ${dados.nome || 'N/A'}</p>
                    </div>
                    <div class="col-md-6 text-end">
                        <button id="btn-desconectar" class="btn btn-danger">
                            <i class="fas fa-power-off"></i> Desconectar
                        </button>
                    </div>
                </div>
            </div>
        `;

        // Para auto-refresh de QR code
        if (intervalStatusCheck) {
            clearInterval(intervalStatusCheck);
            intervalStatusCheck = null;
        }

    } else if (dados.status === 'qrcode' && dados.qr_code) {
        // Aguardando QR Code
        html = `
            <div class="alert alert-warning" role="alert">
                <h5 class="alert-heading"><i class="fas fa-qrcode"></i> Aguardando Conexão</h5>
                <p>Escaneie o QR Code abaixo com seu WhatsApp para conectar:</p>
                <div class="text-center">
                    <img src="${dados.qr_code}" alt="QR Code" class="img-fluid" style="max-width: 300px;">
                    <p class="mt-3 text-muted"><small>O QR Code é atualizado automaticamente a cada 5 segundos</small></p>
                </div>
            </div>
        `;

        // Auto-refresh QR code a cada 5 segundos
        if (!intervalStatusCheck) {
            intervalStatusCheck = setInterval(verificarStatusInstancia, 5000);
        }

    } else {
        // Desconectado
        html = `
            <div class="alert alert-danger" role="alert">
                <h5 class="alert-heading"><i class="fas fa-times-circle"></i> WhatsApp Desconectado</h5>
                <p>A instância do WhatsApp não está conectada.</p>
                <button id="btn-criar-instancia" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Criar Instância
                </button>
            </div>
        `;

        if (intervalStatusCheck) {
            clearInterval(intervalStatusCheck);
            intervalStatusCheck = null;
        }
    }

    $('#status-instancia-container').html(html);

    // Re-bind eventos dos botões
    $('#btn-desconectar').off('click').on('click', function() {
        desconectarWhatsApp();
    });

    $('#btn-criar-instancia').off('click').on('click', function() {
        criarInstancia();
    });
}

function criarInstancia() {
    Swal.fire({
        title: 'Criar Instância',
        text: 'Deseja criar uma nova instância do WhatsApp?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sim, criar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: `${API_BASE}/conexao/criar`,
                method: 'POST',
                dataType: 'json'
            })
            .done(function(response) {
                if (response.sucesso) {
                    mostrarSucesso('Instância criada com sucesso. Aguardando QR Code...');
                    verificarStatusInstancia();
                } else {
                    mostrarErro(response.mensagem);
                }
            })
            .fail(function() {
                mostrarErro('Erro ao criar instância');
            });
        }
    });
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
            // Resposta paginada tem estrutura: {itens: [], paginacao: {}}
            const mensagens = response.dados.itens || response.dados || [];
            renderizarFila(mensagens);
        } else {
            mostrarErro(response.mensagem);
        }
    })
    .fail(function() {
        mostrarErro('Erro ao carregar fila');
    });
}

function renderizarFila(mensagens) {
    if (!mensagens || !Array.isArray(mensagens) || mensagens.length === 0) {
        $('#tabela-fila').html('<tr><td colspan="7" class="text-center">Nenhuma mensagem na fila</td></tr>');
        return;
    }

    let html = '';
    mensagens.forEach(msg => {
        const statusBadge = obterBadgeStatus(msg.status_code);
        const destinatario = Utils.DOM.escapeHtml(msg.destinatario_nome || msg.destinatario || '-');
        const tipo = Utils.String.capitalize(msg.tipo_mensagem || 'text');
        const tentativas = msg.tentativas || 0;
        const dataCriacao = Utils.Format.dataHora(msg.criado_em || msg.created_at);

        html += `
            <tr>
                <td>${msg.id}</td>
                <td>${destinatario}</td>
                <td>${tipo}</td>
                <td>${statusBadge}</td>
                <td>${tentativas}</td>
                <td>${dataCriacao}</td>
                <td>
                    <button class="btn btn-sm btn-danger" onclick="cancelarMensagem(${msg.id})" ${msg.status_code != 1 ? 'disabled' : ''}>
                        <i class="fas fa-times"></i>
                    </button>
                </td>
            </tr>
        `;
    });

    $('#tabela-fila').html(html);
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
    // Atualiza estatísticas da aba Fila
    $('#stat-pendentes').text(stats.pendentes || 0);
    $('#stat-processando').text(stats.processando || 0);
    $('#stat-enviados').text(stats.enviado || stats.enviados || 0);
    $('#stat-erros').text(stats.erro || stats.erros || 0);
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
            // Resposta paginada tem estrutura: {itens: [], paginacao: {}}
            const eventos = response.dados.itens || response.dados || [];
            renderizarHistorico(eventos);
        } else {
            mostrarErro(response.mensagem);
        }
    })
    .fail(function() {
        mostrarErro('Erro ao carregar histórico');
    });
}

function renderizarHistorico(eventos) {
    if (!eventos || !Array.isArray(eventos) || eventos.length === 0) {
        $('#tabela-historico').html('<tr><td colspan="6" class="text-center">Nenhum evento encontrado</td></tr>');
        return;
    }

    let html = '';
    eventos.forEach(evt => {
        const dataCriacao = Utils.Format.dataHora(evt.criado_em || evt.created_at);
        const destinatario = Utils.DOM.escapeHtml(evt.destinatario_nome || evt.destinatario || '-');
        const tipo = Utils.String.capitalize(evt.tipo_mensagem || evt.tipo_evento || '-');
        const statusBadge = obterBadgeStatus(evt.status_code);
        const tempoProcessamento = evt.tempo_processamento ? `${evt.tempo_processamento}s` : '-';
        const dataLeitura = evt.lido_em ? Utils.Format.dataHora(evt.lido_em) : '-';

        html += `
            <tr>
                <td>${dataCriacao}</td>
                <td>${destinatario}</td>
                <td>${tipo}</td>
                <td>${statusBadge}</td>
                <td>${tempoProcessamento}</td>
                <td>${dataLeitura}</td>
            </tr>
        `;
    });

    $('#tabela-historico').html(html);
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
