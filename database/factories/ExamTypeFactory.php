<?php

namespace Database\Factories;

use App\Enums\ErpType;
use App\Models\Client;
use App\Models\ExamType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamType>
 */
class ExamTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => 'ECG '.fake()->unique()->word(),
            'erp_type' => ErpType::TasyHtml5,
            'procedure_code' => '40101010',
            'destination_folder' => '\\\\servidor\\yantra\\ECG',
            'sql_query' => 'SELECT a.nr_atendimento, pf.nm_pessoa_fisica, pf.dt_nascimento FROM prescr_procedimento pp '
                .'JOIN atendimento_paciente a ON a.nr_atendimento = pp.nr_atendimento '
                .'JOIN pessoa_fisica pf ON pf.cd_pessoa_fisica = a.cd_pessoa_fisica '
                .'WHERE pp.cd_procedimento = {CD_PROCEDIMENTO} AND TRUNC(pp.dt_prev_execucao) = {DT_PEDIDO}',
            'sql_auth' => 'SELECT nm_usuario FROM usuario WHERE nm_usuario = {USUARIO_TASY}',
            'sql_insert' => 'INSERT INTO laudo_paciente (nr_sequencia, nr_atendimento, nm_usuario) VALUES ({NR_SEQUENCIA}, {NR_ATENDIMENTO}, {USUARIO_TASY})',
            'sql_image' => 'INSERT INTO laudo_paciente_imagem (nr_sequencia, ds_arquivo) VALUES ({NR_SEQUENCIA}, {CAMINHO_PDF})',
            'sql_update' => "UPDATE prescr_procedimento SET ie_status_execucao = '40' WHERE nr_prescricao = {NR_PRESCRICAO}",
            'print_options' => null,
            'active' => true,
            'version' => 1,
        ];
    }
}
