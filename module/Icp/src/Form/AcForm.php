<?php

declare(strict_types=1);

namespace Icp\Form;

use Application\Form\CsrfForm;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

final class AcForm extends Form implements InputFilterProviderInterface
{
    public function __construct()
    {
        parent::__construct('ac');

        $this->setAttribute('method', 'post');
        $this->add(EspecificacaoCampos::elementoNome('Nome da AC'));
        $this->add(EspecificacaoCampos::elementoSituacao());
        $this->add(CsrfForm::elementoCsrf());
    }

    /** @return array<string, mixed> */
    public function getInputFilterSpecification(): array
    {
        return [
            'nome'     => EspecificacaoCampos::filtroNome(),
            'situacao' => EspecificacaoCampos::filtroSituacao(),
        ];
    }
}
