<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An email that left the site — see the `sent_mails` migration and `RecordSentMail`. */
class SentMail extends Model
{
    public $timestamps = false;

    protected $fillable = ['sent_at', 'recipient', 'subject', 'mailable', 'locale', 'message_id'];

    protected $casts = [
        'sent_at' => 'immutable_datetime',
    ];
}
