<?php

return [
    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'redirect_uri' => env('ZOHO_REDIRECT_URI'),
    'organization_id' => env('ZOHO_ORGANIZATION_ID'),

    'urls' => [
        'accounts' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.com'),
        'api' => env('ZOHO_API_URL', 'https://www.zohoapis.com/books/v3'),
    ],

    'scopes' => [
        'ZohoBooks.fullaccess.all',
    ],

    'token_cache_key' => 'zoho_access_token',
    'token_expiry_buffer' => 300, // 5 min buffer before expiry
];
