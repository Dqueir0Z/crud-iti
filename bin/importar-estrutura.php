<?php

/**
 * Importa um structure.json pela linha de comando (mesma lógica da tela de upload).
 *
 * Uso:
 *   php bin/importar-estrutura.php caminho/structure.json
 */

declare(strict_types=1);

use Icp\Exception\EstruturaInvalidaException;
use Icp\Service\ImportadorEstrutura;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

$caminho = $argv[1] ?? '';
if ($caminho === '' || ! is_file($caminho) || ! is_readable($caminho)) {
    fwrite(STDERR, "Uso: php bin/importar-estrutura.php caminho/structure.json\n");
    exit(1);
}

/** @var Psr\Container\ContainerInterface $container */
$container = require 'config/container.php';
/** @var ImportadorEstrutura $importador */
$importador = $container->get(ImportadorEstrutura::class);

$inicio = microtime(true);

try {
    $resultado = $importador->importarJson((string) file_get_contents($caminho));
} catch (EstruturaInvalidaException $e) {
    fwrite(STDERR, 'Arquivo inválido: ' . $e->getMessage() . "\n");
    exit(1);
}

foreach ($resultado->totais as $entidade => $totais) {
    fwrite(STDOUT, sprintf(
        "%-6s criados: %5d  atualizados: %5d  sem alteração: %5d\n",
        $entidade,
        $totais['criados'],
        $totais['atualizados'],
        $totais['inalterados']
    ));
}
fwrite(STDOUT, sprintf("Vínculos AR-AC N2 criados: %d\n", $resultado->vinculosCriados));

foreach ($resultado->avisos as $aviso) {
    fwrite(STDOUT, 'Aviso: ' . $aviso . "\n");
}

fwrite(STDOUT, sprintf("Concluído em %.1fs.\n", microtime(true) - $inicio));
