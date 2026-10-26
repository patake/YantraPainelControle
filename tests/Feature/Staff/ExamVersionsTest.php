<?php

namespace Tests\Feature\Staff;

use App\Actions\Exams\SaveExamType;
use App\Models\ExamType;
use App\Models\ExamTypeVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * R-EXA-10: cada gravação cria uma versão, e restaurar uma versão antiga cria uma versão nova.
 */
class ExamVersionsTest extends TestCase
{
    use RefreshDatabase;

    private function examWithTwoVersions(): ExamType
    {
        $examType = ExamType::factory()->make(['sql_query' => "SELECT 1\nFROM dual"]);
        $save = app(SaveExamType::class);
        $save->handle($examType, [], [['kind' => 'filter', 'placeholder' => 'CD_SETOR', 'label' => 'Setor', 'input' => 'text']]);
        $save->handle($examType, ['sql_query' => "SELECT 2\nFROM dual"], []);

        return $examType->fresh();
    }

    public function test_each_save_keeps_a_copy_of_the_version(): void
    {
        $examType = $this->examWithTwoVersions();

        $this->assertSame(2, $examType->version);
        $this->assertSame([1, 2], $examType->versions()->orderBy('version')->pluck('version')->all());
        $first = $examType->versions()->where('version', 1)->sole();
        $this->assertSame("SELECT 1\nFROM dual", $first->snapshot['attributes']['sql_query']);
        $this->assertSame('CD_SETOR', $first->snapshot['fields'][0]['placeholder']);
    }

    public function test_admin_saving_the_form_creates_a_version_with_the_user(): void
    {
        $admin = User::factory()->admin()->withTwoFactor()->create();
        $examType = ExamType::factory()->create();
        $this->actingAs($admin);

        Livewire::test('pages::staff.exams.form', ['examType' => $examType])->call('save')->assertHasNoErrors();

        $this->assertSame($admin->id, ExamTypeVersion::where('exam_type_id', $examType->id)->sole()->user_id);
    }

    public function test_diff_page_shows_the_changed_sql_lines_and_readings(): void
    {
        $examType = $this->examWithTwoVersions();
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        $this->get(route('staff.exams.versions.show', [$examType, 2]))
            ->assertOk()
            ->assertSee('SQL de consulta')
            ->assertSee('SELECT 1')
            ->assertSee('SELECT 2')
            ->assertSee('CD_SETOR')
            ->assertSee('removida')
            ->assertDontSee('Restaurar a versão');
    }

    public function test_restoring_an_old_version_creates_a_new_version_and_keeps_the_history(): void
    {
        $examType = $this->examWithTwoVersions();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        Livewire::test('pages::staff.exams.version', ['examType' => $examType, 'version' => 1])
            ->call('restore')
            ->assertRedirect(route('staff.exams.edit', $examType));

        $examType->refresh();
        $this->assertSame(3, $examType->version);
        $this->assertSame("SELECT 1\nFROM dual", $examType->sql_query);
        $this->assertSame(['CD_SETOR'], $examType->fields()->pluck('placeholder')->all());
        $this->assertSame([1, 2, 3], $examType->versions()->orderBy('version')->pluck('version')->all());
        $this->assertSame(1, $examType->versions()->where('version', 3)->value('restored_from'));
    }

    public function test_support_cannot_restore_a_version(): void
    {
        $examType = $this->examWithTwoVersions();
        $this->actingAs(User::factory()->support()->withTwoFactor()->create());

        Livewire::test('pages::staff.exams.version', ['examType' => $examType, 'version' => 1])
            ->call('restore')
            ->assertForbidden();

        $this->assertSame(2, $examType->fresh()->version);
    }

    public function test_returns_404_for_a_version_that_does_not_exist(): void
    {
        $examType = $this->examWithTwoVersions();
        $this->actingAs(User::factory()->admin()->withTwoFactor()->create());

        $this->get(route('staff.exams.versions.show', [$examType, 9]))->assertNotFound();
    }
}
