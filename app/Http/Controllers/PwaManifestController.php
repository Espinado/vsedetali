<?php

namespace App\Http\Controllers;

use App\Pwa\PwaProfileResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PwaManifestController extends Controller
{
    public function __invoke(Request $request, PwaProfileResolver $resolver): Response
    {
        $json = json_encode(
            $resolver->manifestDocument($request),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );

        return response($json, 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
