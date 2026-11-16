<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $group = $request->query('group'); // ai|app|integrations

        $query = Setting::query()->where('user_id', $request->user()->id);
        if (is_string($group) && $group !== '') {
            $query->where('group', $group);
        }

        $items = $query->orderBy('group')->orderBy('key')->get()->map->toPublicArray()->values();

        return response()->json([
            'data' => $items,
            'defaults' => [
                'ai' => config('warehouse.ai'),
                'app' => config('warehouse.app'),
            ],
        ]);
    }

    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'group' => ['required', 'in:ai,app,integrations'],
            'key' => ['required', 'string', 'max:80'],
            'value' => ['nullable', 'string'],
            'is_secret' => ['nullable', 'boolean'],
        ]);

        // Never store API keys in settings if they use OPENAI_API_KEY in .env
        if ($data['group'] === 'ai' && $data['key'] === 'api_key') {
            return response()->json([
                'message' => 'Put OPENAI_API_KEY in server .env, not in settings.',
            ], 422);
        }

        $setting = Setting::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'group' => $data['group'],
                'key' => $data['key'],
            ],
            [
                'value' => $data['value'] ?? null,
                'is_secret' => (bool) ($data['is_secret'] ?? false),
            ],
        );

        return response()->json(['data' => $setting->toPublicArray()]);
    }
}
