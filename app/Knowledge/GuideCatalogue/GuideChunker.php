<?php

namespace App\Knowledge\GuideCatalogue;

use App\Enums\GuideStatus;
use App\Knowledge\Chunk;
use App\Knowledge\ChunkType;
use App\Models\Category;
use App\Models\Guide;
use App\Models\Stage;
use App\Models\Version;
use Illuminate\Support\Collection;

/**
 * What the knowledge index may say about a guide: its promise, never its contents (ADR 0022).
 * One chunk for the guide — title, level, category, goals, pitch and every stage — and one per
 * stage, each under its guide's title, since stage titles repeat across guides.
 *
 * It reads the guide's own fields and its stages, nothing below them: cards and items, with their
 * links, creators and briefs, are never loaded, so they cannot reach a chunk. Only the released
 * version is read — a newer draft would promise what no reader can open yet.
 */
class GuideChunker
{
    /**
     * Leading phrases a stage goal may open with ("Al terminar: …"). Shared by most goals, they
     * would only make every stage look alike to the index.
     */
    private const GOAL_PREFIXES = '/^\s*(al terminar|by the end)\s*[:,]\s*/iu';

    /**
     * Every chunk of every published guide with a released version.
     *
     * @return Collection<int, Chunk>
     */
    public function all(): Collection
    {
        return Guide::query()
            ->where('status', GuideStatus::PUBLISHED)
            ->whereHas('versions', fn ($versions) => $versions->whereNotNull('released_at'))
            ->orderBy('id')
            ->get()
            ->flatMap(fn (Guide $guide) => $this->for($guide))
            ->values();
    }

    /**
     * The guide's chunks, or none when it is not published or nothing is released.
     *
     * @return list<Chunk>
     */
    public function for(Guide $guide): array
    {
        $version = $guide->releasedVersion();

        if ($guide->status !== GuideStatus::PUBLISHED || $version === null) {
            return [];
        }

        $listed = Guide::query()->whereKey($guide->id)->listed()->exists();
        $stages = Stage::query()->where('version_id', $version->id)->orderBy('position')->get()->values();

        return [
            $this->chunk($guide, $version, $listed, ChunkType::GUIDE, $this->guideText($guide, $stages)),
            ...$stages->map(fn (Stage $stage, int $index) => $this->chunk(
                $guide, $version, $listed, ChunkType::STAGE, $this->stageText($guide, $stage, $index + 1, $stages->count()), $stage, $index + 1,
            ))->all(),
        ];
    }

    /**
     * A stage goal without its shared opening, capitalised again.
     */
    public static function goal(?string $goal): string
    {
        $goal = trim(preg_replace(self::GOAL_PREFIXES, '', (string) $goal) ?? '');

        return mb_strtoupper(mb_substr($goal, 0, 1)).mb_substr($goal, 1);
    }

    /** @param  Collection<int, Stage>  $stages */
    private function guideText(Guide $guide, Collection $stages): string
    {
        $locale = $guide->locale;
        $goals = Guide::goalLines($guide->goals);
        $category = Category::query()->whereKey($guide->category_id)->value('name');

        return collect([
            $this->label('guide', $locale).': '.$guide->title,
            $this->label('level', $locale).': '.__("arcadia.levels.{$guide->level->value}", [], $locale),
            $category ? $this->label('category', $locale).': '.$category : null,
            $goals ? $this->label('goals', $locale).":\n".collect($goals)->map(fn (string $goal) => "- {$goal}")->implode("\n") : null,
            filled($guide->pitch) ? $this->label('pitch', $locale).': '.trim($guide->pitch) : null,
            $stages->isNotEmpty() ? $this->label('stages', $locale).":\n".$stages->map(
                fn (Stage $stage, int $index) => ($index + 1).'. '.$this->stageLine($stage),
            )->implode("\n") : null,
        ])->filter()->implode("\n");
    }

    /** Numbered by order, not by `position`, which may skip. */
    private function stageText(Guide $guide, Stage $stage, int $number, int $stageCount): string
    {
        $locale = $guide->locale;

        return collect([
            $this->label('guide', $locale).': '.$guide->title,
            __('knowledge.stage_of', ['position' => $number, 'count' => $stageCount], $locale).': '.$stage->title,
            filled($stage->goal) ? $this->label('stage_goal', $locale).': '.self::goal($stage->goal) : null,
        ])->filter()->implode("\n");
    }

    private function stageLine(Stage $stage): string
    {
        return filled($stage->goal) ? "{$stage->title}: ".self::goal($stage->goal) : $stage->title;
    }

    private function label(string $key, string $locale): string
    {
        return __("knowledge.labels.{$key}", [], $locale);
    }

    private function chunk(Guide $guide, Version $version, bool $listed, ChunkType $type, string $content, ?Stage $stage = null, ?int $stageNumber = null): Chunk
    {
        return new Chunk(
            guideId: $guide->id,
            guideTitle: $guide->title,
            versionId: $version->id,
            locale: $guide->locale,
            chunkType: $type,
            content: $content,
            listed: $listed,
            stageKey: $stage?->key,
            stagePosition: $stageNumber,
        );
    }
}
