<?php

namespace Tests\Feature\Auth;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** "Se souvenir de moi" : une session ordinaire dure une journee, une session retenue un mois. */
class RememberMeTest extends TestCase
{
    use RefreshDatabase;

    private function minutesBeforeExpiry(PersonalAccessToken $token): int
    {
        return (int) round(now()->diffInMinutes($token->expires_at));
    }

    #[Test]
    public function sans_la_case_cochee_le_jeton_du_personnel_expire_au_bout_d_une_journee(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk();

        $this->assertSame(config('auth.token_lifetime.standard'), $this->minutesBeforeExpiry($user->tokens()->firstOrFail()));
    }

    #[Test]
    public function avec_la_case_cochee_le_jeton_du_personnel_dure_un_mois(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password', 'remember' => true])->assertOk();

        $this->assertSame(config('auth.token_lifetime.remembered'), $this->minutesBeforeExpiry($user->tokens()->firstOrFail()));
    }

    #[Test]
    public function la_duree_du_jeton_d_un_parent_suit_la_meme_regle(): void
    {
        $ordinary = $this->studentWithPassword();
        $remembered = $this->studentWithPassword();

        $this->postJson('/api/parent/login', ['matricule' => $ordinary->matricule, 'password' => 'password'])->assertOk();
        $this->postJson('/api/parent/login', ['matricule' => $remembered->matricule, 'password' => 'password', 'remember' => true])->assertOk();

        $this->assertSame(config('auth.token_lifetime.standard'), $this->minutesBeforeExpiry($ordinary->tokens()->firstOrFail()));
        $this->assertSame(config('auth.token_lifetime.remembered'), $this->minutesBeforeExpiry($remembered->tokens()->firstOrFail()));
    }

    #[Test]
    public function un_jeton_expire_est_refuse(): void
    {
        $user = User::factory()->create();
        $token = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->json('data.token');

        $this->travel(config('auth.token_lifetime.standard') + 1)->minutes();

        $this->withToken($token)->getJson('/api/me')->assertStatus(401);
    }

    #[Test]
    public function une_valeur_de_case_invalide_est_refusee(): void
    {
        $user = User::factory()->create();

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password', 'remember' => 'peut-etre'])
            ->assertStatus(422)->assertJsonValidationErrors('remember');
    }
}
