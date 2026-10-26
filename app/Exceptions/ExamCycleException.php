<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ação do ciclo de exame recusada. A mensagem vai para o operador.
 */
class ExamCycleException extends RuntimeException {}
