<?php

declare(strict_types=1);

namespace Icp\Exception;

use RuntimeException;

/**
 * O arquivo enviado não segue o formato do structure.json do ITI.
 * A mensagem é exibida ao usuário.
 */
final class EstruturaInvalidaException extends RuntimeException
{
}
