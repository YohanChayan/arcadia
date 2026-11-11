<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Embeddings
    |--------------------------------------------------------------------------
    |
    | How passages become vectors. The provider is `config/ai.php`'s embeddings default unless
    | named here. The dimensions are fixed by the `knowledge_chunks` migration: changing them
    | means a new column and a full rebuild, never just this value.
    |
    */

    'embeddings' => [
        'provider' => env('KNOWLEDGE_EMBEDDINGS_PROVIDER'),
        'model' => env('KNOWLEDGE_EMBEDDINGS_MODEL', 'gemini-embedding-2'),
        'dimensions' => 768,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retriever
    |--------------------------------------------------------------------------
    |
    | `catalogue` searches the knowledge index; `fake` answers with canned passages, for the tests
    | and for a machine without a Gemini key or an index.
    |
    */

    'retriever' => env('KNOWLEDGE_RETRIEVER', 'catalogue'),

    /*
    |--------------------------------------------------------------------------
    | Reindexing
    |--------------------------------------------------------------------------
    |
    | Whether a released version or a change to a guide's promise queues its reindexing. Off in
    | the tests, which would otherwise reach Gemini and Postgres from every guide they touch.
    |
    */

    'index_on_change' => (bool) env('KNOWLEDGE_INDEX_ON_CHANGE', true),

];
