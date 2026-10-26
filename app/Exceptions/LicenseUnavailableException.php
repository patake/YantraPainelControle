<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Não existe licença livre para ativar o equipamento (R-LIC-2).
 */
class LicenseUnavailableException extends RuntimeException {}
