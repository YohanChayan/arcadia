<?php

namespace App\Actions;

use App\Enums\ContentNode;
use App\Models\Version;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Add a node to the end of its parent in a version's content tree.
 *
 * The parent must belong to the same version: a card can only be added to a stage of this
 * version, an item to a card of it. A released version refuses on its own
 * (`FrozenOnceReleased`); this checks the tree is the version's before it gets that far.
 */
class AppendContent
{
    /**
     * @param  array<string, mixed>  $attributes  the node's own fields
     */
    public function handle(Version $version, ContentNode $node, ?int $parentId, array $attributes): Model
    {
        $parentValue = $version->id;

        if ($parentKind = $node->parent()) {
            $parent = $parentKind->model()::findOrFail($parentId);

            // A parent from another guide or version is, for this version, not there at all.
            if ($parent->owningVersion()?->id !== $version->id) {
                throw (new ModelNotFoundException)->setModel($parentKind->model(), [$parentId]);
            }

            $parentValue = $parent->id;
        }

        $model = $node->model();
        $position = (int) $model::where($node->parentColumn(), $parentValue)->max('position') + 1;

        $row = new $model($attributes);
        $row->{$node->parentColumn()} = $parentValue;
        $row->position = $position;
        $row->save();

        return $row;
    }
}
