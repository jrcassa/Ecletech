/**
 * ============================================================================
 * THEME.JS - Sistema de Gerenciamento de Tema (Claro/Escuro)
 * ============================================================================
 *
 * Este módulo gerencia o tema visual do sistema Ecletech.
 * - Alterna entre tema claro e escuro
 * - Salva preferência no localStorage
 * - Atualiza ícone do botão de tema
 * - Auto-inicialização no carregamento da página
 *
 * Autor: Sistema Ecletech
 * Última atualização: 2025
 * ============================================================================
 */

const ThemeManager = {
    /**
     * Elementos do DOM
     */
    elements: {
        themeToggle: null,
        themeIcon: null,
        htmlElement: document.documentElement
    },

    /**
     * Estado atual
     */
    currentTheme: 'light',

    /**
     * Inicializa o gerenciador de tema
     */
    init() {
        // Busca elementos do DOM
        this.elements.themeToggle = document.getElementById('themeToggle');

        if (!this.elements.themeToggle) {
            console.warn('[ThemeManager] Botão de tema não encontrado. Tema não será gerenciado.');
            return;
        }

        this.elements.themeIcon = this.elements.themeToggle.querySelector('i');

        // Carrega tema salvo
        this.loadTheme();

        // Adiciona event listener
        this.elements.themeToggle.addEventListener('click', () => this.toggleTheme());

        console.log('[ThemeManager] Inicializado com sucesso. Tema atual:', this.currentTheme);
    },

    /**
     * Carrega o tema salvo do localStorage
     */
    loadTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        this.applyTheme(savedTheme);
    },

    /**
     * Aplica um tema específico
     * @param {string} theme - 'light' ou 'dark'
     */
    applyTheme(theme) {
        this.currentTheme = theme;

        if (theme === 'dark') {
            this.elements.htmlElement.setAttribute('data-theme', 'dark');
            if (this.elements.themeIcon) {
                this.elements.themeIcon.classList.remove('fa-moon');
                this.elements.themeIcon.classList.add('fa-sun');
            }
        } else {
            this.elements.htmlElement.setAttribute('data-theme', 'light');
            if (this.elements.themeIcon) {
                this.elements.themeIcon.classList.remove('fa-sun');
                this.elements.themeIcon.classList.add('fa-moon');
            }
        }
    },

    /**
     * Alterna entre tema claro e escuro
     */
    toggleTheme() {
        const newTheme = this.currentTheme === 'light' ? 'dark' : 'light';

        this.applyTheme(newTheme);
        localStorage.setItem('theme', newTheme);

        console.log('[ThemeManager] Tema alterado para:', newTheme);
    },

    /**
     * Retorna o tema atual
     * @returns {string} 'light' ou 'dark'
     */
    getCurrentTheme() {
        return this.currentTheme;
    },

    /**
     * Define o tema programaticamente
     * @param {string} theme - 'light' ou 'dark'
     */
    setTheme(theme) {
        if (theme !== 'light' && theme !== 'dark') {
            console.error('[ThemeManager] Tema inválido:', theme);
            return;
        }

        this.applyTheme(theme);
        localStorage.setItem('theme', theme);
    }
};

/**
 * Auto-inicialização quando o DOM estiver pronto
 */
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        ThemeManager.init();
    });
} else {
    // DOM já está pronto
    ThemeManager.init();
}

/**
 * Expõe globalmente para uso em outros scripts
 */
window.ThemeManager = ThemeManager;
