<?php

/**
 * Cria um usuário de acesso (ou redefine a senha, se o e-mail já existir).
 *
 * Uso:
 *   php bin/criar-usuario.php email@exemplo.com "Nome do usuário"
 *
 * A senha é pedida no terminal (sem eco). Para uso não interativo, defina a
 * variável de ambiente CRUD_ITI_SENHA. A senha nunca é aceita como argumento,
 * para não ficar no histórico do shell.
 */

declare(strict_types=1);

use Auth\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

const TAMANHO_MINIMO_SENHA = 8;

$email = $argv[1] ?? '';
$nome  = $argv[2] ?? 'Administrador';

if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php bin/criar-usuario.php email@exemplo.com \"Nome\"\n");
    exit(1);
}

$senha = getenv('CRUD_ITI_SENHA');
if ($senha === false || $senha === '') {
    $senha = lerSenha('Senha: ');
    if ($senha !== lerSenha('Confirme a senha: ')) {
        fwrite(STDERR, "As senhas não conferem.\n");
        exit(1);
    }
}

if (mb_strlen($senha) < TAMANHO_MINIMO_SENHA) {
    fwrite(STDERR, sprintf("A senha deve ter ao menos %d caracteres.\n", TAMANHO_MINIMO_SENHA));
    exit(1);
}

/** @var Psr\Container\ContainerInterface $container */
$container = require 'config/container.php';
/** @var EntityManagerInterface $entityManager */
$entityManager = $container->get('doctrine.entitymanager.orm_default');

$usuario = $entityManager->getRepository(Usuario::class)->findOneBy(['email' => Usuario::normalizarEmail($email)]);

if ($usuario instanceof Usuario) {
    $usuario->definirSenha($senha);
    $mensagem = 'Senha redefinida para %s.';
} else {
    $usuario = new Usuario($email, $nome, $senha);
    $entityManager->persist($usuario);
    $mensagem = 'Usuário %s criado.';
}

$entityManager->flush();
fwrite(STDOUT, sprintf($mensagem . "\n", $usuario->getEmail()));

function lerSenha(string $rotulo): string
{
    fwrite(STDOUT, $rotulo);

    $terminal = DIRECTORY_SEPARATOR === '/' && stream_isatty(STDIN);
    if ($terminal) {
        shell_exec('stty -echo');
    }

    $linha = fgets(STDIN);

    if ($terminal) {
        shell_exec('stty echo');
        fwrite(STDOUT, "\n");
    }

    return rtrim((string) $linha, "\r\n");
}
