<?php

declare(strict_types=1);

namespace Icp\Service;

use InvalidArgumentException;
use Laminas\Router\RouteStackInterface;

use function rtrim;

/**
 * Monta o link codificado no QR Code: a URL absoluta da página de detalhes
 * do registro. Se "icp.qrcode.base_url" estiver configurado (ex.: o IP da
 * máquina na rede, para ler o QR pelo celular), ele substitui o host da
 * requisição.
 */
final class LinkQrCode
{
    /** tipo usado na rota do QR Code => rota da página de detalhes */
    public const ROTAS_DETALHE = [
        'ac'    => 'ac/view',
        'ac-n2' => 'ac-n2/view',
        'ar'    => 'ar/view',
    ];

    public function __construct(
        private RouteStackInterface $router,
        private ?string $baseUrl = null
    ) {
    }

    public function paraDetalhe(string $tipo, int $id): string
    {
        $rota = self::ROTAS_DETALHE[$tipo] ?? throw new InvalidArgumentException('Tipo de entidade inválido: ' . $tipo);

        if ($this->baseUrl !== null && $this->baseUrl !== '') {
            return rtrim($this->baseUrl, '/') . $this->router->assemble(['id' => $id], ['name' => $rota]);
        }

        return $this->router->assemble(['id' => $id], ['name' => $rota, 'force_canonical' => true]);
    }

    /** Endereço da imagem SVG do QR Code (relativo ao site). */
    public function paraImagem(string $tipo, int $id): string
    {
        return $this->router->assemble(['tipo' => $tipo, 'id' => $id], ['name' => 'qrcode']);
    }
}
