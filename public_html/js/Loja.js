/**
 * Gerenciador de Informações da Loja
 * Implementa edição das informações da loja (singleton - apenas 1 registro)
 */

const Loja = {
    // Estado da aplicação
    state: {
        loja: null,
        dadosOriginais: null,
        salvando: false
    },

    // Elementos DOM
    elements: {
        loading: document.getElementById('loading'),
        lojaForm: document.getElementById('lojaForm'),
        formLoja: document.getElementById('formLoja'),
        successAlert: document.getElementById('successAlert'),
        errorAlert: document.getElementById('errorAlert'),
        btnSalvar: document.getElementById('btnSalvar')
    },

    /**
     * Carrega as informações da loja
     */
    async carregar() {
        this.showLoading();

        try {
            const response = await API.get('/loja');

            if (response.sucesso) {
                this.state.loja = response.dados;
                this.state.dadosOriginais = JSON.parse(JSON.stringify(response.dados));
                this.preencherFormulario();
                this.hideLoading();
                this.elements.lojaForm.style.display = 'block';
            } else {
                throw new Error(response.mensagem || 'Erro ao carregar informações da loja');
            }
        } catch (error) {
            console.error('Erro ao carregar loja:', error);

            const mensagem = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao carregar informações da loja';

            this.showError(mensagem);
            this.hideLoading();
        }
    },

    /**
     * Preenche o formulário com os dados da loja
     */
    preencherFormulario() {
        if (!this.state.loja) return;

        const form = this.elements.formLoja;

        // Dados principais
        form.nome_fantasia.value = this.state.loja.nome_fantasia || '';
        form.razao_social.value = this.state.loja.razao_social || '';
        form.cnpj.value = this.formatarCNPJ(this.state.loja.cnpj) || '';
        form.inscricao_estadual.value = this.state.loja.inscricao_estadual || '';
        form.inscricao_municipal.value = this.state.loja.inscricao_municipal || '';

        // Contatos
        form.email.value = this.state.loja.email || '';
        form.telefone.value = this.formatarTelefone(this.state.loja.telefone) || '';
        form.celular.value = this.formatarCelular(this.state.loja.celular) || '';
        form.site.value = this.state.loja.site || '';

        // Responsável
        form.responsavel.value = this.state.loja.responsavel || '';
        form.cpf_responsavel.value = this.formatarCPF(this.state.loja.cpf_responsavel) || '';

        // Endereço
        form.endereco_cep.value = this.formatarCEP(this.state.loja.endereco_cep) || '';
        form.endereco_logradouro.value = this.state.loja.endereco_logradouro || '';
        form.endereco_numero.value = this.state.loja.endereco_numero || '';
        form.endereco_complemento.value = this.state.loja.endereco_complemento || '';
        form.endereco_bairro.value = this.state.loja.endereco_bairro || '';
        form.endereco_cidade.value = this.state.loja.endereco_cidade || '';
        form.endereco_uf.value = this.state.loja.endereco_uf || '';

        // Configura máscaras
        this.configurarMascaras();
    },

    /**
     * Configura event listeners do formulário
     */
    configurarMascaras() {
        const form = this.elements.formLoja;

        // Máscara CNPJ
        form.cnpj.addEventListener('input', (e) => {
            e.target.value = this.formatarCNPJ(e.target.value.replace(/\D/g, ''));
        });

        // Máscara CPF
        form.cpf_responsavel.addEventListener('input', (e) => {
            e.target.value = this.formatarCPF(e.target.value.replace(/\D/g, ''));
        });

        // Máscara Telefone
        form.telefone.addEventListener('input', (e) => {
            e.target.value = this.formatarTelefone(e.target.value.replace(/\D/g, ''));
        });

        // Máscara Celular
        form.celular.addEventListener('input', (e) => {
            e.target.value = this.formatarCelular(e.target.value.replace(/\D/g, ''));
        });

        // Máscara CEP
        form.endereco_cep.addEventListener('input', (e) => {
            e.target.value = this.formatarCEP(e.target.value.replace(/\D/g, ''));
        });

        // Busca CEP automaticamente
        form.endereco_cep.addEventListener('blur', (e) => {
            const cep = e.target.value.replace(/\D/g, '');
            if (cep.length === 8) {
                this.buscarCEP(cep);
            }
        });

        // Submit do formulário
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.salvar();
        });
    },

    /**
     * Busca endereço pelo CEP via ViaCEP
     */
    async buscarCEP(cep) {
        try {
            const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await response.json();

            if (!data.erro) {
                const form = this.elements.formLoja;
                form.endereco_logradouro.value = data.logradouro || '';
                form.endereco_bairro.value = data.bairro || '';
                form.endereco_cidade.value = data.localidade || '';
                form.endereco_uf.value = data.uf || '';
            }
        } catch (error) {
            console.error('Erro ao buscar CEP:', error);
        }
    },

    /**
     * Salva as informações da loja
     */
    async salvar() {
        if (this.state.salvando) return;

        // Limpa alertas
        this.hideAlerts();

        // Valida o formulário
        const form = this.elements.formLoja;
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Coleta os dados do formulário
        const dados = {
            nome_fantasia: form.nome_fantasia.value.trim(),
            razao_social: form.razao_social.value.trim(),
            cnpj: form.cnpj.value.replace(/\D/g, ''),
            inscricao_estadual: form.inscricao_estadual.value.trim() || null,
            inscricao_municipal: form.inscricao_municipal.value.trim() || null,
            email: form.email.value.trim() || null,
            telefone: form.telefone.value.replace(/\D/g, '') || null,
            celular: form.celular.value.replace(/\D/g, '') || null,
            site: form.site.value.trim() || null,
            responsavel: form.responsavel.value.trim() || null,
            cpf_responsavel: form.cpf_responsavel.value.replace(/\D/g, '') || null,
            endereco_cep: form.endereco_cep.value.replace(/\D/g, '') || null,
            endereco_logradouro: form.endereco_logradouro.value.trim() || null,
            endereco_numero: form.endereco_numero.value.trim() || null,
            endereco_complemento: form.endereco_complemento.value.trim() || null,
            endereco_bairro: form.endereco_bairro.value.trim() || null,
            endereco_cidade: form.endereco_cidade.value.trim() || null,
            endereco_uf: form.endereco_uf.value || null
        };

        // Validações adicionais
        if (dados.cnpj && dados.cnpj.length !== 14) {
            this.showError('CNPJ deve conter 14 dígitos');
            return;
        }

        if (dados.cpf_responsavel && dados.cpf_responsavel.length !== 11) {
            this.showError('CPF deve conter 11 dígitos');
            return;
        }

        if (dados.endereco_cep && dados.endereco_cep.length !== 8) {
            this.showError('CEP deve conter 8 dígitos');
            return;
        }

        // Inicia salvamento
        this.state.salvando = true;
        this.elements.btnSalvar.disabled = true;
        this.elements.btnSalvar.textContent = 'Salvando...';

        try {
            const response = await API.put('/loja', dados);

            if (response.sucesso) {
                this.state.loja = response.dados;
                this.state.dadosOriginais = JSON.parse(JSON.stringify(response.dados));
                this.showSuccess('Informações da loja atualizadas com sucesso!');

                // Recarrega o formulário com os dados atualizados
                this.preencherFormulario();
            } else {
                throw new Error(response.mensagem || 'Erro ao salvar informações');
            }
        } catch (error) {
            console.error('Erro ao salvar loja:', error);

            const mensagem = error.data ?
                Utils.Errors.formatarMensagem(error.data) :
                'Erro ao salvar informações da loja';

            this.showError(mensagem);
        } finally {
            this.state.salvando = false;
            this.elements.btnSalvar.disabled = false;
            this.elements.btnSalvar.textContent = 'Salvar Alterações';
        }
    },

    /**
     * Cancela a edição e restaura os dados originais
     */
    cancelar() {
        if (confirm('Deseja realmente cancelar? As alterações não salvas serão perdidas.')) {
            this.state.loja = JSON.parse(JSON.stringify(this.state.dadosOriginais));
            this.preencherFormulario();
            this.hideAlerts();
        }
    },

    /**
     * Formatação de CNPJ
     */
    formatarCNPJ(cnpj) {
        if (!cnpj) return '';
        cnpj = cnpj.replace(/\D/g, '');
        cnpj = cnpj.substring(0, 14);
        return cnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    },

    /**
     * Formatação de CPF
     */
    formatarCPF(cpf) {
        if (!cpf) return '';
        cpf = cpf.replace(/\D/g, '');
        cpf = cpf.substring(0, 11);
        return cpf.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    },

    /**
     * Formatação de Telefone
     */
    formatarTelefone(telefone) {
        if (!telefone) return '';
        telefone = telefone.replace(/\D/g, '');
        telefone = telefone.substring(0, 10);
        return telefone.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    },

    /**
     * Formatação de Celular
     */
    formatarCelular(celular) {
        if (!celular) return '';
        celular = celular.replace(/\D/g, '');
        celular = celular.substring(0, 11);
        return celular.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    },

    /**
     * Formatação de CEP
     */
    formatarCEP(cep) {
        if (!cep) return '';
        cep = cep.replace(/\D/g, '');
        cep = cep.substring(0, 8);
        return cep.replace(/(\d{5})(\d{3})/, '$1-$2');
    },

    /**
     * Mostra loading
     */
    showLoading() {
        this.elements.loading.style.display = 'block';
        this.elements.lojaForm.style.display = 'none';
    },

    /**
     * Esconde loading
     */
    hideLoading() {
        this.elements.loading.style.display = 'none';
    },

    /**
     * Mostra mensagem de sucesso
     */
    showSuccess(mensagem) {
        this.hideAlerts();
        this.elements.successAlert.textContent = mensagem;
        this.elements.successAlert.classList.add('show');

        // Auto-hide após 5 segundos
        setTimeout(() => {
            this.elements.successAlert.classList.remove('show');
        }, 5000);

        // Scroll para o topo
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    /**
     * Mostra mensagem de erro
     */
    showError(mensagem) {
        this.hideAlerts();
        this.elements.errorAlert.textContent = mensagem;
        this.elements.errorAlert.classList.add('show');

        // Auto-hide após 8 segundos
        setTimeout(() => {
            this.elements.errorAlert.classList.remove('show');
        }, 8000);

        // Scroll para o topo
        window.scrollTo({ top: 0, behavior: 'smooth' });
    },

    /**
     * Esconde todos os alertas
     */
    hideAlerts() {
        this.elements.successAlert.classList.remove('show');
        this.elements.errorAlert.classList.remove('show');
    }
};

// Exporta para uso global
window.Loja = Loja;
