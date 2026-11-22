<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProfile;
use App\Models\User;
use App\Models\WorkProject;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPortfolioController extends Controller
{
    /**
     * Public portfolio for third-party sites (e.g. Vercel portfolio).
     * Optional ?user_id= — defaults to first public owner profile.
     */
    public function show(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');

        $profileQuery = PortfolioProfile::query()->where('is_public', true);

        if ($userId) {
            $profileQuery->where('user_id', (int) $userId);
        }

        $profile = $profileQuery->with('avatar')->orderBy('id')->first();

        if (! $profile) {
            return response()->json([
                'ok' => false,
                'error' => 'No public portfolio found.',
            ], 404);
        }

        $projects = WorkProject::query()
            ->where('user_id', $profile->user_id)
            ->where('is_public', true)
            ->with('gallery')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        /** @var User|null $user */
        $user = User::query()->find($profile->user_id);

        return response()->json([
            'ok' => true,
            'data' => [
                'name' => $user?->name,
                'profile' => [
                    'headline' => $profile->headline,
                    'tagline' => $profile->tagline,
                    'about' => $profile->about,
                    'location' => $profile->location,
                    'email' => $profile->email,
                    'phone' => $profile->phone,
                    'current_role' => $profile->current_role,
                    'current_company' => $profile->current_company,
                    'portfolio_url' => $profile->portfolio_url,
                    'avatar_url' => $profile->avatar_url,
                    'stats' => $profile->stats,
                    'services' => $profile->services,
                    'experience' => $profile->experience,
                    'skills' => $profile->skills,
                    'education' => $profile->education,
                    'social' => $profile->social,
                ],
                'projects' => $projects->map(fn (WorkProject $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'summary' => $p->summary,
                    'description' => $p->description,
                    'role' => $p->role,
                    'stack' => $p->stack,
                    'platform_or_client' => $p->platform_or_client,
                    'status' => $p->status,
                    'url' => $p->url,
                    'repo_url' => $p->repo_url,
                    'cover_url' => $p->cover_url,
                    'images' => $p->images,
                    'sort_order' => $p->sort_order,
                    'meta' => $p->meta,
                ]),
            ],
        ]);
    }
}
