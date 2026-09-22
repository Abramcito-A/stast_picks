<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Parlay;
use App\Models\Pick;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Usuario de prueba principal ────────────────────────────────────────
        $user = User::factory()->create([
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        // ── Eventos deportivos de prueba ───────────────────────────────────────
        // 3 partidos en vivo, 2 programados, 2 finalizados
        $liveEvents     = Event::factory()->count(3)->live()->create();
        $scheduledEvent = Event::factory()->count(2)->create();
        $finishedEvents = Event::factory()->count(2)->finished()->create();

        $allEvents = $liveEvents->merge($scheduledEvent);

        // ── Parlay activo con picks sobre partidos en vivo ─────────────────────
        $activeParlay = Parlay::factory()->active()->create([
            'user_id' => $user->id,
            'name'    => 'Champions Tuesday',
            'stake'   => 25.00,
        ]);

        foreach ($liveEvents as $event) {
            Pick::create([
                'parlay_id'           => $activeParlay->id,
                'event_id'            => $event->id,
                'pick_type'           => 'total_goals',
                'condition'           => 'over',
                'target_value'        => 2.5,
                'current_progress'    => $event->home_score + $event->away_score,
                'progress_percentage' => min(100, round((($event->home_score + $event->away_score) / 2.5) * 100, 2)),
                'status'              => 'pending',
                'odd'                 => 1.85,
            ]);
        }

        // ── Parlay pendiente con picks futuros ─────────────────────────────────
        $pendingParlay = Parlay::factory()->create([
            'user_id' => $user->id,
            'name'    => 'Weekend Special',
            'stake'   => 10.00,
        ]);

        foreach ($scheduledEvent as $event) {
            Pick::create([
                'parlay_id'        => $pendingParlay->id,
                'event_id'         => $event->id,
                'pick_type'        => 'result',
                'condition'        => 'home',
                'target_value'     => 1.0,
                'current_progress' => 0.0,
                'status'           => 'pending',
                'odd'              => 2.10,
            ]);
        }

        // ── Usuario extra con 2 parlays ────────────────────────────────────────
        User::factory()
            ->count(2)
            ->create()
            ->each(function (User $u) use ($allEvents) {
                Parlay::factory()
                    ->count(2)
                    ->create(['user_id' => $u->id])
                    ->each(function (Parlay $parlay) use ($allEvents) {
                        $events = $allEvents->random(2);
                        foreach ($events as $event) {
                            Pick::factory()->create([
                                'parlay_id' => $parlay->id,
                                'event_id'  => $event->id,
                            ]);
                        }
                    });
            });

        $this->command->info('✅ Seed completo: 3 usuarios, 7 eventos, múltiples parlays y picks.');
    }
}
