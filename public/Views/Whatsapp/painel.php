<?php
ob_start();
session_start();

require_once dirname(__DIR__, 3) . "/vendor/autoload.php";
require_once dirname(__DIR__, 3) . "/autoload.php";

use Config\Database;
use Models\Administrador\Administrador;
use Models\Sistema\Modulos;

$database = new Database();
$conn = $database->getConnection();

$Administrador = new Administrador($conn);
$Modulos = new Modulos($conn);

// Valida sessão
if (!$Administrador->valida_sessao()) {
    header('Location: ../../index.php');
    exit;
}

// Verifica permissões do módulo WhatsApp
$modulo_id = 'whatsapp'; // ID do módulo no sistema
$permissoes = $Modulos->verificar_permissoes($Administrador->id, $modulo_id);

// Define permissões padrão se não existir
if (!$permissoes) {
    // Apenas diretor e admin têm acesso
    $tem_acesso = ($Administrador->nivel == "5" || $Administrador->nivel == "0");
    $pode_acessar = $tem_acesso;
    $pode_alterar = $tem_acesso;
    $pode_deletar = false; // NUNCA pode deletar
} else {
    $pode_acessar = $permissoes['acessar'] ?? false;
    $pode_alterar = $permissoes['alterar'] ?? false;
    $pode_deletar = false; // FORÇA false - não pode deletar
}

// Se não tem permissão de acesso, redireciona
if (!$pode_acessar) {
    header('Location: ../../dashboard.php?erro=sem_permissao');
    exit;
}

$page_title = "WhatsApp - Gerenciamento";
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <style>
        :root {
            --whatsapp-green: #25D366;
            --whatsapp-dark: #075E54;
            --whatsapp-light: #DCF8C6;
        }

        body {
            background-color: #f8f9fa;
        }

        .navbar-brand {
            color: var(--whatsapp-green) !important;
            font-weight: bold;
        }

        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background-color: white;
            border-bottom: 2px solid #f0f0f0;
            font-weight: 600;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .status-desconectado {
            background-color: #dc3545;
            color: white;
        }

        .status-conectado {
            background-color: var(--whatsapp-green);
            color: white;
        }

        .status-qrcode {
            background-color: #ffc107;
            color: #000;
        }

        .qr-container {
            position: relative;
            display: inline-block;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .qr-container img {
            max-width: 280px;
            height: auto;
            border-radius: 8px;
        }

        .pulse-bg {
            animation: pulse-bg 2s infinite;
        }

        @keyframes pulse-bg {
            0% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.4);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(37, 211, 102, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(37, 211, 102, 0);
            }
        }

        .info-box {
            background: linear-gradient(135deg, var(--whatsapp-green) 0%, var(--whatsapp-dark) 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .info-box h5 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .info-box p {
            margin-bottom: 0;
            opacity: 0.9;
        }

        .btn-whatsapp {
            background-color: var(--whatsapp-green);
            color: white;
            border: none;
        }

        .btn-whatsapp:hover {
            background-color: var(--whatsapp-dark);
            color: white;
        }

        .tab-content {
            padding: 2rem 0;
        }

        .nav-tabs .nav-link {
            color: #6c757d;
            border: none;
            border-bottom: 3px solid transparent;
            font-weight: 500;
        }

        .nav-tabs .nav-link:hover {
            border-color: transparent;
            color: var(--whatsapp-green);
        }

        .nav-tabs .nav-link.active {
            color: var(--whatsapp-green);
            background-color: transparent;
            border-color: transparent transparent var(--whatsapp-green);
        }

        .permissao-negada {
            opacity: 0.6;
            pointer-events: none;
            position: relative;
        }

        .permissao-negada::after {
            content: '\f023';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 2rem;
            color: #dc3545;
            text-shadow: 0 0 10px rgba(220, 53, 69, 0.5);
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fab fa-whatsapp"></i> WhatsApp Manager
        </a>
        <div class="ms-auto">
            <span class="badge bg-secondary me-2">
                <i class="fas fa-user"></i> <?php echo htmlspecialchars($Administrador->nome); ?>
            </span>
            <a href="../../logout.php" class="btn btn-outline-danger btn-sm">
                <i class="fas fa-sign-out-alt"></i> Sair
            </a>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-cog"></i> Menu
                </div>
                <div class="list-group list-group-flush">
                    <a href="#" class="list-group-item list-group-item-action active" data-tab="conexao">
                        <i class="fas fa-plug"></i> Conexão
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-tab="teste">
                        <i class="fas fa-paper-plane"></i> Teste de Envio
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-tab="fila">
                        <i class="fas fa-list"></i> Fila
                    </a>
                    <a href="#" class="list-group-item list-group-item-action" data-tab="historico">
                        <i class="fas fa-history"></i> Histórico
                    </a>
                    <a href="#" class="list-group-item list-group-item-action <?php echo !$pode_alterar ? 'permissao-negada' : ''; ?>" data-tab="configuracoes">
                        <i class="fas fa-sliders-h"></i> Configurações
                        <?php if (!$pode_alterar): ?>
                            <i class="fas fa-lock float-end text-danger"></i>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Info Permissões -->
            <div class="card mt-3">
                <div class="card-header">
                    <i class="fas fa-shield-alt"></i> Suas Permissões
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-check text-success"></i> Acessar
                        </li>
                        <li class="mb-2">
                            <?php if ($pode_alterar): ?>
                                <i class="fas fa-check text-success"></i> Alterar
                            <?php else: ?>
                                <i class="fas fa-times text-danger"></i> Alterar
                            <?php endif; ?>
                        </li>
                        <li>
                            <i class="fas fa-times text-danger"></i> Deletar
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Conteúdo Principal -->
        <div class="col-md-9">

            <!-- Tab: Conexão -->
            <div id="tab-conexao" class="tab-pane-custom active">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-plug"></i> Gerenciamento de Conexão</h5>
                    </div>
                    <div class="card-body">

                        <!-- Status da Instância -->
                        <div id="status-instancia-container">
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Carregando...</span>
                                </div>
                                <p class="mt-3 text-muted">Verificando status da instância...</p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Tab: Teste de Envio -->
            <div id="tab-teste" class="tab-pane-custom" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-paper-plane"></i> Teste de Envio</h5>
                    </div>
                    <div class="card-body">

                        <form id="form-teste-envio">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tipo de Destinatário</label>
                                    <select class="form-select" id="tipo-destinatario">
                                        <option value="">Selecione...</option>
                                        <option value="cliente">Cliente</option>
                                        <option value="colaborador">Colaborador</option>
                                        <option value="fornecedor">Fornecedor</option>
                                        <option value="numero">Número Direto</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Destinatário</label>
                                    <select class="form-select" id="select-entidade" style="display:none;">
                                        <option value="">Carregando...</option>
                                    </select>
                                    <input type="text" class="form-control" id="input-numero"
                                           placeholder="Ex: 5515999999999" style="display:none;">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Mensagem</label>
                                <select class="form-select" id="tipo-mensagem">
                                    <option value="text">Texto</option>
                                    <option value="image">Imagem (URL)</option>
                                    <option value="pdf">PDF (URL)</option>
                                </select>
                            </div>

                            <div id="campo-texto" class="mb-3">
                                <label class="form-label">Mensagem</label>
                                <textarea class="form-control" id="mensagem-texto" rows="4"
                                          placeholder="Digite sua mensagem..."></textarea>
                            </div>

                            <div id="campo-url" class="mb-3" style="display:none;">
                                <label class="form-label">URL do Arquivo</label>
                                <input type="url" class="form-control" id="arquivo-url"
                                       placeholder="https://exemplo.com/arquivo.jpg">
                            </div>

                            <div id="campo-caption" class="mb-3" style="display:none;">
                                <label class="form-label">Legenda (opcional)</label>
                                <input type="text" class="form-control" id="arquivo-caption"
                                       placeholder="Legenda do arquivo">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Prioridade</label>
                                <select class="form-select" id="prioridade">
                                    <option value="normal">Normal</option>
                                    <option value="alta">Alta</option>
                                    <option value="urgente">Urgente</option>
                                    <option value="baixa">Baixa</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-whatsapp">
                                <i class="fas fa-paper-plane"></i> Enviar Mensagem
                            </button>
                        </form>

                    </div>
                </div>
            </div>

            <!-- Tab: Fila -->
            <div id="tab-fila" class="tab-pane-custom" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Fila de Mensagens</h5>
                    </div>
                    <div class="card-body">

                        <!-- Cards de Estatísticas -->
                        <div class="row mb-4">
                            <div class="col-md-3">
                                <div class="card bg-warning text-white">
                                    <div class="card-body text-center">
                                        <h3 id="stat-pendentes">0</h3>
                                        <small>Pendentes</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-info text-white">
                                    <div class="card-body text-center">
                                        <h3 id="stat-processando">0</h3>
                                        <small>Processando</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-success text-white">
                                    <div class="card-body text-center">
                                        <h3 id="stat-enviados">0</h3>
                                        <small>Enviados Hoje</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card bg-danger text-white">
                                    <div class="card-body text-center">
                                        <h3 id="stat-erros">0</h3>
                                        <small>Erros</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabela de Mensagens -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Destinatário</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th>Tentativas</th>
                                        <th>Data</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-fila">
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                            Carregando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Tab: Histórico -->
            <div id="tab-historico" class="tab-pane-custom" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-history"></i> Histórico de Envios</h5>
                    </div>
                    <div class="card-body">

                        <!-- Filtros -->
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="filtro-data-inicio" placeholder="Data Início">
                            </div>
                            <div class="col-md-3">
                                <input type="date" class="form-control" id="filtro-data-fim" placeholder="Data Fim">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" id="filtro-status">
                                    <option value="">Todos os Status</option>
                                    <option value="enviado">Enviado</option>
                                    <option value="erro">Erro</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <button class="btn btn-primary w-100" onclick="carregarHistorico()">
                                    <i class="fas fa-search"></i> Filtrar
                                </button>
                            </div>
                        </div>

                        <!-- Tabela -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Data</th>
                                        <th>Destinatário</th>
                                        <th>Tipo</th>
                                        <th>Status</th>
                                        <th>Tempo</th>
                                        <th>Lido em</th>
                                    </tr>
                                </thead>
                                <tbody id="tabela-historico">
                                    <tr>
                                        <td colspan="6" class="text-center">
                                            <div class="spinner-border spinner-border-sm" role="status"></div>
                                            Carregando...
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Tab: Configurações -->
            <div id="tab-configuracoes" class="tab-pane-custom <?php echo !$pode_alterar ? 'permissao-negada' : ''; ?>" style="display: none;">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sliders-h"></i> Configurações do Sistema</h5>
                    </div>
                    <div class="card-body">

                        <?php if ($pode_alterar): ?>

                        <!-- Accordion de Configurações -->
                        <div class="accordion" id="accordionConfig">

                            <!-- Fila -->
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseFila">
                                        <i class="fas fa-list me-2"></i> Fila e Processamento
                                    </button>
                                </h2>
                                <div id="collapseFila" class="accordion-collapse collapse show" data-bs-parent="#accordionConfig">
                                    <div class="accordion-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Modo de Envio</label>
                                                <select class="form-select" id="modo_envio">
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
                                <div id="collapseAntiBan" class="accordion-collapse collapse" data-bs-parent="#accordionConfig">
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

                        <?php else: ?>

                        <div class="alert alert-warning" role="alert">
                            <i class="fas fa-lock"></i> Você não tem permissão para alterar as configurações.
                        </div>

                        <?php endif; ?>

                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<!-- Bootstrap 5 -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Variáveis PHP para JavaScript -->
<script>
    const PODE_ALTERAR = <?php echo $pode_alterar ? 'true' : 'false'; ?>;
    const PODE_DELETAR = false; // Sempre false
</script>

<!-- JavaScript do WhatsApp -->
<script src="js/whatsapp.js"></script>

<script>
// Navegação entre tabs
$(document).ready(function() {
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

    // Carrega status inicial
    verificarStatusInstancia();
});
</script>

</body>
</html>
