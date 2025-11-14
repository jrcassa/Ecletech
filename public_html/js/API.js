const API = {
    baseURL: window.location.origin + '/public_html/api',
    userKey: 'user_data',
    csrfTokenKey: 'csrf_token',

    // Token agora é gerenciado via cookie httpOnly (não mais localStorage)
    // Cookies são enviados automaticamente pelo navegador

    // Métodos stub para backward compatibility (token agora é cookie httpOnly)
    getToken() {
        // Token está em cookie httpOnly - não acessível via JavaScript
        // Retornar null para indicar que não está disponível no lado do cliente
        return null;
    },

    setToken(token) {
        // Token é gerenciado pelo servidor via cookie httpOnly
        // Este método não faz nada, mas existe para compatibilidade
        console.warn('setToken() is deprecated: tokens are now managed via httpOnly cookies');
    },

    deleteToken() {
        // Token é gerenciado pelo servidor via cookie httpOnly
        // A limpeza do cookie deve ser feita pelo endpoint de logout
        console.warn('deleteToken() is deprecated: tokens are now managed via httpOnly cookies');
    },

    isAuthenticated() {
        // Verificar se há dados de usuário (indicador que está autenticado)
        // O cookie httpOnly é verificado pelo servidor
        return !!this.getUser();
    },

    getUser() {
        const userData = localStorage.getItem(this.userKey);
        return userData ? JSON.parse(userData) : null;
    },

    setUser(user) {
        const idioma = user.idioma || 'pt-BR';
        const oldIdioma = localStorage.getItem('language') || 'pt-BR';

        localStorage.setItem(this.userKey, JSON.stringify(user));
        localStorage.setItem('language', idioma);

        // Só muda idioma se for diferente E se i18next estiver carregado
        if (oldIdioma !== idioma && typeof i18next !== 'undefined' && typeof window.updateContent === 'function') {
            i18next.changeLanguage(idioma).then(() => {
                window.updateContent();
            });
        }
    },

    deleteUser() {
        localStorage.removeItem(this.userKey);
    },

    getCsrfToken() {
        // Mudança de sessionStorage para localStorage para compartilhar entre tabs
        return localStorage.getItem(this.csrfTokenKey);
    },

    setCsrfToken(token) {
        // Mudança de sessionStorage para localStorage para compartilhar entre tabs
        localStorage.setItem(this.csrfTokenKey, token);
    },

    deleteCsrfToken() {
        // Mudança de sessionStorage para localStorage para compartilhar entre tabs
        localStorage.removeItem(this.csrfTokenKey);
    },

    async fetchCsrfToken() {
        try {
            const url = `${this.baseURL}/auth/csrf-token`;
            const headers = {
                'Content-Type': 'application/json'
            };

            // Cookie é enviado automaticamente pelo navegador
            const response = await fetch(url, {
                method: 'GET',
                headers,
                credentials: 'include' // IMPORTANTE: envia cookies
            });

            if (response.ok) {
                const data = await response.json();
                if (data.sucesso && data.dados && data.dados.csrf_token) {
                    this.setCsrfToken(data.dados.csrf_token);
                    return data.dados.csrf_token;
                }
            }

            return null;
        } catch (error) {
            return null;
        }
    },

    async ensureCsrfToken() {
        let token = this.getCsrfToken();

        if (!token) {
            token = await this.fetchCsrfToken();
        }

        return token;
    },

    async request(endpoint, options = {}) {
        const url = `${this.baseURL}${endpoint}`;

        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };

        // Token JWT agora vem automaticamente via cookie httpOnly
        // Não precisa mais adicionar Authorization header

        const method = options.method || 'GET';

        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method.toUpperCase())) {
            const needsCsrf = !endpoint.includes('/auth/csrf-token') &&
                !endpoint.includes('/auth/login') &&
                endpoint !== '/register' &&
                !endpoint.includes('/verify-email');

            if (needsCsrf) {
                const csrfToken = await this.ensureCsrfToken();

                if (csrfToken) {
                    headers['X-CSRF-Token'] = csrfToken;
                }
            }
        }

        const config = {
            ...options,
            method,
            headers,
            credentials: 'include' // IMPORTANTE: envia cookies automaticamente
        };

        if (config.body && !(config.body instanceof FormData)) {
            config.body = JSON.stringify(config.body);
        }

        try {
            const response = await fetch(url, config);

            const newCsrfToken = response.headers.get('X-New-CSRF-Token');
            if (newCsrfToken) {
                this.setCsrfToken(newCsrfToken);
            }

            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            if (!response.ok) {
                const error = {
                    status: response.status,
                    statusText: response.statusText,
                    data
                };

                if (response.status === 404 && data && !data.sucesso && data.erro === "Usuário não encontrado") {
                    AuthAPI.logout();
                    return false;
                }

                // Retry automático para erros CSRF
                if (response.status === 403 && data && data.erro &&
                    (data.erro.includes('CSRF') || data.erro.includes('csrf'))) {
                    this.deleteCsrfToken();

                    // Tentar novamente uma vez com novo token
                    const retryAttempt = options._csrfRetry || 0;
                    if (retryAttempt < 1) {
                        console.log('Erro CSRF detectado. Tentando novamente com novo token...');
                        const newOptions = { ...options, _csrfRetry: retryAttempt + 1 };
                        return this.request(endpoint, newOptions);
                    }
                }

                // Erros 422 (validação) não mostram toast aqui - deixa o código chamador tratar
                if (response.status === 422) {
                    // Retornar os dados para que o código chamador possa tratar os erros de validação
                    return data;
                }

                if (data && data.erro) {
                    this.showError(data.erro);
                } else if (data && data.mensagem) {
                    this.showError(data.mensagem);
                } else {
                    this.showError(this.handleError(error));
                }

                throw error;
            }

            return data;

        } catch (error) {
            if (error.status === 401 && !window.location.pathname.includes('auth')) {
                // Cookie httpOnly será limpo pelo servidor no logout
                // Limpar apenas dados locais
                this.deleteUser();
                this.deleteCsrfToken();
                this.showError('Sessão expirada. Você será redirecionado para o login.');
                setTimeout(() => {
                    window.location.href = './auth.html';
                }, 2000);
            }

            throw error;
        }
    },

    get(endpoint, options = {}) {
        return this.request(endpoint, {
            ...options,
            method: 'GET'
        });
    },

    post(endpoint, body, options = {}) {
        return this.request(endpoint, {
            ...options,
            method: 'POST',
            body
        });
    },

    put(endpoint, body, options = {}) {
        return this.request(endpoint, {
            ...options,
            method: 'PUT',
            body
        });
    },

    patch(endpoint, body, options = {}) {
        return this.request(endpoint, {
            ...options,
            method: 'PATCH',
            body
        });
    },

    delete(endpoint, options = {}) {
        return this.request(endpoint, {
            ...options,
            method: 'DELETE'
        });
    },

    async postFormData(endpoint, formData, options = {}) {
        const url = `${this.baseURL}${endpoint}`;

        const headers = {
            ...options.headers
        };
        // NÃO adicionar Content-Type para FormData - o browser define automaticamente com boundary

        // Token JWT agora vem automaticamente via cookie httpOnly

        // Adicionar CSRF token para uploads
        const csrfToken = await this.ensureCsrfToken();
        if (csrfToken) {
            headers['X-CSRF-Token'] = csrfToken;
        }

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers,
                body: formData, // FormData não precisa de JSON.stringify
                credentials: 'include' // IMPORTANTE: envia cookies
            });

            const newCsrfToken = response.headers.get('X-New-CSRF-Token');
            if (newCsrfToken) {
                this.setCsrfToken(newCsrfToken);
            }

            let data;
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = await response.text();
            }

            if (!response.ok) {
                const error = {
                    status: response.status,
                    statusText: response.statusText,
                    data
                };

                // Retry automático para erros CSRF
                if (response.status === 403 && data && data.erro &&
                    (data.erro.includes('CSRF') || data.erro.includes('csrf'))) {
                    this.deleteCsrfToken();

                    // Tentar novamente uma vez com novo token
                    const retryAttempt = options._csrfRetry || 0;
                    if (retryAttempt < 1) {
                        console.log('Erro CSRF detectado em FormData. Tentando novamente com novo token...');
                        const newOptions = { ...options, _csrfRetry: retryAttempt + 1 };
                        return this.postFormData(endpoint, formData, newOptions);
                    }
                }

                throw error;
            }

            return data;

        } catch (error) {
            if (error.status === 401 && !window.location.pathname.includes('auth')) {
                // Cookie httpOnly será limpo pelo servidor no logout
                // Limpar apenas dados locais
                this.deleteUser();
                this.deleteCsrfToken();
                this.showError('Sessão expirada. Você será redirecionado para o login.');
                setTimeout(() => {
                    window.location.href = './auth.html';
                }, 2000);
            }

            throw error;
        }
    },

    handleError(error) {
        if (error.data && error.data.erro) {
            return error.data.erro;
        }

        if (error.data && error.data.mensagem) {
            return error.data.mensagem;
        }

        switch (error.status) {
            case 400:
                return 'Dados inválidos. Verifique os campos e tente novamente.';
            case 401:
                return 'Não autorizado. Faça login novamente.';
            case 403:
                return 'Acesso negado.';
            case 404:
                return 'Recurso não encontrado.';
            case 422:
                return 'Erro de validação. Verifique os campos.';
            case 500:
                return 'Erro no servidor. Tente novamente mais tarde.';
            default:
                return 'Erro ao processar requisição. Tente novamente.';
        }
    },

    createToast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) {
            // Fallback para alert se não houver container
            alert(message);
            return;
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
            <i class="fas ${icons[type] || icons.info} toast-icon"></i>
            <div class="toast-message">${message}</div>
            <button class="toast-close">&times;</button>
        `;

        container.appendChild(toast);

        // Animar entrada
        setTimeout(() => toast.classList.add('show'), 10);

        // Fechar ao clicar no X
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => this.closeToast(toast));

        // Auto fechar após 5 segundos
        setTimeout(() => this.closeToast(toast), 5000);
    },

    closeToast(toast) {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300);
    },

    showSuccess(message) {
        this.createToast(message, 'success');
    },

    showError(message) {
        this.createToast(message, 'error');
    },

    showWarning(message) {
        this.createToast(message, 'warning');
    },

    showInfo(message) {
        this.createToast(message, 'info');
    },

    showValidationErrors(errors) {
        if (!errors || typeof errors !== 'object') return;

        // Mostrar cada erro de validação
        Object.entries(errors).forEach(([field, messages]) => {
            const errorMessages = Array.isArray(messages) ? messages : [messages];
            errorMessages.forEach(msg => {
                this.showError(msg);
            });
        });
    }
};

// Sincronização automática de tokens CSRF entre tabs
window.addEventListener('storage', (event) => {
    // Detecta quando o token CSRF foi atualizado em outra tab
    if (event.key === API.csrfTokenKey && event.newValue !== event.oldValue) {
        console.log('Token CSRF atualizado em outra tab. Sincronizando...');
        // O token já foi atualizado no localStorage automaticamente
        // Este evento apenas notifica para possíveis ações adicionais
    }
});

document.addEventListener('DOMContentLoaded', async () => {
    if (API.isAuthenticated()) {
        await API.fetchCsrfToken();
    }
});

window.API = API;
