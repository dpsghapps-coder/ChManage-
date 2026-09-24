<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Town suggestions for Place of Birth, Home Town and Residence (the list built by `php artisan locations:import`). */
class LocationController extends Controller
{
    public function towns(Request $request): Response
    {
        $path = config('church.ghana_towns');
        abort_unless(is_string($path) && is_file($path), 404);

        // ~280 KB that rarely changes: the browser keeps it for a day and revalidates with the ETag after that.
        $response = response()->file($path, ['Content-Type' => 'application/json; charset=utf-8'])
            ->setEtag(md5_file($path))
            ->setPrivate()
            ->setMaxAge(86400);
        $response->isNotModified($request);

        return $response;
    }
}
