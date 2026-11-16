<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = Plan::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->orderByDesc('id')
            ->get();

        return response()->json(['data' => $plans]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,active,done'],
            'target_date' => ['nullable', 'date'],
        ]);

        $data['user_id'] = $request->user()->id;
        $plan = Plan::query()->create($data);

        return response()->json(['data' => $plan->load('items')], 201);
    }

    public function show(Request $request, Plan $plan): JsonResponse
    {
        $this->authorizePlan($request, $plan);

        return response()->json(['data' => $plan->load('items')]);
    }

    public function update(Request $request, Plan $plan): JsonResponse
    {
        $this->authorizePlan($request, $plan);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:190'],
            'description' => ['nullable', 'string'],
            'status' => ['sometimes', 'in:draft,active,done'],
            'target_date' => ['nullable', 'date'],
        ]);

        $plan->update($data);

        return response()->json(['data' => $plan->fresh()->load('items')]);
    }

    public function destroy(Request $request, Plan $plan): JsonResponse
    {
        $this->authorizePlan($request, $plan);
        $plan->delete();

        return response()->json(['ok' => true]);
    }

    public function storeItem(Request $request, Plan $plan): JsonResponse
    {
        $this->authorizePlan($request, $plan);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:190'],
            'detail' => ['nullable', 'string'],
            'progress' => ['nullable', 'integer', 'min:0', 'max:100'],
            'status' => ['nullable', 'in:todo,doing,done,blocked'],
            'priority' => ['nullable', 'in:low,med,high'],
            'due_on' => ['nullable', 'date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['plan_id'] = $plan->id;
        $item = PlanItem::query()->create($data);

        return response()->json(['data' => $item], 201);
    }

    public function updateItem(Request $request, PlanItem $planItem): JsonResponse
    {
        $planItem->loadMissing('plan');
        abort_unless($planItem->plan?->user_id === $request->user()->id, 404);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:190'],
            'detail' => ['nullable', 'string'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'status' => ['sometimes', 'in:todo,doing,done,blocked'],
            'priority' => ['sometimes', 'in:low,med,high'],
            'due_on' => ['nullable', 'date'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $planItem->update($data);

        return response()->json(['data' => $planItem->fresh()]);
    }

    public function destroyItem(Request $request, PlanItem $planItem): JsonResponse
    {
        $planItem->loadMissing('plan');
        abort_unless($planItem->plan?->user_id === $request->user()->id, 404);
        $planItem->delete();

        return response()->json(['ok' => true]);
    }

    private function authorizePlan(Request $request, Plan $plan): void
    {
        abort_unless($plan->user_id === $request->user()->id, 404);
    }
}
