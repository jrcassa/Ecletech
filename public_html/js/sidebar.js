/**
 * Sidebar Manager - Gerencia visibilidade dos itens do menu baseado em permissões
 *
 * Este módulo centraliza a lógica de controle de acesso ao menu lateral.
 * Itens de menu são marcados com o atributo data-permission e são
 * automaticamente ocultados se o usuário não tiver a permissão necessária.
 *
 * Uso:
 * 1. Adicionar data-permission="codigo.permissao" nos elementos do menu
 * 2. Incluir este script nas páginas que possuem sidebar
 * 3. O script inicializa automaticamente após o carregamento do DOM
 *
 * Exemplo:
 * <a href="./colaboradores.html" data-permission="colaboradores.visualizar">
 *     Colaboradores
 * </a>
 */

const SidebarManager = {
    /**
     * Estado da aplicação
     */
    state: {
        permissoes: [],
        carregado: false,
        erro: null
    },

    /**
     * Cache dos elementos do menu
     */
    cache: {
        menuItems: [],
        sections: []
    },

    /**
     * Configurações
     */
    config: {
        atributoPermissao: 'data-permission',
        ocultarComDisplay: true, // Se true, usa display:none, senão remove do DOM
        logDebug: false // Ativa logs para debug
    },

    /**
     * Inicializa o gerenciador do sidebar
     */
    async init() {
        try {
            this.log('Inicializando SidebarManager...');

            // Verifica se usuário está autenticado
            if (!AuthAPI.isAuthenticated()) {
                this.log('Usuário não autenticado, redirecionando...');
                window.location.href = './auth.html';
                return;
            }

            // Carrega permissões do usuário
            await this.carregarPermissoes();

            // Aplica permissões ao menu
            this.aplicarPermissoes();

            // Remove seções vazias
            this.limparSecoesVazias();

            // Marca como carregado
            this.state.carregado = true;

            this.log('SidebarManager inicializado com sucesso', {
                permissoes: this.state.permissoes.length,
                itensVerificados: this.cache.menuItems.length
            });

        } catch (error) {
            console.error('Erro ao inicializar SidebarManager:', error);
            this.state.erro = error;

            // Em caso de erro, mostra todos os itens (fail-open)
            // Isso evita que erros de rede bloqueiem completamente o sistema
            this.log('Erro ao carregar permissões, mostrando todos os itens');
        }
    },

    /**
     * Carrega as permissões do usuário da API
     */
    async carregarPermissoes() {
        try {
            this.log('Carregando permissões do usuário...');

            const response = await API.get('/permissoes/usuario');

            if (response.sucesso && response.dados) {
                this.state.permissoes = response.dados.permissoes || [];
                // Salva permissões globalmente para uso em outros módulos
                window.permissoesUsuario = response.dados.permissoes || [];
                this.log('Permissões carregadas:', this.state.permissoes);
            } else {
                throw new Error('Resposta inválida da API');
            }

        } catch (error) {
            console.error('Erro ao carregar permissões:', error);

            // Se erro 401, redireciona para login
            if (error.status === 401) {
                API.deleteUser();
                window.location.href = './auth.html';
                return;
            }

            throw error;
        }
    },

    /**
     * Aplica as permissões aos itens do menu
     */
    aplicarPermissoes() {
        this.log('Aplicando permissões ao menu...');

        // Seleciona todos os elementos com data-permission
        const elementosComPermissao = document.querySelectorAll(`[${this.config.atributoPermissao}]`);

        this.log(`Encontrados ${elementosComPermissao.length} elementos com permissões`);

        elementosComPermissao.forEach(elemento => {
            const permissaoRequerida = elemento.getAttribute(this.config.atributoPermissao);

            // Verifica se usuário tem a permissão
            const temPermissao = this.verificarPermissao(permissaoRequerida);

            // Guarda referência no cache
            this.cache.menuItems.push({
                elemento,
                permissao: permissaoRequerida,
                visivel: temPermissao
            });

            // Aplica visibilidade
            if (temPermissao) {
                this.mostrarElemento(elemento);
                this.log(`✓ Mostrando: ${permissaoRequerida}`, elemento);
            } else {
                this.ocultarElemento(elemento);
                this.log(`✗ Ocultando: ${permissaoRequerida}`, elemento);
            }
        });
    },

    /**
     * Verifica se o usuário tem uma permissão específica
     * Suporta múltiplas permissões separadas por vírgula (lógica OR)
     *
     * @param {string} permissaoRequerida - Código da permissão ou lista separada por vírgula
     * @returns {boolean}
     */
    verificarPermissao(permissaoRequerida) {
        if (!permissaoRequerida) {
            return true; // Se não há permissão requerida, permite acesso
        }

        // Suporta múltiplas permissões com lógica OR
        // Exemplo: "colaboradores.visualizar,colaboradores.editar"
        const permissoes = permissaoRequerida.split(',').map(p => p.trim());

        // Verifica se usuário tem PELO MENOS UMA das permissões
        return permissoes.some(permissao => {
            return this.state.permissoes.includes(permissao);
        });
    },

    /**
     * Verifica se o usuário tem TODAS as permissões (lógica AND)
     *
     * @param {string[]} permissoes - Array de códigos de permissão
     * @returns {boolean}
     */
    verificarTodasPermissoes(permissoes) {
        return permissoes.every(permissao => {
            return this.state.permissoes.includes(permissao);
        });
    },

    /**
     * Verifica se o usuário tem PELO MENOS UMA permissão (lógica OR)
     *
     * @param {string[]} permissoes - Array de códigos de permissão
     * @returns {boolean}
     */
    verificarAlgumaPermissao(permissoes) {
        return permissoes.some(permissao => {
            return this.state.permissoes.includes(permissao);
        });
    },

    /**
     * Oculta um elemento do menu
     */
    ocultarElemento(elemento) {
        if (this.config.ocultarComDisplay) {
            elemento.style.display = 'none';
        } else {
            elemento.remove();
        }
    },

    /**
     * Mostra um elemento do menu
     */
    mostrarElemento(elemento) {
        elemento.style.display = '';
    },

    /**
     * Remove seções vazias do menu
     * Uma seção é considerada vazia se não possui itens visíveis
     */
    limparSecoesVazias() {
        this.log('Limpando seções vazias...');

        const secoes = document.querySelectorAll('.nav-section');

        secoes.forEach(secao => {
            // Conta itens visíveis (links e buttons que não estão ocultos)
            const itensVisiveis = secao.querySelectorAll('a:not([style*="display: none"]), button.submenu-toggle:not([style*="display: none"])');

            // Se não há itens visíveis, oculta a seção
            if (itensVisiveis.length === 0) {
                this.ocultarElemento(secao);
                this.log('Seção vazia removida:', secao);
            } else {
                // Verifica submenus vazios
                const submenus = secao.querySelectorAll('.submenu');
                submenus.forEach(submenu => {
                    const linksVisiveis = submenu.querySelectorAll('a:not([style*="display: none"])');

                    if (linksVisiveis.length === 0) {
                        // Oculta o botão toggle do submenu também
                        const toggle = secao.querySelector(`button.submenu-toggle[onclick*="${submenu.id}"]`);
                        if (toggle) {
                            this.ocultarElemento(toggle);
                        }
                        this.ocultarElemento(submenu);
                        this.log('Submenu vazio removido:', submenu);
                    }
                });
            }

            this.cache.sections.push(secao);
        });
    },

    /**
     * Recarrega as permissões e reaplica ao menu
     * Útil se as permissões do usuário mudarem durante a sessão
     */
    async recarregar() {
        this.log('Recarregando permissões...');

        // Limpa cache
        this.cache.menuItems = [];
        this.cache.sections = [];

        // Recarrega
        await this.init();
    },

    /**
     * Retorna as permissões do usuário
     */
    obterPermissoes() {
        return [...this.state.permissoes];
    },

    /**
     * Verifica se o SidebarManager está carregado
     */
    estaCarregado() {
        return this.state.carregado;
    },

    /**
     * Helper para logs de debug
     */
    log(mensagem, ...args) {
        if (this.config.logDebug) {
            console.log(`[SidebarManager] ${mensagem}`, ...args);
        }
    },

    /**
     * Ativa/desativa modo debug
     */
    setDebug(ativo) {
        this.config.logDebug = ativo;
        this.log('Modo debug ' + (ativo ? 'ativado' : 'desativado'));
    },

    /**
     * Aguarda as permissões serem carregadas
     * Retorna uma Promise que resolve quando window.permissoesUsuario está disponível
     */
    async aguardarPermissoes(timeout = 5000) {
        const startTime = Date.now();

        while (!window.permissoesUsuario) {
            // Timeout para evitar loop infinito
            if (Date.now() - startTime > timeout) {
                console.error('Timeout ao aguardar permissões');
                return [];
            }

            // Aguarda 50ms antes de verificar novamente
            await new Promise(resolve => setTimeout(resolve, 50));
        }

        return window.permissoesUsuario;
    }
};

/**
 * Inicialização automática quando o DOM estiver pronto
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        SidebarManager.init();
    });
} else {
    // DOM já está pronto
    SidebarManager.init();
}

/**
 * Expõe globalmente para uso em outros scripts
 */
window.SidebarManager = SidebarManager;

/**
 * Helper global para aguardar permissões
 * Uso: const permissoes = await aguardarPermissoes();
 */
window.aguardarPermissoes = async function(timeout = 5000) {
    const startTime = Date.now();

    while (!window.permissoesUsuario) {
        // Timeout para evitar loop infinito
        if (Date.now() - startTime > timeout) {
            console.error('Timeout ao aguardar permissões');
            return [];
        }

        // Aguarda 50ms antes de verificar novamente
        await new Promise(resolve => setTimeout(resolve, 50));
    }

    return window.permissoesUsuario;
};
