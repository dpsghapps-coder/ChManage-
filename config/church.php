<?php

return [
    /*
    | Folder holding the member photo files carried over from the old system (its img/members folder).
    | The database stores only each file's name (media_files.filename, linked from members.photo_id).
    | Members whose file is missing show their initials instead. Kept in storage/app/private/member-photos; override with MEMBER_PHOTOS_PATH.
    */
    'member_photos' => env('MEMBER_PHOTOS_PATH', storage_path('app/private/member-photos')),

    // Photos taken at registration of Junior Youth / Children Service members (file names are stored in young_members.photo_path).
    'young_member_photos' => env('YOUNG_MEMBER_PHOTOS_PATH', storage_path('app/private/young-member-photos')),

    // Photos taken when registering visitors and newcomers (file names are stored in newcomers.photo_path).
    'newcomer_photos' => env('NEWCOMER_PHOTOS_PATH', storage_path('app/private/newcomer-photos')),

    // The PCG presbyteries and their districts (see App\Support\Presbyteries). Edit the file to correct or extend the list.
    'presbyteries' => env('PRESBYTERIES_PATH', resource_path('data/pcg-presbyteries.md')),

    // Ghana towns suggested for Place of Birth, Home Town and Residence. Rebuild with `php artisan locations:import`.
    'ghana_towns' => env('GHANA_TOWNS_PATH', resource_path('data/ghana-towns.json')),

    // Seeds the cities and neighbourhoods tables once (Accra); after that they are edited on the Neighbourhoods page.
    'neighbourhoods' => env('NEIGHBOURHOODS_PATH', resource_path('data/ghana-neighbourhoods.json')),
];
