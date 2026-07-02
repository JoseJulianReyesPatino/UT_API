<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Form;
use App\Models\User;
use App\Models\AcademicCycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportWixDocuments extends Command
{
    protected $signature = 'import:wix
                            {csv : Ruta al archivo CSV (relativa a storage/app/csv/)}
                            {--form-code=ficha_tecnica : form_code del formulario}
                            {--cycle-id= : ID del ciclo escolar (usa el activo si se omite)}
                            {--dry-run : Solo muestra qué haría sin guardar nada}
                            {--skip-download : No descarga archivos, solo crea registros con URL externa}';

    protected $description = 'Importa documentos desde un CSV exportado de Wix';

    // Columnas esperadas (en el orden del CSV de Wix)
    const COL_DATE   = 0; // Fecha de envío
    const COL_FILE   = 1; // URL del archivo
    const COL_NOTE   = 2; // Nota para administrador
    const COL_NAME   = 3; // Nombre del docente

    public function handle(): int
    {
        $csvPath  = storage_path('app/csv/' . $this->argument('csv'));
        $dryRun   = $this->option('dry-run');
        $skipDl   = $this->option('skip-download');
        $formCode = $this->option('form-code');

        // ── Validaciones previas ──────────────────────────────────────────────
        if (!file_exists($csvPath)) {
            $this->error("No se encontró el CSV en: {$csvPath}");
            return 1;
        }

        $form = Form::where('form_code', $formCode)->first();
        if (!$form) {
            $this->error("No existe un formulario con form_code = '{$formCode}'");
            return 1;
        }

        $cycleId = $this->option('cycle-id')
            ? (int) $this->option('cycle-id')
            : optional(AcademicCycle::where('status', 'activo')->first())->id;

        if (!$cycleId) {
            $this->error('No hay ciclo activo y no se proporcionó --cycle-id');
            return 1;
        }

        // ── Cargar todos los usuarios para matching ───────────────────────────
        $users = User::where('is_active', true)->get();

        // ── Leer CSV ──────────────────────────────────────────────────────────
        $rows = $this->parseCsv($csvPath);
        if (empty($rows)) {
            $this->warn('El CSV está vacío o no tiene filas de datos.');
            return 0;
        }

        $this->info("Formulario : {$form->title} (form_id={$form->id})");
        $this->info("Ciclo ID   : {$cycleId}");
        $this->info("Filas CSV  : " . count($rows));
        $this->info("Modo       : " . ($dryRun ? 'DRY-RUN (sin guardar)' : 'REAL'));
        $this->line('');

        $stats = ['ok' => 0, 'skipped' => 0, 'no_user' => 0, 'error' => 0];
        $noMatchNames = [];

        foreach ($rows as $i => $row) {
            $lineNum  = $i + 2; // +2 porque la fila 1 es cabecera
            $rawDate  = trim($row[self::COL_DATE]  ?? '');
            $fileUrl  = trim($row[self::COL_FILE]  ?? '');
            $note     = trim($row[self::COL_NOTE]  ?? '');
            $rawName  = trim($row[self::COL_NAME]  ?? '');

            if (!$fileUrl || !$rawName) {
                $this->warn("  Línea {$lineNum}: Falta URL o nombre. Omitiendo.");
                $stats['skipped']++;
                continue;
            }

            // ── Matching de nombre ────────────────────────────────────────────
            $user = $this->matchUser($rawName, $users);
            if (!$user) {
                $this->error("  Línea {$lineNum}: No se encontró usuario para '{$rawName}'");
                $noMatchNames[] = $rawName;
                $stats['no_user']++;
                continue;
            }

            $this->line("  Línea {$lineNum}: '{$rawName}' → {$user->full_name} (id={$user->id})");

            // ── Descargar o referenciar el archivo ────────────────────────────
            $filePath = null;
            $mimeType = 'application/pdf';
            $fileSize = null;

            if (!$skipDl) {
                [$filePath, $fileSize] = $this->downloadFile($fileUrl, $form->form_code, $dryRun);
                if (!$filePath && !$dryRun) {
                    $this->error("    ✗ No se pudo descargar: {$fileUrl}");
                    $stats['error']++;
                    continue;
                }
            } else {
                // Guarda la URL externa directamente como file_path
                $filePath = $fileUrl;
            }

            // ── Construir título ──────────────────────────────────────────────
            $fileName  = $this->extractFileName($fileUrl);
            $title     = "{$form->title} - {$fileName}";
            $submitted = $this->parseDate($rawDate);

            // ── Crear registro ────────────────────────────────────────────────
            if ($dryRun) {
                $this->line("    [DRY-RUN] Crearía documento: '{$title}'");
                $stats['ok']++;
                continue;
            }

            try {
                Document::create([
                    'form_id'       => $form->id,
                    'cycle_id'      => $cycleId,
                    'uploaded_by'   => $user->id,
                    'title'         => $title,
                    'apartado_label'=> $form->form_code,
                    'file_path'     => $filePath,
                    'mime_type'     => $mimeType,
                    'file_size_bytes' => $fileSize,
                    'status'        => 'pendiente',
                    'submitted_at'  => $submitted,
                    'nota'          => $note ?: null,
                ]);
                $this->info("    ✓ Importado: {$title}");
                $stats['ok']++;
            } catch (\Throwable $e) {
                $this->error("    ✗ Error al guardar: " . $e->getMessage());
                $stats['error']++;
            }
        }

        // ── Resumen ───────────────────────────────────────────────────────────
        $this->line('');
        $this->info('═══════════════════════════════');
        $this->info("✓ Importados : {$stats['ok']}");
        $this->warn("⚠ Omitidos  : {$stats['skipped']}");
        $this->error("✗ Sin usuario: {$stats['no_user']}");
        $this->error("✗ Errores    : {$stats['error']}");

        if (!empty($noMatchNames)) {
            $this->line('');
            $this->warn('Nombres sin coincidencia (revisa manualmente):');
            foreach (array_unique($noMatchNames) as $name) {
                $this->line("  - {$name}");
            }
        }

        return 0;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($fh = fopen($path, 'r')) === false) return [];

        $header = true;
        while (($row = fgetcsv($fh)) !== false) {
            if ($header) { $header = false; continue; } // salta cabecera
            if (array_filter($row)) $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    private function matchUser(string $rawName, $users): ?User
    {
        // ─── MAPEO MANUAL PARA NOMBRES QUE NO COINCIDEN EXACTAMENTE ───
        // Agrega aquí todos los nombres que no coinciden exactamente
        // Formato: 'Nombre en CSV' => ID del usuario en la BD
        $manualMap = [
            'EUTILIA OLIVARES VELAZQUEZ' => 4,              // Lupita Olivares
            'Mónica Alejandra Hernández Cordero' => 11,     // Monica Hernandez
            // Si encuentras más nombres que no coinciden, agrégalos aquí:
            // 'Nombre en CSV' => ID_Usuario,
        ];

        // Verificar mapeo manual primero
        $normalizedInput = $this->normalizeName($rawName);
        foreach ($manualMap as $csvName => $userId) {
            if ($this->normalizeName($csvName) === $normalizedInput) {
                $user = User::find($userId);
                if ($user) {
                    $this->line("    [MAPEO MANUAL] '{$rawName}' → {$user->full_name} (id={$user->id})");
                    return $user;
                }
            }
        }

        // ─── MAPEO AUTOMÁTICO (continúa con la lógica existente) ───
        $normalized = $this->normalizeName($rawName);

        // 1. Coincidencia exacta normalizada
        foreach ($users as $user) {
            if ($this->normalizeName($user->full_name) === $normalized) {
                return $user;
            }
        }

        // 2. El nombre del CSV está contenido en el full_name del usuario
        foreach ($users as $user) {
            $dbNorm = $this->normalizeName($user->full_name);
            if (str_contains($dbNorm, $normalized) || str_contains($normalized, $dbNorm)) {
                return $user;
            }
        }

        // 3. Similitud por palabras: al menos 2 palabras coinciden
        $csvWords = explode(' ', $normalized);
        $bestScore = 0;
        $bestUser  = null;

        foreach ($users as $user) {
            $dbWords = explode(' ', $this->normalizeName($user->full_name));
            $matches = count(array_intersect($csvWords, $dbWords));
            if ($matches >= 2 && $matches > $bestScore) {
                $bestScore = $matches;
                $bestUser  = $user;
            }
        }

        return $bestUser;
    }

    private function normalizeName(string $name): string
    {
        // Quita acentos, convierte a minúsculas, elimina espacios dobles
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
        return strtolower(preg_replace('/\s+/', ' ', trim($normalized)));
    }

    private function downloadFile(string $url, string $formCode, bool $dryRun): array
    {
        if ($dryRun) return ['dry-run/placeholder.pdf', 0];

        try {
            $context  = stream_context_create(['http' => ['timeout' => 30]]);
            $contents = @file_get_contents($url, false, $context);

            if ($contents === false) return [null, null];

            $fileName    = $this->extractFileName($url);
            $safeName    = Str::slug(pathinfo($fileName, PATHINFO_FILENAME)) . '.pdf';
            $storagePath = 'documents/' . $safeName;

            // Evita duplicados con timestamp
            $storagePath = 'documents/wix_' . time() . '_' . $safeName;

            Storage::disk('public')->put($storagePath, $contents);

            return [$storagePath, strlen($contents)];
        } catch (\Throwable $e) {
            return [null, null];
        }
    }

    private function extractFileName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? $url;
        $base = basename($path);
        return $base ?: 'documento.pdf';
    }

    private function parseDate(string $raw): string
    {
        try {
            return (new \DateTime($raw))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return now()->toDateTimeString();
        }
    }
}
