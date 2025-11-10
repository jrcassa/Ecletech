const AuthAPI = {
    async login(email, senha) {
        try {
            const response = await API.post('/login', {
                email,
                senha
            });

            if (response.sucesso) {
                // Token agora é enviado como cookie httpOnly pelo servidor
                // Não precisa mais ser armazenado no localStorage

                const usuario = response.dados?.usuario ||
                               response.dados?.user ||
                               response.usuario ||
                               response.user;

                if (usuario) {
                    API.setUser(usuario);
                }

                await API.fetchCsrfToken();

                API.showSuccess('Login realizado com sucesso!');
                return response;
            }

            throw new Error(response.erro || 'Erro ao fazer login');

        } catch (error) {
            if (error.status === 403 && error.data?.email_nao_verificado) {
                localStorage.setItem('email_pendente', email);
                window.location.href = 'verificar-email-pendente.html';
                throw error;
            }

            if (error.data && error.data.erro) {
                API.showError(error.data.erro);
            } else if (error.message) {
                API.showError(error.message);
            }
            throw error;
        }
    },

    loginWithGoogle() {
        window.location.href = API.baseURL + '/auth/google';
    },

    async register(dados) {
        try {
            // Adicionar dados de tracking de afiliado se existirem
            if (window.AffiliateTracking && window.AffiliateTracking.hasActiveTracking()) {
                const trackingData = window.AffiliateTracking.getData();
                if (trackingData) {
                    dados.codigo_afiliado_referencia = trackingData.codigo_afiliado;
                    dados.codigo_rastreamento = trackingData.codigo_rastreamento;
                }
            }

            const response = await API.post('/register', dados);

            if (response.sucesso) {
                localStorage.setItem('email_pendente', dados.email);
                API.showSuccess('Conta criada! Verifique seu email.');

                setTimeout(() => {
                    window.location.href = './verificar-email-pendente.html';
                }, 1500);

                return response;
            }

            throw new Error(response.erro || 'Erro ao registrar usuário');

        } catch (error) {
            if (error.data && error.data.erro) {
                API.showError(error.data.erro);
            } else if (error.message) {
                API.showError(error.message);
            }
            throw error;
        }
    },

    async reenviarValidacao(email) {
        try {
            const response = await API.post('/resend-verification', { email });

            if (response.sucesso) {
                API.showSuccess('Email de validação reenviado!');
                return response;
            }

            throw new Error(response.erro || 'Erro ao reenviar email');

        } catch (error) {
            if (error.data && error.data.erro) {
                API.showError(error.data.erro);
            } else if (error.message) {
                API.showError(error.message);
            }
            throw error;
        }
    },

    async validarEmail(token) {
        try {
            const response = await API.get(`/verify-email?token=${token}`);

            if (response.sucesso) {
                API.showSuccess('Email verificado com sucesso!');
                return response;
            }

            throw new Error(response.erro || 'Erro ao validar email');

        } catch (error) {
            if (error.data && error.data.erro) {
                API.showError(error.data.erro);
            } else if (error.message) {
                API.showError(error.message);
            }
            throw error;
        }
    },

    async verificarEmailValidado() {
        try {
            const response = await API.get('/me');

            if (response.sucesso) {
                const user = response.dados?.usuario ||
                            response.dados?.user ||
                            response.dados ||
                            response.usuario ||
                            response.user;

                if (user) {
                    API.setUser(user);

                    if (user.email_validado != 1) {
                        const email = user.email;
                        API.deleteToken();
                        API.deleteUser();
                        API.deleteCsrfToken();
                        localStorage.setItem('email_pendente', email);
                        window.location.href = 'verificar-email-pendente.html';
                        return false;
                    }
                }
                return true;
            }

            return false;

        } catch (error) {
            if (error.status === 403 || (error.data && error.data.email_nao_verificado)) {
                const user = API.getUser();
                const email = user?.email;
                API.deleteToken();
                API.deleteUser();
                API.deleteCsrfToken();
                if (email) {
                    localStorage.setItem('email_pendente', email);
                }
                window.location.href = 'verificar-email-pendente.html';
                return false;
            }
            return false;
        }
    },

    async logout() {
        try {
            await API.post('/logout', {});
        } catch (error) {
            console.error('Erro ao fazer logout:', error);
        } finally {
            API.deleteToken();
            API.deleteUser();
            API.deleteCsrfToken();

            API.showSuccess('Logout realizado com sucesso!');
            window.location.href = './auth.html';
        }
    },

    async getMe() {
        try {
            const response = await API.get('/me');

            if (response.sucesso) {
                const user = response.dados?.usuario ||
                            response.dados?.user ||
                            response.dados ||
                            response.usuario ||
                            response.user;

                if (user) {
                    API.setUser(user);
                }
                return user;
            }

            throw new Error(response.erro || 'Erro ao buscar dados do usuário');

        } catch (error) {
            if (error.data && error.data.erro) {
                API.showError(error.data.erro);
            }
            throw error;
        }
    },

    async forgotPassword(email) {
        try {
            const response = await API.post('/forgot-password', { email });

            if (response.sucesso) {
                API.showSuccess('Email de recuperação enviado!');
                return response;
            }

            throw new Error(response.erro || 'Erro ao enviar email de recuperação');

        } catch (error) {
            if (error.data && error.data.erro) {
                API.showError(error.data.erro);
            } else if (error.message) {
                API.showError(error.message);
            }
            throw error;
        }
    },

    async resetPassword(token, novaSenha) {
        try {
            const response = await API.post('/reset-password', {
                token,
                nova_senha: novaSenha
            });

            if (response.sucesso) {
                API.showSuccess('Senha redefinida com sucesso!');
                return response;
            }

            throw new Error(response.erro || 'Erro ao redefinir senha');

        } catch (error) {
            if (error.data && error.data.erro) {
                API.showError(error.data.erro);
            } else if (error.message) {
                API.showError(error.message);
            }
            throw error;
        }
    },

    isAuthenticated() {
        return API.isAuthenticated();
    },

    async requireAuth() {
        if (!this.isAuthenticated()) {
            window.location.href = './auth.html';
            return false;
        }

        await API.ensureCsrfToken();

        const emailValidado = await this.verificarEmailValidado();
        if (!emailValidado) {
            return false;
        }

        return true;
    },

    redirectIfAuthenticated() {
        if (this.isAuthenticated()) {
            window.location.href = './home.html';
            return true;
        }
        return false;
    }
};

// Verificar autenticação em páginas protegidas
if (!window.location.pathname.includes('auth') &&
    !window.location.pathname.includes('validar-email') &&
    !window.location.pathname.includes('verificar-email-pendente') &&
    !window.location.pathname.includes('forgot-password') &&
    !window.location.pathname.includes('reset-password')) {
    if (AuthAPI.isAuthenticated()) {
        AuthAPI.verificarEmailValidado();
    }
}

window.AuthAPI = AuthAPI;
