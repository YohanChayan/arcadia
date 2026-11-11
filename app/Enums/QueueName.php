<?php

namespace App\Enums;

/**
 * The queues jobs may run on. One supervised worker takes them in the order its `--queue=` lists
 * them: mail first, the knowledge index only when no mail waits.
 *
 * **Every queued class names its queue from here**: a job that named none would land on
 * whatever the connection's fallback is, and a worker started with `--queue=` never sees a queue
 * it was not told about — the job would wait unworked. The connections' fallback is `ARCADIA`
 * too (`config/queue.php`), so a forgotten name still lands somewhere worked.
 *
 * A new queue is a case here and a name in the worker's `--queue=`, in production and in the
 * compose file, and nothing else.
 */
enum QueueName: string
{
    /** Arcadia's mail: its account emails, guide requests. */
    case ARCADIA = 'arcadia';

    /** The knowledge index: embedding a guide's passages again after a release. Worked after mail. */
    case KNOWLEDGE = 'knowledge';
}
