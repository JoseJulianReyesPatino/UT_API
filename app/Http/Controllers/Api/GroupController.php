<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Group::query()
            ->join('careers', 'careers.id', '=', 'groups.career_id')
            ->select('groups.*', 'careers.code as career_code');

        if ($request->filled('career_code')) {
            $query->where('careers.code', '=', strtoupper($request->career_code));
        }

        if ($request->filled('career_id')) {
            $query->where('groups.career_id', '=', (int) $request->career_id);
        }

        if ($request->filled('cuatrimestre')) {
            $query->where('groups.cuatrimestre', '=', (int) $request->cuatrimestre);
        }

        if ($request->filled('cycle_id')) {
            $query->where('groups.cycle_id', '=', (int) $request->cycle_id);
        }

        $groups = $query->orderBy('groups.group_number')->get();

        $data = $groups->map(function (Group $group) {
            return [
                'id' => $group->id,
                'career_id' => $group->career_id,
                'cycle_id' => $group->cycle_id,
                'cuatrimestre' => $group->cuatrimestre,
                'group_number' => $group->group_number,
                'group_code' => str_replace('_', '-', $group->group_code),
                'career_code' => $group->career_code ?? null,
                'careerCode' => $group->career_code ?? null,
                'plan' => $this->planFromCareerId($group->career_id),
                'groupNumber' => $group->group_number,
                'name' => $group->group_code,
            ];
        });

        return response()->json(['data' => $data]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'careerCode' => ['required', 'string', 'max:32'],
            'plan' => ['required', Rule::in(['nuevo-modelo', 'plan-normal'])],
            'cuatrimestre' => ['required', 'integer', 'min:1', 'max:12'],
            'groupNumber' => ['required', 'integer', 'min:1', 'max:99'],
        ]);

        $careerId = $this->resolveCareerId(
            strtoupper($data['careerCode']),
            $data['plan'] === 'nuevo-modelo' ? 'nuevo_modelo' : 'plan_normal',
            (int) $data['cuatrimestre']
        );

        $cycleId = $this->getActiveCycleId();

        $groupCode = strtoupper($data['careerCode']) . $data['cuatrimestre'] . '-' . $data['groupNumber'];

        $group = Group::create([
            'career_id' => $careerId,
            'cycle_id' => $cycleId,
            'cuatrimestre' => (int) $data['cuatrimestre'],
            'group_number' => (int) $data['groupNumber'],
            'group_code' => $groupCode,
        ]);

        return response()->json([
            'data' => [
                'id' => $group->id,
                'career_id' => $group->career_id,
                'cycle_id' => $group->cycle_id,
                'cuatrimestre' => $group->cuatrimestre,
                'group_number' => $group->group_number,
                'group_code' => $group->group_code,
                'careerCode' => $data['careerCode'],
                'plan' => $data['plan'],
                'groupNumber' => $group->group_number,
                'name' => $group->group_code,
            ],
        ], 201);
    }

    public function show(Group $group): JsonResponse
    {
        $group->load('career');

        return response()->json([
            'data' => [
                'id' => $group->id,
                'career_id' => $group->career_id,
                'cycle_id' => $group->cycle_id,
                'cuatrimestre' => $group->cuatrimestre,
                'group_number' => $group->group_number,
                'group_code' => str_replace('_', '-', $group->group_code),
                'careerCode' => $group->career?->code ?? null,
                'plan' => $this->planFromCareerId($group->career_id),
                'groupNumber' => $group->group_number,
                'name' => $group->group_code,
            ],
        ]);
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        $data = $request->validate([
            'careerCode' => ['sometimes', 'string', 'max:32'],
            'plan' => ['sometimes', Rule::in(['nuevo-modelo', 'plan-normal'])],
            'cuatrimestre' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'groupNumber' => ['sometimes', 'integer', 'min:1', 'max:99'],
        ]);

        if (isset($data['careerCode']) || isset($data['plan']) || isset($data['cuatrimestre'])) {
            $careerCode = strtoupper($data['careerCode'] ?? $group->career->code ?? '');
            $plan = $data['plan'] ?? $this->planFromCareerId($group->career_id);
            $plan = $plan === 'nuevo-modelo' ? 'nuevo_modelo' : 'plan_normal';
            $cuatrimestre = $data['cuatrimestre'] ?? $group->cuatrimestre;

            $group->career_id = $this->resolveCareerId($careerCode, $plan, (int) $cuatrimestre);
        }

        if (isset($data['cuatrimestre'])) {
            $group->cuatrimestre = (int) $data['cuatrimestre'];
        }

        if (isset($data['groupNumber'])) {
            $group->group_number = (int) $data['groupNumber'];
        }

        $careerCode = $data['careerCode'] ?? $group->career?->code ?? '';
        $group->group_code = strtoupper($careerCode) . $group->cuatrimestre . '-' . $group->group_number;

        $group->save();

        return response()->json(['data' => $group->fresh()]);
    }

    public function destroy(Group $group): JsonResponse
    {
        $group->delete();
        return response()->json(null, 204);
    }

    private function resolveCareerId(string $careerCode, string $plan, int $cuatrimestre): int
    {
        $level = null;
        if ($plan === 'nuevo_modelo') {
            $level = ($cuatrimestre > 0 && $cuatrimestre <= 6) ? 'TSU' : 'Ingenieria';
        } elseif ($plan === 'plan_normal') {
            $level = 'Ingenieria';
        }

        $query = Career::where('code', $careerCode)->where('plan', $plan);
        if ($level) {
            $query->where('level', $level);
        }

        $career = $query->first();

        if ($career) {
            return $career->id;
        }

        $career = Career::create([
            'code' => $careerCode,
            'name' => $careerCode,
            'plan' => $plan,
            'level' => $level ?? 'Ingenieria',
            'is_active' => true,
        ]);

        return $career->id;
    }

    private function getActiveCycleId(): ?int
    {
        return \App\Models\AcademicCycle::where('status', 'activo')->first()?->id;
    }

    private function planFromCareerId(int $careerId): string
    {
        $career = Career::find($careerId);
        if (!$career) {
            return 'plan-normal';
        }
        return $career->plan === 'nuevo_modelo' ? 'nuevo-modelo' : 'plan-normal';
    }
}
