<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/** The Ghana town list behind Place of Birth, Home Town and Residence: view it, and refresh it from its sources. */
class TownController extends Controller
{
    public function index(): Response
    {
        $path = config('church.ghana_towns');
        $exists = is_string($path) && is_file($path);

        // The rows themselves are fetched by the page from locations.towns (the same cached file the forms use).
        return Inertia::render('admin/towns/index', [
            'updatedAt' => $exists ? date(DATE_ATOM, filemtime($path)) : null,
            'sources' => [
                ['name' => 'blingyplus/ghana-location-api', 'url' => 'https://github.com/blingyplus/ghana-location-api', 'license' => 'MIT', 'covers' => 'Towns with their district and region, plus regional and district capitals'],
                ['name' => 'oliverboamah/ghana-cities', 'url' => 'https://github.com/oliverboamah/ghana-cities', 'license' => 'MIT', 'covers' => 'Major towns by region'],
                ['name' => 'maaddae/ghana-cities-database', 'url' => 'https://github.com/maaddae/ghana-cities-database', 'license' => 'Apache 2.0', 'covers' => 'Greater Accra towns and neighbourhoods'],
            ],
        ]);
    }

    /** Downloads the sources again and rebuilds the list (php artisan locations:import). */
    public function refresh(): RedirectResponse
    {
        try {
            $failed = Artisan::call('locations:import') !== 0;
            $output = trim(Artisan::output());
        } catch (Throwable $e) {
            $failed = true;
            $output = $e->getMessage();
        }

        if ($failed) {
            logger()->warning('Refreshing the Ghana town list failed', ['output' => $output]);
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Could not refresh the town list. Check the internet connection and try again.']);

            return back();
        }

        Audit::record('settings.towns_refreshed', 'Refreshed the Ghana town list from its sources', null, ['result' => $output]);
        Inertia::flash('toast', ['type' => 'success', 'message' => preg_replace('/ to .*$/', '.', $output) ?: 'Town list refreshed.']);

        return back();
    }
}
