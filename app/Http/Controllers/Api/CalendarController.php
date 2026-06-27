<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCycle;
use App\Models\CalendarFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CalendarController extends Controller
{
    public function index(): JsonResponse
    {
        $current = CalendarFile::where('is_active', true)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->first();

        return response()->json(['data' => $this->formatMeta($current)]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:20480'],
        ]);

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $originalName) ?: 'calendario.pdf';
        if (strtolower(pathinfo($safeName, PATHINFO_EXTENSION)) !== 'pdf') {
            $safeName .= '.pdf';
        }

        $storedName = uniqid('calendar_', true) . '_' . $safeName;
        $subDir = 'uploads/calendar/' . now()->format('Y/m');
        $relativePath = $subDir . '/' . $storedName;

        $file->storeAs($subDir, $storedName, 'public');

        $activeCycleId = AcademicCycle::where('status', 'activo')
            ->orderByDesc('id')
            ->value('id');

        CalendarFile::where('is_active', true)->update(['is_active' => false]);

        $record = CalendarFile::create([
            'cycle_id'    => $activeCycleId,
            'file_name'   => $originalName,
            'file_path'   => $relativePath,
            'uploaded_by' => $request->user()->id,
            'uploaded_at' => now(),
            'is_active'   => true,
        ]);

        return response()->json(['data' => $this->formatMeta($record)], 201);
    }

    public function file(Request $request)
    {
        $current = CalendarFile::where('is_active', true)
            ->orderByDesc('uploaded_at')
            ->orderByDesc('id')
            ->first();

        if (!$current) {
            abort(404, 'No hay calendario activo');
        }

        $path = $current->file_path;

        // Normalise: strip leading "storage/" prefix if stored with it (legacy backup format)
        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $fullPath = Storage::disk('public')->path($path);

        if (!file_exists($fullPath)) {
            abort(404, 'Archivo de calendario no encontrado');
        }

        $fileName = $current->file_name ?: 'Calendario.pdf';

        if ($request->boolean('download')) {
            return response()->download($fullPath, $fileName);
        }

        return response()->file($fullPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($fileName) . '"',
            'Cache-Control'       => 'no-store, no-cache, must-revalidate',
        ]);
    }

    private function formatMeta(?CalendarFile $record): array
    {
        if (!$record) {
            return [
                'id'          => null,
                'file_name'   => 'Calendario25-26.pdf',
                'uploaded_at' => null,
                'is_active'   => false,
            ];
        }

        return [
            'id'          => $record->id,
            'file_name'   => $record->file_name,
            'uploaded_at' => $record->uploaded_at?->toIso8601String(),
            'is_active'   => $record->is_active,
        ];
    }
}
