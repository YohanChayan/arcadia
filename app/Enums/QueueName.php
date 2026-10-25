<?php

namespace App\Enums;

/**
 * The queues jobs may run on, each worked by its own supervised worker.
 *
 * **Every queued class names its queue from here**: a job that named none would land on
 * whatever the connection's fallback is, and a worker started with `--queue=` never sees a queue
 * it was not told about — the job would wait unworked. The connections' fallback is `ARCADIA`
 * too (`config/queue.php`), so a forgotten name still lands somewhere worked.
 *
 * A new queue is a case here, a worker in production and in the compose file, and nothing else.
 */
enum QueueName: string
{
    /** Arcadia's mail: its account emails, guide requests. */
    case ARCADIA = 'arcadia';
}
