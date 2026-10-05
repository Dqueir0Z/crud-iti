<?php

declare(strict_types=1);

namespace Icp\Exception;

use RuntimeException;

/**
 * Outra importação está gravando no banco neste momento.
 * A mensagem é exibida ao usuário.
 */
final class ImportacaoEmAndamentoException extends RuntimeException
{
}
