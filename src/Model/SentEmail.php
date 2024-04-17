<?php

namespace jdavidbakr\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use jdavidbakr\MailTracker\Concerns\IsSentEmailModel;
use jdavidbakr\MailTracker\Contracts\SentEmailModel;

/**
 * @property string $hash
 * @property string $headers
 * @property string $sender
 * @property string $recipient
 * @property string $subject
 * @property string $content
 * @property int $opens
 * @property int $clicks
 * @property int|null $message_id
 * @property Collection $meta
 */
class SentEmail extends Model implements SentEmailModel
{
    use IsSentEmailModel;

    protected $fillable = [
        'hash',
        'headers',
        'sender_name',
        'sender_email',
        'recipient_name',
        'recipient_email',
        'subject',
        'content',
        'opens',
        'clicks',
        'message_id',
        'meta',
        'opened_at',
        'clicked_at',
    ];

    protected $casts = [
        'meta' => 'collection',
        'opened_at' => 'datetime',
        'clicked_at' => 'datetime',
    ];

    protected $appends = [
        'domains_in_context'
    ];

    public function getDomainsInContextAttribute(){
        preg_match_all("/(<a[^>]*href=[\"])([^\"]*)/", $this->content, $matches);
        if ( ! isset($matches[2]) ) return [];
        $domains = [];
        foreach($matches[2] as $url){
            $domain = parse_url($url, PHP_URL_HOST);
            if ( ! in_array($domain, $domains) ){
                $domains[] = $domain;
            }
        }

        return $domains;
    }
}
