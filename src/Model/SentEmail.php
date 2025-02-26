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
        $domains = [];

        // If the content of the email is logged in the sent_emails table (default behavior),
        // get the domains from the content.
        if (config('mail-tracker.log-content')) {
            // Match href links in <a> tags (handles both single and double quotes)
            preg_match_all("/<a[^>]*href=['\"]([^'\"]+)['\"]/i", $this->content, $matches);

            if (empty($matches[1])) {
                return [];
            }

            foreach ($matches[1] as $url) {
                // Skip invalid, mailto, tel, and JavaScript links
                if (preg_match('/^(mailto:|tel:|javascript:|#)/i', $url)) {
                    continue;
                }

                // Decode URL in case it's encoded inside a tracking link
                $decodedUrl = urldecode($url);

                // Extract original domain (including subdomains)
                $originalDomain = strtolower(parse_url($decodedUrl, PHP_URL_HOST));

                if ($originalDomain && !in_array($originalDomain, $domains)) {
                    $domains[] = $originalDomain; // Store the tracking domain
                }

                // Check if the URL contains a tracking redirect (`?l=`)
                if (preg_match('/[?&]l=([^&]+)/', $decodedUrl, $redirectMatch)) {
                    $finalUrl = urldecode($redirectMatch[1]); // Extract and decode the real target URL

                    // Extract final destination domain (including subdomains)
                    $finalDomain = strtolower(parse_url($finalUrl, PHP_URL_HOST));

                    if ($finalDomain && !in_array($finalDomain, $domains)) {
                        $domains[] = $finalDomain; // Store the real destination domain
                    }
                }
            }
        } else {
            // If the content of the email is not logged in the sent_emails table,
            // return an array of the domains you are tracking in your email.
            $domains = [];
        }

        return $domains;
    }
}
