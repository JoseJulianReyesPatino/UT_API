<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupervisorSectionPermission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupervisorPermissionController extends Controller
{
    private const VALID_SECTIONS = [
        'planeacion',
        'instrumento-30',
        'instrumento-40',
        'instrumento-60',
        'instrumento-70',
        'remedial',
        'lista-concentrada',
        'asesoria',
        'portafolio',
        'acta-final',
        'estadias',
        'tutorias',
    ];

    private function isAdmin(Request $request): bool
    {
        $user = $request->user();
        if (!$user) return false;

        return $user->roles()->where(function ($q) {
            $q->whereIn('code', ['administrador', 'admin'])
              ->orWhereIn('name', ['Administrador', 'Admin'])
              ->orWhere('roles.id', 1);
        })->exists();
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $supervisors = User::query()
            ->whereHas('roles', fn ($q) => $q->where('code', 'supervisor'))
            ->with(['supervisorSectionPermission'])
            ->get()
            ->map(fn (User $user) => [
                'user_id'   => $user->id,
                'user_name' => $user->full_name,
                'email'     => $user->email,
                'sections'  => $user->supervisorSectionPermission?->sections ?? [],
            ]);

        return response()->json(['data' => $supervisors]);
    }

    public function update(Request $request, int $userId): JsonResponse
    {
        if (!$this->isAdmin($request)) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'sections'   => ['required', 'array'],
            'sections.*' => ['string', 'in:' . implode(',', self::VALID_SECTIONS)],
        ]);

        $user = User::query()
            ->whereKey($userId)
            ->whereHas('roles', fn ($q) => $q->where('code', 'supervisor'))
            ->firstOrFail();

        SupervisorSectionPermission::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['sections' => array_values(array_unique($data['sections']))]
        );

        return response()->json(['message' => 'Permisos actualizados correctamente']);
    }
}
