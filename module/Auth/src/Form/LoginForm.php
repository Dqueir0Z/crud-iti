<?php

declare(strict_types=1);

namespace Auth\Form;

use Application\Form\CsrfForm;
use Laminas\Filter\StringTrim;
use Laminas\Form\Element\Email;
use Laminas\Form\Element\Password;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\NotEmpty;

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
                        'name'    => NotEmpty::class,
                        'options' => ['messages' => [NotEmpty::IS_EMPTY => 'Informe o e-mail.']],
                    ],
                ],
            ],
            'senha' => [
                'required'   => true,
                'validators' => [
                    [
                        'name'    => NotEmpty::class,
                        'options' => ['messages' => [NotEmpty::IS_EMPTY => 'Informe a senha.']],
                    ],
                ],
            ],
        ];
    }
}
