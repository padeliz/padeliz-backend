<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\BaseVersions\V1\V1BaseController;
use App\Models\PlayerDeviceToken;
use Illuminate\Http\Request;

class DeviceTokenController extends V1BaseController
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'nullable|string|max:32',
        ]);

        $token = PlayerDeviceToken::query()->updateOrCreate(
            ['token' => $validated['token']],
            [
                'player_id' => $request->user()->id_player,
                'platform' => $validated['platform'] ?? null,
            ]
        );

        return $this->successResponse([
            'id_player_device_token' => $token->id_player_device_token,
            'token' => $token->token,
            'platform' => $token->platform,
        ], 'Device token registered', 201);
    }

    public function destroy(Request $request)
    {
        $validated = $request->validate([
            'token' => 'required|string|max:512',
        ]);

        PlayerDeviceToken::query()
            ->where('player_id', $request->user()->id_player)
            ->where('token', $validated['token'])
            ->delete();

        return $this->successResponse(null, 'Device token removed');
    }
}
