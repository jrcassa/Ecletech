/**
 * Theme Toggle e User Dropdown
 * Funções compartilhadas entre todas as páginas
 */

// Inicialização do tema
function initTheme() {
    const themeToggle = document.getElementById('themeToggle');
    const themeIcon = themeToggle?.querySelector('i');
    const htmlElement = document.documentElement;

    // Carregar tema salvo do localStorage
    function loadTheme() {
        const savedTheme = localStorage.getItem('theme') || 'light';
        if (savedTheme === 'dark') {
            htmlElement.setAttribute('data-theme', 'dark');
            if (themeIcon) {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            }
        } else {
            htmlElement.setAttribute('data-theme', 'light');
            if (themeIcon) {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }
    }

    // Alternar tema
    function toggleTheme() {
        const currentTheme = htmlElement.getAttribute('data-theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';

        htmlElement.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);

        if (themeIcon) {
            if (newTheme === 'dark') {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }
    }

    // Event listener para o botão de tema
    if (themeToggle) {
        themeToggle.addEventListener('click', toggleTheme);
    }

    // Carregar tema ao iniciar
    loadTheme();
}

// Inicialização do dropdown do usuário
function initUserDropdown() {
    const userInfoDropdown = document.getElementById('userInfoDropdown');
    const userDropdownMenu = document.getElementById('userDropdownMenu');
    const logoutBtnSidebar = document.getElementById('logoutBtnSidebar');

    if (userInfoDropdown && userDropdownMenu) {
        userInfoDropdown.addEventListener('click', (e) => {
            e.stopPropagation();
            userInfoDropdown.classList.toggle('open');
            userDropdownMenu.classList.toggle('show');
        });

        document.addEventListener('click', () => {
            userInfoDropdown.classList.remove('open');
            userDropdownMenu.classList.remove('show');
        });

        userDropdownMenu.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    if (logoutBtnSidebar) {
        logoutBtnSidebar.addEventListener('click', async () => {
            if (confirm('Tem certeza que deseja sair?')) {
                await AuthAPI.logout();
            }
        });
    }
}

// Inicialização do toggle do sidebar
function initSidebarToggle() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const menuToggle = document.getElementById('menuToggle');
    const mainContent = document.getElementById('mainContent');

    function toggleSidebar() {
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            sidebar?.classList.toggle('open');
            sidebarOverlay?.classList.toggle('show');
        } else {
            sidebar?.classList.toggle('closed');
            mainContent?.classList.toggle('expanded');
        }
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', toggleSidebar);
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', toggleSidebar);
    }

    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            sidebar?.classList.remove('open');
            sidebarOverlay?.classList.remove('show');
        }
    });
}

// Função para toggle de submenu
function toggleSubmenu(submenuId, button) {
    const submenu = document.getElementById(submenuId);
    const isOpen = submenu?.classList.contains('open');

    document.querySelectorAll('.submenu').forEach(sm => {
        sm.classList.remove('open');
    });
    document.querySelectorAll('.submenu-toggle').forEach(btn => {
        btn.classList.remove('open');
    });

    if (!isOpen && submenu) {
        submenu.classList.add('open');
        button.classList.add('open');
    }
}

// Expor função globalmente
window.toggleSubmenu = toggleSubmenu;

// Inicialização automática quando DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initUserDropdown();
    initSidebarToggle();

    // Auto-abrir submenu se houver link ativo
    const activeLink = document.querySelector('.sidebar-nav a.active');
    if (activeLink) {
        const submenu = activeLink.closest('.submenu');
        if (submenu) {
            submenu.classList.add('open');
            const toggleButton = submenu.previousElementSibling;
            if (toggleButton && toggleButton.classList.contains('submenu-toggle')) {
                toggleButton.classList.add('open');
            }
        }
    }
});
