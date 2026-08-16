<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicCycle;
use App\Models\Document;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $activeCycle = AcademicCycle::query()->where('status', 'activo')->first();
        $activeCycleId = $activeCycle?->id;

        $base = Document::query()
            ->when($activeCycleId, fn ($q) => $q->where('cycle_id', $activeCycleId))
            ->when(!$activeCycleId, fn ($q) => $q->whereRaw('0 = 1'));

        return response()->json([
            'users_total'        => User::query()->count(),
            'documents_total'    => (clone $base)->count(),
            'documents_pending'  => (clone $base)->where('status', 'pendiente')->count(),
            'documents_reviewed' => (clone $base)->where('status', 'revisado')->count(),
            'messages_total'     => Message::query()->count(),
            'active_cycle'       => $activeCycle,
        ]);
    }
}
