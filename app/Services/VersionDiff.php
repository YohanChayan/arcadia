<?php

namespace App\Services;

use App\Models\Version;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * What changed between two versions of a guide, node by node.
 *
 * Nodes are matched by their lineage key (`HasLineageKey`), never by position or text: a video
 * moved from stage 2 to stage 3 is one video, moved — not one removed and another added — and a
 * renamed stage is that stage, renamed. Each node in the result is:
 *
 * - `added` (only in `to`), `removed` (only in `from`), or `same` key in both;
 * - with the fields that differ, before and after (`changed`);
 * - `moved` when its parent changed (a card to another stage, an item to another card);
 * - `reordered` when it changed place among siblings it kept — the longest common run of
 *   siblings stays put, so one item moving up does not mark every item after it.
 *
 * The result is a tree in `to`'s order, with what was removed placed under its old parent.
 * `touched` marks a node that changed or holds one that did, so the screen can fold the rest.
 */
class VersionDiff
{
    /** The fields compared, per kind of node. Position is not one: order is `reordered`. */
    private const FIELDS = [
        'sections' => ['kind', 'title', 'body', 'activity'],
        'stages' => ['title', 'goal', 'activity'],
        'cards' => ['kind', 'byline', 'description', 'quote', 'closing_note'],
        'items' => ['kind', 'title', 'url', 'hint', 'body', 'is_preview', 'is_available', 'clip_start', 'clip_end', 'activity'],
    ];

    /**
     * @return array{sections: list<array<string, mixed>>, stages: list<array<string, mixed>>, summary: array<string, array<string, int>>}
     */
    public function between(Version $from, Version $to): array
    {
        $before = $this->flatten($from);
        $after = $this->flatten($to);

        $nodes = [];
        foreach (array_keys(self::FIELDS) as $level) {
            $nodes[$level] = $this->compare($level, $before[$level], $after[$level]);
        }

        $stages = array_map(function (array $stage) use ($nodes) {
            $stage['children'] = array_map(function (array $card) use ($nodes) {
                $card['children'] = $this->children($nodes['items'], $card['key']);

                return $card;
            }, $this->children($nodes['cards'], $stage['key']));

            return $stage;
        }, $this->children($nodes['stages'], null));

        return [
            'sections' => array_map(fn (array $section) => $this->touch($section), $this->children($nodes['sections'], null)),
            'stages' => array_map(fn (array $stage) => $this->touch($stage), $stages),
            'summary' => $this->summary($nodes),
        ];
    }

    /**
     * Every node of a version, per level, keyed by its lineage key, with its parent's key and
     * its place among its siblings.
     *
     * @return array<string, array<string, array{attributes: array<string, mixed>, parent: ?string, position: int}>>
     */
    private function flatten(Version $version): array
    {
        $version->load(['sections', 'stages.cards.items']);

        $flat = ['sections' => [], 'stages' => [], 'cards' => [], 'items' => []];

        $flat['sections'] = $this->keyed('sections', $version->sections, null);
        $flat['stages'] = $this->keyed('stages', $version->stages, null);

        foreach ($version->stages as $stage) {
            $flat['cards'] += $this->keyed('cards', $stage->cards, $stage->key);

            foreach ($stage->cards as $card) {
                $flat['items'] += $this->keyed('items', $card->items, $card->key);
            }
        }

        return $flat;
    }

    /**
     * @param  Collection<int, Model>  $rows
     * @return array<string, array{attributes: array<string, mixed>, parent: ?string, position: int}>
     */
    private function keyed(string $level, Collection $rows, ?string $parent): array
    {
        $keyed = [];

        foreach ($rows->sortBy('position')->values() as $index => $row) {
            $keyed[$row->key] = [
                // Serialised, so an enum compares by its value and a boolean as a boolean.
                'attributes' => Arr::only($row->attributesToArray(), self::FIELDS[$level]),
                'parent' => $parent,
                'position' => $index,
            ];
        }

        return $keyed;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function compare(string $level, array $before, array $after): array
    {
        $reordered = $this->reordered($before, $after);
        $nodes = [];

        foreach ($after as $key => $now) {
            $was = $before[$key] ?? null;
            $changed = [];

            foreach ($was ? self::FIELDS[$level] : [] as $field) {
                if ($was['attributes'][$field] !== $now['attributes'][$field]) {
                    $changed[$field] = ['from' => $was['attributes'][$field], 'to' => $now['attributes'][$field]];
                }
            }

            $nodes[$key] = [
                'key' => $key,
                'status' => $was === null ? 'added' : ($changed ? 'changed' : 'unchanged'),
                'node' => $now['attributes'],
                'changed' => $changed,
                'moved' => $was !== null && $was['parent'] !== $now['parent'],
                'reordered' => in_array($key, $reordered, true),
                'parent' => $now['parent'],
                'position' => $now['position'],
            ];
        }

        foreach (array_diff_key($before, $after) as $key => $was) {
            $nodes[$key] = [
                'key' => $key,
                'status' => 'removed',
                'node' => $was['attributes'],
                'changed' => [],
                'moved' => false,
                'reordered' => false,
                'parent' => $was['parent'],
                // After everything still there, in their old order.
                'position' => 10_000 + $was['position'],
            ];
        }

        return $nodes;
    }

    /**
     * The keys that changed place among the siblings they kept: under the same parent in both
     * versions, but outside the longest run whose order survived.
     *
     * @return list<string>
     */
    private function reordered(array $before, array $after): array
    {
        $reordered = [];
        $parents = array_unique(array_column($after, 'parent'));

        foreach ($parents as $parent) {
            $kept = fn (array $side, array $other) => array_keys(array_filter(
                $side,
                fn (array $node, string $key) => $node['parent'] === $parent && ($other[$key]['parent'] ?? false) === $parent,
                ARRAY_FILTER_USE_BOTH,
            ));
            $sorted = function (array $keys, array $side) {
                usort($keys, fn ($a, $b) => $side[$a]['position'] <=> $side[$b]['position']);

                return $keys;
            };

            $was = $sorted($kept($before, $after), $before);
            $now = $sorted($kept($after, $before), $after);

            $reordered = [...$reordered, ...array_diff($now, $this->longestCommonRun($was, $now))];
        }

        return array_values($reordered);
    }

    /**
     * The longest subsequence the two orders share — the siblings that did not move relative to
     * each other. Sibling lists are short, so the plain table is fine.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private function longestCommonRun(array $a, array $b): array
    {
        $lengths = array_fill(0, count($a) + 1, array_fill(0, count($b) + 1, 0));

        for ($i = count($a) - 1; $i >= 0; $i--) {
            for ($j = count($b) - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $a[$i] === $b[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $run = [];
        for ($i = 0, $j = 0; $i < count($a) && $j < count($b);) {
            if ($a[$i] === $b[$j]) {
                $run[] = $a[$i];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $i++;
            } else {
                $j++;
            }
        }

        return $run;
    }

    /**
     * The nodes under one parent, in display order.
     *
     * @return list<array<string, mixed>>
     */
    private function children(array $nodes, ?string $parent): array
    {
        $children = array_values(array_filter($nodes, fn (array $node) => $node['parent'] === $parent));
        usort($children, fn ($a, $b) => $a['position'] <=> $b['position']);

        return array_map(fn (array $node) => Arr::except($node, ['parent', 'position']), $children);
    }

    /** Whether the node, or anything under it, changed. */
    private function touch(array $node): array
    {
        $node['children'] = array_map(fn (array $child) => $this->touch($child), $node['children'] ?? []);
        $node['touched'] = $node['status'] !== 'unchanged' || $node['moved'] || $node['reordered']
            || in_array(true, array_column($node['children'], 'touched'), true);

        return $node;
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function summary(array $nodes): array
    {
        $summary = [];

        foreach ($nodes as $level => $levelNodes) {
            $summary[$level] = [
                'added' => count(array_filter($levelNodes, fn ($node) => $node['status'] === 'added')),
                'removed' => count(array_filter($levelNodes, fn ($node) => $node['status'] === 'removed')),
                'changed' => count(array_filter($levelNodes, fn ($node) => $node['status'] === 'changed')),
                'moved' => count(array_filter($levelNodes, fn ($node) => $node['moved'] || $node['reordered'])),
            ];
        }

        return $summary;
    }
}
