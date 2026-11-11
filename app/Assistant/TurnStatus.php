<?php

namespace App\Assistant;

/** Where one visitor turn stands. Only a `DONE` turn is shown to the model as history. */
enum TurnStatus: string
{
    case REPLYING = 'replying';
    case DONE = 'done';
    case FAILED = 'failed';
}
