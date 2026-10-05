<?php

declare(strict_types=1);

namespace ApplicationTest\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Laminas\Authentication\AuthenticationService;
use Laminas\Authentication\Storage\NonPersistent;
use Laminas\Stdlib\ArrayUtils;
use Laminas\Test\PHPUnit\Controller\AbstractHttpControllerTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Garante que nenhuma rota de CRUD é acessível sem login.
 * A autenticação usa armazenamento em memória para não depender de sessão nem de banco.
 */
class ProtecaoDeRotasTest extends AbstractHttpControllerTestCase
{
    public function setUp(): void
    {
        $this->setApplicationConfig(ArrayUtils::merge(
            include __DIR__ . '/../../../../config/application.config.php',
            ['module_listener_options' => ['config_cache_enabled' => false, 'module_map_cache_enabled' => false]]
        ));

        parent::setUp();

        $servicos = $this->getApplicationServiceLocator();
        $servicos->setAllowOverride(true);
        $servicos->setService(AuthenticationService::class, new AuthenticationService(new NonPersistent()));
    }

    /** @return iterable<string, array{string, string}> */
    public static function rotasProtegidas(): iterable
    {
        yield 'início' => ['/', '/login?redirect=/'];
        yield 'lista de AC' => ['/ac', '/login?redirect=/ac'];
        yield 'lista de AR com filtro' => ['/ar?busca=x', '/login?redirect=/ar?busca%3Dx'];
        yield 'edição de AC N2' => ['/ac-n2/edit/1', '/login?redirect=/ac-n2/edit/1'];
        yield 'estrutura' => ['/estrutura', '/login?redirect=/estrutura'];
        yield 'importação' => ['/importar', '/login?redirect=/importar'];
        yield 'QR Code' => ['/qrcode/ac/1', '/login?redirect=/qrcode/ac/1'];
    }

    #[DataProvider('rotasProtegidas')]
    public function testRotaProtegidaRedirecionaParaLogin(string $url, string $destino): void
    {
        $this->dispatch($url, 'GET');

        $this->assertResponseStatusCode(302);
        $this->assertRedirectTo($destino);
    }

    public function testPostSemLoginNaoExecutaAAcao(): void
    {
        $this->dispatch('/ac/delete/1', 'POST', ['csrf' => 'qualquer']);

        $this->assertResponseStatusCode(302);
        $this->assertRedirectTo('/login');
    }

    public function testRotaInexistenteRetorna404(): void
    {
        $this->dispatch('/rota/inexistente', 'GET');

        $this->assertResponseStatusCode(404);
    }

    public function testExclusaoPorGetRetorna405(): void
    {
        $this->getApplicationServiceLocator()
            ->get(AuthenticationService::class)
            ->getStorage()
            ->write(['id' => 1, 'email' => 'pessoa@exemplo.test', 'nome' => 'Pessoa']);

        $this->dispatch('/ac/delete/1', 'GET');

        $this->assertResponseStatusCode(405);
        $this->assertResponseHeaderContains('Allow', 'POST');
    }

    public function testUsuarioAutenticadoPassaPeloGuard(): void
    {
        $servicos = $this->getApplicationServiceLocator();
        $servicos->get(AuthenticationService::class)
            ->getStorage()
            ->write(['id' => 1, 'email' => 'pessoa@exemplo.test', 'nome' => 'Pessoa']);

        // EntityManager falso: o registro não existe, então o controller responde 404.
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('find')->willReturn(null);
        $servicos->setService('doctrine.entitymanager.orm_default', $entityManager);

        $this->dispatch('/qrcode/ac/42', 'GET');

        $this->assertNotRedirect();
        $this->assertMatchedRouteName('qrcode');
        $this->assertResponseStatusCode(404);
    }
}
