<?php

return [
    'host'         => env('ELASTICSEARCH_HOST', 'elasticsearch'),
    'port'         => env('ELASTICSEARCH_PORT', 9200),
    'index_prefix' => env('ELASTICSEARCH_INDEX_PREFIX', 'medarea'),
    'password'     => env('ELASTIC_PASSWORD'),
];
