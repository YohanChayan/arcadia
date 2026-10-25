<?php

namespace App\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Move an ordered row one place up or down among its siblings — the rows sharing its parent
 * column — by swapping positions with the neighbour. At either end it stays put. Serves a
 * guide's content nodes and a prompt's sections alike. Never touches an item's key: a reader's
 * progress follows the line, not its place.
 */
class MoveContent
{
    public function handle(Model $row, string $parentColumn, bool $up): void
    {
        $neighbour = $row->newQuery()
            ->where($parentColumn, $row->{$parentColumn})
            ->where('position', $up ? '<' : '>', $row->position)
            ->orderBy('position', $up ? 'desc' : 'asc')
            ->first();

        if ($neighbour === null) {
            return;
        }

        DB::transaction(function () use ($row, $neighbour) {
            [$row->position, $neighbour->position] = [$neighbour->position, $row->position];
            $row->save();
            $neighbour->save();
        });
    }
}
