#!/bin/bash
# 🚀 Deploy automatizado para endor.dev/s/lamp
# PHP 8.3 + MariaDB no Alpine Linux

set -e

echo "🚀 Deploy Teleman para endor.dev/s/lamp"
echo "======================================="

# 1. Verificar ambiente
echo "📋 Verificando ambiente..."
php --version
composer --version 2>/dev/null || echo "Composer não instalado"
mysql --version 2>/dev/null || echo "MySQL client não instalado"

# 2. Configurar diretório
HTDOCS="/var/www/localhost/htdocs"
mkdir -p "$HTDOCS"

# 3. Instalar dependências
echo "📦 Instalando dependências..."
cd "$HTDOCS"

# Instalar Composer se não existir
if [ ! -f "composer.phar" ]; then
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# Instalar dependências PHP
composer install --no-interaction --optimize-autoloader

# 4. Configurar .env
echo "⚙️ Configurando .env..."
cp .env.example .env

# Ajustar para MariaDB
sed -i 's/DB_CONNECTION=sqlite/DB_CONNECTION=mysql/' .env
sed -i 's/# DB_HOST=127.0.0.1/DB_HOST=127.0.0.1/' .env
sed -i 's/# DB_PORT=3306/DB_PORT=3306/' .env
sed -i 's/# DB_DATABASE=teleman/DB_DATABASE=teleman/' .env
sed -i 's/# DB_USERNAME=root/DB_USERNAME=root/' .env
sed -i 's/# DB_PASSWORD=/DB_PASSWORD=root/' .env

# Gerar chave
php artisan key:generate

# 5. Criar banco de dados
echo "🗄️ Criando banco de dados..."
mysql -h 127.0.0.1 -u root -proot -e "CREATE DATABASE IF NOT EXISTS teleman CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>/dev/null || \
mysql -u root -proot -e "CREATE DATABASE IF NOT EXISTS teleman CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 6. Executar migrations
echo "🏃 Executando migrations..."
php artisan migrate --force

# 7. Build frontend
echo "🔨 Buildando frontend..."
npm install
npm run build

# 8. Cache e permissões
echo "🔒 Configurando cache..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permissões
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "✅ Deploy concluído!"
echo ""
echo "🌐 Acesse: http://localhost"
echo "🔑 Credenciais MariaDB: root / root"