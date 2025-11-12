/**
 * Gerenciador de Níveis, Roles e Permissões
 * Sistema completo de gestão de acessos
 */

const ManagementApp = {
    state: {
        niveis: [],
        roles: [],
        permissoes: [],
        currentRoleId: null,
        permissoes: {
            niveis: { visualizar: false, criar: false, editar: false, deletar: false },
            roles: { visualizar: false, criar: false, editar: false, deletar: false },
            permissoes: { visualizar: false, criar: false, editar: false, deletar: false }
        }
    },

    async init() {
        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            window.location.href = './auth.html';
            return;
        }

        // Event listeners
        this.setupEventListeners();

        // Verifica permissões
        await this.verificarPermissoes();

        // Carrega dados iniciais
        await Promise.all([
            this.carregarNiveis(),
            this.carregarRoles(),
            this.carregarPermissoes()
        ]);

        // Exibe conteúdo
        document.getElementById('pageContent').style.display = 'block';
    },

    setupEventListeners() {
        // Logout
        document.getElementById('logoutBtn')?.addEventListener('click', async () => {
            if (confirm('Tem certeza que deseja sair?')) {
                await AuthAPI.logout();
            }
        });

        // Modals - Níveis
        document.getElementById('btnNovoNivel')?.addEventListener('click', () => this.abrirModalNivel());
        document.getElementById('closeModalNivel')?.addEventListener('click', () => this.fecharModalNivel());
        document.getElementById('btnCancelarNivel')?.addEventListener('click', () => this.fecharModalNivel());
        document.getElementById('formNivel')?.addEventListener('submit', (e) => this.salvarNivel(e));

        // Modals - Roles
        document.getElementById('btnNovoRole')?.addEventListener('click', () => this.abrirModalRole());
        document.getElementById('closeModalRole')?.addEventListener('click', () => this.fecharModalRole());
        document.getElementById('btnCancelarRole')?.addEventListener('click', () => this.fecharModalRole());
        document.getElementById('formRole')?.addEventListener('submit', (e) => this.salvarRole(e));

        // Modals - Permissões
        document.getElementById('btnNovaPermissao')?.addEventListener('click', () => this.abrirModalPermissao());
        document.getElementById('closeModalPermissao')?.addEventListener('click', () => this.fecharModalPermissao());
        document.getElementById('btnCancelarPermissao')?.addEventListener('click', () => this.fecharModalPermissao());
        document.getElementById('formPermissao')?.addEventListener('submit', (e) => this.salvarPermissao(e));

        // Modal Role-Permissões
        document.getElementById('closeModalRolePermissoes')?.addEventListener('click', () => this.fecharModalRolePermissoes());
        document.getElementById('btnCancelarRolePermissoes')?.addEventListener('click', () => this.fecharModalRolePermissoes());
        document.getElementById('btnSalvarRolePermissoes')?.addEventListener('click', () => this.salvarRolePermissoes());
    },

    async verificarPermissoes() {
        // Para simplificar, vamos usar as permissões de colaboradores
        // Em um sistema real, você criaria permissões específicas
        try {
            const permissoes = window.permissoesUsuario;

            if (permissoes) {
                // Usa as permissões de colaboradores como base
                const perms = {
                    visualizar: permissoes.includes('colaboradores.visualizar'),
                    criar: permissoes.includes('colaboradores.criar'),
                    editar: permissoes.includes('colaboradores.editar'),
                    deletar: permissoes.includes('colaboradores.deletar')
                };

                this.state.permissoes.niveis = perms;
                this.state.permissoes.roles = perms;
                this.state.permissoes.permissoes = perms;

                // Esconde botões se não tem permissão de criar
                if (!perms.criar) {
                    document.getElementById('btnNovoNivel').style.display = 'none';
                    document.getElementById('btnNovoRole').style.display = 'none';
                    document.getElementById('btnNovaPermissao').style.display = 'none';
                }
            }
        } catch (error) {
            console.error('Erro ao verificar permissões:', error);
        }
    },

    // ==================== NÍVEIS ====================
    async carregarNiveis() {
        const loading = document.getElementById('loadingNiveis');
        const table = document.getElementById('tableNiveis');
        const noData = document.getElementById('noDataNiveis');

        try {
            loading.style.display = 'flex';
            table.style.display = 'none';

            const response = await API.get('/niveis');

            if (response.sucesso && response.dados) {
                this.state.niveis = response.dados;
                this.renderNiveis();
            }
        } catch (error) {
            Utils.Notificacao.erro('Erro ao carregar níveis');
            console.error('Erro ao carregar níveis:', error);
        } finally {
            loading.style.display = 'none';
        }
    },

    renderNiveis() {
        const tbody = document.getElementById('tableBodyNiveis');
        const table = document.getElementById('tableNiveis');
        const noData = document.getElementById('noDataNiveis');

        if (this.state.niveis.length === 0) {
            table.style.display = 'none';
            noData.style.display = 'block';
            return;
        }

        tbody.innerHTML = '';
        table.style.display = 'block';
        noData.style.display = 'none';

        this.state.niveis.forEach(nivel => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${nivel.id}</td>
                <td>${Utils.DOM.escapeHtml(nivel.nome)}</td>
                <td>${Utils.DOM.escapeHtml(nivel.codigo)}</td>
                <td>${Utils.DOM.escapeHtml(nivel.descricao || '-')}</td>
                <td>${nivel.ordem}</td>
                <td>
                    <span class="badge ${nivel.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${nivel.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.niveis.editar ?
                            `<button class="btn btn-small" onclick="ManagementApp.editarNivel(${nivel.id})">Editar</button>` : ''}
                        ${this.state.permissoes.niveis.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="ManagementApp.deletarNivel(${nivel.id})">Deletar</button>` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    },

    abrirModalNivel(id = null) {
        const modal = document.getElementById('modalNivel');
        const title = document.getElementById('modalNivelTitle');
        const form = document.getElementById('formNivel');

        if (id) {
            title.textContent = 'Editar Nível';
        } else {
            title.textContent = 'Novo Nível';
            form.reset();
            document.getElementById('nivelId').value = '';
        }

        modal.classList.add('show');
    },

    async editarNivel(id) {
        try {
            const response = await API.get(`/niveis/${id}`);

            if (response.sucesso && response.dados) {
                const nivel = response.dados;

                document.getElementById('nivelId').value = nivel.id;
                document.getElementById('nivelNome').value = nivel.nome;
                document.getElementById('nivelCodigo').value = nivel.codigo;
                document.getElementById('nivelDescricao').value = nivel.descricao || '';
                document.getElementById('nivelOrdem').value = nivel.ordem || 0;
                document.getElementById('nivelAtivo').value = nivel.ativo;

                this.abrirModalNivel(id);
            }
        } catch (error) {
            Utils.Notificacao.erro('Erro ao carregar nível');
            console.error('Erro ao carregar nível:', error);
        }
    },

    async salvarNivel(e) {
        e.preventDefault();

        const id = document.getElementById('nivelId').value;
        const dados = {
            nome: document.getElementById('nivelNome').value,
            codigo: document.getElementById('nivelCodigo').value,
            descricao: document.getElementById('nivelDescricao').value,
            ordem: parseInt(document.getElementById('nivelOrdem').value),
            ativo: parseInt(document.getElementById('nivelAtivo').value)
        };

        try {
            let response;
            if (id) {
                response = await API.put(`/niveis/${id}`, dados);
            } else {
                response = await API.post('/niveis', dados);
            }

            if (response.sucesso) {
                Utils.Notificacao.sucesso(response.mensagem || 'Nível salvo com sucesso!');
                this.fecharModalNivel();
                this.carregarNiveis();
            }
        } catch (error) {
            Utils.Notificacao.erro(error.data?.mensagem || 'Erro ao salvar nível');
            console.error('Erro ao salvar nível:', error);
        }
    },

    async deletarNivel(id) {
        if (!confirm('Tem certeza que deseja deletar este nível?')) {
            return;
        }

        try {
            const response = await API.delete(`/niveis/${id}`);

            if (response.sucesso) {
                Utils.Notificacao.sucesso(response.mensagem || 'Nível deletado com sucesso!');
                this.carregarNiveis();
            }
        } catch (error) {
            Utils.Notificacao.erro(error.data?.mensagem || 'Erro ao deletar nível');
            console.error('Erro ao deletar nível:', error);
        }
    },

    fecharModalNivel() {
        document.getElementById('modalNivel').classList.remove('show');
        document.getElementById('formNivel').reset();
    },

    // ==================== ROLES ====================
    async carregarRoles() {
        const loading = document.getElementById('loadingRoles');
        const table = document.getElementById('tableRoles');

        try {
            loading.style.display = 'flex';
            table.style.display = 'none';

            const response = await API.get('/roles');

            if (response.sucesso && response.dados) {
                this.state.roles = response.dados;
                this.renderRoles();
            }
        } catch (error) {
            Utils.Notificacao.erro('Erro ao carregar roles');
            console.error('Erro ao carregar roles:', error);
        } finally {
            loading.style.display = 'none';
        }
    },

    renderRoles() {
        const tbody = document.getElementById('tableBodyRoles');
        const table = document.getElementById('tableRoles');
        const noData = document.getElementById('noDataRoles');

        if (this.state.roles.length === 0) {
            table.style.display = 'none';
            noData.style.display = 'block';
            return;
        }

        tbody.innerHTML = '';
        table.style.display = 'block';
        noData.style.display = 'none';

        this.state.roles.forEach(role => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${role.id}</td>
                <td>${Utils.DOM.escapeHtml(role.nome)}</td>
                <td>${Utils.DOM.escapeHtml(role.codigo)}</td>
                <td>${Utils.DOM.escapeHtml(role.nivel_nome || '-')}</td>
                <td>
                    <span class="badge ${role.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${role.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.roles.editar ?
                            `<button class="btn btn-small" onclick="ManagementApp.editarRole(${role.id})">Editar</button>` : ''}
                        ${this.state.permissoes.roles.editar ?
                            `<button class="btn btn-small" onclick="ManagementApp.gerenciarPermissoesRole(${role.id})">Permissões</button>` : ''}
                        ${this.state.permissoes.roles.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="ManagementApp.deletarRole(${role.id})">Deletar</button>` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    },

    abrirModalRole(id = null) {
        const modal = document.getElementById('modalRole');
        const title = document.getElementById('modalRoleTitle');
        const form = document.getElementById('formRole');

        // Popula select de níveis
        this.popularSelectNiveis();

        if (id) {
            title.textContent = 'Editar Role';
        } else {
            title.textContent = 'Novo Role';
            form.reset();
            document.getElementById('roleId').value = '';
        }

        modal.classList.add('show');
    },

    popularSelectNiveis() {
        const select = document.getElementById('roleNivelId');
        select.innerHTML = '<option value="">Selecione...</option>';

        this.state.niveis
            .filter(n => n.ativo == 1)
            .forEach(nivel => {
                const option = document.createElement('option');
                option.value = nivel.id;
                option.textContent = nivel.nome;
                select.appendChild(option);
            });
    },

    async editarRole(id) {
        try {
            const response = await API.get(`/roles/${id}`);

            if (response.sucesso && response.dados) {
                const role = response.dados;

                document.getElementById('roleId').value = role.id;
                document.getElementById('roleNome').value = role.nome;
                document.getElementById('roleCodigo').value = role.codigo;
                document.getElementById('roleDescricao').value = role.descricao || '';
                document.getElementById('roleNivelId').value = role.nivel_id;
                document.getElementById('roleAtivo').value = role.ativo;

                this.abrirModalRole(id);
            }
        } catch (error) {
            Utils.Notificacao.erro('Erro ao carregar role');
            console.error('Erro ao carregar role:', error);
        }
    },

    async salvarRole(e) {
        e.preventDefault();

        const id = document.getElementById('roleId').value;
        const dados = {
            nome: document.getElementById('roleNome').value,
            codigo: document.getElementById('roleCodigo').value,
            descricao: document.getElementById('roleDescricao').value,
            nivel_id: parseInt(document.getElementById('roleNivelId').value),
            ativo: parseInt(document.getElementById('roleAtivo').value)
        };

        try {
            let response;
            if (id) {
                response = await API.put(`/roles/${id}`, dados);
            } else {
                response = await API.post('/roles', dados);
            }

            if (response.sucesso) {
                Utils.Notificacao.sucesso(response.mensagem || 'Role salvo com sucesso!');
                this.fecharModalRole();
                this.carregarRoles();
            }
        } catch (error) {
            Utils.Notificacao.erro(error.data?.mensagem || 'Erro ao salvar role');
            console.error('Erro ao salvar role:', error);
        }
    },

    async deletarRole(id) {
        if (!confirm('Tem certeza que deseja deletar este role?')) {
            return;
        }

        try {
            const response = await API.delete(`/roles/${id}`);

            if (response.sucesso) {
                Utils.Notificacao.sucesso(response.mensagem || 'Role deletado com sucesso!');
                this.carregarRoles();
            }
        } catch (error) {
            Utils.Notificacao.erro(error.data?.mensagem || 'Erro ao deletar role');
            console.error('Erro ao deletar role:', error);
        }
    },

    fecharModalRole() {
        document.getElementById('modalRole').classList.remove('show');
        document.getElementById('formRole').reset();
    },

    // ==================== PERMISSÕES ====================
    async carregarPermissoes() {
        const loading = document.getElementById('loadingPermissoes');
        const table = document.getElementById('tablePermissoes');

        try {
            loading.style.display = 'flex';
            table.style.display = 'none';

            const response = await API.get('/permissoes');

            if (response.sucesso && response.dados) {
                this.state.permissoes.lista = response.dados;
                this.renderPermissoes();
            }
        } catch (error) {
            Utils.Notificacao.erro('Erro ao carregar permissões');
            console.error('Erro ao carregar permissões:', error);
        } finally {
            loading.style.display = 'none';
        }
    },

    renderPermissoes() {
        const tbody = document.getElementById('tableBodyPermissoes');
        const table = document.getElementById('tablePermissoes');
        const noData = document.getElementById('noDataPermissoes');
        const lista = this.state.permissoes.lista || [];

        if (lista.length === 0) {
            table.style.display = 'none';
            noData.style.display = 'block';
            return;
        }

        tbody.innerHTML = '';
        table.style.display = 'block';
        noData.style.display = 'none';

        lista.forEach(perm => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${perm.id}</td>
                <td>${Utils.DOM.escapeHtml(perm.nome)}</td>
                <td>${Utils.DOM.escapeHtml(perm.codigo)}</td>
                <td>${Utils.DOM.escapeHtml(perm.modulo || 'geral')}</td>
                <td>
                    <span class="badge ${perm.ativo == 1 ? 'badge-success' : 'badge-danger'}">
                        ${perm.ativo == 1 ? 'Ativo' : 'Inativo'}
                    </span>
                </td>
                <td>
                    <div class="actions">
                        ${this.state.permissoes.permissoes.editar ?
                            `<button class="btn btn-small" onclick="ManagementApp.editarPermissao(${perm.id})">Editar</button>` : ''}
                        ${this.state.permissoes.permissoes.deletar ?
                            `<button class="btn btn-small btn-danger" onclick="ManagementApp.deletarPermissao(${perm.id})">Deletar</button>` : ''}
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    },

    abrirModalPermissao(id = null) {
        const modal = document.getElementById('modalPermissao');
        const title = document.getElementById('modalPermissaoTitle');
        const form = document.getElementById('formPermissao');

        if (id) {
            title.textContent = 'Editar Permissão';
        } else {
            title.textContent = 'Nova Permissão';
            form.reset();
            document.getElementById('permissaoId').value = '';
        }

        modal.classList.add('show');
    },

    async editarPermissao(id) {
        try {
            const response = await API.get(`/permissoes/${id}`);

            if (response.sucesso && response.dados) {
                const perm = response.dados;

                document.getElementById('permissaoId').value = perm.id;
                document.getElementById('permissaoNome').value = perm.nome;
                document.getElementById('permissaoCodigo').value = perm.codigo;
                document.getElementById('permissaoDescricao').value = perm.descricao || '';
                document.getElementById('permissaoModulo').value = perm.modulo || 'geral';
                document.getElementById('permissaoAtivo').value = perm.ativo;

                this.abrirModalPermissao(id);
            }
        } catch (error) {
            Utils.Notificacao.erro('Erro ao carregar permissão');
            console.error('Erro ao carregar permissão:', error);
        }
    },

    async salvarPermissao(e) {
        e.preventDefault();

        const id = document.getElementById('permissaoId').value;
        const dados = {
            nome: document.getElementById('permissaoNome').value,
            codigo: document.getElementById('permissaoCodigo').value,
            descricao: document.getElementById('permissaoDescricao').value,
            modulo: document.getElementById('permissaoModulo').value,
            ativo: parseInt(document.getElementById('permissaoAtivo').value)
        };

        try {
            let response;
            if (id) {
                response = await API.put(`/permissoes/${id}`, dados);
            } else {
                response = await API.post('/permissoes', dados);
            }

            if (response.sucesso) {
                Utils.Notificacao.sucesso(response.mensagem || 'Permissão salva com sucesso!');
                this.fecharModalPermissao();
                this.carregarPermissoes();
            }
        } catch (error) {
            Utils.Notificacao.erro(error.data?.mensagem || 'Erro ao salvar permissão');
            console.error('Erro ao salvar permissão:', error);
        }
    },

    async deletarPermissao(id) {
        if (!confirm('Tem certeza que deseja deletar esta permissão?')) {
            return;
        }

        try {
            const response = await API.delete(`/permissoes/${id}`);

            if (response.sucesso) {
                Utils.Notificacao.sucesso(response.mensagem || 'Permissão deletada com sucesso!');
                this.carregarPermissoes();
            }
        } catch (error) {
            Utils.Notificacao.erro(error.data?.mensagem || 'Erro ao deletar permissão');
            console.error('Erro ao deletar permissão:', error);
        }
    },

    fecharModalPermissao() {
        document.getElementById('modalPermissao').classList.remove('show');
        document.getElementById('formPermissao').reset();
    },

    // ==================== GERENCIAR PERMISSÕES DE ROLE ====================
    async gerenciarPermissoesRole(roleId) {
        this.state.currentRoleId = roleId;

        try {
            // Carrega role e suas permissões
            const [roleResponse, permissoesResponse] = await Promise.all([
                API.get(`/roles/${roleId}`),
                API.get(`/roles/${roleId}/permissoes`)
            ]);

            if (roleResponse.sucesso && permissoesResponse.sucesso) {
                const role = roleResponse.dados;
                const permissoesRole = permissoesResponse.dados || [];

                // Carrega todas as permissões disponíveis
                const todasPermissoesResponse = await API.get('/permissoes/modulos/listar');

                if (todasPermissoesResponse.sucesso) {
                    this.renderModalRolePermissoes(role, todasPermissoesResponse.dados, permissoesRole);
                    document.getElementById('modalRolePermissoes').classList.add('show');
                }
            }
        } catch (error) {
            Utils.Notificacao.erro('Erro ao carregar permissões');
            console.error('Erro ao carregar permissões:', error);
        }
    },

    renderModalRolePermissoes(role, permissoesPorModulo, permissoesRole) {
        const title = document.getElementById('modalRolePermissoesTitle');
        const container = document.getElementById('permissoesContainer');

        title.textContent = `Gerenciar Permissões - ${role.nome}`;

        const permissoesIds = permissoesRole.map(p => p.id);

        let html = '';
        for (const [modulo, permissoes] of Object.entries(permissoesPorModulo)) {
            html += `
                <div class="module-group">
                    <div class="module-title">${modulo.toUpperCase()}</div>
                    <div class="permissions-grid">
            `;

            permissoes.forEach(perm => {
                const checked = permissoesIds.includes(perm.id) ? 'checked' : '';
                html += `
                    <div class="permission-item">
                        <input type="checkbox" id="perm_${perm.id}" value="${perm.id}" ${checked}>
                        <label for="perm_${perm.id}">
                            <span class="permission-name">${Utils.DOM.escapeHtml(perm.nome)}</span>
                            <span class="permission-code">${Utils.DOM.escapeHtml(perm.codigo)}</span>
                        </label>
                    </div>
                `;
            });

            html += `
                    </div>
                </div>
            `;
        }

        container.innerHTML = html;
    },

    async salvarRolePermissoes() {
        const checkboxes = document.querySelectorAll('#permissoesContainer input[type="checkbox"]:checked');
        const permissoesIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

        try {
            const response = await API.post(`/roles/${this.state.currentRoleId}/permissoes`, {
                permissoes: permissoesIds
            });

            if (response.sucesso) {
                Utils.Notificacao.sucesso('Permissões atualizadas com sucesso!');
                this.fecharModalRolePermissoes();
            }
        } catch (error) {
            Utils.Notificacao.erro(error.data?.mensagem || 'Erro ao salvar permissões');
            console.error('Erro ao salvar permissões:', error);
        }
    },

    fecharModalRolePermissoes() {
        document.getElementById('modalRolePermissoes').classList.remove('show');
        this.state.currentRoleId = null;
    }
};

// Inicializa quando o DOM estiver pronto
document.addEventListener('DOMContentLoaded', () => {
    ManagementApp.init();
});

// Expõe globalmente para uso nos event handlers inline
window.ManagementApp = ManagementApp;
