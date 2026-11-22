<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AiModel;
use App\Models\Bid;
use App\Models\BidWorkspace;
use Illuminate\Http\Request;

trait AuthorizesOwnedResources
{
    protected function authorizeWorkspace(Request $request, BidWorkspace $workspace): void
    {
        abort_unless($workspace->user_id === $request->user()->id, 404);
    }

    protected function authorizeBid(Request $request, Bid $bid): void
    {
        $bid->loadMissing('workspace');
        abort_unless($bid->workspace?->user_id === $request->user()->id, 404);
    }

    protected function authorizeAiModel(Request $request, AiModel $model): void
    {
        abort_unless($model->user_id === $request->user()->id, 404);
    }
}
