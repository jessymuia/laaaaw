<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'textsms' => [
        'url' => env('TEXTSMS_URL', 'https://sms.textsms.co.ke/api/services/sendsms/'),
        'api_key' => env('TEXTSMS_API_KEY'),
        'partner_id' => env('TEXTSMS_PARTNER_ID'),
        'shortcode' => env('TEXTSMS_SHORTCODE'),
    ],

    // §3.3/§6: default manifest path for migration:document-checksums,
    // overridable per-invocation with --manifest=.
    'migration' => [
        'checksum_manifest_path' => env('MIGRATION_CHECKSUM_MANIFEST_PATH'),
    ],

];
