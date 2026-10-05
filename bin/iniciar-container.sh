#!/usr/bin/env bash
# Inicialização do container (Render ou docker run): prepara o banco e sobe o Apache.
set -euo pipefail

cd /var/www/app

# Certificado da CA do MySQL: arquivo secreto do Render (DB_SSL_CA_ARQUIVO, ex.:
# /etc/secrets/ca.pem) ou texto colado numa variável (DB_SSL_CA). Em ambos os
# casos vira /tmp/db-ca.pem, legível pelo Apache (o arquivo secreto pode ser só do root).
if [[ -n "${DB_SSL_CA_ARQUIVO:-}" ]]; then
    cp "$DB_SSL_CA_ARQUIVO" /tmp/db-ca.pem
elif [[ -n "${DB_SSL_CA:-}" ]]; then
    printf '%s\n' "$DB_SSL_CA" > /tmp/db-ca.pem
fi
if [[ -f /tmp/db-ca.pem ]]; then
    chmod 644 /tmp/db-ca.pem
    export DB_SSL_CA_ARQUIVO=/tmp/db-ca.pem
fi

mkdir -p /tmp/sessoes data/cache data/DoctrineORMModule/Proxy
chown www-data:www-data /tmp/sessoes
chmod 700 /tmp/sessoes

# O cache de configuração guarda os valores das variáveis de ambiente.
rm -f data/cache/*.php
chown -R www-data:www-data data

runuser -u www-data -- php bin/preparar-banco.php

exec apache2-foreground
