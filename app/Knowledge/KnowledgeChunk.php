<?php

namespace App\Knowledge;

use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Model;

/**
 * A passage in the knowledge index and its vector, on the `vectors` connection. Derived from the
 * guides in MySQL and rebuilt at will, so it holds no foreign keys to them.
 *
 * @property int $guide_id
 * @property string $guide_title
 * @property string $locale
 * @property ChunkType $chunk_type
 * @property ?int $stage_position
 * @property bool $listed
 * @property string $content
 * @property string $content_hash
 * @property string $embedding_model
 */
class KnowledgeChunk extends Model
{
    protected $connection = 'vectors';

    protected $fillable = [
        'identity', 'source', 'guide_id', 'guide_title', 'version_id', 'locale', 'chunk_type',
        'stage_key', 'stage_position', 'listed', 'content', 'content_hash', 'embedding_model', 'embedding',
    ];

    protected $casts = [
        'chunk_type' => ChunkType::class,
        'listed' => 'boolean',
        'embedding' => AsVector::class,
    ];
}
