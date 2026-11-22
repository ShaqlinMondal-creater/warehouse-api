<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProfile;
use App\Models\WorkProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    /** Full portfolio for Warehouse (auth). */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = PortfolioProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['is_public' => true],
        );

        $projects = WorkProject::query()
            ->where('user_id', $user->id)
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => [
                'profile' => $profile,
                'projects' => $projects,
            ],
        ]);
    }

    /** Update profile fields from Warehouse (auth). */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'headline' => ['sometimes', 'nullable', 'string', 'max:190'],
            'tagline' => ['sometimes', 'nullable', 'string', 'max:255'],
            'about' => ['sometimes', 'nullable', 'string'],
            'location' => ['sometimes', 'nullable', 'string', 'max:120'],
            'email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'current_role' => ['sometimes', 'nullable', 'string', 'max:120'],
            'current_company' => ['sometimes', 'nullable', 'string', 'max:120'],
            'portfolio_url' => ['sometimes', 'nullable', 'url', 'max:255'],
            'stats' => ['sometimes', 'nullable', 'array'],
            'services' => ['sometimes', 'nullable', 'array'],
            'experience' => ['sometimes', 'nullable', 'array'],
            'skills' => ['sometimes', 'nullable', 'array'],
            'education' => ['sometimes', 'nullable', 'array'],
            'social' => ['sometimes', 'nullable', 'array'],
            'meta' => ['sometimes', 'nullable', 'array'],
            'is_public' => ['sometimes', 'boolean'],
        ]);

        $profile = PortfolioProfile::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            $data,
        );

        return response()->json(['data' => $profile->fresh()]);
    }
}
