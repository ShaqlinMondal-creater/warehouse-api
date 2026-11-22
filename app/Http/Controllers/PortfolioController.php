<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProfile;
use App\Models\Upload;
use App\Models\WorkProject;
use App\Services\UploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function __construct(private UploadService $uploads) {}

    /** Full portfolio for Warehouse (auth). */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = PortfolioProfile::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['is_public' => true],
        );
        $profile->load('avatar');

        $projects = WorkProject::query()
            ->where('user_id', $user->id)
            ->with('gallery')
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

        return response()->json(['data' => $profile->fresh()->load('avatar')]);
    }

    /** Upload / replace profile photo (uploads table → collection=avatar). */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:4096'],
        ]);

        $profile = PortfolioProfile::query()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['is_public' => true],
        );

        $this->uploads->replace(
            $profile,
            $request->user()->id,
            Upload::COLLECTION_AVATAR,
            $request->file('avatar'),
            'portfolio/avatars/'.$request->user()->id,
        );

        return response()->json(['data' => $profile->fresh()->load('avatar')]);
    }

    /** Remove profile photo. */
    public function destroyAvatar(Request $request): JsonResponse
    {
        $profile = PortfolioProfile::query()->where('user_id', $request->user()->id)->first();

        if ($profile) {
            $this->uploads->clearCollection($profile, Upload::COLLECTION_AVATAR);
        }

        return response()->json(['data' => $profile?->fresh()->load('avatar')]);
    }
}
