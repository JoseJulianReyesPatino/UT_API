<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class PruneOldTokens extends Command
{
    protected $signature = 'tokens:prune
                            {--keep=3 : Número de tokens más recientes a conservar por usuario}
                            {--user= : ID del usuario (deja vacío para todos)}
                            {--dry-run : Muestra cuántos se eliminarían sin borrar nada}';

    protected $description = 'Elimina tokens de autenticación antiguos, conservando solo los N más recientes por usuario';

    public function handle(): int
    {
        $keep    = (int) $this->option('keep');
        $userId  = $this->option('user');
        $dryRun  = $this->option('dry-run');

        if ($keep < 1) {
            $this->error('--keep debe ser al menos 1.');
            return self::FAILURE;
        }

        $query = DB::table('personal_access_tokens')
            ->select('id', 'tokenable_id', 'tokenable_type', 'created_at')
            ->where('tokenable_type', 'App\\Models\\User')
            ->orderBy('tokenable_id')
            ->orderByDesc('created_at');

        if ($userId) {
            $query->where('tokenable_id', $userId);
        }

        $tokens  = $query->get();
        $toDelete = [];

        // Agrupar por usuario y marcar los que superan el límite
        $byUser = $tokens->groupBy('tokenable_id');
        foreach ($byUser as $uid => $userTokens) {
            $old = $userTokens->skip($keep);
            foreach ($old as $token) {
                $toDelete[] = $token->id;
            }
        }

        $count = count($toDelete);

        if ($count === 0) {
            $this->info('No hay tokens para eliminar.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn("[DRY RUN] Se eliminarían {$count} token(s).");
            foreach ($byUser as $uid => $userTokens) {
                $toRemove = $userTokens->count() - $keep;
                if ($toRemove > 0) {
                    $this->line("  Usuario {$uid}: {$userTokens->count()} tokens → eliminar {$toRemove}");
                }
            }
            return self::SUCCESS;
        }

        DB::table('personal_access_tokens')->whereIn('id', $toDelete)->delete();
        $this->info("Se eliminaron {$count} token(s) antiguos.");

        return self::SUCCESS;
    }
}
