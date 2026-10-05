<?php

declare(strict_types=1);

namespace Icp\View\Helper;

use Icp\Service\LinkQrCode;
use Laminas\Escaper\Escaper;
use Laminas\View\Helper\AbstractHelper;

use function sprintf;

/**
 * Botão "Gerar QRCode" que abre o modal compartilhado (#modalQrCode).
 * O JavaScript em public/js/app.js lê os atributos data-* e carrega a imagem.
 */
final class BotaoQrCode extends AbstractHelper
{
    private Escaper $escaper;

    public function __construct(private LinkQrCode $links)
    {
        $this->escaper = new Escaper('utf-8');
    }

    public function __invoke(string $tipo, int $id, string $nome, string $classe = 'btn btn-sm btn-outline-dark'): string
    {
        return sprintf(
            '<button type="button" class="%s" data-bs-toggle="modal" data-bs-target="#modalQrCode"'
            . ' data-qrcode-imagem="%s" data-qrcode-link="%s" data-qrcode-titulo="%s">Gerar QRCode</button>',
            $this->escaper->escapeHtmlAttr($classe),
            $this->escaper->escapeHtmlAttr($this->links->paraImagem($tipo, $id)),
            $this->escaper->escapeHtmlAttr($this->links->paraDetalhe($tipo, $id)),
            $this->escaper->escapeHtmlAttr($nome)
        );
    }
}
