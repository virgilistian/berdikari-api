<?php

use Dedoc\Scramble\SecurityDocumentation\MiddlewareAuthSecurityStrategy;
use Dedoc\Scramble\Support\Generator\SecurityScheme;

return [
    /*
    |--------------------------------------------------------------------------
    | API Path
    |--------------------------------------------------------------------------
    | Which routes to document. Matches paths starting with 'api'.
    */
    'api_path' => 'api',

    'api_domain' => null,

    'export_path' => 'api.json',

    'cache' => [
        'key' => 'scramble.openapi',
        'store' => 'file',
    ],

    'info' => [
        'version' => env('API_VERSION', '1.0.0'),
        'description' => <<<'MD'
# Berdikari API

REST API untuk sistem ERP mobile-first **Berdikari** — dirancang untuk UMKM Indonesia.

## Autentikasi

Seluruh endpoint yang dilindungi menggunakan **Bearer Token** via Laravel Sanctum.

1. Login melalui `POST /api/v1/auth/login` untuk mendapatkan token.
2. Sertakan token pada header setiap request:
   ```
   Authorization: Bearer <token>
   ```

## Format Respons Standar

```json
{
  "success": true,
  "data": { ... },
  "message": "Pesan sukses"
}
```

## Modul API

| Modul | Prefix | Deskripsi |
|---|---|---|
| IAM | `/api/v1/auth`, `/api/v1/users` | Autentikasi & manajemen pengguna |
| Catalog | `/api/v1/catalog` | Produk & kategori |
| Inventory | `/api/v1/inventory` | Stok & opname harian |
| Sales | `/api/v1/sales` | Checkout POS & scan piring |
MD,
    ],

    'ui' => [
        'title' => 'Berdikari API Docs',
    ],

    'servers' => null,

    'enum_cases_description_strategy' => 'description',
    'enum_cases_names_strategy'       => false,
    'flatten_deep_query_parameters'   => true,

    'middleware' => [
        'web',
        \Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess::class,
    ],

    'extensions' => [],

    /*
    |--------------------------------------------------------------------------
    | Security Strategy
    |--------------------------------------------------------------------------
    | Auto-detect `auth:sanctum` middleware and document as Bearer token.
    */
    'security_strategy' => [
        MiddlewareAuthSecurityStrategy::class,
        [
            'middleware' => ['auth', 'auth:sanctum', 'auth:*'],
            'scheme'     => SecurityScheme::http('bearer'),
        ],
    ],
];
