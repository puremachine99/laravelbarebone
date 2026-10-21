<?php

namespace App\Http\Controllers\Profile;

use App\Enums\UserAppearance;
use App\Enums\UserTheme;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Saves the user's theme and/or light-dark appearance (profile → Appearance, and the sidebar sun/moon toggle),
 * so the choice follows them to every device.
 */
class AppearanceController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['sometimes', 'required', Rule::enum(UserTheme::class)],
            'appearance' => ['sometimes', 'required', Rule::enum(UserAppearance::class)],
        ]);

        $user = $request->user();
        $user->update($validated);

        return response()->json([
            'theme' => $user->theme->value,
            'appearance' => $user->appearance->value,
        ]);
    }
}
