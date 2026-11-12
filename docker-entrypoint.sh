#!/bin/bash
set -e

echo "================================================================="
echo " Iniciando container Map-OS (Nexus Ticket)..."
echo "================================================================="

# 1. Garantir que application/.env existe para que o CodeIgniter carregue as configuraÃ§Ãµes
if [ ! -f /var/www/html/application/.env ]; then
    echo "[Entrypoint] Gerando application/.env a partir das variaveis de ambiente..."
    cat <<EOF > /var/www/html/application/.env
APP_NAME="${APP_NAME:-Nexus Ticket}"
APP_SUBNAME="${APP_SUBNAME:-Sistema de Controle de Ordens de ServiÃ§o}"
APP_TIMEZONE="${APP_TIMEZONE:-America/Sao_Paulo}"
APP_BASEURL="${APP_BASEURL:-}"
APP_CHARSET="${APP_CHARSET:-UTF-8}"
APP_ENCRYPTION_KEY="${APP_ENCRYPTION_KEY:-f7a8b9c0d1e2f3a4b5c6d7e8f9a0b1c2}"
APP_ENVIRONMENT="${APP_ENVIRONMENT:-production}"
CI_ENV="${CI_ENV:-production}"

APP_SESS_DRIVER="${APP_SESS_DRIVER:-files}"
APP_SESS_COOKIE_NAME="${APP_SESS_COOKIE_NAME:-MAPOS_SESSION}"
APP_SESS_EXPIRATION="${APP_SESS_EXPIRATION:-7200}"
APP_SESS_SAVE_PATH="${APP_SESS_SAVE_PATH:-}"
APP_SESS_MATCH_IP="${APP_SESS_MATCH_IP:-false}"
APP_SESS_TIME_TO_UPDATE="${APP_SESS_TIME_TO_UPDATE:-300}"
APP_SESS_REGENERATE_DESTROY="${APP_SESS_REGENERATE_DESTROY:-true}"

APP_COOKIE_PREFIX="${APP_COOKIE_PREFIX:-}"
APP_COOKIE_DOMAIN="${APP_COOKIE_DOMAIN:-}"
APP_COOKIE_PATH="${APP_COOKIE_PATH:-/}"
APP_COOKIE_SECURE="${APP_COOKIE_SECURE:-auto}"
APP_COOKIE_HTTPONLY="${APP_COOKIE_HTTPONLY:-true}"
APP_COOKIE_SAMESITE="${APP_COOKIE_SAMESITE:-Lax}"

APP_CSRF_PROTECTION="${APP_CSRF_PROTECTION:-true}"
APP_CSRF_TOKEN_NAME="${APP_CSRF_TOKEN_NAME:-MAPOS_CSRF_TOKEN}"
APP_CSRF_COOKIE_NAME="${APP_CSRF_COOKIE_NAME:-MAPOS_CSRF_COOKIE}"
APP_CSRF_EXPIRE="${APP_CSRF_EXPIRE:-7200}"
APP_CSRF_REGENERATE="${APP_CSRF_REGENERATE:-true}"

APP_COMPRESS_OUTPUT="${APP_COMPRESS_OUTPUT:-false}"
APP_SECURITY_HEADERS="${APP_SECURITY_HEADERS:-false}"
APP_CSP="${APP_CSP:-off}"

DB_DSN="${DB_DSN:-}"
DB_HOSTNAME="${DB_HOSTNAME:-}"
DB_PORT="${DB_PORT:-3306}"
DB_USERNAME="${DB_USERNAME:-}"
DB_PASSWORD="${DB_PASSWORD:-}"
DB_DATABASE="${DB_DATABASE:-NexusDB}"
DB_DRIVER="${DB_DRIVER:-mysqli}"
DB_PREFIX="${DB_PREFIX:-}"
DB_CHARSET="${DB_CHARSET:-utf8mb4}"
DB_COLLATION="${DB_COLLATION:-utf8mb4_general_ci}"
DB_SSL="${DB_SSL:-false}"
DB_SSL_CA="${DB_SSL_CA:-}"
API_ENABLED="${API_ENABLED:-false}"

EMAIL_PROTOCOL="${EMAIL_PROTOCOL:-smtp}"
EMAIL_SMTP_HOST="${EMAIL_SMTP_HOST:-}"
EMAIL_SMTP_CRYPTO="${EMAIL_SMTP_CRYPTO:-tls}"
EMAIL_SMTP_PORT="${EMAIL_SMTP_PORT:-587}"
EMAIL_SMTP_USER="${EMAIL_SMTP_USER:-}"
EMAIL_SMTP_PASS="${EMAIL_SMTP_PASS:-}"
EMAIL_FROM="${EMAIL_FROM:-nao-responder@nexustkt.com.br}"
EMAIL_FROM_NAME="${EMAIL_FROM_NAME:-Nexus Ticket}"
EOF
    chown www-data:www-data /var/www/html/application/.env
    chmod 600 /var/www/html/application/.env
fi

# 2. Executar script de inicializaÃ§Ã£o do banco de dados (se DB_HOSTNAME estiver preenchido)
if [ -n "$DB_HOSTNAME" ] && [ "$DB_HOSTNAME" != "enter_hostname" ]; then
    echo "[Entrypoint] Inicializando e verificando banco de dados..."
    php /var/www/html/scripts/db_init.php || echo "[Entrypoint] Aviso: Falha ao rodar db_init.php. Prosseguindo..."
else
    echo "[Entrypoint] DB_HOSTNAME nao definido. Pulando verificacao do banco."
fi

# 3. Garantir permissÃµes das pastas de armazenamento e logs
mkdir -p /var/www/html/application/storage \
         /var/www/html/application/logs \
         /var/www/html/assets/uploads \
         /var/www/html/assets/userImage
chown -R www-data:www-data /var/www/html/application/storage /var/www/html/application/logs /var/www/html/assets/uploads /var/www/html/assets/userImage
chmod -R 775 /var/www/html/application/storage /var/www/html/application/logs /var/www/html/assets/uploads /var/www/html/assets/userImage

echo "[Entrypoint] Tudo pronto! Executando: $@"
exec "$@"
