<?php

namespace App\Console\Commands;

use App\Support\Neighbourhoods;
use Illuminate\Console\Command;
use JsonException;

class ImportNeighbourhoods extends Command
{
    /** The file is a list of regions with their cities (like ghana-cities-neighbourhoods.json) or an object of city => names. */
    protected $signature = 'neighbourhoods:import {file : Path to the JSON file}';

    protected $description = 'Add cities and neighbourhoods from a JSON file (adds what is missing; removes nothing)';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (! is_file($path)) {
            $this->error("No file at {$path}.");

            return self::FAILURE;
        }

        try {
            $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            $this->error("{$path} is not valid JSON: {$e->getMessage()}");

            return self::FAILURE;
        }

        $stats = Neighbourhoods::import((array) $data);
        $this->info("Added {$stats['cities_added']} cities and {$stats['neighbourhoods_added']} neighbourhoods.");

        return self::SUCCESS;
    }
}
