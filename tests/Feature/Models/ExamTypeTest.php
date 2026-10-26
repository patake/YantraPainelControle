<?php

namespace Tests\Feature\Models;

use App\Models\ExamType;
use Tests\TestCase;

class ExamTypeTest extends TestCase
{
    public function test_lists_each_placeholder_of_the_five_sqls_once(): void
    {
        $examType = new ExamType([
            'sql_query' => 'SELECT * FROM t WHERE a = {CD_PROCEDIMENTO} AND b = {DT_PEDIDO}',
            'sql_auth' => 'SELECT 1 FROM usuario WHERE nm_usuario = {USUARIO_TASY}',
            'sql_insert' => 'INSERT INTO l VALUES ({NR_SEQUENCIA}, {USUARIO_TASY})',
            'sql_image' => 'INSERT INTO i VALUES ({NR_SEQUENCIA}, {CAMINHO_PDF})',
            'sql_update' => "UPDATE p SET s = '40' WHERE n = {NR_PRESCRICAO}",
        ]);

        $this->assertSame(
            ['CD_PROCEDIMENTO', 'DT_PEDIDO', 'USUARIO_TASY', 'NR_SEQUENCIA', 'CAMINHO_PDF', 'NR_PRESCRICAO'],
            $examType->placeholders(),
        );
    }
}
