/**
 * ============================================================================
 * SIDEBAR.JS - Sistema de Navegação Centralizado
 * ============================================================================
 *
 * Este módulo gerencia todo o sidebar do sistema Ecletech, incluindo:
 * - Renderização dinâmica do HTML do sidebar
 * - Controle de permissões de acesso
 * - Gerenciamento de dados do usuário
 * - Eventos de interação (toggle, dropdown, etc)
 *
 * Autor: Sistema Ecletech
 * Última atualização: 2025
 * ============================================================================
 */


/* ============================================================================
   SIDEBAR RENDERER - Renderiza o HTML do Sidebar Dinamicamente
   ============================================================================ */

/**
 * Classe responsável por renderizar todo o HTML do sidebar
 */
class SidebarRenderer {
    constructor() {
        this.menuStructure = this.getMenuStructure();
    }

    /**
     * Define a estrutura completa do menu de navegação
     * @returns {Object} Estrutura do menu
     */
    getMenuStructure() {
        return {
            header: {
                logo: 'E',
                title: 'Ecletech'
            },
            sections: [
                {
                    title: 'Principal',
                    items: [
                        {
                            type: 'link',
                            label: 'Home',
                            icon: 'fa-home',
                            href: './home.html',
                            permission: null
                        },
                        {
                            type: 'link',
                            label: 'Frotas',
                            icon: 'fa-car',
                            href: './frotas.html',
                            permission: 'frota.visualizar'
                        }
                    ]
                },
                {
                    title: 'Cadastros',
                    items: [
                        {
                            type: 'submenu',
                            label: 'Cadastros Gerais',
                            icon: 'fa-database',
                            id: 'cadastros-submenu',
                            items: [
                                {
                                    label: 'Estados',
                                    icon: 'fa-map',
                                    href: './estados.html',
                                    permission: 'estado.visualizar'
                                },
                                {
                                    label: 'Cidades',
                                    icon: 'fa-city',
                                    href: './cidades.html',
                                    permission: 'cidade.visualizar'
                                },
                                {
                                    label: 'Situações de Vendas',
                                    icon: 'fa-chart-line',
                                    href: './situacoes_vendas.html',
                                    permission: 'situacao_venda.visualizar'
                                },
                                {
                                    label: 'Tipos de Endereços',
                                    icon: 'fa-map-marker-alt',
                                    href: './tipos_enderecos.html',
                                    permission: 'tipo_endereco.visualizar'
                                },
                                {
                                    label: 'Tipos de Contatos',
                                    icon: 'fa-phone',
                                    href: './tipos_contatos.html',
                                    permission: 'tipo_contato.visualizar'
                                },
                                {
                                    label: 'Grupos de Produtos',
                                    icon: 'fa-boxes',
                                    href: './grupos_produtos.html',
                                    permission: 'grupos_produtos.visualizar'
                                }
                            ]
                        }
                    ]
                },
                {
                    title: 'Gestão',
                    items: [
                        {
                            type: 'link',
                            label: 'Colaboradores',
                            icon: 'fa-users',
                            href: './colaboradores.html',
                            permission: 'colaboradores.visualizar'
                        },
                        {
                            type: 'link',
                            label: 'Gestão de Acessos',
                            icon: 'fa-user-shield',
                            href: './colaborador_management.html',
                            permission: 'permissoes.visualizar,roles.visualizar'
                        },
                        {
                            type: 'link',
                            label: 'Informações da Loja',
                            icon: 'fa-store',
                            href: './loja.html',
                            permission: 'loja.visualizar'
                        },
                        {
                            type: 'link',
                            label: 'Contas Bancárias',
                            icon: 'fa-university',
                            href: './contas_bancarias.html',
                            permission: 'conta_bancaria.visualizar'
                        }
                    ]
                },
                {
                    title: 'Administração',
                    items: [
                        {
                            type: 'submenu',
                            label: 'Ferramentas',
                            icon: 'fa-tools',
                            id: 'admin-submenu',
                            items: [
                                {
                                    label: 'AWS S3',
                                    icon: 'fab fa-aws',
                                    href: './s3.html',
                                    permission: 's3.acessar'
                                },
                                {
                                    label: 'Proteção Brute Force',
                                    icon: 'fa-shield-alt',
                                    href: './brute_force.html',
                                    permission: 'auditoria.visualizar'
                                },
                                {
                                    label: 'Auditoria',
                                    icon: 'fa-clipboard-list',
                                    href: './auditoria.html',
                                    permission: 'auditoria.visualizar'
                                }
                            ]
                        }
                    ]
                }
            ],
            footer: {
                userDropdownItems: [
                    {
                        label: 'Meu Perfil',
                        icon: 'fa-user',
                        href: './perfil.html'
                    },
                    {
                        label: 'Configurações',
                        icon: 'fa-cog',
                        href: './configuracoes.html'
                    }
                ]
            }
        };
    }

    /**
     * Renderiza o sidebar completo no container especificado
     * @param {string} containerId - ID do container onde o sidebar será renderizado
     */
    render(containerId = 'sidebar-container') {
        const container = document.getElementById(containerId);
        if (!container) {
            console.warn(`Container #${containerId} não encontrado. Sidebar não será renderizado.`);
            return;
        }

        // Gera o HTML completo
        const sidebarHTML = this.generateHTML();
        container.innerHTML = sidebarHTML;

        // Inicializa eventos após renderização
        this.initializeEvents();

        // Detecta e marca a página ativa
        this.markActivePage();

        console.log('[SidebarRenderer] Sidebar renderizado com sucesso');
    }

    /**
     * Gera o HTML completo do sidebar
     * @returns {string} HTML do sidebar
     */
    generateHTML() {
        return `
            <div class="sidebar" id="sidebar">
                ${this.generateHeader()}
                ${this.generateNav()}
                ${this.generateFooter()}
            </div>
            <div class="sidebar-overlay" id="sidebarOverlay"></div>
        `;
    }

    /**
     * Gera o HTML do header do sidebar
     * @returns {string} HTML do header
     */
    generateHeader() {
        const { logo, title } = this.menuStructure.header;
        return `
            <div class="sidebar-header">
                <div class="logo">${logo}</div>
                <h2>${title}</h2>
            </div>
        `;
    }

    /**
     * Gera o HTML da navegação do sidebar
     * @returns {string} HTML da navegação
     */
    generateNav() {
        const sectionsHTML = this.menuStructure.sections.map(section =>
            this.generateSection(section)
        ).join('');

        return `
            <div class="sidebar-scroll">
                <nav class="sidebar-nav">
                    ${sectionsHTML}
                </nav>
            </div>
        `;
    }

    /**
     * Gera o HTML de uma seção de navegação
     * @param {Object} section - Dados da seção
     * @returns {string} HTML da seção
     */
    generateSection(section) {
        const itemsHTML = section.items.map(item => {
            if (item.type === 'submenu') {
                return this.generateSubmenu(item);
            } else {
                return this.generateLink(item);
            }
        }).join('');

        return `
            <div class="nav-section">
                <div class="nav-section-title">${section.title}</div>
                ${itemsHTML}
            </div>
        `;
    }

    /**
     * Gera o HTML de um link de navegação
     * @param {Object} item - Dados do link
     * @returns {string} HTML do link
     */
    generateLink(item) {
        const permissionAttr = item.permission ? `data-permission="${item.permission}"` : '';

        return `
            <a href="${item.href}" ${permissionAttr}>
                <div class="menu-item-content">
                    <span class="icon"><i class="fas ${item.icon}"></i></span>
                    <span>${item.label}</span>
                </div>
            </a>
        `;
    }

    /**
     * Gera o HTML de um submenu
     * @param {Object} submenu - Dados do submenu
     * @returns {string} HTML do submenu
     */
    generateSubmenu(submenu) {
        const submenuItemsHTML = submenu.items.map(item => {
            const permissionAttr = item.permission ? `data-permission="${item.permission}"` : '';
            const iconClass = item.icon.startsWith('fab') ? item.icon : `fas ${item.icon}`;

            return `
                <a href="${item.href}" ${permissionAttr}>
                    <div class="menu-item-content">
                        <span class="icon"><i class="${iconClass}"></i></span>
                        <span>${item.label}</span>
                    </div>
                </a>
            `;
        }).join('');

        return `
            <button class="submenu-toggle" data-submenu-id="${submenu.id}">
                <div class="menu-item-content">
                    <span class="icon"><i class="fas ${submenu.icon}"></i></span>
                    <span>${submenu.label}</span>
                </div>
                <i class="fas fa-chevron-down submenu-arrow"></i>
            </button>
            <div class="submenu" id="${submenu.id}">
                ${submenuItemsHTML}
            </div>
        `;
    }

    /**
     * Gera o HTML do footer do sidebar (user info e dropdown)
     * @returns {string} HTML do footer
     */
    generateFooter() {
        const dropdownItemsHTML = this.menuStructure.footer.userDropdownItems.map(item => `
            <a href="${item.href}" class="user-dropdown-item">
                <i class="fas ${item.icon}"></i>
                <span>${item.label}</span>
            </a>
        `).join('');

        return `
            <div class="sidebar-footer">
                <div class="user-info-sidebar" id="userInfoDropdown">
                    <div class="user-avatar" id="userAvatar">U</div>
                    <div class="user-details">
                        <div class="user-name" id="userNameSidebar">Carregando...</div>
                        <div class="user-role" id="userRoleSidebar">Usuário</div>
                    </div>
                    <i class="fas fa-chevron-down user-dropdown-arrow"></i>

                    <div class="user-dropdown-menu" id="userDropdownMenu">
                        ${dropdownItemsHTML}
                        <div class="user-dropdown-item" style="border-top: 1px solid var(--border-light); margin-top: 4px; padding-top: 14px;"></div>
                        <button class="user-dropdown-item danger" id="logoutBtnSidebar">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Sair da Conta</span>
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Inicializa todos os eventos do sidebar
     */
    initializeEvents() {
        this.initSubmenuToggles();
        this.initUserDropdown();
        this.initLogout();
        this.initSidebarToggle();
        this.initActiveSubmenuExpand();
    }

    /**
     * Inicializa os toggles de submenu
     */
    initSubmenuToggles() {
        const toggles = document.querySelectorAll('.submenu-toggle');

        toggles.forEach(toggle => {
            toggle.addEventListener('click', (e) => {
                const submenuId = toggle.getAttribute('data-submenu-id');
                const submenu = document.getElementById(submenuId);

                if (!submenu) return;

                // Fecha outros submenus
                document.querySelectorAll('.submenu').forEach(sm => {
                    if (sm.id !== submenuId) {
                        sm.classList.remove('open');
                    }
                });
                document.querySelectorAll('.submenu-toggle').forEach(btn => {
                    if (btn !== toggle) {
                        btn.classList.remove('open');
                    }
                });

                // Toggle do submenu atual
                const isOpen = submenu.classList.contains('open');
                if (!isOpen) {
                    submenu.classList.add('open');
                    toggle.classList.add('open');
                } else {
                    submenu.classList.remove('open');
                    toggle.classList.remove('open');
                }
            });
        });
    }

    /**
     * Inicializa o dropdown de usuário
     */
    initUserDropdown() {
        const userInfoDropdown = document.getElementById('userInfoDropdown');
        const userDropdownMenu = document.getElementById('userDropdownMenu');

        if (!userInfoDropdown || !userDropdownMenu) return;

        userInfoDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
            userInfoDropdown.classList.toggle('open');
            userDropdownMenu.classList.toggle('show');
        });

        // Fecha dropdown ao clicar fora
        document.addEventListener('click', () => {
            userInfoDropdown.classList.remove('open');
            userDropdownMenu.classList.remove('show');
        });

        // Previne fechamento ao clicar dentro do dropdown
        userDropdownMenu.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    /**
     * Inicializa o botão de logout
     */
    initLogout() {
        const logoutBtn = document.getElementById('logoutBtnSidebar');

        if (logoutBtn) {
            logoutBtn.addEventListener('click', async () => {
                if (confirm('Tem certeza que deseja sair?')) {
                    await AuthAPI.logout();
                }
            });
        }
    }

    /**
     * Inicializa o toggle do sidebar (mobile e desktop)
     */
    initSidebarToggle() {
        // Cria ou obtém o botão de toggle no header (se existir)
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        const mainContent = document.getElementById('mainContent');

        if (!menuToggle || !sidebar) return;

        // Função de toggle
        const toggleSidebar = () => {
            const isMobile = window.innerWidth <= 768;

            if (isMobile) {
                sidebar.classList.toggle('open');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.toggle('show');
                }
            } else {
                sidebar.classList.toggle('closed');
                if (mainContent) {
                    mainContent.classList.toggle('expanded');
                }
            }
        };

        // Event listeners
        menuToggle.addEventListener('click', toggleSidebar);

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', toggleSidebar);
        }

        // Fecha sidebar mobile ao redimensionar para desktop
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('open');
                if (sidebarOverlay) {
                    sidebarOverlay.classList.remove('show');
                }
            }
        });

        // Expõe função globalmente para uso em outros scripts
        window.toggleSidebar = toggleSidebar;
    }

    /**
     * Expande submenu que contém a página ativa
     */
    initActiveSubmenuExpand() {
        const activeLink = document.querySelector('.sidebar-nav a.active');

        if (activeLink) {
            const submenu = activeLink.closest('.submenu');
            if (submenu) {
                submenu.classList.add('open');
                const toggleButton = document.querySelector(`[data-submenu-id="${submenu.id}"]`);
                if (toggleButton) {
                    toggleButton.classList.add('open');
                }
            }
        }
    }

    /**
     * Marca a página ativa no menu baseado na URL atual
     */
    markActivePage() {
        const currentPage = window.location.pathname.split('/').pop() || 'home.html';
        const links = document.querySelectorAll('.sidebar-nav a[href]');

        links.forEach(link => {
            const href = link.getAttribute('href');
            if (href && href.includes(currentPage)) {
                link.classList.add('active');
            }
        });
    }
}


/* ============================================================================
   SIDEBAR MANAGER - Gerencia Permissões de Acesso
   ============================================================================ */

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
 * <a href="./colaborador.html" data-permission="colaborador.visualizar">
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
        // Exemplo: "colaborador.visualizar,colaborador.editar"
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
                        const toggle = secao.querySelector(`button.submenu-toggle[data-submenu-id="${submenu.id}"]`);
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


/* ============================================================================
   USER DATA MANAGER - Gerencia Dados do Usuário no Sidebar
   ============================================================================ */

/**
 * Gerenciador de Dados do Usuário no Sidebar
 */
const UserDataManager = {
    /**
     * Exibe os dados do usuário no sidebar
     * @param {Object} user - Dados do usuário
     */
    showUserData(user) {
        if (!user) return;

        const nome = user.nome || user.name || user.email?.split('@')[0] || 'Usuário';

        // Atualiza avatar
        const userAvatarEl = document.getElementById('userAvatar');
        if (userAvatarEl) {
            const initial = nome.charAt(0).toUpperCase();
            userAvatarEl.textContent = initial;
        }

        // Atualiza nome do usuário
        const userNameSidebarEl = document.getElementById('userNameSidebar');
        if (userNameSidebarEl) {
            userNameSidebarEl.textContent = nome;
        }

        // Atualiza tipo/role do usuário
        const userRoleSidebarEl = document.getElementById('userRoleSidebar');
        if (userRoleSidebarEl) {
            userRoleSidebarEl.textContent = user.tipo_usuario || 'Usuário';
        }
    },

    /**
     * Carrega os dados do usuário da API
     */
    async loadUserData() {
        try {
            if (!AuthAPI.isAuthenticated()) {
                window.location.href = './auth.html';
                return;
            }

            const user = await AuthAPI.getMe();

            if (user) {
                this.showUserData(user);
            }
        } catch (error) {
            console.error('Erro ao carregar dados do usuário:', error);
        }
    }
};


/* ============================================================================
   INICIALIZAÇÃO AUTOMÁTICA
   ============================================================================ */

/**
 * Inicializa o sidebar quando o DOM estiver pronto
 */
function initSidebar() {
    // 1. Renderiza o sidebar
    const renderer = new SidebarRenderer();
    renderer.render();

    // 2. Aplica permissões
    SidebarManager.init();

    // 3. Carrega dados do usuário
    UserDataManager.loadUserData();
}

// Auto-inicialização
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initSidebar);
} else {
    // DOM já está pronto
    initSidebar();
}


/* ============================================================================
   EXPORTS GLOBAIS
   ============================================================================ */

/**
 * Expõe módulos globalmente para uso em outros scripts
 */
window.SidebarRenderer = SidebarRenderer;
window.SidebarManager = SidebarManager;
window.UserDataManager = UserDataManager;

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

/**
 * Função global toggleSubmenu (mantida para compatibilidade com código antigo)
 * @deprecated Use SidebarRenderer que gerencia automaticamente os toggles
 */
window.toggleSubmenu = function(submenuId, button) {
    const submenu = document.getElementById(submenuId);
    if (!submenu) return;

    const isOpen = submenu.classList.contains('open');

    // Fecha outros submenus
    document.querySelectorAll('.submenu').forEach(sm => {
        sm.classList.remove('open');
    });
    document.querySelectorAll('.submenu-toggle').forEach(btn => {
        btn.classList.remove('open');
    });

    // Abre o submenu clicado
    if (!isOpen) {
        submenu.classList.add('open');
        button.classList.add('open');
    }
};
