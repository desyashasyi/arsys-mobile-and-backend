<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FcmTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate(['token' => 'required|string']);

        $request->user()->update(['fcm_token' => $request->token]);

        return response()->json(['success' => true]);
    }
}
