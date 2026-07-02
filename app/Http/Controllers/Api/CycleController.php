<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCycle;
use App\Models\Document;
use App\Models\DocumentStatusHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CycleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => AcademicCycle::query()->orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'period_name' => ['required', 'string', 'max:80'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'status' => ['required', 'in:activo,cerrado'],
        ]);

        return response()->json([
            'data' => DB::transaction(function () use ($data) {
                if ($data['status'] === 'activo') {
                    AcademicCycle::query()->where('status', 'activo')->update(['status' => 'cerrado']);
                }

                return AcademicCycle::query()->create($data);
            }),
        ], 201);
    }

    public function show(AcademicCycle $cycle): JsonResponse
    {
        return response()->json(['data' => $cycle]);
    }

    public function update(Request $request, AcademicCycle $cycle): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'year' => ['sometimes', 'integer', 'min:2000', 'max:2100'],
            'period_name' => ['sometimes', 'string', 'max:80'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date'],
            'status' => ['sometimes', 'in:activo,cerrado'],
        ]);

        if (($data['status'] ?? null) === 'activo') {
            AcademicCycle::query()->where('id', '!=', $cycle->id)->where('status', 'activo')->update(['status' => 'cerrado']);
        }

        $cycle->fill($data)->save();

        return response()->json(['data' => $cycle->fresh()]);
    }

    public function destroy(AcademicCycle $cycle): JsonResponse
    {
        DB::transaction(function () use ($cycle) {
            $documents = Document::where('cycle_id', $cycle->id)->get();

            // Delete physical PDF files from disk
            foreach ($documents as $document) {
                $resolved = $this->resolveDocumentPath($document->file_path);
                if ($resolved) {
                    Storage::disk('public')->delete($resolved);
                }
            }

            // Delete status history records (FK constraint)
            $documentIds = $documents->pluck('id');
            DocumentStatusHistory::whereIn('document_id', $documentIds)->delete();

            // Delete document records
            Document::where('cycle_id', $cycle->id)->delete();

            // Delete the cycle itself
            $cycle->delete();
        });

        return response()->json(['message' => 'Ciclo eliminado']);
    }

    private function resolveDocumentPath(?string $filePath): ?string
    {
        if (!$filePath) {
            return null;
        }

        $normalized = ltrim(trim($filePath), '/');
        $stripped   = preg_replace('#^(storage|public|uploads)/+#', '', $normalized);

        foreach ([$normalized, $stripped, 'documents/' . $stripped] as $candidate) {
            if ($candidate && Storage::disk('public')->exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
