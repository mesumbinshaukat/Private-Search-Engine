<?php

return [
    'min_records_per_category' => env('INDEXER_MIN_RECORDS_PER_CATEGORY', 5),
    
    'max_data_age_days' => env('INDEXER_MAX_DATA_AGE_DAYS', 5),
    
    'min_token_length' => env('INDEXER_MIN_TOKEN_LENGTH', 2),

    'schema_version' => env('INDEXER_SCHEMA_VERSION', '1.0'),
    
    'json_pretty_print' => env('INDEXER_JSON_PRETTY_PRINT', true),
    
    'deterministic_sorting' => env('INDEXER_DETERMINISTIC_SORTING', true),
    
    'include_metadata' => env('INDEXER_INCLUDE_METADATA', true),
    
    'checksum_algorithm' => env('INDEXER_CHECKSUM_ALGORITHM', 'sha256'),
];
