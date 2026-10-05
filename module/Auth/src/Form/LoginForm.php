<?php

declare(strict_types=1);

namespace Auth\Form;

use Application\Form\CsrfForm;
use Laminas\Filter\StringTrim;
use Laminas\Form\Element\Email;
use Laminas\Form\Element\Password;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Callback;
use Laminas\Validator\NotEmpty;

use function is_string;

final class LoginForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('login');

        $this->setAttribute('method', 'post');

        $this->add([
            'name'       => 'email',
            'type'       => Email::class,
            'options'    => ['label' => 'E-mail'],
            'attributes' => [
                'id'           => 'email',
                'class'        => 'form-control',
                'autocomplete' => 'username',
                'required'     => true,
                'autofocus'    => true,
            ],
        ]);

        $this->add([
            'name'       => 'senha',
            'type'       => Password::class,
            'options'    => ['label' => 'Senha'],
            'attributes' => [
                'id'           => 'senha',
                'class'        => 'form-control',
                'autocomplete' => 'current-password',
                'required'     => true,
            ],
        ]);

        $this->add(CsrfForm::elementoCsrf());
    }

    /** @return array<string, mixed> */
    public function getInputFilterSpecification(): array
    {
        return [
            // Sem validação de formato: o e-mail só é usado para localizar o usuário.
            'email' => [
                'required'   => true,
                'filters'    => [['name' => StringTrim::class]],
                'validators' => [
                    [
                        'name'                   => NotEmpty::class,
                        'break_chain_on_failure' => true,
                        'options'                => ['messages' => [NotEmpty::IS_EMPTY => 'Informe o e-mail.']],
                    ],
                    self::exigirTexto(),
                ],
            ],
            'senha' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'                   => NotEmpty::class,
                        'break_chain_on_failure' => true,
                        'options'                => ['messages' => [NotEmpty::IS_EMPTY => 'Informe a senha.']],
                    ],
                    self::exigirTexto(),
                ],
            ],
        ];
    }

    /**
     * Um POST forjado pode enviar o campo como lista (senha[]=x). NotEmpty aceita
     * arrays, e o valor chegaria ao CredenciaisAdapter, que exige string.
     *
     * @return array<string, mixed>
     */
    private static function exigirTexto(): array
    {
        return [
            'name'    => Callback::class,
            'options' => [
                'callback' => static fn (mixed $valor): bool => is_string($valor),
                'messages' => [Callback::INVALID_VALUE => 'Valor inválido.'],
            ],
        ];
    }
}
