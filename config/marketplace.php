<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage
    |--------------------------------------------------------------------------
    |
    | Original artwork and production files live on a private disk. Use "artwork"
    | (local private storage) in development and an S3-compatible disk in production
    | by setting ARTWORK_DISK=s3_private.
    |
    */

    'artwork_disk' => env('ARTWORK_DISK', 'artwork'),

    'artwork' => [
        'max_upload_kb' => (int) env('ARTWORK_MAX_UPLOAD_KB', 40960),
        'min_pixels' => (int) env('ARTWORK_MIN_PIXELS', 1000),
        'max_pixels' => (int) env('ARTWORK_MAX_PIXELS', 8000),
        'preview_size' => 1600,
        'thumbnail_size' => 480,
        'processing_memory' => env('ARTWORK_PROCESSING_MEMORY', '1024M'),
    ],

    /*
    | Print quality thresholds in effective DPI (dots per inch on the printed product).
    | They are the defaults for new print areas; each print area can override minimum and
    | sharp values in the admin catalogue. Below "minimum" a placement fails and cannot be
    | sold, between "minimum" and "sharp" it is printable with a warning.
    */
    'print_quality' => [
        'minimum_dpi' => (int) env('PRINT_MINIMUM_DPI', 150),
        'sharp_dpi' => (int) env('PRINT_SHARP_DPI', 250),
        'preferred_dpi' => (int) env('PRINT_PREFERRED_DPI', 300),
    ],

    /*
    | Design Studio. Artboards are rendered at the preferred DPI unless that would exceed
    | max_export_side pixels; the safe margin is drawn inside every print area.
    */
    'design' => [
        'safe_margin_mm' => (int) env('DESIGN_SAFE_MARGIN_MM', 6),
        'max_asset_kb' => (int) env('DESIGN_MAX_ASSET_KB', 30720),
    ],

    'agreement_version' => '2026-10',

    'locales' => [
        'en' => 'English',
        'rw' => 'Kinyarwanda',
    ],

    'default_currency' => env('MARKETPLACE_CURRENCY', 'RWF'),

    'cart_max_quantity' => 20,

];
