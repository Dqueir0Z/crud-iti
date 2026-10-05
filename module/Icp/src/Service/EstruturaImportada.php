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
     * @param array<int, array{nome: string, situacao: Situacao, vinculos: array<int, Situacao>}> $ars
     *        situacao = situação geral; vinculos = situação por id do ITI da AC N2
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
            $total += count($ar['vinculos']);
        }

        return $total;
    }
}
