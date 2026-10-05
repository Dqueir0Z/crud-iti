<?php

declare(strict_types=1);

namespace Icp\Form;

use Icp\Enum\Situacao;
use Laminas\Filter\StringTrim;
use Laminas\Filter\ToInt;
use Laminas\Validator\InArray;
use Laminas\Validator\NotEmpty;
use Laminas\Validator\StringLength;

use function array_keys;
use function array_map;

/**
 * Especificações de InputFilter e de elementos compartilhadas pelos formulários
 * do módulo, com mensagens em português.
 */
final class EspecificacaoCampos
{
    public const TAMANHO_NOME = 150;

    /** @return array<string, mixed> */
    public static function elementoNome(string $rotulo): array
    {
        return [
            'name'       => 'nome',
            'type'       => 'text',
            'options'    => ['label' => $rotulo],
            'attributes' => [
                'id'        => 'nome',
                'class'     => 'form-control',
                'maxlength' => self::TAMANHO_NOME,
                'required'  => true,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function elementoSituacao(): array
    {
        return [
            'name'       => 'situacao',
            'type'       => 'select',
            'options'    => ['label' => 'Situação', 'value_options' => Situacao::opcoes()],
            'attributes' => ['id' => 'situacao', 'class' => 'form-select'],
        ];
    }

    /** @return array<string, mixed> */
    public static function filtroNome(): array
    {
        return [
            'required'   => true,
            'filters'    => [['name' => StringTrim::class]],
            'validators' => [
                [
                    'name'                   => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options'                => ['messages' => [NotEmpty::IS_EMPTY => 'Informe o nome.']],
                ],
                [
                    'name'    => StringLength::class,
                    'options' => [
                        'max'      => self::TAMANHO_NOME,
                        'encoding' => 'UTF-8',
                        'messages' => [StringLength::TOO_LONG => 'O nome deve ter no máximo %max% caracteres.'],
                    ],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function filtroSituacao(): array
    {
        return self::filtroEscolha(array_keys(Situacao::opcoes()), 'Selecione uma situação válida.');
    }

    /**
     * Campo obrigatório cujo valor precisa ser um dos ids informados.
     *
     * @param list<int|string> $idsValidos
     * @return array<string, mixed>
     */
    public static function filtroEscolha(array $idsValidos, string $mensagem): array
    {
        return [
            'required'   => true,
            'filters'    => [['name' => ToInt::class]],
            'validators' => [
                [
                    'name'                   => NotEmpty::class,
                    'break_chain_on_failure' => true,
                    'options'                => [
                        'type'     => NotEmpty::INTEGER | NotEmpty::STRING | NotEmpty::NULL,
                        'messages' => [NotEmpty::IS_EMPTY => $mensagem],
                    ],
                ],
                [
                    'name'    => InArray::class,
                    'options' => [
                        'haystack' => array_map('intval', $idsValidos),
                        'strict'   => InArray::COMPARE_STRICT,
                        'messages' => [InArray::NOT_IN_ARRAY => $mensagem],
                    ],
                ],
            ],
        ];
    }
}
