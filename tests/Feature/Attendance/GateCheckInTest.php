<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceGateSetting;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Pointage geolocalise au portail (cahier des charges — pointage par QR code, option 1). */
class GateCheckInTest extends TestCase
{
    use RefreshDatabase;

    private const SCHOOL_LATITUDE = 14.6937;

    private const SCHOOL_LONGITUDE = -17.4441;

    #[Test]
    public function un_eleve_a_proximite_du_portail_pointe_sa_presence(): void
    {
        $student = $this->studentWithPassword();
        $setting = $this->enabledGate();

        // ~55 m plus au nord : dans le rayon de 100 m.
        $this->actingAs($student)->postJson('/api/parent/attendance/check-in', [
            'token' => $setting->gate_token,
            'latitude' => self::SCHOOL_LATITUDE + 0.0005,
            'longitude' => self::SCHOOL_LONGITUDE,
        ])->assertCreated()->assertJsonPath('data.status', 'present')->assertJsonPath('data.source', 'self_service');

        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id,
            'status' => 'present',
            'source' => 'self_service',
        ]);
    }

    #[Test]
    public function un_eleve_trop_loin_du_portail_est_refuse(): void
    {
        $student = $this->studentWithPassword();
        $setting = $this->enabledGate();

        // ~555 m plus au nord : hors du rayon de 100 m.
        $response = $this->actingAs($student)->postJson('/api/parent/attendance/check-in', [
            'token' => $setting->gate_token,
            'latitude' => self::SCHOOL_LATITUDE + 0.005,
            'longitude' => self::SCHOOL_LONGITUDE,
        ]);

        $response->assertStatus(422)->assertJsonPath('error_code', 'outside_gate_radius');
        $this->assertGreaterThan(100, $response->json('context.distance_meters'));
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $student->id]);
    }

    #[Test]
    public function un_jeton_invalide_est_refuse(): void
    {
        $student = $this->studentWithPassword();
        $this->enabledGate();

        $this->actingAs($student)->postJson('/api/parent/attendance/check-in', [
            'token' => (string) Str::uuid(),
            'latitude' => self::SCHOOL_LATITUDE,
            'longitude' => self::SCHOOL_LONGITUDE,
        ])->assertStatus(422)->assertJsonPath('error_code', 'invalid_gate_token');
    }

    #[Test]
    public function le_pointage_desactive_est_refuse(): void
    {
        $student = $this->studentWithPassword();
        $setting = AttendanceGateSetting::create([
            'latitude' => self::SCHOOL_LATITUDE,
            'longitude' => self::SCHOOL_LONGITUDE,
            'radius_meters' => 100,
            'gate_token' => (string) Str::uuid(),
            'is_enabled' => false,
        ]);

        $this->actingAs($student)->postJson('/api/parent/attendance/check-in', [
            'token' => $setting->gate_token,
            'latitude' => self::SCHOOL_LATITUDE,
            'longitude' => self::SCHOOL_LONGITUDE,
        ])->assertStatus(422)->assertJsonPath('error_code', 'gate_disabled');
    }

    #[Test]
    public function un_second_pointage_le_meme_jour_ne_duplique_pas_la_ligne(): void
    {
        $student = $this->studentWithPassword();
        $setting = $this->enabledGate();

        $payload = [
            'token' => $setting->gate_token,
            'latitude' => self::SCHOOL_LATITUDE,
            'longitude' => self::SCHOOL_LONGITUDE,
        ];

        $this->actingAs($student)->postJson('/api/parent/attendance/check-in', $payload)->assertCreated();
        $this->actingAs($student)->postJson('/api/parent/attendance/check-in', $payload)->assertCreated();

        $this->assertSame(1, AttendanceRecord::where('student_id', $student->id)->count());
    }

    private function enabledGate(): AttendanceGateSetting
    {
        return AttendanceGateSetting::create([
            'latitude' => self::SCHOOL_LATITUDE,
            'longitude' => self::SCHOOL_LONGITUDE,
            'radius_meters' => 100,
            'gate_token' => (string) Str::uuid(),
            'is_enabled' => true,
        ]);
    }
}
