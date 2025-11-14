/**
 * Script para gerenciamento do perfil do usuário
 */

// Elementos do DOM
const profileForm = document.getElementById('profileForm');
const passwordForm = document.getElementById('passwordForm');
const btnSalvar = document.getElementById('btnSalvar');
const btnCancelar = document.getElementById('btnCancelar');
const btnAlterarSenha = document.getElementById('btnAlterarSenha');

// Campos do perfil
const inputNome = document.getElementById('nome');
const inputEmail = document.getElementById('email');
const inputCelular = document.getElementById('celular');

// Campos de senha
const inputSenhaAtual = document.getElementById('senha_atual');
const inputNovaSenha = document.getElementById('nova_senha');
const inputConfirmarSenha = document.getElementById('confirmar_senha');

// Dados originais do usuário
let dadosOriginais = {};

/**
 * Carrega os dados do perfil do usuário
 */
async function carregarPerfil() {
    try {
        if (!AuthAPI.isAuthenticated()) {
            window.location.href = './auth.html';
            return;
        }

        const usuario = await AuthAPI.getMe();

        if (usuario) {
            dadosOriginais = { ...usuario };
            preencherFormulario(usuario);
        } else {
            showToast('Erro ao carregar perfil', 'error');
        }
    } catch (error) {
        console.error('Erro ao carregar perfil:', error);
        showToast('Erro ao carregar dados do perfil', 'error');
    }
}

/**
 * Preenche o formulário com os dados do usuário
 */
function preencherFormulario(usuario) {
    inputNome.value = usuario.nome || '';
    inputEmail.value = usuario.email || '';
    inputCelular.value = usuario.celular || '';
}

/**
 * Salva as alterações do perfil
 */
async function salvarPerfil(event) {
    event.preventDefault();

    // Validação básica
    const nome = inputNome.value.trim();
    const email = inputEmail.value.trim();
    const celular = inputCelular.value.trim();

    if (!nome) {
        showToast('O nome é obrigatório', 'error');
        inputNome.focus();
        return;
    }

    if (!email) {
        showToast('O e-mail é obrigatório', 'error');
        inputEmail.focus();
        return;
    }

    // Validação de email
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        showToast('E-mail inválido', 'error');
        inputEmail.focus();
        return;
    }

    // Desabilita o botão durante o envio
    btnSalvar.disabled = true;
    btnSalvar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';

    try {
        const dados = {
            nome,
            email,
            celular
        };

        const response = await API.put('/auth/perfil', dados);

        if (response.sucesso) {
            showToast('Perfil atualizado com sucesso!', 'success');

            // Atualiza os dados originais
            dadosOriginais = { ...response.dados };

            // Atualiza o nome no sidebar se existir
            const userNameSidebar = document.getElementById('userNameSidebar');
            if (userNameSidebar) {
                userNameSidebar.textContent = nome;
            }

            // Atualiza o avatar no sidebar
            const userAvatar = document.getElementById('userAvatar');
            if (userAvatar) {
                userAvatar.textContent = nome.charAt(0).toUpperCase();
            }
        } else {
            showToast(response.mensagem || 'Erro ao atualizar perfil', 'error');
        }
    } catch (error) {
        console.error('Erro ao salvar perfil:', error);
        showToast(error.message || 'Erro ao atualizar perfil', 'error');
    } finally {
        btnSalvar.disabled = false;
        btnSalvar.innerHTML = '<i class="fas fa-save"></i> Salvar Alterações';
    }
}

/**
 * Cancela as alterações e restaura os dados originais
 */
function cancelarAlteracoes() {
    if (confirm('Deseja realmente cancelar as alterações?')) {
        preencherFormulario(dadosOriginais);
        showToast('Alterações canceladas', 'info');
    }
}

/**
 * Altera a senha do usuário
 */
async function alterarSenha(event) {
    event.preventDefault();

    const senhaAtual = inputSenhaAtual.value;
    const novaSenha = inputNovaSenha.value;
    const confirmarSenha = inputConfirmarSenha.value;

    // Validações
    if (!senhaAtual) {
        showToast('A senha atual é obrigatória', 'error');
        inputSenhaAtual.focus();
        return;
    }

    if (!novaSenha) {
        showToast('A nova senha é obrigatória', 'error');
        inputNovaSenha.focus();
        return;
    }

    if (novaSenha.length < 8) {
        showToast('A nova senha deve ter no mínimo 8 caracteres', 'error');
        inputNovaSenha.focus();
        return;
    }

    // Validação de senha forte
    const senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&#])[A-Za-z\d@$!%*?&#]{8,}$/;
    if (!senhaRegex.test(novaSenha)) {
        showToast('A senha deve conter letras maiúsculas, minúsculas, números e caracteres especiais', 'error');
        inputNovaSenha.focus();
        return;
    }

    if (novaSenha !== confirmarSenha) {
        showToast('As senhas não coincidem', 'error');
        inputConfirmarSenha.focus();
        return;
    }

    if (senhaAtual === novaSenha) {
        showToast('A nova senha deve ser diferente da senha atual', 'error');
        inputNovaSenha.focus();
        return;
    }

    // Desabilita o botão durante o envio
    btnAlterarSenha.disabled = true;
    btnAlterarSenha.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Alterando...';

    try {
        const dados = {
            senha_atual: senhaAtual,
            nova_senha: novaSenha,
            confirmar_senha: confirmarSenha
        };

        const response = await API.post('/auth/alterar-senha', dados);

        if (response.sucesso) {
            showToast('Senha alterada com sucesso!', 'success');

            // Limpa o formulário
            passwordForm.reset();
        } else {
            showToast(response.mensagem || 'Erro ao alterar senha', 'error');
        }
    } catch (error) {
        console.error('Erro ao alterar senha:', error);
        showToast(error.message || 'Erro ao alterar senha', 'error');
    } finally {
        btnAlterarSenha.disabled = false;
        btnAlterarSenha.innerHTML = '<i class="fas fa-key"></i> Alterar Senha';
    }
}

/**
 * Máscara para celular
 */
function aplicarMascaraCelular(event) {
    let valor = event.target.value.replace(/\D/g, '');

    if (valor.length <= 11) {
        if (valor.length <= 10) {
            // (00) 0000-0000
            valor = valor.replace(/^(\d{2})(\d{4})(\d{0,4}).*/, '($1) $2-$3');
        } else {
            // (00) 00000-0000
            valor = valor.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3');
        }
        event.target.value = valor;
    }
}

/**
 * Mostra uma notificação toast
 */
function showToast(message, type = 'info') {
    // Verifica se já existe um container de toast
    let toastContainer = document.getElementById('toastContainer');

    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container';
        toastContainer.id = 'toastContainer';
        document.body.appendChild(toastContainer);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;

    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };

    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icons[type]}"></i>
        </div>
        <div class="toast-message">${message}</div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;

    toastContainer.appendChild(toast);

    // Adiciona a classe show após um pequeno delay para animação
    setTimeout(() => toast.classList.add('show'), 10);

    // Remove automaticamente após 5 segundos
    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    }, 5000);
}

// Event Listeners
profileForm.addEventListener('submit', salvarPerfil);
passwordForm.addEventListener('submit', alterarSenha);
btnCancelar.addEventListener('click', cancelarAlteracoes);
inputCelular.addEventListener('input', aplicarMascaraCelular);

// Carrega o perfil ao iniciar
document.addEventListener('DOMContentLoaded', carregarPerfil);
