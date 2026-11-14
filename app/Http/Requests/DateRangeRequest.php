<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The date filters on Site settings' monitoring lists, each a preset or a custom pair of days,
 * told apart by the prefix its query parameters carry (`failed_range`, `failed_from`, …).
 * Absent, a filter means the last 24 hours.
 */
class DateRangeRequest extends FormRequest
{
    public const RANGES = ['24h', '7d', '30d', 'all', 'custom'];

    /** The filters, by the prefix their query parameters carry. */
    public const FILTERS = ['failed', 'log', 'agents', 'mail'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return collect(self::FILTERS)->flatMap(fn (string $filter) => [
            "{$filter}_range" => ['nullable', Rule::in(self::RANGES)],
            "{$filter}_from" => ["required_if:{$filter}_range,custom", 'nullable', 'date_format:Y-m-d'],
            "{$filter}_to" => ["required_if:{$filter}_range,custom", 'nullable', 'date_format:Y-m-d', "after_or_equal:{$filter}_from"],
        ])->all();
    }

    /**
     * The filter as the screen echoes it back, and the instants it covers. A custom `to` is
     * inclusive: the whole of that day.
     *
     * @return array{range: string, from: ?string, to: ?string, start: ?CarbonImmutable, end: ?CarbonImmutable}
     */
    public function range(string $filter): array
    {
        $range = $this->validated("{$filter}_range") ?? '24h';
        $from = $this->validated("{$filter}_from");
        $to = $this->validated("{$filter}_to");

        [$start, $end] = match ($range) {
            '7d' => [now()->subDays(7), null],
            '30d' => [now()->subDays(30), null],
            'all' => [null, null],
            'custom' => [CarbonImmutable::parse($from)->startOfDay(), CarbonImmutable::parse($to)->endOfDay()],
            default => [now()->subDay(), null], // 24h
        };

        return [
            'range' => $range,
            'from' => $range === 'custom' ? $from : null,
            'to' => $range === 'custom' ? $to : null,
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * The filter as the screen opens on it.
     *
     * @return array{range: string, from: ?string, to: ?string}
     */
    public function shown(string $filter): array
    {
        return array_intersect_key($this->range($filter), array_flip(['range', 'from', 'to']));
    }
}
