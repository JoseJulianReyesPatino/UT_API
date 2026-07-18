<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\Group;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DocumentController extends Controller
{
    private function extractCuatrimestreFromGroupCode(?string $groupCode): ?string
    {
        $normalized = $groupCode ? strtoupper(str_replace('_', '-', trim($groupCode))) : '';
        $cuatrimestre = null;

        if ($normalized !== '' && preg_match('/(\d{1,2})/', $normalized, $matches)) {
            $cuatrimestre = $matches[1];
        }

        return $cuatrimestre;
    }

    private function resolveCycleId(?int $requestedCycleId = null, ?int $groupId = null, ?int $currentCycleId = null): ?int
    {
        if ($requestedCycleId && $requestedCycleId > 0) {
            return $requestedCycleId;
        }

        if ($groupId && $groupId > 0) {
            $groupCycleId = Group::query()->whereKey($groupId)->value('cycle_id');
            if ($groupCycleId) {
                return (int) $groupCycleId;
            }
        }

        if ($currentCycleId && $currentCycleId > 0) {
            return $currentCycleId;
        }

        return $this->getActiveCycleId();
    }

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        return $user->roles()
            ->where(function ($query) {
                $query
                    ->whereIn('code', ['administrador', 'admin'])
                    ->orWhereIn('name', ['Administrador', 'Admin'])
                    ->orWhere('roles.id', 1);
            })
            ->exists();
    }

    private function isSupervisor(Request $request): bool
    {
        $user = $request->user();
        if (!$user) {
            return false;
        }

        return $user->roles()->where('code', 'supervisor')->exists();
    }

    private function canAccessDocument(Request $request, Document $document): bool
    {
        return $this->isAdmin($request) || $this->isSupervisor($request) || $document->uploaded_by === $request->user()?->id;
    }

    private function formatDocument(Document $document): array
    {
        $submittedAt = $document->submitted_at ?? $document->created_at;
        $groupCode = $document->group?->group_code ?? ($document->group_id ? (string) $document->group_id : null);
        $cuatrimestre = $document->group?->cuatrimestre ?? $this->extractCuatrimestreFromGroupCode($groupCode);
        $tipo = $document->apartado_label
            ? strtolower(str_replace(' ', '-', $document->apartado_label))
            : ($document->form?->form_code ?? 'documento');

        $fileUrl = null;
        $downloadUrl = null;
        $hasFile = false;

        if ($document->file_path) {
            $storedPath = $this->resolveDocumentStoragePath($document->file_path);
            if ($storedPath) {
                $hasFile = true;
                $fileUrl = Storage::disk('public')->url($storedPath);
                $downloadUrl = '/documents/' . $document->id . '/file?download=1';
            }
        }

        $docenteName = $document->uploader?->full_name ?: $document->uploader?->name ?: 'Sin nombre';
        $formTitle = $document->form?->title ?? $document->apartado_label ?? 'Documento';

        return [
            'id' => $document->id,
            'batch_id' => $document->batch_id,
            // Campos en español (compatibilidad con código existente)
            'nombre' => $document->title,
            'tipo' => $tipo,
            'tipoLabel' => $formTitle,
            'materia' => $document->materia ?? 'Sin materia',
            'parcial' => $document->parcial ?? '-',
            'cuatrimestre' => $cuatrimestre,
            'grupo' => $groupCode ?? '-',
            'plan' => $document->plan,
            'carrera' => $document->carrera_label,
            'docente' => $docenteName,
            'fecha' => $submittedAt?->toDateString(),
            'hora' => $submittedAt?->format('H:i'),
            'status' => $document->status,
            'observaciones' => $document->returned_comment,
            'returned_comment' => $document->returned_comment,
            'has_file' => $hasFile,
            'fileUrl' => $fileUrl,
            'downloadUrl' => $downloadUrl,
            // Campos en inglés esperados por el frontend
            'title' => $document->title,
            'form_title' => $formTitle,
            'carrera_label' => $document->carrera_label,
            'uploaded_by_name' => $docenteName,
            'uploaded_by' => $document->uploaded_by,
            'cycle_id' => $document->cycle_id,
            'cycle_name' => $document->cycle?->name,
            'apartado_label' => $document->apartado_label,
            'group_code' => $groupCode,
            'file_path' => $document->file_path,
            'submitted_at' => $submittedAt?->toIso8601String(),
            'reviewed_at' => $document->reviewed_at?->toIso8601String(),
            'returned_at' => $document->returned_at?->toIso8601String(),
            'resubmitted_at' => $document->resubmitted_at?->toIso8601String(),
            'nota' => $document->nota,
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $isAdmin = $this->isAdmin($request);
        $isSupervisor = $this->isSupervisor($request);
        $canSeeAll = $isAdmin || $isSupervisor;
        $query = Document::query()->with(['form', 'group', 'uploader', 'cycle']);

        if ($request->filled('uploaded_by')) {
            $requestedUploaderId = $request->integer('uploaded_by');
            if ($canSeeAll) {
                $query->where('uploaded_by', $requestedUploaderId);
            } else {
                $query->where('uploaded_by', $request->user()->id);
            }
        } elseif (!$canSeeAll) {
            $query->where('uploaded_by', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('apartado_label')) {
            $query->whereRaw('LOWER(apartado_label) = LOWER(?)', [$request->string('apartado_label')]);
        }

        if ($request->filled('cycle_id')) {
            $cycleId = $request->integer('cycle_id');
            $query->where(function ($cycleQuery) use ($cycleId) {
                $cycleQuery
                    ->where('cycle_id', $cycleId)
                    ->orWhere(function ($legacyQuery) use ($cycleId) {
                        $legacyQuery
                            ->whereNull('cycle_id')
                            ->whereHas('group', function ($groupQuery) use ($cycleId) {
                                $groupQuery->where('cycle_id', $cycleId);
                            });
                    });
            });
        }

        if ($request->filled('form_id')) {
            $query->where('form_id', $request->integer('form_id'));
        } elseif ($request->filled('form_codes')) {
            $raw   = $request->input('form_codes');
            $parts = is_array($raw)
                ? $raw
                : explode(',', (string) $raw);
            $codes = array_values(array_filter(array_map(
                fn($c) => str_replace('-', '_', trim($c)),
                $parts
            )));
            $query->whereHas('form', function ($formQuery) use ($codes) {
                $formQuery->whereIn('form_code', $codes);
            });
        } elseif ($request->filled('form_code')) {
            $formCode = str_replace('-', '_', (string) $request->input('form_code', ''));
            $query->whereHas('form', function ($formQuery) use ($formCode) {
                $formQuery->where('form_code', $formCode);
            });
        }

        // Los docentes solo ven sus documentos no ocultos, a menos que pidan incluir ocultos explícitamente
        if (!$canSeeAll && !$request->boolean('include_hidden')) {
            $query->where('hidden_by_docente', false);
        }

        $documents = $query->orderByDesc('submitted_at')->paginate($request->integer('per_page', 20));
        $documents->setCollection($documents->getCollection()->map(fn (Document $document) => $this->formatDocument($document)));

        return response()->json($documents);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'form_id' => ['required', 'integer', 'exists:forms,id'],
            'cycle_id' => ['nullable', 'integer', 'exists:academic_cycles,id'],
            'title' => ['required', 'string', 'max:180'],
            'apartado_label' => ['nullable', 'string', 'max:120'],
            'plan' => ['nullable', 'in:nuevo_modelo,plan_normal'],
            'carrera_label' => ['nullable', 'string', 'max:180'],
            'materia' => ['nullable', 'string', 'max:140'],
            'parcial' => ['nullable', 'string', 'max:40'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'batch_id' => ['nullable', 'string', 'max:64'],
            'file' => ['required', 'file', 'max:15360', function ($attribute, $value, $fail) {
                $ext        = strtolower($value->getClientOriginalExtension());
                $mime       = strtolower($value->getMimeType() ?? '');
                $clientMime = strtolower($value->getClientMimeType() ?? '');
                $validMimes = ['application/pdf', 'application/x-pdf', 'application/acrobat', 'application/vnd.pdf', 'text/pdf'];
                if ($ext !== 'pdf' && !in_array($mime, $validMimes) && !in_array($clientMime, $validMimes)) {
                    $fail('El archivo debe ser un PDF.');
                    return;
                }
                // Verify actual file content via magic bytes (%PDF-)
                $handle = fopen($value->getPathname(), 'rb');
                $header = fread($handle, 5);
                fclose($handle);
                if ($header !== '%PDF-') {
                    $fail('El archivo no es un PDF válido. Asegúrate de subir el archivo correcto y no una página web o acceso directo.');
                }
            }],
            'nota' => ['nullable', 'string', 'max:1000'],
        ]);

        $resolvedCycleId = $this->resolveCycleId(
            isset($data['cycle_id']) ? (int) $data['cycle_id'] : null,
            isset($data['group_id']) ? (int) $data['group_id'] : null,
        );

        if (!$resolvedCycleId) {
            return response()->json([
                'message' => 'No hay ciclo disponible para registrar el documento. Define un ciclo activo o selecciona un grupo con ciclo.',
            ], 422);
        }

        // Verificar que el formulario esté abierto y que el rol del usuario tenga acceso
        $form = \App\Models\Form::with('accessRule.roles')->find($data['form_id']);
        if ($form) {
            if ($form->accessRule?->due_at?->isPast()) {
                return response()->json([
                    'message' => 'El plazo para enviar este formulario ha vencido.',
                ], 422);
            }
            $userRoles    = $request->user()->roles()->pluck('code')->toArray();
            $allowedRoles = $form->accessRule?->roles->pluck('code')->all() ?? [];
            if (!empty($allowedRoles) && empty(array_intersect($userRoles, $allowedRoles))) {
                return response()->json([
                    'message' => 'No tienes permiso para enviar este tipo de formulario.',
                ], 403);
            }
        }

        $file     = $request->file('file');
        $ext      = strtolower($file->getClientOriginalExtension()) ?: 'pdf';
        $safeName = 'doc_' . uniqid('', true) . '.' . $ext;
        $subDir   = 'documents/' . now()->format('Y/m');
        $path     = $file->storeAs($subDir, $safeName, 'public');

        $document = Document::query()->create([
            'form_id' => $data['form_id'],
            'cycle_id' => $resolvedCycleId,
            'uploaded_by' => $request->user()->id,
            'title' => $data['title'],
            'apartado_label' => $data['apartado_label'] ?? null,
            'plan' => $data['plan'] ?? null,
            'carrera_label' => $data['carrera_label'] ?? null,
            'materia' => $data['materia'] ?? null,
            'parcial' => $data['parcial'] ?? null,
            'group_id' => $data['group_id'] ?? null,
            'file_path' => $path,
            'mime_type' => $request->file('file')->getMimeType(),
            'file_size_bytes' => $request->file('file')->getSize(),
            'status' => 'pendiente',
            'submitted_at' => now(),
            'nota' => $data['nota'] ?? null,
            'batch_id' => $data['batch_id'] ?? null,
        ]);

        return response()->json(['data' => $document], 201);
    }

    public function show(Request $request, Document $document): JsonResponse
    {
        if (!$this->canAccessDocument($request, $document)) {
            abort(403);
        }

        $document->loadMissing(['form', 'group']);

        return response()->json(['data' => $this->formatDocument($document)]);
    }

    public function update(Request $request, Document $document): JsonResponse
    {
        if ($document->uploaded_by !== $request->user()?->id && !$this->isAdmin($request)) {
            return response()->json(['message' => 'No tienes permiso para editar este documento.'], 403);
        }

        $request->validate([
            'title'         => ['nullable', 'string', 'max:255'],
            'apartado_label'=> ['nullable', 'string', 'max:100'],
            'plan'          => ['nullable', 'string', 'in:nuevo_modelo,plan_normal,nuevo-modelo,plan-normal'],
            'carrera_label' => ['nullable', 'string', 'max:255'],
            'materia'       => ['nullable', 'string', 'max:255'],
            'parcial'       => ['nullable', 'string', 'max:50'],
            'group_id'      => ['nullable', 'integer', 'exists:groups,id'],
            'cycle_id'      => ['nullable', 'integer', 'exists:cycles,id'],
            'nota'          => ['nullable', 'string', 'max:2000'],
        ]);

        $payload = $request->only([
            'title', 'apartado_label', 'plan', 'carrera_label', 'materia', 'parcial', 'group_id', 'cycle_id', 'nota'
        ]);

        if (array_key_exists('plan', $payload)) {
            $normalized = str_replace('-', '_', (string) ($payload['plan'] ?? ''));
            $payload['plan'] = in_array($normalized, ['nuevo_modelo', 'plan_normal']) ? $normalized : null;
        }

        $resolvedGroupId = array_key_exists('group_id', $payload)
            ? (int) $payload['group_id']
            : (int) ($document->group_id ?? 0);

        $resolvedCycleId = $this->resolveCycleId(
            array_key_exists('cycle_id', $payload) ? (int) $payload['cycle_id'] : null,
            $resolvedGroupId,
            (int) ($document->cycle_id ?? 0),
        );

        if (!$resolvedCycleId) {
            return response()->json([
                'message' => 'No hay ciclo disponible para actualizar el documento. Define un ciclo activo o selecciona un grupo con ciclo.',
            ], 422);
        }

        $payload['cycle_id'] = $resolvedCycleId;

        $document->fill($payload)->save();

        return response()->json(['data' => $document->fresh()]);
    }

    /**
     * Ocultar un documento del historial del docente sin eliminarlo
     */
    public function hide(Request $request, Document $document): JsonResponse
    {
        if ($document->uploaded_by !== $request->user()?->id) {
            return response()->json(['message' => 'No tienes permiso para ocultar este documento.'], 403);
        }

        $document->update(['hidden_by_docente' => true]);

        return response()->json(['message' => 'Documento ocultado del historial correctamente.']);
    }

    /**
     * Eliminar un documento (con manejo de errores mejorado)
     */
    public function destroy(Request $request, Document $document): JsonResponse
    {
        try {
            // Verificar permisos
            if (!$this->canAccessDocument($request, $document)) {
                return response()->json([
                    'message' => 'No tienes permiso para eliminar este documento.'
                ], 403);
            }

            // Eliminar el archivo físico si existe
            if ($document->file_path) {
                $storedPath = $this->resolveDocumentStoragePath($document->file_path);
                if ($storedPath && Storage::disk('public')->exists($storedPath)) {
                    Storage::disk('public')->delete($storedPath);
                }
            }

            // Eliminar el registro de la base de datos
            $document->delete();

            return response()->json([
                'message' => 'Documento eliminado correctamente',
                'data' => ['id' => $document->id]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al eliminar el documento: ' . $e->getMessage()
            ], 500);
        }
    }

    public function history(Request $request, Document $document): JsonResponse
    {
        if (!$this->canAccessDocument($request, $document)) {
            return response()->json(['message' => 'No tienes permiso para ver el historial de este documento.'], 403);
        }

        $history = \App\Models\DocumentStatusHistory::where('document_id', $document->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($entry) {
                return [
                    'action' => $entry->action,
                    'notes' => $entry->notes,
                    'created_at' => $entry->created_at?->toIso8601String(),
                    'user' => $entry->user?->full_name ?? 'Sistema',
                ];
            });

        return response()->json(['data' => $history]);
    }

    public function file(Request $request, Document $document)
    {
        if (!$this->canAccessDocument($request, $document)) {
            abort(403);
        }

        $storedPath = $document->file_path ? $this->resolveDocumentStoragePath($document->file_path) : null;

        if (!$storedPath) {
            abort(404, 'Archivo no encontrado');
        }

        if ($request->boolean('download')) {
            return Storage::disk('public')->download($storedPath, $document->title . '.pdf');
        }

        return response()->file(Storage::disk('public')->path($storedPath), [
            'Content-Type'  => $document->mime_type ?? 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function resolveDocumentStoragePath(?string $filePath): ?string
    {
        if (!$filePath) {
            return null;
        }

        // Ruta normalizada (quita prefijos residuales de versiones anteriores)
        $normalized = ltrim(trim($filePath), '/');
        $stripped   = preg_replace('#^(storage|public|uploads)/+#', '', $normalized);

        foreach ([$normalized, $stripped, 'documents/' . $stripped] as $candidate) {
            if ($candidate && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function review(Request $request, Document $document): JsonResponse
    {
        if (!$this->isAdmin($request) && !$this->isSupervisor($request)) {
            return response()->json(['message' => 'Solo administradores o supervisores pueden revisar documentos.'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:revisado,devuelto'],
            'notes' => ['nullable', 'string'],
        ]);

        $document->status = $data['status'];
        $document->reviewed_at = now();
        $document->save();

        return response()->json(['data' => $this->formatDocument($document->fresh()->load(['form', 'group', 'uploader', 'cycle']))]);
    }

    public function returnDocument(Request $request, Document $document): JsonResponse
    {
        if (!$this->isAdmin($request) && !$this->isSupervisor($request)) {
            return response()->json(['message' => 'Solo administradores o supervisores pueden devolver documentos.'], 403);
        }

        $data = $request->validate([
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $document->status = 'devuelto';
        $document->returned_at = now();
        $document->returned_comment = $data['notes'] ?? null;
        $document->save();

        return response()->json(['data' => $this->formatDocument($document->fresh())]);
    }

    public function resubmit(Request $request, Document $document): JsonResponse
    {
        if ($document->uploaded_by !== $request->user()?->id) {
            return response()->json(['message' => 'No autorizado para reenviar este documento.'], 403);
        }

        if ($document->status !== 'devuelto') {
            return response()->json(['message' => 'Solo se pueden reenviar documentos con estado devuelto.'], 422);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:15360', function ($attribute, $value, $fail) {
                $ext        = strtolower($value->getClientOriginalExtension());
                $mime       = strtolower($value->getMimeType() ?? '');
                $clientMime = strtolower($value->getClientMimeType() ?? '');
                $validMimes = ['application/pdf', 'application/x-pdf', 'application/acrobat', 'application/vnd.pdf', 'text/pdf'];
                if ($ext !== 'pdf' && !in_array($mime, $validMimes) && !in_array($clientMime, $validMimes)) {
                    $fail('El archivo debe ser un PDF.');
                    return;
                }
                $handle = fopen($value->getPathname(), 'rb');
                $header = fread($handle, 5);
                fclose($handle);
                if ($header !== '%PDF-') {
                    $fail('El archivo no es un PDF válido. Asegúrate de subir el archivo correcto.');
                }
            }],
        ]);

        $file     = $request->file('file');
        $ext      = strtolower($file->getClientOriginalExtension()) ?: 'pdf';
        $safeName = 'doc_' . uniqid('', true) . '.' . $ext;
        $subDir   = 'documents/' . now()->format('Y/m');
        $path     = $file->storeAs($subDir, $safeName, 'public');

        if (!$path) {
            return response()->json(['message' => 'No se pudo guardar el archivo. Intenta de nuevo.'], 500);
        }

        $oldPath = $document->file_path
            ? $this->resolveDocumentStoragePath($document->file_path)
            : null;

        $newTitle = mb_substr(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 0, 180);

        $document->title            = $newTitle ?: $document->title;
        $document->file_path        = $path;
        $document->mime_type        = $file->getMimeType();
        $document->file_size_bytes  = $file->getSize();
        $document->status           = 'reenviado';
        $document->resubmitted_at   = now();
        $document->save();

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        return response()->json(['data' => $this->formatDocument($document->fresh())]);
    }

    private function getActiveCycleId(): ?int
    {
        $activeCycle = \App\Models\AcademicCycle::where('status', 'activo')->first();
        return $activeCycle?->id;
    }

    public function byCycleActive(Request $request): JsonResponse
    {
        $activeCycle = \App\Models\AcademicCycle::where('status', 'activo')->first();

        if (!$activeCycle) {
            return response()->json(['data' => [], 'message' => 'No active cycle'], 200);
        }

        $query = Document::where('cycle_id', $activeCycle->id)
            ->with(['form', 'group', 'uploader', 'cycle']);

        if (!$this->isAdmin($request)) {
            $query->where('uploaded_by', $request->user()->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $documents = $query->orderByDesc('submitted_at')
            ->paginate($request->integer('per_page', 20));
        $documents->setCollection($documents->getCollection()->map(fn (Document $document) => $this->formatDocument($document)));

        return response()->json([
            'data' => $documents,
            'cycle' => $activeCycle,
        ]);
    }

    public function byDocente(Request $request): JsonResponse
    {
        $docenteId = $request->integer('docente_id');
        $cycleId = $request->integer('cycle_id', $this->getActiveCycleId());

        $query = Document::where('uploaded_by', $docenteId)
            ->with(['form', 'group', 'uploader', 'cycle']);

        if ($cycleId) {
            $query->where('cycle_id', $cycleId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('type')) {
            $type = $request->string('type');
            if ($type === 'docentes') {
                $query->whereIn('apartado_label', ['planeacion', 'instrumento-30', 'instrumento-40', 'instrumento-60', 'instrumento-70', 'instrumento-30-40', 'instrumento-60-70', 'lista-concentrada', 'asesoria', 'portafolio', 'acta-final']);
            } elseif ($type === 'tutores') {
                $query->whereIn('apartado_label', ['carga-academica', 'reporte-bajas', 'concentrado-asesorias', 'acta-asistencia', 'ficha-tecnica']);
            }
        }

        $documents = $query->orderByDesc('submitted_at')
            ->paginate($request->integer('per_page', 20));
        $documents->setCollection($documents->getCollection()->map(fn (Document $document) => $this->formatDocument($document)));

        return response()->json(['data' => $documents]);
    }

    public function pendingForReview(Request $request): JsonResponse
    {
        $cycleId = $request->integer('cycle_id', $this->getActiveCycleId());

        $query = Document::where('status', 'pendiente')
            ->with(['form', 'group', 'uploader', 'cycle']);

        if ($cycleId) {
            $query->where('cycle_id', $cycleId);
        }

        $documents = $query->orderBy('submitted_at')
            ->paginate($request->integer('per_page', 50));
        $documents->setCollection($documents->getCollection()->map(fn (Document $document) => $this->formatDocument($document)));

        return response()->json(['data' => $documents]);
    }

    public function countByStatus(Request $request): JsonResponse
    {
        $cycleId = $request->integer('cycle_id', $this->getActiveCycleId());

        $counts = [];
        foreach (['pendiente', 'revisado', 'devuelto', 'reenviado'] as $status) {
            $query = Document::where('status', $status);
            if ($cycleId) {
                $query->where('cycle_id', $cycleId);
            }
            $counts[$status] = $query->count();
        }

        return response()->json(['data' => $counts, 'cycle_id' => $cycleId]);
    }
}
