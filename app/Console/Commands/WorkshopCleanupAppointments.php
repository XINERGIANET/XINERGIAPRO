<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class WorkshopCleanupAppointments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workshop:cleanup-appointments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cambia el estado de las citas pendientes vencidas a "no llego" (no_show)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando limpieza de citas vencidas...');

        // Buscamos citas PENDIENTES cuya fecha de inicio sea menor al inicio de hoy
        $count = Appointment::query()
            ->where('status', 'pending')
            ->where('start_at', '<', now()->startOfDay())
            ->whereNull('movement_id') // Doble check para no marcar citas ya atendidas
            ->update(['status' => 'no_show']);

        $message = "Se han actualizado {$count} citas a estado 'no_show'.";
        
        $this->info($message);
        Log::info('[WorkshopCleanup] ' . $message);
    }
}
