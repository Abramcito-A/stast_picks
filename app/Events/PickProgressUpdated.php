<?php

namespace App\Events;

use App\Models\Pick;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento que se emite vía WebSocket (Reverb) cada vez que
 * el progreso de un pick cambia. El frontend (Angular) escucha
 * este evento en el canal privado del usuario para actualizar
 * la barra de progreso en tiempo real.
 */
class PickProgressUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly Pick $pick)
    {
        //
    }

    /**
     * Canal privado del usuario dueño del parlay.
     * Angular escuchará: Echo.private(`user.${userId}`)
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->pick->parlay->user_id}"),
        ];
    }

    /**
     * Nombre del evento que escuchará el frontend.
     * Angular: .listen('.pick.progress.updated', callback)
     */
    public function broadcastAs(): string
    {
        return 'pick.progress.updated';
    }

    /**
     * Payload JSON que recibirá Angular para actualizar la UI.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $pick  = $this->pick;
        $event = $pick->event;

        return [
            'pick' => [
                'id'                  => $pick->id,
                'pick_type'           => $pick->pick_type,
                'condition'           => $pick->condition,
                'target_value'        => (float) $pick->target_value,
                'current_progress'    => (float) $pick->current_progress,
                'progress_percentage' => (float) $pick->progress_percentage,
                'status'              => $pick->status,
                'odd'                 => (float) $pick->odd,
            ],
            'parlay' => [
                'id'     => $pick->parlay_id,
                'status' => $pick->parlay->status,
            ],
            'event' => [
                'id'              => $event->id,
                'home_team'       => $event->home_team,
                'away_team'       => $event->away_team,
                'home_score'      => $event->home_score,
                'away_score'      => $event->away_score,
                'status'          => $event->status,
                'elapsed_minutes' => $event->elapsed_minutes,
            ],
        ];
    }
}
