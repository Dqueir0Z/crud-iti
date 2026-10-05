<?php

declare(strict_types=1);

namespace Icp\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Icp\Entity\Ac;
use Icp\Entity\AcN2;
use Icp\Entity\Ar;
use Icp\Service\GeradorQrCode;
use Icp\Service\LinkQrCode;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;

use function sprintf;

/**
 * Devolve o QR Code (SVG) com o link da página de detalhes de uma entidade.
 * Rota: /qrcode/:tipo/:id — com ?download=1 o navegador baixa o arquivo.
 */
final class QrCodeController extends AbstractActionController
{
    private const ENTIDADES = [
        'ac'    => Ac::class,
        'ac-n2' => AcN2::class,
        'ar'    => Ar::class,
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private GeradorQrCode $gerador,
        private LinkQrCode $links
    ) {
    }

    public function svgAction(): Response
    {
        $tipo = (string) $this->params()->fromRoute('tipo');
        $id   = (int) $this->params()->fromRoute('id');

        /** @var Response $response */
        $response = $this->getResponse();

        $classe = self::ENTIDADES[$tipo] ?? null;
        if ($classe === null || $this->entityManager->find($classe, $id) === null) {
            $response->setStatusCode(Response::STATUS_CODE_404);
            $response->setContent('Registro não encontrado.');

            return $response;
        }

        $response->setContent($this->gerador->svg($this->links->paraDetalhe($tipo, $id)));
        $cabecalhos = $response->getHeaders();
        $cabecalhos->addHeaderLine('Content-Type', 'image/svg+xml; charset=utf-8');
        $cabecalhos->addHeaderLine('Cache-Control', 'private, max-age=300');

        if ($this->params()->fromQuery('download')) {
            $cabecalhos->addHeaderLine(
                'Content-Disposition',
                sprintf('attachment; filename="qrcode-%s-%d.svg"', $tipo, $id)
            );
        }

        return $response;
    }
}
