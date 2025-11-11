/**
 * Utilitários Gerais
 * Funções reutilizáveis para tratamento de erros, validação e formatação
 */

const Utils = {
    /**
     * Tratamento de Erros da API
     */
    Errors: {
        /**
         * Formata erros de validação da API
         * @param {Object} erros - Objeto com erros de validação { campo: [mensagens] }
         * @returns {string|null} - Mensagens formatadas ou null
         */
        formatarErrosValidacao(erros) {
            if (!erros || typeof erros !== 'object') {
                return null;
            }

            const mensagens = [];
            for (const [campo, mensagensArray] of Object.entries(erros)) {
                if (Array.isArray(mensagensArray)) {
                    mensagensArray.forEach(msg => {
                        mensagens.push(`• ${msg}`);
                    });
                }
            }

            return mensagens.length > 0 ? mensagens.join('\n') : null;
        },

        /**
         * Formata mensagem de erro de forma consistente
         * Suporta erros da API com estrutura: { sucesso: false, mensagem: "...", erros: {...} }
         * @param {string|Object} error - Erro da API
         * @returns {string} - Mensagem formatada
         */
        formatarMensagem(error) {
            // Se é uma string simples
            if (typeof error === 'string') {
                return error;
            }

            // Se não é um objeto, retorna mensagem padrão
            if (!error || typeof error !== 'object') {
                return 'Erro ao processar requisição';
            }

            // Verifica se é um objeto de erro da API com validações
            const errosValidacao = this.formatarErrosValidacao(error.erros);

            if (errosValidacao) {
                // Se tem erros de validação, mostra eles formatados
                const titulo = error.mensagem || 'Erro de validação';
                return `${titulo}:\n\n${errosValidacao}`;
            }

            // Se não tem erros detalhados, usa a mensagem geral
            return error.mensagem || error.erro || 'Erro ao processar requisição';
        },

        /**
         * Destaca campos com erro no formulário
         * @param {Object} erros - Objeto com erros { campo: [mensagens] }
         * @param {Object} mapeamentoCampos - Mapeamento de campos do backend para IDs dos inputs
         */
        destacarCampos(erros, mapeamentoCampos = {}) {
            if (!erros || typeof erros !== 'object') {
                return;
            }

            for (const [campo, mensagens] of Object.entries(erros)) {
                // Converte campo snake_case para camelCase se não houver mapeamento
                const inputId = mapeamentoCampos[campo] || this._snakeToCamel(campo);
                const inputElement = document.getElementById(inputId);

                if (inputElement) {
                    // Adiciona borda vermelha
                    inputElement.style.borderColor = '#dc3545';
                    inputElement.style.borderWidth = '2px';

                    // Adiciona classe para identificar campos com erro
                    inputElement.classList.add('campo-com-erro');

                    // Adiciona tooltip com a mensagem de erro (opcional)
                    if (Array.isArray(mensagens) && mensagens.length > 0) {
                        inputElement.title = mensagens.join(', ');
                    }
                }
            }
        },

        /**
         * Limpa destaques de erro dos campos
         * @param {string} seletor - Seletor CSS dos campos (padrão: '.campo-com-erro')
         */
        limparCampos(seletor = '.campo-com-erro') {
            const camposComErro = document.querySelectorAll(seletor);
            camposComErro.forEach(campo => {
                campo.style.borderColor = '';
                campo.style.borderWidth = '';
                campo.classList.remove('campo-com-erro');
                campo.removeAttribute('title');
            });
        },

        /**
         * Exibe erro em um container específico
         * @param {HTMLElement} container - Container de erro
         * @param {HTMLElement} messageElement - Elemento para a mensagem
         * @param {string|Object} error - Erro a ser exibido
         */
        exibir(container, messageElement, error) {
            if (!container || !messageElement) {
                console.error('Container ou messageElement não fornecido');
                return;
            }

            const mensagemFinal = this.formatarMensagem(error);

            container.style.display = 'block';
            messageElement.textContent = mensagemFinal;

            // Aplica estilo para preservar quebras de linha
            messageElement.style.whiteSpace = 'pre-line';
        },

        /**
         * Esconde container de erro
         * @param {HTMLElement} container - Container de erro
         */
        esconder(container) {
            if (container) {
                container.style.display = 'none';
            }
        },

        /**
         * Converte string snake_case para camelCase
         * @private
         * @param {string} str - String em snake_case
         * @returns {string} - String em camelCase
         */
        _snakeToCamel(str) {
            return str.replace(/_([a-z])/g, (match, letter) => letter.toUpperCase());
        }
    },

    /**
     * Formatação de Dados
     */
    Format: {
        /**
         * Formata número com separadores de milhares
         * @param {number} numero - Número a ser formatado
         * @param {string} locale - Locale (padrão: 'pt-BR')
         * @returns {string} - Número formatado
         */
        numero(numero, locale = 'pt-BR') {
            if (numero === null || numero === undefined) return '-';
            return new Intl.NumberFormat(locale).format(numero);
        },

        /**
         * Formata moeda
         * @param {number} valor - Valor a ser formatado
         * @param {string} moeda - Código da moeda (padrão: 'BRL')
         * @param {string} locale - Locale (padrão: 'pt-BR')
         * @returns {string} - Valor formatado
         */
        moeda(valor, moeda = 'BRL', locale = 'pt-BR') {
            if (valor === null || valor === undefined) return '-';
            return new Intl.NumberFormat(locale, {
                style: 'currency',
                currency: moeda
            }).format(valor);
        },

        /**
         * Formata data
         * @param {string|Date} data - Data a ser formatada
         * @param {Object} opcoes - Opções de formatação
         * @returns {string} - Data formatada
         */
        data(data, opcoes = {}) {
            if (!data) return '-';

            const date = typeof data === 'string' ? new Date(data) : data;

            if (isNaN(date.getTime())) return '-';

            const opcoesDefault = {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                ...opcoes
            };

            return date.toLocaleDateString('pt-BR', opcoesDefault);
        },

        /**
         * Formata data e hora
         * @param {string|Date} data - Data a ser formatada
         * @returns {string} - Data e hora formatadas
         */
        dataHora(data) {
            if (!data) return '-';

            const date = typeof data === 'string' ? new Date(data) : data;

            if (isNaN(date.getTime())) return '-';

            return date.toLocaleString('pt-BR');
        }
    },

    /**
     * Validação
     */
    Validation: {
        /**
         * Valida email
         * @param {string} email - Email a ser validado
         * @returns {boolean} - True se válido
         */
        email(email) {
            const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return regex.test(email);
        },

        /**
         * Valida CPF
         * @param {string} cpf - CPF a ser validado
         * @returns {boolean} - True se válido
         */
        cpf(cpf) {
            cpf = cpf.replace(/[^\d]/g, '');

            if (cpf.length !== 11) return false;

            // Verifica se todos os dígitos são iguais
            if (/^(\d)\1+$/.test(cpf)) return false;

            // Validação do primeiro dígito verificador
            let soma = 0;
            for (let i = 0; i < 9; i++) {
                soma += parseInt(cpf.charAt(i)) * (10 - i);
            }
            let resto = 11 - (soma % 11);
            let digito1 = resto >= 10 ? 0 : resto;

            if (digito1 !== parseInt(cpf.charAt(9))) return false;

            // Validação do segundo dígito verificador
            soma = 0;
            for (let i = 0; i < 10; i++) {
                soma += parseInt(cpf.charAt(i)) * (11 - i);
            }
            resto = 11 - (soma % 11);
            let digito2 = resto >= 10 ? 0 : resto;

            return digito2 === parseInt(cpf.charAt(10));
        },

        /**
         * Valida CNPJ
         * @param {string} cnpj - CNPJ a ser validado
         * @returns {boolean} - True se válido
         */
        cnpj(cnpj) {
            cnpj = cnpj.replace(/[^\d]/g, '');

            if (cnpj.length !== 14) return false;

            // Verifica se todos os dígitos são iguais
            if (/^(\d)\1+$/.test(cnpj)) return false;

            // Validação do primeiro dígito verificador
            let tamanho = cnpj.length - 2;
            let numeros = cnpj.substring(0, tamanho);
            let digitos = cnpj.substring(tamanho);
            let soma = 0;
            let pos = tamanho - 7;

            for (let i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2) pos = 9;
            }

            let resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);

            if (resultado != digitos.charAt(0)) return false;

            // Validação do segundo dígito verificador
            tamanho = tamanho + 1;
            numeros = cnpj.substring(0, tamanho);
            soma = 0;
            pos = tamanho - 7;

            for (let i = tamanho; i >= 1; i--) {
                soma += numeros.charAt(tamanho - i) * pos--;
                if (pos < 2) pos = 9;
            }

            resultado = soma % 11 < 2 ? 0 : 11 - (soma % 11);

            return resultado == digitos.charAt(1);
        },

        /**
         * Valida telefone brasileiro
         * @param {string} telefone - Telefone a ser validado
         * @returns {boolean} - True se válido
         */
        telefone(telefone) {
            telefone = telefone.replace(/[^\d]/g, '');
            // Aceita (XX) XXXXX-XXXX ou (XX) XXXX-XXXX
            return telefone.length === 10 || telefone.length === 11;
        },

        /**
         * Normaliza placa de veículo
         * Remove espaços, converte para maiúsculas e aplica formato correto
         * Suporta formato Mercosul (ABC1D23) e antigo (ABC-1234 ou ABC1234)
         * @param {string} placa - Placa a ser normalizada
         * @returns {string} - Placa normalizada
         */
        normalizarPlaca(placa) {
            if (!placa) return '';

            // Remove espaços e converte para maiúsculas
            placa = placa.trim().toUpperCase().replace(/\s+/g, '');

            // Remove caracteres especiais exceto hífen
            placa = placa.replace(/[^A-Z0-9-]/g, '');

            // Detecta e normaliza formato Mercosul: ABC1D23
            const mercosul = /^([A-Z]{3})(\d{1})([A-Z]{1})(\d{2})$/;
            if (mercosul.test(placa)) {
                return placa; // Já está no formato correto
            }

            // Detecta e normaliza formato antigo: ABC-1234 ou ABC1234
            const antigoComHifen = /^([A-Z]{3})-?(\d{4})$/;
            const match = placa.match(antigoComHifen);
            if (match) {
                // Retorna no formato com hífen
                return `${match[1]}-${match[2]}`;
            }

            // Se não está em nenhum formato reconhecido, retorna como está
            // (deixa o backend validar e retornar erro apropriado)
            return placa;
        },

        /**
         * Valida placa de veículo
         * @param {string} placa - Placa a ser validada
         * @returns {boolean} - True se válido
         */
        placa(placa) {
            if (!placa) return false;

            const placaNormalizada = this.normalizarPlaca(placa);

            // Formato Mercosul: ABC1D23
            const mercosul = /^[A-Z]{3}\d[A-Z]\d{2}$/;

            // Formato antigo: ABC-1234
            const antigo = /^[A-Z]{3}-\d{4}$/;

            return mercosul.test(placaNormalizada) || antigo.test(placaNormalizada);
        }
    },

    /**
     * Utilitários DOM
     */
    DOM: {
        /**
         * Escape HTML para prevenir XSS
         * @param {string} text - Texto a ser escapado
         * @returns {string} - Texto escapado
         */
        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Mostra elemento
         * @param {HTMLElement|string} elemento - Elemento ou ID
         * @param {string} display - Tipo de display (padrão: 'block')
         */
        mostrar(elemento, display = 'block') {
            const el = typeof elemento === 'string' ?
                document.getElementById(elemento) : elemento;

            if (el) {
                el.style.display = display;
            }
        },

        /**
         * Esconde elemento
         * @param {HTMLElement|string} elemento - Elemento ou ID
         */
        esconder(elemento) {
            const el = typeof elemento === 'string' ?
                document.getElementById(elemento) : elemento;

            if (el) {
                el.style.display = 'none';
            }
        },

        /**
         * Toggle de visibilidade
         * @param {HTMLElement|string} elemento - Elemento ou ID
         * @param {string} display - Tipo de display quando visível (padrão: 'block')
         */
        toggle(elemento, display = 'block') {
            const el = typeof elemento === 'string' ?
                document.getElementById(elemento) : elemento;

            if (el) {
                el.style.display = el.style.display === 'none' ? display : 'none';
            }
        }
    },

    /**
     * Utilitários de String
     */
    String: {
        /**
         * Capitaliza primeira letra
         * @param {string} str - String a ser capitalizada
         * @returns {string} - String capitalizada
         */
        capitalize(str) {
            if (!str) return '';
            return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
        },

        /**
         * Trunca string
         * @param {string} str - String a ser truncada
         * @param {number} length - Tamanho máximo
         * @param {string} suffix - Sufixo (padrão: '...')
         * @returns {string} - String truncada
         */
        truncate(str, length, suffix = '...') {
            if (!str || str.length <= length) return str;
            return str.substring(0, length - suffix.length) + suffix;
        },

        /**
         * Remove acentos
         * @param {string} str - String com acentos
         * @returns {string} - String sem acentos
         */
        removerAcentos(str) {
            if (!str) return '';
            return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }
    },

    /**
     * Utilitários de Objeto
     */
    Object: {
        /**
         * Cria deep copy de um objeto
         * @param {Object} obj - Objeto a ser copiado
         * @returns {Object} - Cópia profunda do objeto
         */
        deepCopy(obj) {
            return JSON.parse(JSON.stringify(obj));
        },

        /**
         * Verifica se objeto está vazio
         * @param {Object} obj - Objeto a ser verificado
         * @returns {boolean} - True se vazio
         */
        isEmpty(obj) {
            return Object.keys(obj).length === 0;
        }
    },

    /**
     * Debounce - Limita execução de função
     * @param {Function} func - Função a ser executada
     * @param {number} wait - Tempo de espera em ms
     * @returns {Function} - Função com debounce
     */
    debounce(func, wait = 300) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    },

    /**
     * Throttle - Limita frequência de execução
     * @param {Function} func - Função a ser executada
     * @param {number} limit - Intervalo mínimo entre execuções em ms
     * @returns {Function} - Função com throttle
     */
    throttle(func, limit = 300) {
        let inThrottle;
        return function(...args) {
            if (!inThrottle) {
                func.apply(this, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        };
    },

    /**
     * Sistema de Notificações Toast
     */
    Notificacao: {
        /**
         * Container para as notificações
         */
        container: null,

        /**
         * Inicializa o container de notificações
         * @private
         */
        _inicializarContainer() {
            if (!this.container) {
                this.container = document.getElementById('toast-container');
                if (!this.container) {
                    this.container = document.createElement('div');
                    this.container.id = 'toast-container';
                    this.container.className = 'toast-container';
                    document.body.appendChild(this.container);
                }
            }
        },

        /**
         * Exibe notificação toast
         * @param {string} mensagem - Mensagem a ser exibida
         * @param {string} tipo - Tipo da notificação: 'sucesso', 'erro', 'aviso', 'info'
         * @param {number} duracao - Duração em ms (padrão: 5000)
         */
        exibir(mensagem, tipo = 'info', duracao = 5000) {
            this._inicializarContainer();

            const toast = document.createElement('div');
            toast.className = `toast toast-${tipo}`;

            // Ícone baseado no tipo
            const icones = {
                'sucesso': '✓',
                'erro': '✕',
                'aviso': '⚠',
                'info': 'ℹ'
            };

            toast.innerHTML = `
                <div class="toast-icon">${icones[tipo] || icones.info}</div>
                <div class="toast-message">${this._formatarMensagem(mensagem)}</div>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            `;

            this.container.appendChild(toast);

            // Animação de entrada
            setTimeout(() => toast.classList.add('toast-show'), 10);

            // Remove automaticamente após a duração
            setTimeout(() => {
                toast.classList.remove('toast-show');
                setTimeout(() => toast.remove(), 300);
            }, duracao);
        },

        /**
         * Formata mensagem preservando quebras de linha
         * @private
         * @param {string} mensagem - Mensagem a ser formatada
         * @returns {string} - Mensagem formatada
         */
        _formatarMensagem(mensagem) {
            if (!mensagem) return '';
            // Substitui quebras de linha por <br> e escapa HTML
            return Utils.DOM.escapeHtml(mensagem).replace(/\n/g, '<br>');
        },

        /**
         * Notificação de sucesso
         * @param {string} mensagem - Mensagem de sucesso
         * @param {number} duracao - Duração em ms
         */
        sucesso(mensagem, duracao = 4000) {
            this.exibir(mensagem, 'sucesso', duracao);
        },

        /**
         * Notificação de erro
         * @param {string|Object} erro - Mensagem de erro ou objeto de erro da API
         * @param {number} duracao - Duração em ms
         */
        erro(erro, duracao = 6000) {
            const mensagem = typeof erro === 'string' ? erro : Utils.Errors.formatarMensagem(erro);
            this.exibir(mensagem, 'erro', duracao);
        },

        /**
         * Notificação de aviso
         * @param {string} mensagem - Mensagem de aviso
         * @param {number} duracao - Duração em ms
         */
        aviso(mensagem, duracao = 5000) {
            this.exibir(mensagem, 'aviso', duracao);
        },

        /**
         * Notificação de informação
         * @param {string} mensagem - Mensagem informativa
         * @param {number} duracao - Duração em ms
         */
        info(mensagem, duracao = 4000) {
            this.exibir(mensagem, 'info', duracao);
        },

        /**
         * Remove todas as notificações
         */
        limparTodas() {
            if (this.container) {
                this.container.innerHTML = '';
            }
        }
    }
};

// Expõe globalmente
window.Utils = Utils;
