<?php

namespace App\Enums;

/**
 * Comandos que o painel envia ao agente pela consulta periódica.
 */
enum CommandType: string
{
    case StartExam = 'start_exam';
    case QueryPatients = 'query_patients';
    case RecordReport = 'record_report';
    case TestErp = 'test_erp';
    case CancelExam = 'cancel_exam';
    case Sync = 'sync';

    /**
     * Validade do comando, em segundos (especificação, seção 3.3).
     */
    public function ttlSeconds(): int
    {
        return match ($this) {
            self::StartExam, self::CancelExam, self::Sync => 300,
            self::QueryPatients, self::TestErp => 60,
            self::RecordReport => 120,
        };
    }
}
