<?php

namespace Tests\Feature;

use App\Enums\ExamFileKind;
use App\Enums\ExamStatus;
use App\Models\Client;
use App\Models\Device;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ataques da revisão de segurança da E9: XSS guardado, SQL injection, acesso a outro cliente,
 * troca de credencial entre o painel e a API do agente, envio de arquivo disfarçado e excesso
 * de tentativas de login.
 */
class SecurityAttacksTest extends TestCase
{
    use RefreshDatabase;

    private const SCRIPT = '<script>alert("xss")</script>';

    private const IMG = '<img src=x onerror=alert(1)>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');
    }

    public function test_html_from_the_agent_and_the_erp_is_escaped_on_the_operator_screens(): void
    {
        $operator = User::factory()->withTwoFactor()->operatorWithClient()->create();
        $device = Device::factory()->active()->create(['client_id' => $operator->clients()->first()->id, 'hostname' => self::SCRIPT]);
        $exam = Exam::factory()->create([
            'device_id' => $device->id, 'status' => ExamStatus::Reviewing,
            'patient' => ['PACIENTE' => self::SCRIPT, 'ATENDIMENTO' => self::IMG],
            'serial_data' => ['FC' => self::SCRIPT],
        ]);
        $exam->files()->create(['kind' => ExamFileKind::Pdf, 'original_name' => self::IMG.'.pdf', 'sha256' => str_repeat('a', 64), 'size_bytes' => 3]);

        $this->actingAs($operator);
        foreach ([route('app.exams.index'), route('app.exams.show', $exam)] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertDontSee(self::SCRIPT, false)
                ->assertDontSee(self::IMG, false);
        }
        $this->get(route('app.exams.show', $exam))->assertSee(e(self::IMG.'.pdf'), false);
    }

    public function test_html_typed_by_users_and_sent_by_the_agent_is_escaped_on_the_staff_screens(): void
    {
        $admin = User::factory()->withTwoFactor()->admin()->create();
        $client = Client::factory()->create(['trade_name' => self::SCRIPT, 'legal_name' => self::IMG]);
        Device::factory()->create(['client_id' => $client->id, 'hostname' => self::SCRIPT, 'domain' => self::IMG]);
        User::factory()->create(['name' => self::SCRIPT]);

        $this->actingAs($admin);
        foreach (['staff.clients.index', 'staff.devices.index', 'staff.users.index'] as $route) {
            $this->get(route($route))
                ->assertOk()
                ->assertDontSee(self::SCRIPT, false)
                ->assertDontSee(self::IMG, false);
        }
        $this->get(route('staff.clients.show', $client))->assertOk()->assertDontSee(self::SCRIPT, false);
    }

    public function test_sql_injection_in_the_searches_neither_breaks_nor_shows_other_clients(): void
    {
        $operator = User::factory()->withTwoFactor()->operatorWithClient()->create();
        $device = Device::factory()->active()->create(['client_id' => $operator->clients()->first()->id]);
        Exam::factory()->create(['device_id' => $device->id, 'patient' => ['PACIENTE' => 'ANA SOUZA']]);
        Exam::factory()->create(['patient' => ['PACIENTE' => 'PACIENTE DE OUTRO HOSPITAL']]);

        $this->actingAs($operator);
        foreach (["' OR 1=1 --", "%' OR '1'='1", '%', '_', "'); DROP TABLE exams; --", '\\'] as $payload) {
            Livewire::test('pages::app.exams.index')
                ->set('search', $payload)
                ->assertOk()
                ->assertDontSee('PACIENTE DE OUTRO HOSPITAL');
        }
        $this->assertSame(2, Exam::count());

        $admin = User::factory()->withTwoFactor()->admin()->create();
        $this->actingAs($admin);
        foreach (['pages::staff.clients.index', 'pages::staff.devices.index', 'pages::staff.users.index'] as $page) {
            Livewire::test($page)->set('search', "' OR 1=1; DROP TABLE users; --")->assertOk();
        }
        $this->assertTrue(User::whereKey($admin->id)->exists());
    }

    public function test_an_operator_cannot_reach_the_staff_area_nor_the_data_of_another_client(): void
    {
        $operator = User::factory()->withTwoFactor()->operatorWithClient()->create();
        $foreign = Exam::factory()->create(['status' => ExamStatus::Reviewing]);
        $this->actingAs($operator);

        $this->get(route('staff.dashboard'))->assertForbidden();
        $this->get(route('staff.clients.show', $foreign->client_id))->assertForbidden();
        $this->get(route('app.exams.show', $foreign))->assertNotFound();
        $this->get(route('app.settings'))->assertForbidden();
    }

    public function test_the_agent_token_and_the_user_session_do_not_cross(): void
    {
        $device = Device::factory()->active()->create();
        $token = $device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;

        // O token do equipamento não abre o painel.
        $this->withToken($token)->get(route('staff.dashboard'))->assertRedirect(route('login'));

        // Um token sem a habilidade do agente não usa a API do agente.
        $other = $device->createToken('outro', ['*-qualquer'])->plainTextToken;
        $this->withToken($other)->getJson(route('agent.license'))->assertForbidden();

        // A sessão de um administrador não usa a API do agente.
        $this->app['auth']->forgetGuards();
        $admin = User::factory()->withTwoFactor()->admin()->create();
        $this->actingAs($admin)->getJson(route('agent.license'))->assertForbidden();
    }

    public function test_the_agent_cannot_upload_a_file_disguised_as_another_kind(): void
    {
        $exam = Exam::factory()->create(['status' => ExamStatus::Capturing]);
        $token = $exam->device->createToken('agente', [Device::TOKEN_ABILITY])->plainTextToken;

        // Arquivos reais: o UploadedFile::fake() informa o tipo pelo nome, e o ataque usa o conteúdo.
        $disguised = [
            ['pdf', 'captura.pdf', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==')],
            ['pdf', 'shell.pdf', "<?php system(\$_GET['c']); ?>"],
            ['serial_raw', 'serial.txt', '<html><body><script>alert(1)</script></body></html>'],
            ['png', 'imagem.png', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>'],
        ];
        foreach ($disguised as [$kind, $name, $content]) {
            $path = tempnam(sys_get_temp_dir(), 'ataque');
            file_put_contents($path, $content);

            $response = $this->withToken($token)->postJson(route('agent.exams.files.store', $exam), [
                'kind' => $kind, 'sha256' => hash('sha256', $content), 'file' => new UploadedFile($path, $name, null, null, true),
            ]);

            $this->assertSame(422, $response->status(), "{$name} como {$kind} foi aceito");
        }
        $this->withToken($token)
            ->postJson(route('agent.exams.files.store', $exam), ['kind' => ['pdf'], 'sha256' => str_repeat('a', 64), 'file' => UploadedFile::fake()->create('a.pdf')])
            ->assertUnprocessable();
        $this->assertSame(0, $exam->files()->count());
    }

    public function test_password_reset_requests_are_limited_per_ip(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => "pessoa{$i}@exemplo.com.br"])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'outra@exemplo.com.br'])->assertTooManyRequests();
    }

    public function test_login_is_blocked_after_5_wrong_passwords(): void
    {
        $user = User::factory()->withTwoFactor()->admin()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.store'), ['email' => $user->email, 'password' => 'senha-errada'])->assertSessionHasErrors('email');
        }

        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertTooManyRequests();
        $this->assertGuest();
    }
}
