<?php

namespace Tests\Feature\Actions\Exams;

use App\Actions\Exams\ImportYantraCardioExam;
use App\Enums\ErpType;
use App\Enums\ExamFieldKind;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ImportYantraCardioExamTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $exam, string $file): string
    {
        return (string) file_get_contents(base_path("tests/Fixtures/yantra-cardio/{$exam}/{$file}"));
    }

    public function test_imports_the_eletro_exam_of_the_yantra_cardio_3_installation(): void
    {
        $client = Client::factory()->create();

        $exam = app(ImportYantraCardioExam::class)->handle($client, $this->fixture('Eletro', 'EXAME.json'), $this->fixture('Eletro', 'PARAMETROS.json'));

        $this->assertSame('Eletro', $exam->name);
        $this->assertSame(ErpType::Delphi, $exam->erp_type);
        $this->assertSame('T:\Yantra\Eletrocardiogograma', $exam->destination_folder);
        $this->assertSame('40101010', $exam->procedure_code);
        $this->assertTrue($exam->print_options['print']);
        $this->assertTrue($exam->print_options['complete_after_file']);
        $this->assertStringContainsString('{NR_PROCEDIMENTO}', $exam->sql_query);
        $this->assertStringContainsString('LAUDO_PACIENTE_IMAGEM', $exam->sql_image);

        // PASTA vira a pasta de destino; as outras 6 leituras viram parâmetros internos, na ordem SEQUENCIA.
        $this->assertSame(
            ['NR_PROCEDIMENTO', 'STATUS_EXECUCAO', 'DIAS_PESQUISA', 'TITULO_LAUDO', 'SEQUENCE', 'ATUALIZAR_STATUS_EXECUCAO'],
            $exam->fields->pluck('placeholder')->all(),
        );
        $this->assertTrue($exam->fields->every(fn ($field) => $field->kind === ExamFieldKind::Internal));
        $this->assertSame('22', $exam->fields->firstWhere('placeholder', 'ATUALIZAR_STATUS_EXECUCAO')->default_value);
    }

    /**
     * O modelo do exame Teste usa a chave PARAMETROS, e não LEITURAEXAME.
     */
    public function test_importing_again_updates_the_exam_in_a_new_version(): void
    {
        $client = Client::factory()->create();
        $import = app(ImportYantraCardioExam::class);

        $import->handle($client, $this->fixture('Teste', 'EXAME.json'), $this->fixture('Teste', 'PARAMETROS.json'));
        $exam = $import->handle($client, $this->fixture('Teste', 'EXAME.json'), $this->fixture('Teste', 'PARAMETROS.json'));

        $this->assertSame(2, $exam->version);
        $this->assertSame(1, $client->examTypes()->count());
        $this->assertSame(6, $exam->fields()->count());
    }

    public function test_reads_complementary_information_with_a_list_of_options(): void
    {
        $parameters = json_encode(['LEITURAEXAME' => [
            ['DESCRICAO' => 'PASTA', 'FILTRO' => 'Parâmetro Interno', 'VALOR' => '\\\\srv\\yantra', 'CAMPOBD' => null, 'SEQUENCIA' => 0],
            ['DESCRICAO' => 'Ritmo', 'FILTRO' => 'Informação Complementar', 'VALOR' => '{"combo":[{"id":1,"valor":"Sinusal"},{"id":2,"valor":"FA"}]}', 'CAMPOBD' => 'DS_RITMO', 'SEQUENCIA' => 1],
            ['DESCRICAO' => 'Setor', 'FILTRO' => 'Filtro', 'VALOR' => null, 'CAMPOBD' => 'SETOR', 'SEQUENCIA' => 2],
        ]]);

        $exam = app(ImportYantraCardioExam::class)->handle(Client::factory()->create(), $this->fixture('Eletro', 'EXAME.json'), $parameters);

        $rhythm = $exam->fields->firstWhere('label', 'Ritmo');
        $this->assertSame(ExamFieldKind::Info, $rhythm->kind);
        $this->assertSame('DS_RITMO', $rhythm->placeholder);
        $this->assertSame('select', $rhythm->input);
        $this->assertSame([['id' => 1, 'valor' => 'Sinusal'], ['id' => 2, 'valor' => 'FA']], $rhythm->options);

        $sector = $exam->fields->firstWhere('label', 'Setor');
        $this->assertSame(ExamFieldKind::Filter, $sector->kind);
        $this->assertSame('SETOR', $sector->db_column);
    }

    public function test_rejects_files_outside_the_3_0_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        app(ImportYantraCardioExam::class)->handle(Client::factory()->create(), '{"outro": []}', '{"LEITURAEXAME": []}');
    }
}
