<?php

namespace App\Services\Site;

use App\Listeners\RecordSentMail;
use App\Models\SentMail;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * The emails the site sent recently, as `RecordSentMail` wrote them: newest first, in a window,
 * with which mail each was by name. Kept `RecordSentMail::RETENTION_DAYS` days, so "all" is that.
 */
class SentMailLog
{
    /** The most rows a filtered list returns; the screen says when there are more. */
    public const MAX_ROWS = 200;

    /**
     * @return array<string, mixed>
     */
    public function sent(?CarbonInterface $start = null, ?CarbonInterface $end = null): array
    {
        $inWindow = SentMail::query()
            ->when($start, fn (Builder $query) => $query->where('sent_at', '>=', $start))
            ->when($end, fn (Builder $query) => $query->where('sent_at', '<=', $end));

        return [
            'in_range' => (clone $inWindow)->count(),
            'by_kind' => (clone $inWindow)->toBase()->selectRaw('mailable, count(*) as total')->groupBy('mailable')->get()
                ->map(fn (object $row) => ['kind' => $this->kind($row->mailable), 'total' => (int) $row->total])
                ->sortByDesc('total')->values()->all(),
            'mails' => (clone $inWindow)->latest('sent_at')->latest('id')->limit(self::MAX_ROWS)->get()
                ->map(fn (SentMail $mail) => [
                    'id' => $mail->id,
                    'sent_at' => $mail->sent_at->toAtomString(),
                    'recipient' => $mail->recipient,
                    'subject' => $mail->subject,
                    'kind' => $this->kind($mail->mailable),
                    'locale' => $mail->locale,
                ])->all(),
            'limit' => self::MAX_ROWS,
            'retention_days' => RecordSentMail::RETENTION_DAYS,
        ];
    }

    /** "Guide shared", "Password reset" — or the class's own name for a mail not named in the lang file. */
    private function kind(?string $mailable): string
    {
        $key = 'arcadia.editor.settings.mail.kinds.'.($mailable ? Str::snake(class_basename($mailable)) : 'direct');

        return Lang::has($key) ? __($key) : class_basename((string) $mailable);
    }
}
