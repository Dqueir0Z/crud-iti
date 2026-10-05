<?php

declare(strict_types=1);

namespace Icp\Form;

use Application\Form\CsrfForm;
use Icp\Entity\AcN2;
use Laminas\Filter\Callback as CallbackFilter;
use Laminas\Form\Form;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\Callback as CallbackValidator;

use function array_diff;
use function array_map;
use function array_unique;
use function array_values;
use function is_array;

final class ArForm extends Form implements InputFilterProviderInterface
{
    /** @var list<int> */
    private array $idsAcN2 = [];

    /** @param list<AcN2> $acN2s AC N2 disponíveis para vínculo (com a AC carregada) */
    public function __construct(array $acN2s)
    {
        parent::__construct('ar');

        // Agrupa as opções por AC principal (<optgroup>), como na árvore do ITI.
        $grupos = [];
        foreach ($acN2s as $acN2) {
            $ac = $acN2->getAc();
            $grupos[$ac->getId()] ??= ['label' => $ac->getNome(), 'options' => []];
            $grupos[$ac->getId()]['options'][(int) $acN2->getId()] = $acN2->getNome();
            $this->idsAcN2[] = (int) $acN2->getId();
        }

        $this->setAttribute('method', 'post');
        $this->add(EspecificacaoCampos::elementoNome('Nome da AR'));
        $this->add([
            'name'       => 'acN2s',
            'type'       => 'select',
            'options'    => [
                'label'                     => 'AC N2 vinculadas',
                'value_options'             => array_values($grupos),
                'disable_inarray_validator' => true,
            ],
            'attributes' => [
                'id'       => 'acN2s',
                'class'    => 'form-select',
                'multiple' => true,
                'size'     => 12,
                'required' => true,
            ],
        ]);
        $this->add(EspecificacaoCampos::elementoSituacao());
        $this->add(CsrfForm::elementoCsrf());
    }

    /** @return array<string, mixed> */
    public function getInputFilterSpecification(): array
    {
        return [
            'nome'     => EspecificacaoCampos::filtroNome(),
            'acN2s'    => $this->inputAcN2s(),
            'situacao' => EspecificacaoCampos::filtroSituacao(),
        ];
    }

    /**
     * Lista de ids do <select multiple>.
     *
     * Usa Input comum com callbacks (e não ArrayInput): o Select registra antes
     * o próprio Input e a especificação do formulário é mesclada nele, o que
     * descartaria o tipo ArrayInput.
     *
     * @return array<string, mixed>
     */
    private function inputAcN2s(): array
    {
        $idsValidos = $this->idsAcN2;

        return [
            'required'      => true,
            'error_message' => 'Selecione ao menos uma AC N2 válida.',
            'filters'       => [
                [
                    'name'    => CallbackFilter::class,
                    'options' => [
                        'callback' => static fn (mixed $valor): array => is_array($valor)
                            ? array_values(array_unique(array_map('intval', $valor)))
                            : [],
                    ],
                ],
            ],
            'validators'    => [
                [
                    'name'    => CallbackValidator::class,
                    'options' => [
                        'callback' => static fn (array $ids): bool => $ids !== []
                            && array_diff($ids, $idsValidos) === [],
                    ],
                ],
            ],
        ];
    }
}
