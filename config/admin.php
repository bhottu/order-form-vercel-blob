<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Admin Access (tanpa database)
    |--------------------------------------------------------------------------
    |
    | Kredensial admin diambil dari environment variable agar tidak ada
    | tabel users / database. Login memakai session flag biasa.
    | Di Vercel gunakan SESSION_DRIVER=cookie agar session tetap bekerja
    | pada serverless (stateless).
    |
    */

    'username' => env('ADMIN_USERNAME'),

    'password' => env('ADMIN_PASSWORD'),

];
