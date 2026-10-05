<?php

/**
 * Prepara o banco quando o container sobe (deploy). Pode rodar a cada boot:
 * cada etapa é aplicada uma única vez e fica registrada em crud_iti_instalacao.
 *
 *   schema                 banco vazio recebe data/sql/schema.sql
 *   situacao-por-vinculo   banco anterior recebe a atualização e é reimportado
 *   dados-iti              structure.json importado uma vez
 *   restauracao:<valor>    com RESTAURAR_DADOS=<valor>, AC/AC N2/AR são apagadas
 *                          e reimportadas (uma vez por valor; troque o valor
 *                          para restaurar de novo a demonstração)
 *
 * Também garante o usuário de demonstração quando DEMO_EMAIL e DEMO_SENHA
 * estão definidos (trocar DEMO_SENHA e reiniciar troca a senha).
 *
 * Uma trava nomeada do MySQL impede duas preparações simultâneas.
 */

declare(strict_types=1);

use Auth\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Icp\Service\ImportadorEstrutura;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

const TRAVA           = 'crud_iti_preparacao';
const ARQUIVO_ITI     = 'data/exemplo/structure.json';
const TENTATIVAS      = 10;
const ESPERA_SEGUNDOS = 3;

/** @var Psr\Container\ContainerInterface $container */
$container = require 'config/container.php';
/** @var EntityManagerInterface $entityManager */
$entityManager = $container->get('doctrine.entitymanager.orm_default');
$conexao       = $entityManager->getConnection();

conectar($conexao);

if ((int) $conexao->fetchOne('SELECT GET_LOCK(?, 60)', [TRAVA]) !== 1) {
    falhar('Outra preparação do banco está em andamento.');
}

try {
    $conexao->executeStatement(
        'CREATE TABLE IF NOT EXISTS crud_iti_instalacao ('
        . 'etapa VARCHAR(100) NOT NULL PRIMARY KEY, aplicada_em DATETIME NOT NULL'
        . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB'
    );
    $aplicadas = array_flip($conexao->fetchFirstColumn('SELECT etapa FROM crud_iti_instalacao'));
    $reimportar = false;

    $temTabelas = $conexao->fetchOne("SHOW TABLES LIKE 'ar'") !== false;

    if (! $temTabelas) {
        executarArquivoSql($conexao, 'data/sql/schema.sql');
        registrar($conexao, 'schema');
        registrar($conexao, 'situacao-por-vinculo');
        informar('Tabelas criadas.');
    } else {
        $temSituacao = $conexao->fetchOne("SHOW COLUMNS FROM ar_ac_n2 LIKE 'situacao'") !== false;
        if (! $temSituacao) {
            executarArquivoSql($conexao, 'data/sql/atualizacao-situacao-por-vinculo.sql');
            $reimportar = true;
            informar('Banco atualizado para a situação por vínculo.');
        }
        foreach (['schema', 'situacao-por-vinculo'] as $etapa) {
            if (! isset($aplicadas[$etapa])) {
                registrar($conexao, $etapa);
            }
        }
    }

    $restauracao = getenv('RESTAURAR_DADOS');
    if (is_string($restauracao) && $restauracao !== '' && ! isset($aplicadas['restauracao:' . $restauracao])) {
        $conexao->transactional(static function (Connection $c): void {
            foreach (['ar_ac_n2', 'ar', 'ac_n2', 'ac'] as $tabela) {
                $c->executeStatement('DELETE FROM ' . $tabela);
            }
        });
        $reimportar = true;
        registrar($conexao, 'restauracao:' . $restauracao);
        informar('Dados de AC, AC N2 e AR apagados para restauração.');
    }

    if ($reimportar || ! isset($aplicadas['dados-iti'])) {
        /** @var ImportadorEstrutura $importador */
        $importador = $container->get(ImportadorEstrutura::class);
        $resultado  = $importador->importarJson((string) file_get_contents(ARQUIVO_ITI));
        if (! isset($aplicadas['dados-iti'])) {
            registrar($conexao, 'dados-iti');
        }
        informar(sprintf(
            'structure.json importado: %d AR criadas, %d vínculos criados.',
            $resultado->totais['AR']['criados'],
            $resultado->vinculosCriados
        ));
    }

    garantirUsuarioDemo($entityManager);
} finally {
    $conexao->fetchOne('SELECT RELEASE_LOCK(?)', [TRAVA]);
}

informar('Banco pronto.');

function conectar(Connection $conexao): void
{
    for ($tentativa = 1; $tentativa <= TENTATIVAS; $tentativa++) {
        try {
            $conexao->fetchOne('SELECT 1');

            return;
        } catch (Doctrine\DBAL\Exception\ConnectionException $e) {
            informar(sprintf('Banco indisponível (tentativa %d de %d): %s', $tentativa, TENTATIVAS, $e->getMessage()));
            sleep(ESPERA_SEGUNDOS);
        }
    }

    falhar('Não foi possível conectar ao banco.');
}

function executarArquivoSql(Connection $conexao, string $arquivo): void
{
    $sql = (string) preg_replace('/^--.*$/m', '', (string) file_get_contents($arquivo));

    foreach (array_filter(array_map('trim', explode(';', $sql))) as $comando) {
        $conexao->executeStatement($comando);
    }
}

function registrar(Connection $conexao, string $etapa): void
{
    $conexao->executeStatement(
        'INSERT INTO crud_iti_instalacao (etapa, aplicada_em) VALUES (?, NOW())',
        [$etapa]
    );
}

function garantirUsuarioDemo(EntityManagerInterface $entityManager): void
{
    $email = getenv('DEMO_EMAIL');
    $senha = getenv('DEMO_SENHA');
    if (! is_string($email) || $email === '' || ! is_string($senha) || $senha === '') {
        return;
    }

    $usuario = $entityManager->getRepository(Usuario::class)->findOneBy(['email' => Usuario::normalizarEmail($email)]);

    if (! $usuario instanceof Usuario) {
        $entityManager->persist(new Usuario($email, 'Usuário Demonstração', $senha));
        informar('Usuário de demonstração criado.');
    } elseif (! $usuario->verificarSenha($senha)) {
        $usuario->definirSenha($senha);
        informar('Senha do usuário de demonstração atualizada.');
    } else {
        return;
    }

    $entityManager->flush();
}

function informar(string $mensagem): void
{
    fwrite(STDOUT, '[preparar-banco] ' . $mensagem . "\n");
}

function falhar(string $mensagem): never
{
    fwrite(STDERR, '[preparar-banco] ' . $mensagem . "\n");
    exit(1);
}
