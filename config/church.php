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
];
