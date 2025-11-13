/**
 * S3 Manager - Gerenciamento de arquivos AWS S3
 * Ecletech System
 */

const S3Manager = {
    currentPage: 1,
    pageSize: 20,
    totalFiles: 0,
    selectedFiles: [],
    filters: {},

    /**
     * Inicializa o gerenciador S3
     */
    init() {
        console.log('Inicializando S3 Manager...');

        // Verifica autenticação
        if (!AuthAPI.isAuthenticated()) {
            window.location.href = './auth.html';
            return;
        }

        // Carrega status inicial
        this.loadStatus();

        // Configura drag and drop para upload
        this.setupDragAndDrop();

        // Event listeners
        this.setupEventListeners();
    },

    /**
     * Configura event listeners
     */
    setupEventListeners() {
        const fileInput = document.getElementById('fileInput');
        const uploadArea = document.getElementById('uploadArea');

        // Clique na área de upload
        if (uploadArea) {
            uploadArea.addEventListener('click', () => {
                fileInput.click();
            });
        }

        // Seleção de arquivos
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                this.handleFileSelect(e.target.files);
            });
        }
    },

    /**
     * Configura drag and drop
     */
    setupDragAndDrop() {
        const uploadArea = document.getElementById('uploadArea');

        if (!uploadArea) return;

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, this.preventDefaults, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            });
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            });
        });

        uploadArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            this.handleFileSelect(files);
        });
    },

    preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    },

    /**
     * Manipula seleção de arquivos
     */
    handleFileSelect(files) {
        if (files.length === 0) return;

        this.selectedFiles = Array.from(files);

        // Mostra opções de upload
        document.getElementById('uploadOptions').style.display = 'block';

        // Mostra preview dos arquivos
        let html = '<h6 class="mt-3">Arquivos Selecionados:</h6><ul class="list-group">';
        Array.from(files).forEach(file => {
            const sizeStr = this.formatBytes(file.size);
            html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-file"></i> ${file.name}</span>
                        <span class="badge bg-secondary">${sizeStr}</span>
                     </li>`;
        });
        html += '</ul>';

        document.getElementById('uploadResults').innerHTML = html;
    },

    /**
     * Faz upload dos arquivos selecionados
     */
    async uploadFiles() {
        if (this.selectedFiles.length === 0) {
            Swal.fire('Atenção', 'Selecione ao menos um arquivo', 'warning');
            return;
        }

        const bucket = document.getElementById('uploadBucket').value;
        const acl = document.getElementById('uploadAcl').value;
        const categoria = document.getElementById('uploadCategoria').value;
        const entidadeTipo = document.getElementById('uploadEntidadeTipo').value;
        const entidadeId = document.getElementById('uploadEntidadeId').value;

        // Mostra progresso
        document.getElementById('uploadProgress').style.display = 'block';
        document.getElementById('uploadOptions').style.display = 'none';

        let successCount = 0;
        let errorCount = 0;
        const total = this.selectedFiles.length;

        for (let i = 0; i < total; i++) {
            const file = this.selectedFiles[i];

            try {
                // Atualiza progresso
                const percent = Math.round(((i + 1) / total) * 100);
                document.getElementById('progressBar').style.width = percent + '%';
                document.getElementById('progressText').textContent = `${percent}% - Enviando ${file.name}...`;

                // Converte arquivo para base64
                const base64 = await this.fileToBase64(file);

                // Prepara dados
                const uploadData = {
                    nome_original: file.name,
                    base64: base64,
                    tipo_mime: file.type,
                    acl: acl || 'private',
                    categoria: categoria || null,
                    entidade_tipo: entidadeTipo || null,
                    entidade_id: entidadeId ? parseInt(entidadeId) : null
                };

                if (bucket) {
                    uploadData.bucket = bucket;
                }

                // Faz upload
                const response = await API.post('/s3/upload/base64', uploadData);

                if (response.sucesso) {
                    successCount++;
                } else {
                    errorCount++;
                }

            } catch (error) {
                console.error('Erro no upload:', error);
                errorCount++;
            }
        }

        // Esconde progresso
        document.getElementById('uploadProgress').style.display = 'none';

        // Mostra resultado
        if (errorCount === 0) {
            await Swal.fire({
                icon: 'success',
                title: 'Upload Concluído!',
                text: `${successCount} arquivo(s) enviado(s) com sucesso!`,
                confirmButtonColor: '#FF9900'
            });
        } else {
            await Swal.fire({
                icon: 'warning',
                title: 'Upload Parcial',
                html: `<p>Sucesso: ${successCount}</p><p>Falhas: ${errorCount}</p>`,
                confirmButtonColor: '#FF9900'
            });
        }

        // Limpa seleção
        this.cancelUpload();

        // Recarrega lista se estiver na tab de arquivos
        if (document.getElementById('tab-arquivos').style.display !== 'none') {
            this.loadFiles();
        }
    },

    /**
     * Converte arquivo para base64
     */
    fileToBase64(file) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = () => resolve(reader.result);
            reader.onerror = error => reject(error);
        });
    },

    /**
     * Cancela upload
     */
    cancelUpload() {
        this.selectedFiles = [];
        document.getElementById('fileInput').value = '';
        document.getElementById('uploadOptions').style.display = 'none';
        document.getElementById('uploadResults').innerHTML = '';
        document.getElementById('uploadCategoria').value = '';
        document.getElementById('uploadEntidadeId').value = '';
    },

    /**
     * Carrega status do S3
     */
    async loadStatus() {
        try {
            const response = await API.get('/s3/status');

            const statusDiv = document.getElementById('statusInfo');

            // A API retorna { sucesso, mensagem, codigo, dados }
            const dados = response.dados || {};

            if (dados.configurado) {
                if (dados.habilitado) {
                    statusDiv.innerHTML = `
                        <div class="status-badge status-configurado">
                            <i class="fas fa-check-circle"></i> Configurado e Habilitado
                        </div>
                        <p class="mt-3 text-muted">${dados.mensagem || response.mensagem}</p>
                    `;
                } else {
                    statusDiv.innerHTML = `
                        <div class="status-badge status-desabilitado">
                            <i class="fas fa-pause-circle"></i> Configurado mas Desabilitado
                        </div>
                        <p class="mt-3 text-muted">${dados.mensagem || response.mensagem}</p>
                        <button class="btn btn-s3 mt-2" onclick="S3Manager.enableS3()">
                            <i class="fas fa-play"></i> Habilitar S3
                        </button>
                    `;
                }
            } else {
                statusDiv.innerHTML = `
                    <div class="status-badge status-nao-configurado">
                        <i class="fas fa-exclamation-circle"></i> Não Configurado
                    </div>
                    <p class="mt-3 text-muted">${dados.mensagem || response.mensagem}</p>
                    <button class="btn btn-s3 mt-2" onclick="showTab('configuracoes')">
                        <i class="fas fa-cog"></i> Configurar Agora
                    </button>
                `;
            }

            // Carrega info do servidor
            this.loadServerInfo();

        } catch (error) {
            console.error('Erro ao carregar status:', error);
            document.getElementById('statusInfo').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Erro ao carregar status
                </div>
            `;
        }
    },

    /**
     * Carrega informações do servidor
     */
    async loadServerInfo() {
        try {
            const response = await API.get('/s3/info');

            const serverInfoDiv = document.getElementById('serverInfo');

            // A API retorna { sucesso, mensagem, codigo, dados }
            const dados = response.dados || {};

            if (dados.configurado) {
                const configs = dados.configuracoes || {};
                serverInfoDiv.innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Região:</strong> ${configs.aws_region || 'N/A'}</p>
                            <p><strong>Bucket Padrão:</strong> ${configs.aws_default_bucket || 'N/A'}</p>
                            <p><strong>Access Key:</strong> ${configs.aws_access_key_id || 'N/A'}</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Endpoint:</strong> ${configs.aws_endpoint || 'AWS Padrão'}</p>
                            <p><strong>Path Style:</strong> ${configs.aws_use_path_style_endpoint == '1' ? 'Sim' : 'Não'}</p>
                            <p><strong>ACL Padrão:</strong> ${configs.aws_default_acl || 'private'}</p>
                        </div>
                    </div>
                `;
            } else {
                serverInfoDiv.innerHTML = '<p class="text-muted">S3 não configurado</p>';
            }
        } catch (error) {
            console.error('Erro ao carregar info:', error);
        }
    },

    /**
     * Executa health check
     */
    async checkHealth() {
        try {
            const healthDiv = document.getElementById('healthInfo');
            healthDiv.innerHTML = '<div class="spinner-border spinner-border-s3"></div><p class="mt-2">Verificando...</p>';

            const response = await API.get('/s3/health');

            // A API retorna { sucesso, mensagem, codigo, dados }
            const dados = response.dados || {};

            let html = '<h6>Resultado do Health Check:</h6><ul class="list-group">';

            for (const [check, data] of Object.entries(dados.checks || {})) {
                const icon = data.status === 'ok' ? '<i class="fas fa-check-circle text-success"></i>' :
                            data.status === 'erro' ? '<i class="fas fa-times-circle text-danger"></i>' :
                            '<i class="fas fa-exclamation-circle text-warning"></i>';

                html += `<li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>${check}: ${data.mensagem}</span>
                            <span>${icon}</span>
                         </li>`;
            }

            html += '</ul>';
            html += `<p class="mt-3"><strong>Status Geral:</strong> <span class="badge ${dados.status === 'ok' ? 'bg-success' : 'bg-danger'}">${(dados.status || '').toUpperCase()}</span></p>`;
            html += `<p class="text-muted">Verificado em: ${dados.timestamp || ''}</p>`;

            healthDiv.innerHTML = html;

        } catch (error) {
            console.error('Erro no health check:', error);
            document.getElementById('healthInfo').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Erro ao executar health check
                </div>
            `;
        }
    },

    /**
     * Carrega lista de arquivos
     */
    async loadFiles(page = 1) {
        try {
            this.currentPage = page;

            const params = {
                pagina: page,
                limite: this.pageSize,
                ...this.filters
            };

            const queryString = new URLSearchParams(params).toString();
            const response = await API.get(`/s3/arquivos?${queryString}`);

            this.totalFiles = response.total;

            const filesListDiv = document.getElementById('filesList');

            if (response.arquivos && response.arquivos.length > 0) {
                let html = '';

                response.arquivos.forEach(file => {
                    const icon = this.getFileIcon(file.tipo_mime);
                    const sizeStr = this.formatBytes(file.tamanho_bytes);
                    const date = new Date(file.criado_em).toLocaleString('pt-BR');

                    html += `
                        <div class="file-item">
                            <div class="file-icon">
                                <i class="${icon}"></i>
                            </div>
                            <div class="file-info">
                                <div class="file-name">${file.nome_original}</div>
                                <div class="file-meta">
                                    <span><i class="fas fa-hdd"></i> ${sizeStr}</span> |
                                    <span><i class="fas fa-calendar"></i> ${date}</span> |
                                    <span><i class="fas fa-tag"></i> ${file.categoria || 'Sem categoria'}</span>
                                </div>
                            </div>
                            <div class="file-actions">
                                <button class="btn btn-sm btn-s3-outline" onclick="S3Manager.downloadFile(${file.id})" title="Download">
                                    <i class="fas fa-download"></i>
                                </button>
                                <button class="btn btn-sm btn-info" onclick="S3Manager.showFileDetails(${file.id})" title="Detalhes">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="S3Manager.deleteFile(${file.id})" title="Deletar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });

                filesListDiv.innerHTML = html;

                // Atualiza paginação
                this.updatePagination(response.total_paginas);

            } else {
                filesListDiv.innerHTML = `
                    <div class="text-center py-5">
                        <i class="fas fa-folder-open" style="font-size: 4rem; color: #dee2e6;"></i>
                        <p class="mt-3 text-muted">Nenhum arquivo encontrado</p>
                    </div>
                `;
            }

        } catch (error) {
            console.error('Erro ao carregar arquivos:', error);
            document.getElementById('filesList').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Erro ao carregar arquivos
                </div>
            `;
        }
    },

    /**
     * Aplica filtros
     */
    applyFilters() {
        this.filters = {};

        const nome = document.getElementById('filterNome').value;
        const categoria = document.getElementById('filterCategoria').value;
        const tipoMime = document.getElementById('filterTipoMime').value;

        if (nome) this.filters.nome = nome;
        if (categoria) this.filters.categoria = categoria;
        if (tipoMime) this.filters.tipo_mime = tipoMime;

        this.loadFiles(1);
    },

    /**
     * Atualiza paginação
     */
    updatePagination(totalPages) {
        const paginationDiv = document.getElementById('pagination');

        if (totalPages <= 1) {
            paginationDiv.style.display = 'none';
            return;
        }

        paginationDiv.style.display = 'block';
        const ul = paginationDiv.querySelector('.pagination');

        let html = '';

        // Anterior
        html += `<li class="page-item ${this.currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="S3Manager.loadFiles(${this.currentPage - 1}); return false;">Anterior</a>
                 </li>`;

        // Páginas
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= this.currentPage - 2 && i <= this.currentPage + 2)) {
                html += `<li class="page-item ${i === this.currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="S3Manager.loadFiles(${i}); return false;">${i}</a>
                         </li>`;
            } else if (i === this.currentPage - 3 || i === this.currentPage + 3) {
                html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Próxima
        html += `<li class="page-item ${this.currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="S3Manager.loadFiles(${this.currentPage + 1}); return false;">Próxima</a>
                 </li>`;

        ul.innerHTML = html;
    },

    /**
     * Faz download de arquivo
     */
    async downloadFile(id) {
        try {
            const response = await API.get(`/s3/download/${id}?expiracao=3600`);

            if (response.sucesso && response.url) {
                // Abre URL em nova aba
                window.open(response.url, '_blank');

                Swal.fire({
                    icon: 'success',
                    title: 'Link Gerado!',
                    text: 'O download será iniciado em uma nova aba',
                    timer: 2000,
                    showConfirmButton: false
                });
            }
        } catch (error) {
            console.error('Erro no download:', error);
            Swal.fire('Erro', 'Não foi possível gerar link de download', 'error');
        }
    },

    /**
     * Mostra detalhes do arquivo
     */
    async showFileDetails(id) {
        try {
            const response = await API.get(`/s3/arquivos/${id}?incluir_url=true`);

            const modal = new bootstrap.Modal(document.getElementById('fileDetailsModal'));
            const bodyDiv = document.getElementById('fileDetailsBody');

            const file = response;
            const sizeStr = this.formatBytes(file.tamanho_bytes);
            const date = new Date(file.criado_em).toLocaleString('pt-BR');

            let html = `
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Nome:</strong> ${file.nome_original}</p>
                        <p><strong>Tamanho:</strong> ${sizeStr}</p>
                        <p><strong>Tipo MIME:</strong> ${file.tipo_mime}</p>
                        <p><strong>Extensão:</strong> ${file.extensao}</p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Bucket:</strong> ${file.bucket}</p>
                        <p><strong>Caminho:</strong> ${file.caminho_s3}</p>
                        <p><strong>ACL:</strong> ${file.acl}</p>
                        <p><strong>Criado em:</strong> ${date}</p>
                    </div>
                </div>
                <hr>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>UUID:</strong> <code>${file.uuid}</code></p>
                        <p><strong>Hash MD5:</strong> <code>${file.hash_md5 || 'N/A'}</code></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Categoria:</strong> ${file.categoria || 'Sem categoria'}</p>
                        <p><strong>Entidade:</strong> ${file.entidade_tipo ? `${file.entidade_tipo} #${file.entidade_id}` : 'Nenhuma'}</p>
                    </div>
                </div>
            `;

            if (file.url_download) {
                html += `
                    <hr>
                    <div class="alert alert-info">
                        <strong>URL Temporária:</strong><br>
                        <small>${file.url_download}</small><br>
                        <small class="text-muted">Expira em: ${file.url_expira_em}</small>
                    </div>
                `;
            }

            bodyDiv.innerHTML = html;
            modal.show();

        } catch (error) {
            console.error('Erro ao carregar detalhes:', error);
            Swal.fire('Erro', 'Não foi possível carregar detalhes do arquivo', 'error');
        }
    },

    /**
     * Deleta arquivo
     */
    async deleteFile(id) {
        const result = await Swal.fire({
            title: 'Confirmar Exclusão',
            text: 'Deseja realmente deletar este arquivo? Esta ação não pode ser desfeita.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sim, deletar',
            cancelButtonText: 'Cancelar'
        });

        if (result.isConfirmed) {
            try {
                await API.delete(`/s3/arquivos/${id}`, { deletar_s3: true });

                Swal.fire({
                    icon: 'success',
                    title: 'Deletado!',
                    text: 'Arquivo deletado com sucesso',
                    timer: 2000,
                    showConfirmButton: false
                });

                this.loadFiles(this.currentPage);

            } catch (error) {
                console.error('Erro ao deletar:', error);
                Swal.fire('Erro', 'Não foi possível deletar o arquivo', 'error');
            }
        }
    },

    /**
     * Carrega estatísticas
     */
    async loadStatistics() {
        try {
            const response = await API.get('/s3/estatisticas');

            document.getElementById('statTotalFiles').textContent = response.total_arquivos.toLocaleString('pt-BR');
            document.getElementById('statTotalSize').textContent = this.formatBytes(response.tamanho_total_bytes);
            document.getElementById('statAvgSize').textContent = this.formatBytes(response.tamanho_medio_bytes);

            // Carrega uploads de hoje
            const historyResponse = await API.get('/s3/historico/uploads-recentes?limite=100');
            const today = new Date().toDateString();
            const uploadsToday = historyResponse.uploads.filter(u => {
                return new Date(u.criado_em).toDateString() === today;
            }).length;
            document.getElementById('statUploadsToday').textContent = uploadsToday;

            // Arquivos por tipo
            let html = '<table class="table table-hover"><thead><tr><th>Tipo MIME</th><th>Quantidade</th><th>Tamanho</th></tr></thead><tbody>';
            response.por_tipo.forEach(tipo => {
                html += `<tr>
                            <td><code>${tipo.tipo_mime}</code></td>
                            <td>${tipo.quantidade}</td>
                            <td>${this.formatBytes(tipo.tamanho)}</td>
                         </tr>`;
            });
            html += '</tbody></table>';
            document.getElementById('filesByType').innerHTML = html;

            // Atividade recente
            this.loadRecentActivity();

        } catch (error) {
            console.error('Erro ao carregar estatísticas:', error);
        }
    },

    /**
     * Carrega atividade recente
     */
    async loadRecentActivity() {
        try {
            const response = await API.get('/s3/historico/atividade?periodo=day&limite=7');

            let html = '<table class="table table-sm"><thead><tr><th>Período</th><th>Total</th><th>Sucessos</th><th>Falhas</th></tr></thead><tbody>';

            response.atividade.forEach(item => {
                html += `<tr>
                            <td>${item.periodo}</td>
                            <td>${item.total}</td>
                            <td><span class="badge bg-success">${item.sucessos}</span></td>
                            <td><span class="badge bg-danger">${item.falhas}</span></td>
                         </tr>`;
            });

            html += '</tbody></table>';
            document.getElementById('recentActivity').innerHTML = html;

        } catch (error) {
            console.error('Erro ao carregar atividade:', error);
        }
    },

    /**
     * Carrega histórico
     */
    async loadHistory() {
        try {
            const response = await API.get('/s3/historico?limite=50');

            const tbody = document.getElementById('historyBody');

            if (response.historico && response.historico.length > 0) {
                let html = '';

                response.historico.forEach(item => {
                    const date = new Date(item.criado_em).toLocaleString('pt-BR');
                    const statusBadge = item.status === 'sucesso' ?
                        '<span class="badge bg-success">Sucesso</span>' :
                        '<span class="badge bg-danger">Falha</span>';

                    html += `<tr>
                                <td>${date}</td>
                                <td><span class="badge badge-s3">${item.operacao}</span></td>
                                <td>${item.caminho_s3 || 'N/A'}</td>
                                <td>Usuário #${item.colaborador_id || 'N/A'}</td>
                                <td>${statusBadge}</td>
                                <td>
                                    ${item.erro ? `<small class="text-danger">${item.erro}</small>` :
                                      item.tempo_execucao_ms ? `<small>${item.tempo_execucao_ms}ms</small>` : '-'}
                                </td>
                             </tr>`;
                });

                tbody.innerHTML = html;
            } else {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4">Nenhum registro encontrado</td></tr>';
            }

        } catch (error) {
            console.error('Erro ao carregar histórico:', error);
            document.getElementById('historyBody').innerHTML =
                '<tr><td colspan="6" class="text-center text-danger">Erro ao carregar histórico</td></tr>';
        }
    },

    /**
     * Carrega configurações
     */
    async loadConfig() {
        try {
            const response = await API.get('/s3/config');

            // A API retorna { sucesso, mensagem, codigo, dados }
            const configs = response.dados || [];

            if (configs && configs.length > 0) {
                configs.forEach(config => {
                    const inputId = this.getConfigInputId(config.chave);
                    const input = document.getElementById(inputId);

                    if (input) {
                        // Não mostra valores de senha (já vem mascarado da API)
                        if (config.tipo === 'senha') {
                            input.placeholder = '********';
                        } else {
                            input.value = config.valor || '';
                        }
                    }
                });
            }

        } catch (error) {
            console.error('Erro ao carregar configurações:', error);
        }
    },

    /**
     * Salva configurações
     */
    async saveConfig() {
        try {
            const configuracoes = {
                aws_access_key_id: document.getElementById('configAccessKey').value,
                aws_secret_access_key: document.getElementById('configSecretKey').value,
                aws_region: document.getElementById('configRegion').value,
                aws_default_bucket: document.getElementById('configBucket').value,
                aws_endpoint: document.getElementById('configEndpoint').value,
                aws_use_path_style_endpoint: document.getElementById('configPathStyle').value,
                aws_max_file_size: (parseInt(document.getElementById('configMaxSize').value) * 1024 * 1024).toString(),
                aws_default_acl: document.getElementById('configDefaultAcl').value,
                aws_url_expiration: document.getElementById('configUrlExpiration').value,
                aws_s3_status: '1' // Habilita automaticamente ao salvar
            };

            await API.post('/s3/config/salvar-lote', { configuracoes });

            Swal.fire({
                icon: 'success',
                title: 'Configurações Salvas!',
                text: 'As configurações foram salvas com sucesso',
                confirmButtonColor: '#FF9900'
            });

            // Recarrega status
            this.loadStatus();

        } catch (error) {
            console.error('Erro ao salvar configurações:', error);
            Swal.fire('Erro', 'Não foi possível salvar as configurações', 'error');
        }
    },

    /**
     * Testa conexão
     */
    async testConnection() {
        try {
            Swal.fire({
                title: 'Testando Conexão...',
                text: 'Por favor, aguarde',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const response = await API.post('/s3/testar-conexao', {});

            Swal.close();

            if (response.sucesso) {
                // A API retorna { sucesso, mensagem, codigo, dados }
                const dados = response.dados || {};
                const bucketPadrao = dados.bucket_padrao || 'Não configurado';

                Swal.fire({
                    icon: 'success',
                    title: 'Conexão OK!',
                    html: `<p>${dados.mensagem || response.mensagem}</p><p><strong>Bucket:</strong> ${bucketPadrao}</p>`,
                    confirmButtonColor: '#FF9900'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Falha na Conexão',
                    text: response.mensagem || 'Não foi possível conectar ao S3',
                    confirmButtonColor: '#FF9900'
                });
            }

        } catch (error) {
            Swal.close();
            console.error('Erro ao testar conexão:', error);
            Swal.fire('Erro', 'Erro ao testar conexão com S3', 'error');
        }
    },

    /**
     * Habilita S3
     */
    async enableS3() {
        try {
            await API.post('/s3/habilitar', {});

            Swal.fire({
                icon: 'success',
                title: 'S3 Habilitado!',
                timer: 2000,
                showConfirmButton: false
            });

            this.loadStatus();

        } catch (error) {
            console.error('Erro ao habilitar S3:', error);
            Swal.fire('Erro', 'Não foi possível habilitar o S3', 'error');
        }
    },

    /**
     * Mapeia chave de config para ID do input
     */
    getConfigInputId(chave) {
        const map = {
            'aws_access_key_id': 'configAccessKey',
            'aws_secret_access_key': 'configSecretKey',
            'aws_region': 'configRegion',
            'aws_default_bucket': 'configBucket',
            'aws_endpoint': 'configEndpoint',
            'aws_use_path_style_endpoint': 'configPathStyle',
            'aws_max_file_size': 'configMaxSize',
            'aws_default_acl': 'configDefaultAcl',
            'aws_url_expiration': 'configUrlExpiration'
        };

        return map[chave] || null;
    },

    /**
     * Retorna ícone baseado no tipo MIME
     */
    getFileIcon(mimeType) {
        if (!mimeType) return 'fas fa-file';

        if (mimeType.startsWith('image/')) return 'fas fa-file-image';
        if (mimeType.startsWith('video/')) return 'fas fa-file-video';
        if (mimeType.startsWith('audio/')) return 'fas fa-file-audio';
        if (mimeType === 'application/pdf') return 'fas fa-file-pdf';
        if (mimeType.includes('word')) return 'fas fa-file-word';
        if (mimeType.includes('excel') || mimeType.includes('spreadsheet')) return 'fas fa-file-excel';
        if (mimeType.includes('zip') || mimeType.includes('rar')) return 'fas fa-file-archive';
        if (mimeType.includes('text')) return 'fas fa-file-alt';

        return 'fas fa-file';
    },

    /**
     * Formata bytes para formato legível
     */
    formatBytes(bytes, decimals = 2) {
        if (bytes === 0 || bytes === null) return '0 Bytes';

        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];

        const i = Math.floor(Math.log(bytes) / Math.log(k));

        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }
};
