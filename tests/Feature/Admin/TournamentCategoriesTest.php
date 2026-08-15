<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Competition\Domain\Models\Round;
use App\Modules\Horses\Domain\Models\Horse;
use App\Modules\Participants\Domain\Models\Participant;
use App\Modules\Registrations\Application\Services\RegisterParticipantInCategory;
use App\Modules\Tournaments\Application\Services\SaveTournamentCategory;
use App\Modules\Tournaments\Domain\Enums\CategoryStatus;
use App\Modules\Tournaments\Domain\Enums\TournamentStatus;
use App\Modules\Tournaments\Domain\Models\Tournament;
use App\Modules\Tournaments\Domain\Models\TournamentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regresión: las páginas que reciben un recurso suelto (un solo JsonResource)
 * deben exponer las props en forma plana — no envueltas en { data: ... } —,
 * porque el frontend lee tournament.slug, category.name, etc. directamente.
 *
 * El envoltorio «data» rompía el detalle del torneo y, con él, la creación de
 * categorías (el formulario posteaba a /admin/tournaments/undefined/categories).
 */
class TournamentCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    #[Test]
    public function el_detalle_del_torneo_expone_el_recurso_en_forma_plana(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tournaments/Show')
                ->where('tournament.slug', $tournament->slug)
                ->where('tournament.name', $tournament->name)
                ->where('tournament.categories.0.slug', $category->slug)
                ->where('tournament.categories.0.name', $category->name)
                ->has('tournament.categories', 1)
            );
    }

    #[Test]
    public function crea_una_categoria_con_su_estructura_de_dias_y_rondas(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->post("/admin/tournaments/{$tournament->slug}/categories", [
                'name' => 'Profesional',
                'order' => 0,
                'number_of_days' => 1,
                'rounds_per_day' => 4,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $category = TournamentCategory::query()->where('slug', 'profesional')->firstOrFail();

        $this->assertSame(1, $category->number_of_days);
        $this->assertSame(4, $category->rounds_per_day);
        $this->assertSame(1, $category->competitionDays()->count());
        $this->assertSame(5, Round::query()
            ->whereIn('competition_day_id', $category->competitionDays()->select('id'))
            ->count(), '1 día × 4 lazos + lazo final = 5 rondas.');
    }

    #[Test]
    public function la_portada_subida_se_guarda_y_viaja_a_las_dos_caras(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin)
            ->post('/admin/tournaments', [
                'name' => 'Copa Colombia',
                'start_date' => '2026-09-10',
                'cover_image' => UploadedFile::fake()->image('afiche.jpg', 1200, 675),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $tournament = Tournament::query()->where('name', 'Copa Colombia')->firstOrFail();

        $this->assertNotNull($tournament->cover_image_path);
        Storage::disk('public')->assertExists($tournament->cover_image_path);

        // El panel la pinta en el detalle…
        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tournaments/Show')
                ->where('tournament.cover_image_url', $tournament->cover_image_url)
            );

        // …y la cara pública en el directorio, una vez publicado.
        $tournament->update(['status' => TournamentStatus::Published]);

        $this->get('/torneos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/tournaments/Index')
                ->where('tournaments.data.0.cover_image_url', $tournament->cover_image_url)
            );
    }

    #[Test]
    public function elimina_un_torneo_vacio_con_todo_su_andamiaje(): void
    {
        Storage::fake('public');

        $tournament = Tournament::factory()->create([
            'name' => 'Copa Ensayo',
            'slug' => 'copa-ensayo',
            'cover_image_path' => UploadedFile::fake()->image('afiche.jpg')->store('tournaments', 'public'),
        ]);

        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->delete("/admin/tournaments/{$tournament->slug}")
            ->assertRedirect('/admin/tournaments')
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tournaments', ['id' => $tournament->id]);
        $this->assertDatabaseMissing('tournament_categories', ['id' => $category->id]);
        $this->assertSame(0, $category->competitionDays()->count());
        Storage::disk('public')->assertMissing($tournament->cover_image_path);
    }

    #[Test]
    public function no_elimina_un_torneo_con_jinetes_inscritos(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        app(RegisterParticipantInCategory::class)->handle($category, Participant::factory()->create());

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->delete("/admin/tournaments/{$tournament->slug}")
            ->assertRedirect("/admin/tournaments/{$tournament->slug}")
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('tournaments', ['id' => $tournament->id]);
    }

    #[Test]
    public function el_detalle_dice_si_el_torneo_se_puede_eliminar(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}")
            ->assertInertia(fn (Assert $page) => $page->where('tournament.can_be_deleted', true));

        app(RegisterParticipantInCategory::class)->handle($category, Participant::factory()->create());

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}")
            ->assertInertia(fn (Assert $page) => $page->where('tournament.can_be_deleted', false));
    }

    #[Test]
    public function edita_una_categoria_sin_cambiarle_el_slug(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->put("/admin/tournaments/{$tournament->slug}/categories/{$category->slug}", [
                'name' => 'Profesional Élite',
                // El administrador manda el slug actual: renombrar no debe
                // mover la URL pública de la categoría.
                'slug' => $category->slug,
                'description' => 'La categoría mayor del torneo.',
                'order' => 3,
                'number_of_days' => 2,
                'rounds_per_day' => 3,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $category->refresh();

        $this->assertSame('Profesional Élite', $category->name);
        $this->assertSame('profesional', $category->slug);
        $this->assertSame('La categoría mayor del torneo.', $category->description);
        $this->assertSame(3, $category->order);

        // La estructura se rehízo: 2 días × 3 rondas + lazo final en el día 2.
        $this->assertSame(2, $category->competitionDays()->count());
        $this->assertSame(7, Round::query()
            ->whereIn('competition_day_id', $category->competitionDays()->select('id'))
            ->count());
    }

    #[Test]
    public function no_admite_dos_categorias_con_el_mismo_nombre_en_un_torneo(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->post("/admin/tournaments/{$tournament->slug}/categories", [
                'name' => 'Profesional',
                'number_of_days' => 1,
                'rounds_per_day' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, $tournament->categories()->count());
    }

    #[Test]
    public function no_permite_cambiar_la_estructura_de_una_categoria_en_progreso(): void
    {
        $tournament = Tournament::factory()->inProgress()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $category->update(['status' => CategoryStatus::InProgress]);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->put("/admin/tournaments/{$tournament->slug}/categories/{$category->slug}", [
                'name' => 'Profesional',
                'slug' => $category->slug,
                'number_of_days' => 4,
                'rounds_per_day' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('number_of_days');

        $this->assertSame(1, $category->refresh()->number_of_days);
    }

    #[Test]
    public function renombrar_una_categoria_en_progreso_si_esta_permitido(): void
    {
        $tournament = Tournament::factory()->inProgress()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $category->update(['status' => CategoryStatus::InProgress]);

        $this->actingAs($this->admin)
            ->from("/admin/tournaments/{$tournament->slug}")
            ->put("/admin/tournaments/{$tournament->slug}/categories/{$category->slug}", [
                'name' => 'Profesional A',
                'slug' => $category->slug,
                'number_of_days' => 1,
                'rounds_per_day' => 2,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('Profesional A', $category->refresh()->name);
    }

    #[Test]
    public function editar_un_torneo_expone_el_recurso_en_forma_plana(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tournaments/Form')
                ->where('tournament.slug', $tournament->slug)
                ->where('tournament.name', $tournament->name)
            );
    }

    #[Test]
    public function la_pagina_de_inscripciones_expone_la_categoria_en_forma_plana(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}/categories/{$category->slug}/registrations")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('registrations/Index')
                ->where('category.slug', $category->slug)
                ->where('category.name', $category->name)
                ->where('category.tournament.slug', $tournament->slug)
            );
    }

    #[Test]
    public function la_consola_expone_la_categoria_en_forma_plana(): void
    {
        $tournament = Tournament::factory()->published()->create(['slug' => 'copa-colombia']);
        $category = app(SaveTournamentCategory::class)->create($tournament, [
            'name' => 'Profesional',
            'number_of_days' => 1,
            'rounds_per_day' => 2,
        ]);

        $this->actingAs($this->admin)
            ->get("/admin/tournaments/{$tournament->slug}/categories/{$category->slug}/competition")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('competition/Console')
                ->where('category.slug', $category->slug)
                ->where('category.name', $category->name)
            );
    }

    #[Test]
    public function las_paginas_de_participantes_y_caballos_expone_recursos_planos(): void
    {
        $participant = Participant::factory()->create();
        $horse = Horse::factory()->for($participant)->create();

        $this->actingAs($this->admin)
            ->get("/admin/participants/{$participant->public_id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('participants/Show')
                ->where('participant.public_id', $participant->public_id)
                ->where('participant.full_name', $participant->full_name)
            );

        $this->actingAs($this->admin)
            ->get("/admin/participants/{$participant->public_id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('participants/Form')
                ->where('participant.public_id', $participant->public_id)
            );

        $this->actingAs($this->admin)
            ->get("/admin/horses/{$horse->public_id}/edit")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('horses/Form')
                ->where('horse.public_id', $horse->public_id)
                ->where('horse.name', $horse->name)
            );
    }
}
