<?php

return [
    'min_records_per_category' => env('INDEXER_MIN_RECORDS_PER_CATEGORY', 5),
    
    'max_data_age_days' => env('INDEXER_MAX_DATA_AGE_DAYS', 5),
    
    'schema_version' => '1.0',
    
    'json_pretty_print' => env('INDEXER_JSON_PRETTY_PRINT', true),
    
    'deterministic_sorting' => true,
    
    'include_metadata' => true,
    
    'checksum_algorithm' => 'sha256',
];
