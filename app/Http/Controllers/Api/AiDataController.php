<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiData\DataQueryService;
use App\Services\AiData\DatasetCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The data API the AI service calls as tools (path B). The user always comes from the signed assertion
 * (AuthenticateAiUserAssertion); the request body cannot choose it. Request shape: see DataQueryService.
 */
class AiDataController extends Controller
{
    /**
     * The datasets ("tools") the asserted user may use, with their skill, department, dimensions, metrics and filters.
     */
    public function datasets(Request $request, DatasetCatalog $catalog): JsonResponse
    {
        return response()->json(['data' => $catalog->describeFor($request->user())]);
    }

    public function query(Request $request, DataQueryService $queries): JsonResponse
    {
        try {
            return response()->json($queries->run($request->user(), $request->json()->all()));
        } catch (AuthorizationException $exception) {
            return response()->json(['message' => $exception->getMessage()], 403);
        }
    }
}
