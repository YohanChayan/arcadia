<?php

namespace App\Services;

/**
 * The part of a YouTube video a line plays, as the research AI states it: `segment {start, end,
 * evidence}`. A clip is kept only when it carries evidence — a chapter from the video's
 * description, or a line of its transcript — because an AI cannot watch a video, and without
 * evidence it writes believable timestamps it invented. No evidence, no clip: the line plays the
 * whole video.
 */
class VideoClip
{
    public const EVIDENCE_MAX = 1000;

    /**
     * The line's clip columns from an AI's segment, or none when it is not a usable clip.
     *
     * @param  mixed  $segment  `{start, end?, evidence}`, start and end as seconds or `m:ss` / `h:mm:ss`
     * @return array{clip_start: int, clip_end: ?int, clip_evidence: string}|array{}
     */
    public static function fromSegment(mixed $segment): array
    {
        if (! is_array($segment)) {
            return [];
        }

        $evidence = trim((string) (is_string($segment['evidence'] ?? null) ? $segment['evidence'] : ''));
        $start = self::seconds($segment['start'] ?? null);
        $end = self::seconds($segment['end'] ?? null);

        if ($evidence === '' || $start === null || ($end !== null && $end <= $start)) {
            return [];
        }

        return ['clip_start' => $start, 'clip_end' => $end, 'clip_evidence' => mb_substr($evidence, 0, self::EVIDENCE_MAX)];
    }

    /**
     * The owner's clip fields, times as typed (`14:00`) turned into seconds so the rules see
     * numbers; a time that cannot be read is left as typed, for the rules to refuse. A line that
     * is not a video carries no clip.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function input(array $input, bool $isVideo): array
    {
        if (! $isVideo) {
            return array_fill_keys(['clip_start', 'clip_end', 'clip_evidence'], null);
        }

        return collect(['clip_start', 'clip_end'])
            ->filter(fn (string $field) => filled($input[$field] ?? null))
            ->mapWithKeys(fn (string $field) => [$field => self::seconds($input[$field]) ?? $input[$field]])
            ->all();
    }

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'clip_start' => ['nullable', 'integer', 'min:0', 'required_with:clip_end,clip_evidence'],
            'clip_end' => ['nullable', 'integer', 'gt:clip_start'],
            // No evidence, no clip: the owner reviews what the AI proposed, and says what they checked.
            'clip_evidence' => ['nullable', 'string', 'max:'.self::EVIDENCE_MAX, 'required_with:clip_start'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'clip_start.integer' => __('arcadia.editor.content.errors.clip_time'),
            'clip_end.integer' => __('arcadia.editor.content.errors.clip_time'),
            'clip_start.required_with' => __('arcadia.editor.content.errors.clip_start'),
            'clip_end.gt' => __('arcadia.editor.content.errors.clip_end'),
            'clip_evidence.required_with' => __('arcadia.editor.content.errors.clip_evidence'),
        ];
    }

    /** `840`, `"840"`, `"840s"`, `"14:00"` or `"1:02:49"` as seconds; anything else is null. */
    public static function seconds(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        $value = trim((string) (is_string($value) ? $value : ''));

        if (preg_match('/^(\d+)s?$/', $value, $m) === 1) {
            return (int) $m[1];
        }

        if (preg_match('/^(?:(\d+):)?(\d{1,2}):(\d{2})$/', $value, $m) === 1 && (int) $m[3] < 60 && ($m[1] === '' || (int) $m[2] < 60)) {
            return (int) $m[1] * 3600 + (int) $m[2] * 60 + (int) $m[3];
        }

        return null;
    }
}
