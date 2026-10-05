<?php

declare(strict_types=1);

namespace Icp\Enum;

/**
 * Situação de credenciamento, com os mesmos códigos usados no structure.json do ITI.
 */
enum Situacao: int
{
    case EmCredenciamento = 4001;
    case Credenciado      = 4002;

    public function rotulo(): string
    {
        return match ($this) {
            self::Credenciado      => 'Credenciado',
            self::EmCredenciamento => 'Em credenciamento',
        };
    }

    /** Classe de badge Bootstrap usada nas listagens. */
    public function classeBadge(): string
    {
        return match ($this) {
            self::Credenciado      => 'text-bg-success',
            self::EmCredenciamento => 'text-bg-warning',
        };
    }

    /**
     * Opções para elementos Select (valor => rótulo).
     *
     * @return array<int, string>
     */
    public static function opcoes(): array
    {
        $opcoes = [];
        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->rotulo();
        }

        return $opcoes;
    }
}
