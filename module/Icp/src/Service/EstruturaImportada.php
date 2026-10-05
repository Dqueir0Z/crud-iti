<?php

declare(strict_types=1);

namespace Icp\Service;

use Icp\Enum\Situacao;

use function count;

/**
 * Resultado normalizado da leitura do structure.json, indexado pelo id do ITI.
 */
final class EstruturaImportada
{
    /**
     * @param array<int, array{nome: string, situacao: Situacao}> $acs
     * @param array<int, array{nome: string, situacao: Situacao, acItiId: int}> $acN2s
     * @param array<int, array{nome: string, situacao: Situacao, acN2ItiIds: list<int>}> $ars
     * @param list<string> $avisos
     */
    public function __construct(
        public readonly array $acs,
        public readonly array $acN2s,
        public readonly array $ars,
        public readonly array $avisos
    ) {
    }

    public function totalVinculos(): int
    {
        $total = 0;
        foreach ($this->ars as $ar) {
            $total += count($ar['acN2ItiIds']);
        }

        return $total;
    }
}
