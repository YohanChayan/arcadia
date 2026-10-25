<?php

namespace App\Notifications\Concerns;

use App\Enums\QueueName;
use App\Services\ArcadiaSite;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * An account email in Arcadia's brand, on Arcadia's queue. Sent from the queue, where there is no
 * request, so its link is built on `APP_URL`.
 */
trait ArcadiaAccountMail
{
    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => QueueName::ARCADIA->value];
    }

    /**
     * One account email in Arcadia's brand: `verify` or `reset`, worded from `lang/*\/mail.php`
     * in the notification's locale.
     *
     * @param  array<string, mixed>  $replace
     */
    protected function accountMail(string $kind, string $url, array $replace = []): MailMessage
    {
        $brand = app(ArcadiaSite::class)->mailBrand();
        $t = fn (string $key) => __("mail.account.{$kind}.{$key}", ['site' => $brand->name, ...$replace]);

        return (new MailMessage)
            ->subject($t('subject'))
            // Kept on the message as data — tests and logs read the link from it.
            ->action($t('action'), $url)
            ->view(['html' => 'mail.account', 'text' => 'mail.account-text'], [
                'brand' => $brand,
                'heading' => $t('heading'),
                'lines' => [$t('body'), $t('expires')],
                'action' => $t('action'),
                'url' => $url,
                'ignore' => $t('ignore'),
                'fallback' => __('mail.account.fallback'),
                'signature' => __('mail.account.signature', ['site' => $brand->name]),
            ]);
    }
}
