<?php

namespace App\Actions\Exams;

use App\Enums\ErpType;
use App\Enums\ExamFieldKind;
use App\Models\Client;
use App\Models\ExamType;
use InvalidArgumentException;

/**
 * Importa um exame do Yantra Cardio 3.0: os arquivos EXAME.json e PARAMETROS.json
 * da pasta Queries\<Exame>. Um exame com o mesmo nome no cliente é atualizado.
 */
class ImportYantraCardioExam
{
    /**
     * Leitura do 3.0 que vira a pasta de destino, e não um campo.
     */
    private const FOLDER_PARAMETER = 'PASTA';

    /**
     * @var array<string, ExamFieldKind>
     */
    private const KINDS = [
        'Parâmetro Interno' => ExamFieldKind::Internal,
        'Parâmetro' => ExamFieldKind::Param,
        'Filtro' => ExamFieldKind::Filter,
        'Informação Complementar' => ExamFieldKind::Info,
    ];

    public function __construct(private SaveExamType $save) {}

    public function handle(Client $client, string $examJson, string $parametersJson): ExamType
    {
        $exam = $this->decode($examJson, 'EXAME.json')['EXAME'][0] ?? null;
        // Os modelos do 3.0 usam a chave LEITURAEXAME ou, em alguns exames, PARAMETROS.
        $parameters = $this->decode($parametersJson, 'PARAMETROS.json');
        $readings = $parameters['LEITURAEXAME'] ?? $parameters['PARAMETROS'] ?? null;

        if (! is_array($exam) || ! is_array($readings) || ! isset($exam['DESCRICAO'])) {
            throw new InvalidArgumentException(__('Os arquivos não têm o formato do Yantra Cardio 3.0 (EXAME e LEITURAEXAME).'));
        }

        $readings = collect($readings);
        $folder = $readings->firstWhere('DESCRICAO', self::FOLDER_PARAMETER)['VALOR'] ?? '';
        $procedure = $readings->firstWhere('DESCRICAO', 'NR_PROCEDIMENTO')['VALOR'] ?? null;

        $examType = ExamType::firstOrNew(['client_id' => $client->id, 'name' => $exam['DESCRICAO']]);

        return $this->save->handle(
            $examType,
            [
                'erp_type' => $this->erpType((string) ($exam['TIPO'] ?? '')),
                'procedure_code' => $procedure,
                'destination_folder' => $folder,
                'sql_query' => (string) ($exam['QUERYLEITURA'] ?? ''),
                'sql_auth' => (string) ($exam['QUERYAUTENTICACAO'] ?? ''),
                'sql_insert' => (string) ($exam['QUERYGRAVACAO'] ?? ''),
                'sql_image' => (string) ($exam['QUERYGRAVACAOIMAGENS'] ?? ''),
                'sql_update' => (string) ($exam['QUERYGRAVACAOANEXOS'] ?? ''),
                'print_options' => [
                    'print' => (bool) ($exam['IMPRIMIR'] ?? false),
                    'print_one_page' => (bool) ($exam['IMPRIMIRUMAPAGINA'] ?? false),
                    'complete_after_file' => (bool) ($exam['FECHARAPOSIMPRIMIR'] ?? false),
                    'form' => (bool) ($exam['FORMULARIO'] ?? false),
                    'logout_after_close' => (bool) ($exam['DESLOGARAPOSFECHAR'] ?? false),
                ],
                'active' => (bool) ($exam['ATIVO'] ?? true),
            ],
            $readings
                ->reject(fn (array $reading) => ($reading['DESCRICAO'] ?? '') === self::FOLDER_PARAMETER)
                ->sortBy('SEQUENCIA')
                ->values()
                ->map(fn (array $reading, int $index) => $this->field($reading, $index))
                ->all(),
        );
    }

    /**
     * O Java do 3.0 aceita vírgula antes de "}" e "]". O JSON estrito não aceita.
     *
     * @return array<string, mixed>
     */
    private function decode(string $json, string $file): array
    {
        $json = preg_replace('/^\xEF\xBB\xBF/', '', $json) ?? $json;
        $data = json_decode((string) preg_replace('/,(\s*[}\]])/', '$1', $json), true);

        if (! is_array($data)) {
            throw new InvalidArgumentException(__('O arquivo :file não é um JSON válido.', ['file' => $file]));
        }

        return $data;
    }

    private function erpType(string $type): ErpType
    {
        return match (true) {
            str_contains(strtoupper($type), 'HTML5') => ErpType::TasyHtml5,
            str_contains(strtoupper($type), 'DELPHI') => ErpType::Delphi,
            default => ErpType::Java,
        };
    }

    /**
     * Converte uma leitura do 3.0 num campo do exame (DOCUMENTACAO.md, seção 10.1).
     *
     * @param  array<string, mixed>  $reading
     * @return array<string, mixed>
     */
    private function field(array $reading, int $position): array
    {
        $kind = self::KINDS[$reading['FILTRO'] ?? ''] ?? ExamFieldKind::Internal;
        $value = isset($reading['VALOR']) ? (string) $reading['VALOR'] : null;
        $column = isset($reading['CAMPOBD']) ? (string) $reading['CAMPOBD'] : null;
        $combo = $kind === ExamFieldKind::Info && $value !== null ? json_decode($value, true)['combo'] ?? null : null;

        return match (true) {
            // Informação complementar com lista: o marcador é o CAMPOBD e o valor gravado é o id da opção.
            is_array($combo) => [
                'kind' => $kind, 'placeholder' => $column ?? $reading['DESCRICAO'], 'db_column' => $column,
                'label' => $reading['DESCRICAO'], 'input' => 'select', 'default_value' => null,
                'options' => array_values($combo), 'required' => (bool) ($reading['OBRIGATORIO'] ?? false), 'position' => $position,
            ],
            // Informação complementar livre: o marcador é o VALOR e o texto inicial é o CAMPOBD.
            $kind === ExamFieldKind::Info => [
                'kind' => $kind, 'placeholder' => $value ?? $reading['DESCRICAO'], 'db_column' => null,
                'label' => $reading['DESCRICAO'], 'input' => ($reading['NUMERO'] ?? false) ? 'number' : 'textarea',
                'default_value' => $column, 'options' => null,
                'required' => (bool) ($reading['OBRIGATORIO'] ?? false), 'position' => $position,
            ],
            default => [
                'kind' => $kind, 'placeholder' => $reading['DESCRICAO'], 'db_column' => $column,
                'label' => $kind === ExamFieldKind::Internal ? null : $reading['DESCRICAO'],
                'input' => ($reading['NUMERO'] ?? false) ? 'number' : 'text', 'default_value' => $value,
                'options' => null, 'required' => (bool) ($reading['OBRIGATORIO'] ?? false), 'position' => $position,
            ],
        };
    }
}
