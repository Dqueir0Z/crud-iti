<?php

/**
 * Prepara o banco quando o container sobe (deploy). Pode rodar a cada boot e
 * retoma o que tiver ficado pela metade se um boot anterior foi interrompido:
 *
 * 1. Estrutura: cada tabela e cada FK de data/sql/schema.sql é criada só se
 *    estiver faltando (o estado vem do próprio banco, não de um marcador).
 * 2. Banco anterior à situação por vínculo (ar_ac_n2 sem a coluna situacao):
 *    cria a coluna e copia a situação da AR para os vínculos; a cópia e a
 *    pendência de reimportação são gravadas na mesma transação.
 * 3. Dados do ITI: importados se o banco não tem AR nem o marcador "dados-iti",
 *    ou se há reimportação pendente (só sai depois da importação concluída).
 *    Banco que já tem AR e não tem o marcador é adotado sem reimportar.
 * 4. RESTAURAR_DADOS=<valor>: apaga AC/AC N2/AR, reimporta e registra
 *    "restauracao:<valor>" numa única transação: ou tudo, ou nada. Troque o
 *    valor para restaurar de novo.
 * 5. Usuário de demonstração (DEMO_EMAIL / DEMO_SENHA): criado ou com a senha
 *    atualizada.
 *
 * Usa a mesma trava nomeada da importação pela tela (ImportadorEstrutura::TRAVA):
 * preparação e upload nunca gravam AC/AC N2/AR ao mesmo tempo.
 */

declare(strict_types=1);

use Auth\Entity\Usuario;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Icp\Service\ImportadorEstrutura;

chdir(dirname(__DIR__));
require 'vendor/autoload.php';

const TENTATIVAS      = 10;
const ESPERA_SEGUNDOS = 3;
const ESPERA_TRAVA    = 120;
/** A coluna etapa tem 100 caracteres, incluindo o prefixo "restauracao:". */
const TAMANHO_MAXIMO_RESTAURACAO = 80;

/** @var Psr\Container\ContainerInterface $container */
$container = require 'config/container.php';
/** @var EntityManagerInterface $entityManager */
$entityManager = $container->get('doctrine.entitymanager.orm_default');
/** @var ImportadorEstrutura $importador */
$importador = $container->get(ImportadorEstrutura::class);
$conexao    = $entityManager->getConnection();
$arquivoIti = getenv('ARQUIVO_ESTRUTURA') ?: 'data/exemplo/structure.json';

conectar($conexao);

if ((int) $conexao->fetchOne('SELECT GET_LOCK(?, ?)', [ImportadorEstrutura::TRAVA, ESPERA_TRAVA]) !== 1) {
    falhar('Outra preparação ou importação está em andamento.');
}

try {
    $conexao->executeStatement(
        'CREATE TABLE IF NOT EXISTS crud_iti_instalacao ('
        . 'etapa VARCHAR(100) NOT NULL PRIMARY KEY, aplicada_em DATETIME NOT NULL'
        . ') DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB'
    );

    // Banco anterior à situação por vínculo: a tabela existe sem a coluna. O
    // marcador "pendente:copiar-situacao" é gravado antes do ALTER (DDL não entra
    // em transação) e só sai junto com a cópia, então um boot interrompido no meio
    // refaz a cópia no próximo.
    if (tabelaExiste($conexao, 'ar_ac_n2') && ! colunaExiste($conexao, 'ar_ac_n2', 'situacao')) {
        marcar($conexao, 'pendente:copiar-situacao');
        $conexao->executeStatement('ALTER TABLE ar_ac_n2 ADD situacao SMALLINT DEFAULT 4002 NOT NULL');
    }

    completarEstrutura($conexao, 'data/sql/schema.sql');

    if (marcado($conexao, 'pendente:copiar-situacao')) {
        $conexao->transactional(static function (Connection $c): void {
            $c->executeStatement('UPDATE ar_ac_n2 v JOIN ar r ON r.id = v.ar_id SET v.situacao = r.situacao');
            desmarcar($c, 'pendente:copiar-situacao');
            marcar($c, 'pendente:reimportar');
        });
        informar('Banco atualizado para a situação por vínculo; reimportação agendada.');
    }

    // Banco existente sem o marcador (ex.: instalado pelo README) é adotado como
    // está: os dados não são sobrescritos. Como a importação grava tudo numa
    // transação, ter AR sem o marcador nunca é uma importação pela metade.
    if (! marcado($conexao, 'dados-iti') && (int) $conexao->fetchOne('SELECT COUNT(*) FROM ar') > 0) {
        marcar($conexao, 'dados-iti');
        informar('Banco existente adotado sem reimportar os dados.');
    }

    $restauracao = getenv('RESTAURAR_DADOS');
    if (is_string($restauracao) && $restauracao !== '') {
        if (strlen($restauracao) > TAMANHO_MAXIMO_RESTAURACAO) {
            falhar(sprintf('RESTAURAR_DADOS deve ter até %d caracteres (ex.: a data).', TAMANHO_MAXIMO_RESTAURACAO));
        }
    }
    if (is_string($restauracao) && $restauracao !== '' && ! marcado($conexao, 'restauracao:' . $restauracao)) {
        $json = lerArquivo($arquivoIti);
        $conexao->transactional(static function (Connection $c) use ($importador, $json, $restauracao): void {
            foreach (['ar_ac_n2', 'ar', 'ac_n2', 'ac'] as $tabela) {
                $c->executeStatement('DELETE FROM ' . $tabela);
            }
            $importador->importarJson($json);
            marcar($c, 'restauracao:' . $restauracao);
            marcar($c, 'dados-iti');
            desmarcar($c, 'pendente:reimportar');
        });
        informar('Dados de AC, AC N2 e AR restaurados a partir do structure.json.');
    }

    if (! marcado($conexao, 'dados-iti') || marcado($conexao, 'pendente:reimportar')) {
        $resultado = $importador->importarJson(lerArquivo($arquivoIti));
        $conexao->transactional(static function (Connection $c): void {
            marcar($c, 'dados-iti');
            desmarcar($c, 'pendente:reimportar');
        });
        informar(sprintf(
            'structure.json importado: %d AR criadas, %d vínculos criados, %d vínculos atualizados.',
            $resultado->totais['AR']['criados'],
            $resultado->vinculosCriados,
            $resultado->vinculosAtualizados
        ));
    }

    garantirUsuarioDemo($entityManager);
} finally {
    $conexao->fetchOne('SELECT RELEASE_LOCK(?)', [ImportadorEstrutura::TRAVA]);
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

/**
 * Executa os comandos do schema.sql que ainda faltam: CREATE TABLE de tabelas
 * inexistentes e ADD CONSTRAINT de FKs inexistentes. DDL no MySQL não volta
 * atrás numa transação, então a retomada é por verificação de cada objeto.
 */
function completarEstrutura(Connection $conexao, string $arquivo): void
{
    foreach (comandosSql($arquivo) as $comando) {
        if (preg_match('/^CREATE TABLE (\w+)/i', $comando, $m) === 1) {
            if (! tabelaExiste($conexao, $m[1])) {
                $conexao->executeStatement($comando);
                informar('Tabela criada: ' . $m[1]);
            }
        } elseif (preg_match('/^ALTER TABLE (\w+) ADD CONSTRAINT (\w+)/i', $comando, $m) === 1) {
            if (! restricaoExiste($conexao, $m[1], $m[2])) {
                $conexao->executeStatement($comando);
                informar('Chave estrangeira criada: ' . $m[2]);
            }
        } else {
            falhar('Comando inesperado em ' . $arquivo . ': ' . substr($comando, 0, 60));
        }
    }
}

/** @return list<string> */
function comandosSql(string $arquivo): array
{
    $sql = (string) preg_replace('/^--.*$/m', '', lerArquivo($arquivo));

    return array_values(array_filter(array_map('trim', explode(';', $sql))));
}

function tabelaExiste(Connection $conexao, string $tabela): bool
{
    return (int) $conexao->fetchOne(
        'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
        [$tabela]
    ) > 0;
}

function colunaExiste(Connection $conexao, string $tabela, string $coluna): bool
{
    return (int) $conexao->fetchOne(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
        [$tabela, $coluna]
    ) > 0;
}

function restricaoExiste(Connection $conexao, string $tabela, string $nome): bool
{
    return (int) $conexao->fetchOne(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
         WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
        [$tabela, $nome]
    ) > 0;
}

function marcado(Connection $conexao, string $etapa): bool
{
    return $conexao->fetchOne('SELECT 1 FROM crud_iti_instalacao WHERE etapa = ?', [$etapa]) !== false;
}

function marcar(Connection $conexao, string $etapa): void
{
    $conexao->executeStatement(
        'INSERT IGNORE INTO crud_iti_instalacao (etapa, aplicada_em) VALUES (?, NOW())',
        [$etapa]
    );
}

function desmarcar(Connection $conexao, string $etapa): void
{
    $conexao->executeStatement('DELETE FROM crud_iti_instalacao WHERE etapa = ?', [$etapa]);
}

function lerArquivo(string $arquivo): string
{
    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        falhar('Arquivo não encontrado: ' . $arquivo);
    }

    return $conteudo;
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
