<?php
return [
    'db' => [
        'host' => 'localhost',
        'port' => 3306,
        'name' => 'your_database',
        'user' => 'db_user',
        'pass' => 'db_password',
        'charset' => 'utf8mb4',
    ],
    'azure_openai' => [
        'endpoint' => 'https://your-resource-name.openai.azure.com/',
        'api_key' => 'your-azure-openai-key',
        'api_version' => '2024-02-15-preview',
        'deployment_summarize' => 'summary-model',
        'deployment_expand' => 'expansion-model',
        'deployment_revision' => 'revision-model',
    ],
    'uploads_dir' => __DIR__ . '/uploads',
];
