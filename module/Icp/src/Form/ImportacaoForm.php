<?php

declare(strict_types=1);

namespace Icp\Form;

use Application\Form\CsrfForm;
use Laminas\Form\Element\File;
use Laminas\Form\Form;
use Laminas\InputFilter\FileInput;
use Laminas\InputFilter\InputFilterProviderInterface;
use Laminas\Validator\File\Extension;
use Laminas\Validator\File\Size;
use Laminas\Validator\File\UploadFile;

final class ImportacaoForm extends Form implements InputFilterProviderInterface
{
    public const TAMANHO_MAXIMO = '10MB';

    public function __construct()
    {
        parent::__construct('importacao');

        $this->setAttribute('method', 'post');
        $this->setAttribute('enctype', 'multipart/form-data');

        $this->add([
            'name'       => 'arquivo',
            'type'       => File::class,
            'options'    => ['label' => 'Arquivo structure.json'],
            'attributes' => [
                'id'       => 'arquivo',
                'class'    => 'form-control',
                'accept'   => '.json,application/json',
                'required' => true,
            ],
        ]);
        $this->add(CsrfForm::elementoCsrf());
    }

    /** @return array<string, mixed> */
    public function getInputFilterSpecification(): array
    {
        return [
            'arquivo' => [
                'type'       => FileInput::class,
                'required'   => true,
                'validators' => [
                    [
                        'name'                   => UploadFile::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'messages' => [
                                UploadFile::NO_FILE   => 'Selecione um arquivo .json.',
                                UploadFile::INI_SIZE  => 'O arquivo excede o limite de upload do PHP.',
                                UploadFile::FORM_SIZE => 'O arquivo é grande demais.',
                                UploadFile::PARTIAL   => 'O envio do arquivo foi interrompido.',
                            ],
                        ],
                    ],
                    [
                        'name'                   => Extension::class,
                        'break_chain_on_failure' => true,
                        'options'                => [
                            'extension' => 'json',
                            'messages'  => [Extension::FALSE_EXTENSION => 'O arquivo deve ter extensão .json.'],
                        ],
                    ],
                    [
                        'name'    => Size::class,
                        'options' => [
                            'max'      => self::TAMANHO_MAXIMO,
                            'messages' => [Size::TOO_BIG => 'O arquivo deve ter no máximo %max%.'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
