<?php

declare(strict_types=1);

namespace IcpTest\Integracao;

use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\ConnectionException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Icp\Entity\Ac;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Icp\Entity\VinculoArAcN2;
use Icp\Enum\Situacao;
use Icp\Service\ImportadorEstrutura;
use Icp\Service\LeitorEstrutura;
use Laminas\Mvc\Application;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

use function chdir;
use function getcwd;
use function is_file;
use function json_encode;

/**
 * Comportamento do vínculo AR ↔ AC N2 no MySQL de verdade (FK, flush, importação).
 *
 * Usa o banco configurado em config/autoload/local.php, sempre dentro de uma
 * transação desfeita no tearDown: nada fica gravado. Sem banco, é pulado.
 * Os ids do ITI usados aqui (2.100.000.000+) não existem no arquivo real.
 */
#[Group('integracao')]
final class VinculoArAcN2IntegracaoTest extends TestCase
{
    private const BASE = 2100000000;

    private EntityManagerInterface $em;
    private Connection $conexao;

    protected function setUp(): void
    {
        $diretorio = getcwd();
        chdir(__DIR__ . '/../../../..');

        try {
            if (! is_file('config/autoload/local.php')) {
                self::markTestSkipped('Sem config/autoload/local.php: banco não configurado.');
            }

            // Erros de configuração ou de bootstrap não são engolidos: só a falta
            // de conexão com o MySQL faz o teste ser pulado.
            $app = Application::init(require 'config/application.config.php');
            /** @var EntityManagerInterface $em */
            $em = $app->getServiceManager()->get('doctrine.entitymanager.orm_default');

            try {
                $em->getConnection()->executeQuery('SELECT 1');
            } catch (ConnectionException $e) {
                self::markTestSkipped('MySQL indisponível para o teste de integração: ' . $e->getMessage());
            }
        } finally {
            chdir((string) $diretorio);
        }

        $this->em      = $em;
        $this->conexao = $em->getConnection();
        $this->conexao->beginTransaction();
    }

    protected function tearDown(): void
    {
        if (isset($this->conexao) && $this->conexao->isTransactionActive()) {
            $this->conexao->rollBack();
        }
    }

    /** @return array{AcN2, AcN2, Ar} AC N2 "A" e "B" e uma AR ligada só à "A" */
    private function criarCenario(): array
    {
        $ac = new Ac('AC TESTE');
        $a  = new AcN2('AC N2 A', $ac);
        $b  = new AcN2('AC N2 B', $ac);
        $ar = new Ar('AR TESTE');
        $ar->vincularAcN2($a, Situacao::EmCredenciamento);

        // Só a AR é persistida explicitamente; o vínculo vai por cascade e os
        // pais novos (AC, AC N2) precisam ser persistidos antes do flush.
        $this->em->persist($ac);
        $this->em->persist($a);
        $this->em->persist($b);
        $this->em->persist($ar);
        $this->em->flush();

        return [$a, $b, $ar];
    }

    private function contarVinculos(Ar $ar): int
    {
        return (int) $this->conexao->fetchOne('SELECT COUNT(*) FROM ar_ac_n2 WHERE ar_id = ?', [$ar->getId()]);
    }

    public function testVinculoEhGravadoComASituacao(): void
    {
        [$a, , $ar] = $this->criarCenario();

        self::assertSame(
            Situacao::EmCredenciamento->value,
            (int) $this->conexao->fetchOne(
                'SELECT situacao FROM ar_ac_n2 WHERE ar_id = ? AND ac_n2_id = ?',
                [$ar->getId(), $a->getId()]
            )
        );
    }

    /**
     * Regressão (achado B2 da revisão final do Codex): remover a AC N2 apagava
     * as linhas de ar_ac_n2 antes do DELETE, e a AR ficava sem AC N2.
     */
    public function testExcluirAcN2ComVinculoEhBloqueadoPeloBanco(): void
    {
        [$a, , $ar] = $this->criarCenario();
        $this->em->clear();

        $acN2 = $this->em->find(AcN2::class, $a->getId());
        self::assertInstanceOf(AcN2::class, $acN2);

        $this->em->remove($acN2);
        try {
            $this->em->flush();
            self::fail('A exclusão deveria ter sido recusada pelo FK RESTRICT.');
        } catch (ForeignKeyConstraintViolationException) {
            self::assertSame(1, $this->contarVinculos($ar));
        }
    }

    public function testExcluirAcN2ComAColecaoDeVinculosCarregadaTambemEhBloqueado(): void
    {
        [$a, , $ar] = $this->criarCenario();
        $this->em->clear();

        $acN2 = $this->em->find(AcN2::class, $a->getId());
        self::assertInstanceOf(AcN2::class, $acN2);
        /** @var Collection<int, VinculoArAcN2> $colecao */
        $colecao = (new ReflectionProperty(AcN2::class, 'vinculos'))->getValue($acN2);
        self::assertCount(1, $colecao->toArray()); // inicializa a coleção inversa

        $this->em->remove($acN2);
        $this->expectException(ForeignKeyConstraintViolationException::class);
        $this->em->flush();
    }

    public function testTrocarRetirarEDevolverVinculosNoMesmoFlush(): void
    {
        [$a, $b, $ar] = $this->criarCenario();

        $ar->definirAcN2s([$b]);      // retira A, cria B
        $ar->definirAcN2s([$a, $b]);  // devolve A (mesma instância) antes do flush
        $this->em->flush();
        self::assertSame(2, $this->contarVinculos($ar));

        $ar->definirAcN2s([$b]);
        $this->em->flush();
        self::assertSame(1, $this->contarVinculos($ar));
    }

    /**
     * Retirar, gravar e devolver a mesma AC N2 cria um vínculo novo com a
     * situação geral atual, e não o antigo (revisão do Codex).
     */
    public function testDevolverAcN2DepoisDoFlushCriaVinculoNovoComASituacaoAtual(): void
    {
        [$a, $b, $ar] = $this->criarCenario();   // A em credenciamento
        $ar->vincularAcN2($b, Situacao::Credenciado);
        $this->em->flush();

        $ar->definirAcN2s([$b]);
        $ar->recalcularSituacao();
        $this->em->flush();
        self::assertSame(Situacao::Credenciado, $ar->getSituacao());

        $ar->definirAcN2s([$a, $b]);
        $this->em->flush();

        self::assertSame(
            Situacao::Credenciado->value,
            (int) $this->conexao->fetchOne(
                'SELECT situacao FROM ar_ac_n2 WHERE ar_id = ? AND ac_n2_id = ?',
                [$ar->getId(), $a->getId()]
            )
        );
    }

    public function testExcluirArRemoveOsVinculos(): void
    {
        [, , $ar] = $this->criarCenario();
        $id = $ar->getId();

        $this->em->remove($ar);
        $this->em->flush();

        self::assertSame(0, (int) $this->conexao->fetchOne('SELECT COUNT(*) FROM ar_ac_n2 WHERE ar_id = ?', [$id]));
    }

    /**
     * Reimportação: idempotente, devolve a situação do arquivo a um vínculo
     * alterado e preserva vínculos que não estão no arquivo.
     */
    public function testReimportacaoAtualizaSituacaoEPreservaVinculosForaDoArquivo(): void
    {
        $b    = self::BASE;
        $json = (string) json_encode([
            'tipo'                 => 'ac-root',
            'entidades_vinculadas' => [[
                'tipo' => 'ac-1', 'id' => $b + 1, 'situacao' => 4002, 'nome' => 'AC INTEGRACAO',
                'entidades_vinculadas' => [
                    ['tipo' => 'ac-2', 'id' => $b + 10, 'situacao' => 4002, 'nome' => 'N2 X', 'entidades_vinculadas' => [
                        ['tipo' => 'ar', 'id' => $b + 100, 'situacao' => 4002, 'nome' => 'AR MISTA'],
                    ]],
                    ['tipo' => 'ac-2', 'id' => $b + 11, 'situacao' => 4002, 'nome' => 'N2 Y', 'entidades_vinculadas' => [
                        ['tipo' => 'ar', 'id' => $b + 100, 'situacao' => 4001, 'nome' => 'AR MISTA'],
                    ]],
                ],
            ]],
        ]);

        $importador = $this->importador();
        $primeira   = $importador->importarJson($json);
        self::assertSame(2, $primeira->vinculosCriados);

        $ar = $this->em->getRepository(Ar::class)->findOneBy(['itiId' => $b + 100]);
        self::assertInstanceOf(Ar::class, $ar);
        self::assertSame(Situacao::Credenciado, $ar->getSituacao());

        // Mudança manual num vínculo e um vínculo novo fora do arquivo.
        $y      = $this->em->getRepository(AcN2::class)->findOneBy(['itiId' => $b + 11]);
        $manual = new AcN2('N2 MANUAL', $y->getAc());
        $this->em->persist($manual);
        $ar->aplicarSituacaoATodos(Situacao::Credenciado);
        $ar->vincularAcN2($manual);
        $this->em->flush();

        $segunda = $importador->importarJson($json);
        self::assertSame(0, $segunda->vinculosCriados);
        self::assertSame(1, $segunda->vinculosAtualizados, 'o vínculo com N2 Y volta a "em credenciamento"');
        self::assertSame(3, $this->contarVinculos($ar), 'o vínculo manual é preservado');

        $terceira = $importador->importarJson($json);
        self::assertSame(0, $terceira->vinculosCriados + $terceira->vinculosAtualizados);
        self::assertSame(['criados' => 0, 'atualizados' => 0, 'inalterados' => 1], $terceira->totais['AR']);
    }

    /** Sobre o mesmo EntityManager (e a mesma transação) do teste. */
    private function importador(): ImportadorEstrutura
    {
        return new ImportadorEstrutura($this->em, new LeitorEstrutura());
    }
}
