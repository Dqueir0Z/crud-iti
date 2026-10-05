<?php

declare(strict_types=1);

namespace Application\Form;

use Laminas\Form\Element\Csrf;
use Laminas\Form\Form;
use Laminas\Validator\Csrf as CsrfValidator;

/**
 * Formulário que contém apenas o token CSRF; usado em ações via POST sem
 * outros campos (exclusão, logout).
 */
final class CsrfForm extends Form
{
    public const MENSAGEM_TOKEN_INVALIDO = 'Formulário expirado ou inválido. Recarregue a página e tente novamente.';

    public function __construct(string $nome = 'csrf_form')
    {
        parent::__construct($nome);

        $this->setAttribute('method', 'post');
        $this->add(self::elementoCsrf());
    }

    /**
     * Especificação do elemento CSRF reutilizada por todos os formulários.
     *
     * @return array<string, mixed>
     */
    public static function elementoCsrf(): array
    {
        return [
            'name'    => 'csrf',
            'type'    => Csrf::class,
            'options' => [
                'csrf_options' => [
                    'timeout'  => 3600,
                    'messages' => [
                        CsrfValidator::NOT_SAME => self::MENSAGEM_TOKEN_INVALIDO,
                    ],
                ],
            ],
        ];
    }
}
