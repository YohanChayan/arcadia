<?php

namespace App\Mail;

use App\Models\Reader;
use App\Services\ArcadiaSite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * "A guide is yours": to a reader the owner added, or whose request for access was accepted —
 * in the guide's language and Arcadia's brand, laid out as Arcadia's account emails are
 * (`mail/account`). It names the guide, opens it, and says which address to sign in with, since a
 * reader with several Google accounts otherwise lands on "not shared with this account".
 */
class GuideSharedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Reader $reader, public bool $accessGranted = false) {}

    public function build(): static
    {
        $site = app(ArcadiaSite::class);
        $guide = $this->reader->guide;
        $brand = $site->mailBrand();
        $url = $site->guideUrl($guide->link_token, $guide->locale);

        $t = fn (string $key) => __("mail.guide_shared.{$key}", [
            'site' => $brand->name,
            'title' => $guide->title,
            'email' => $this->reader->email,
            'contact' => $site->contactEmail() ?? '',
            'name' => $this->reader->displayName() ?? '',
        ], $guide->locale);

        // Greeted by name when the guide has one for them.
        $hello = $this->reader->displayName() ? [$t('hello')] : [];

        return $this->locale($guide->locale)
            ->subject($t('subject'))
            ->view('mail.account', [
                'brand' => $brand,
                'heading' => $t($this->accessGranted ? 'heading_access' : 'heading'),
                'lines' => [...$hello, $t($this->accessGranted ? 'body_access' : 'body'), $t('sign_in')],
                'action' => $t('action'),
                'url' => $url,
                'ignore' => $t('help'),
                'fallback' => __('mail.account.fallback', [], $guide->locale),
                'signature' => __('mail.account.signature', ['site' => $brand->name], $guide->locale),
            ])
            ->text('mail.account-text', [
                'heading' => $t($this->accessGranted ? 'heading_access' : 'heading'),
                'lines' => [...$hello, $t($this->accessGranted ? 'body_access' : 'body'), $t('sign_in')],
                'action' => $t('action'),
                'url' => $url,
                'ignore' => $t('help'),
                'signature' => __('mail.account.signature', ['site' => $brand->name], $guide->locale),
            ]);
    }
}
