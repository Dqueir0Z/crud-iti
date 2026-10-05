<?php

/**
 * Configuração por variáveis de ambiente, usada no deploy (Docker / Render).
 *
 * Sem DB_HOST não altera nada: o desenvolvimento local continua usando
 * config/autoload/local.php. Nenhum segredo fica neste arquivo; os valores
 * vêm do painel do serviço de hospedagem (ver "Demonstração online" no README).
 *
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD  conexão MySQL
 *   DB_SSL_CA_ARQUIVO   caminho do certificado da CA do banco (TLS verificado);
 *                       bin/iniciar-container.sh também aceita o texto em DB_SSL_CA
 *   DB_SSL=desligado    só para testar o container com um MySQL local sem TLS
 *   APP_URL             endereço público (o Render informa RENDER_EXTERNAL_URL)
 */

declare(strict_types=1);

use Doctrine\DBAL\Driver\PDO\MySQL\Driver;

$ambiente = static function (string $nome): ?string {
    $valor = getenv($nome);

    return $valor === false || $valor === '' ? null : $valor;
};

$config = [];

if ($ambiente('DB_HOST') !== null) {
    $ca = $ambiente('DB_SSL_CA_ARQUIVO');

    // O banco publicado fica fora da máquina: sem a CA, a conexão não seria
    // verificada. Melhor não subir do que conectar sem TLS.
    if ($ca === null && $ambiente('DB_SSL') !== 'desligado') {
        throw new RuntimeException(
            'DB_SSL_CA_ARQUIVO não definido: a conexão com o banco publicado exige TLS verificado.'
        );
    }

    $config['doctrine']['connection']['orm_default'] = [
        'driverClass' => Driver::class,
        'params'      => [
            'host'          => $ambiente('DB_HOST'),
            'port'          => $ambiente('DB_PORT') ?? '3306',
            'user'          => $ambiente('DB_USER'),
            'password'      => $ambiente('DB_PASSWORD'),
            'dbname'        => $ambiente('DB_NAME'),
            'charset'       => 'utf8mb4',
            'driverOptions' => $ca === null ? [] : [
                PDO::MYSQL_ATTR_SSL_CA                 => $ca,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => true,
            ],
        ],
    ];
}

$urlPublica = $ambiente('APP_URL') ?? $ambiente('RENDER_EXTERNAL_URL');

if ($urlPublica !== null) {
    // O QR Code passa a apontar para o endereço público (lido pelo celular).
    $config['icp']['qrcode']['base_url'] = $urlPublica;

    if (str_starts_with($urlPublica, 'https://')) {
        $config['session_config']['cookie_secure'] = true;
    }
}

return $config;
