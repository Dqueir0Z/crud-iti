<?php

declare(strict_types=1);

namespace Icp\Form;

use Application\Form\CsrfForm;
use Icp\Entity\Ac;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;

use function array_keys;

final class AcN2Form extends Form implements InputFilterProviderInterface
{
    /** @var array<int, string> */
    private array $opcoesAc = [];

    /** @param list<Ac> $acs ACs disponíveis como AC principal */
    public function __construct(array $acs)
    {
        parent::__construct('ac_n2');

        foreach ($acs as $ac) {
            $this->opcoesAc[(int) $ac->getId()] = $ac->getNome();
        }

        $this->setAttribute('method', 'post');
        $this->add(EspecificacaoCampos::elementoNome('Nome da AC N2'));
        $this->add([
            'name'       => 'ac',
            'type'       => 'select',
            'options'    => [
                'label'         => 'AC principal',
                'empty_option'  => 'Selecione a AC…',
                'value_options' => $this->opcoesAc,
            ],
            'attributes' => ['id' => 'ac', 'class' => 'form-select', 'required' => true],
        ]);
        $this->add(EspecificacaoCampos::elementoSituacao());
        $this->add(CsrfForm::elementoCsrf());
    }

    /** @return array<string, mixed> */
    public function getInputFilterSpecification(): array
    {
        return [
            'nome'     => EspecificacaoCampos::filtroNome(),
            'ac'       => EspecificacaoCampos::filtroEscolha(array_keys($this->opcoesAc), 'Selecione a AC principal.'),
            'situacao' => EspecificacaoCampos::filtroSituacao(),
        ];
    }
}
