<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateDocumentStorage extends Command
{
    protected $signature = 'documents:migrate-storage
                            {--dry-run    : Simular sin mover ni eliminar nada}
                            {--clean-orphans : Eliminar archivos huérfanos del disco (sin registro en BD)}';

    protected $description = 'Consolida todos los PDFs a documents/YYYY/MM/ y limpia archivos huérfanos';

    // Directorios del disco "public" que se escanean para detectar huérfanos
    private const SCAN_DIRS = [
        'documents',
        'uploads/documents',
    ];

    public function handle(): int
    {
        $dryRun        = $this->option('dry-run');
        $cleanOrphans  = $this->option('clean-orphans');

        if ($dryRun) {
            $this->warn('[DRY-RUN] No se moverá ni eliminará ningún archivo.');
        }

        $this->newLine();
        $this->info('═══ FASE 1: Migrar documentos de la BD ══════════════════════════');
        $migrateStats = $this->migrateDbDocuments($dryRun);

        $this->newLine();
        $this->info('═══ FASE 2: Detectar archivos huérfanos en disco ════════════════');
        $orphanStats  = $this->handleOrphans($dryRun, $cleanOrphans);

        $this->newLine();
        $this->info('═══ RESUMEN FINAL ════════════════════════════════════════════════');
        $this->table(
            ['Categoría', 'Resultado', 'Cantidad'],
            [
                ['BD → disco',  $dryRun ? 'Por mover'          : 'Movidos',   $migrateStats['moved']],
                ['BD → disco',  'Ya en ruta correcta',                         $migrateStats['skipped']],
                ['BD → disco',  'Archivo no encontrado en disco',               $migrateStats['missing']],
                ['BD → disco',  'Errores',                                      $migrateStats['errors']],
                ['Huérfanos',   $cleanOrphans ? 'Eliminados' : 'Encontrados',  $orphanStats['count']],
            ]
        );

        return ($migrateStats['errors'] > 0) ? self::FAILURE : self::SUCCESS;
    }

    /* ─────────────────────────────────────────────
     * FASE 1 – Migrar registros de BD
     * ───────────────────────────────────────────── */
    private function migrateDbDocuments(bool $dryRun): array
    {
        $stats = ['moved' => 0, 'skipped' => 0, 'missing' => 0, 'errors' => 0];

        $documents = Document::query()
            ->whereNotNull('file_path')
            ->orderBy('id')
            ->get(['id', 'file_path', 'created_at', 'submitted_at']);

        $this->line("Documentos en BD: {$documents->count()}");
        $bar = $this->output->createProgressBar($documents->count());
        $bar->start();

        foreach ($documents as $doc) {
            $bar->advance();

            $currentPath = $this->resolveCurrentPath($doc->file_path);

            if ($currentPath === null) {
                $this->newLine();
                $this->warn("  [#{$doc->id}] Sin archivo en disco: {$doc->file_path}");
                $stats['missing']++;
                continue;
            }

            $canonicalPath = $this->buildCanonicalPath($doc, $currentPath);

            if ($currentPath === $canonicalPath) {
                $stats['skipped']++;
                continue;
            }

            if ($dryRun) {
                $this->newLine();
                $this->line("  [#{$doc->id}] {$currentPath}  →  {$canonicalPath}");
                $stats['moved']++;
                continue;
            }

            try {
                $dir = dirname($canonicalPath);
                if (!Storage::disk('public')->exists($dir)) {
                    Storage::disk('public')->makeDirectory($dir);
                }

                // Si el destino ya existe con otro nombre, no sobreescribir
                if (Storage::disk('public')->exists($canonicalPath)) {
                    $info  = pathinfo($canonicalPath);
                    $canonicalPath = $info['dirname'] . '/' . $info['filename'] . '_' . uniqid() . '.' . ($info['extension'] ?? 'pdf');
                }

                Storage::disk('public')->copy($currentPath, $canonicalPath);
                Storage::disk('public')->delete($currentPath);
                $doc->update(['file_path' => $canonicalPath]);
                $stats['moved']++;
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("  [#{$doc->id}] Error: {$e->getMessage()} (origen: {$currentPath})");
                $stats['errors']++;
            }
        }

        $bar->finish();
        $this->newLine();

        return $stats;
    }

    /* ─────────────────────────────────────────────
     * FASE 2 – Detectar / eliminar huérfanos
     * ───────────────────────────────────────────── */
    private function handleOrphans(bool $dryRun, bool $cleanOrphans): array
    {
        // Obtener todos los file_path activos de la BD
        $knownPaths = DB::table('documents')
            ->whereNotNull('file_path')
            ->pluck('file_path')
            ->map(fn($p) => ltrim(trim($p), '/'))
            ->flip()   // convertir a map para búsqueda O(1)
            ->all();

        $orphans = [];

        foreach (self::SCAN_DIRS as $dir) {
            if (!Storage::disk('public')->exists($dir)) {
                continue;
            }

            $files = Storage::disk('public')->allFiles($dir);

            foreach ($files as $file) {
                if (!isset($knownPaths[$file])) {
                    $orphans[] = $file;
                }
            }
        }

        if (empty($orphans)) {
            $this->line('  No se encontraron archivos huérfanos.');
            return ['count' => 0];
        }

        $this->warn("  Archivos huérfanos encontrados: " . count($orphans));
        foreach ($orphans as $orphan) {
            $size = Storage::disk('public')->size($orphan);
            $this->line("    " . ($cleanOrphans && !$dryRun ? '[ELIMINAR] ' : '[HUÉRFANO] ') . "{$orphan}  (" . number_format($size / 1024, 1) . " KB)");
        }

        if ($cleanOrphans && !$dryRun) {
            foreach ($orphans as $orphan) {
                Storage::disk('public')->delete($orphan);
            }
            $this->info('  Archivos huérfanos eliminados: ' . count($orphans));
        } elseif ($cleanOrphans && $dryRun) {
            $this->warn('  [DRY-RUN] Se eliminarían ' . count($orphans) . ' archivos huérfanos.');
        } else {
            $this->warn('  Usa --clean-orphans para eliminarlos.');
        }

        return ['count' => count($orphans)];
    }

    /* ─────────────────────────────────────────────
     * Helpers
     * ───────────────────────────────────────────── */
    private function resolveCurrentPath(string $filePath): ?string
    {
        $normalized = ltrim(trim($filePath), '/');

        $candidates = array_values(array_unique([
            $normalized,
            preg_replace('#^(storage|public)/+#', '', $normalized),
            preg_replace('#^uploads/#', '', $normalized),
            preg_replace('#^documents/#', '', $normalized),
            'uploads/'   . $normalized,
            'documents/' . $normalized,
            'uploads/'   . preg_replace('#^documents/#', '', $normalized),
            'documents/' . preg_replace('#^uploads/#',   '', $normalized),
        ]));

        foreach ($candidates as $candidate) {
            if ($candidate && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function buildCanonicalPath(Document $doc, string $currentPath): string
    {
        $date      = $doc->submitted_at ?? $doc->created_at ?? now();
        $yearMonth = $date->format('Y/m');
        $basename  = basename($currentPath);

        if (!str_starts_with($basename, 'doc_')) {
            $basename = 'doc_' . $basename;
        }

        return "documents/{$yearMonth}/{$basename}";
    }
}
