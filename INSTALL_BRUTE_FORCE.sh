#!/bin/bash
# Script de instalação da proteção Brute Force

echo "==================================="
echo "Instalação: Proteção Brute Force"
echo "==================================="
echo ""

# Verifica se MySQL está disponível
if ! command -v mysql &> /dev/null; then
    echo "⚠️  MySQL não encontrado no PATH"
    echo ""
    echo "Por favor, execute manualmente:"
    echo "mysql -u root -p ecletech < database/migrations/010_criar_tabela_login_attempts.sql"
    echo ""
    exit 1
fi

# Executa migration
echo "📦 Executando migration..."
mysql -u root ecletech < database/migrations/010_criar_tabela_login_attempts.sql

if [ $? -eq 0 ]; then
    echo "✅ Migration executada com sucesso!"
    echo ""
    echo "Tabelas criadas:"
    echo "  - login_attempts"
    echo "  - login_bloqueios"
    echo ""
    echo "Event criado:"
    echo "  - limpar_login_attempts_antigos (executa diariamente)"
    echo ""
    echo "🎉 Instalação concluída!"
    echo ""
    echo "Acesse: http://localhost/brute_force.html"
else
    echo "❌ Erro ao executar migration"
    exit 1
fi
