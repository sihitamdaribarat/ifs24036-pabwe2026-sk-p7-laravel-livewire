<?php

// Gunakan `?:` (bukan default env()) agar baris kosong seperti `OPENAI_MODEL=` di .env tetap jatuh ke default.
return [
    'endpoint' => rtrim(env('OPENAI_ENDPOINT') ?: 'https://api.openai.com/v1', '/'),
    'key' => env('OPENAI_KEY') ?: '',
    'model' => env('OPENAI_MODEL') ?: 'gpt-4o-mini',
];
