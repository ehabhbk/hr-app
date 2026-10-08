<?php

return [
    // تفعيل التكامل مع Firebase (يتطلب ملف حساب الخدمة أدناه)
    'enabled' => env('FIREBASE_ENABLED', false),

    'project_id' => env('FIREBASE_PROJECT_ID', ''),
    'client_email' => env('FIREBASE_CLIENT_EMAIL', ''),

    // المفتاح الخاص لحساب الخدمة (يُفضَّل وضعه في ملف لا في .env)
    'private_key' => env('FIREBASE_PRIVATE_KEY', ''),
    'credentials_file' => env('FIREBASE_CREDENTIALS_FILE', storage_path('app/firebase-service-account.json')),
];
