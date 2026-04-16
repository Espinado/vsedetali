<?php

namespace App\Http\Controllers;

use App\Services\AutoPartsCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogOemLookupController extends Controller
{
    public function __invoke(Request $request, AutoPartsCatalogService $catalog): JsonResponse
    {
        $validated = $request->validate([
            'oem' => [
                'required',
                'string',
                'max:100',
                'not_regex:/<\s*script/i',
                'not_regex:/javascript\s*:/i',
                'not_regex:/on\w+\s*=/i',
            ],
        ]);

        $oem = trim((string) $validated['oem']);
        if ($oem === '') {
            return response()->json([
                'ok' => false,
                'message' => 'OEM номер не может быть пустым.',
            ], 422);
        }

        try {
            $payload = $catalog->lookupFullOemBundleForPersistence($oem);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'data' => $payload,
        ]);
    }
}
