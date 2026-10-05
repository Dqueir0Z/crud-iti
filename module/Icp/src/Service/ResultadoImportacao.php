<?php

declare(strict_types=1);

namespace Icp\Service;

/**
 * Totais de uma importação, por entidade, para exibir ao usuário.
 */
final class ResultadoImportacao
{
    /** @var array<string, array{criados: int, atualizados: int, inalterados: int}> */
    public array $totais = [
        'AC'    => ['criados' => 0, 'atualizados' => 0, 'inalterados' => 0],
        'AC N2' => ['criados' => 0, 'atualizados' => 0, 'inalterados' => 0],
        'AR'    => ['criados' => 0, 'atualizados' => 0, 'inalterados' => 0],
    ];

    public int $vinculosCriados = 0;

    /** @param list<string> $avisos */
    public function __construct(public readonly array $avisos = [])
    {
    }

    public function contar(string $entidade, string $situacao): void
    {
        $this->totais[$entidade][$situacao]++;
    }
}
