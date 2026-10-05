<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NetopiaReturnController extends Controller
{
    public function handle(Request $request)
    {
        $query = http_build_query($request->query());
        $target = 'padeliz://payments/netopia/return' . ($query !== '' ? '?' . $query : '');

        return redirect()->away($target);
    }
}
