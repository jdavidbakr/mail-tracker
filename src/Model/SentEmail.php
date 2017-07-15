<?php

namespace jdavidbakr\MailTracker\Model;

use Illuminate\Database\Eloquent\Model;

class SentEmail extends Model
{
    protected $fillable = [
    	'hash',
    	'headers',
    	'sender',
    	'recipient',
    	'subject',
    	'content',
    	'opens',
    	'clicks',
        'message_id',
        'meta',
    ];

    protected $casts = [
        'meta'=>'collection',
    ];

    public function getConnectionName()
    {
        $connName = config('mail-tracker.connection');
        return $connName ?: config('database.default');
    }

    /**
     * Returns a bootstrap class about the success/failure of the message
     * @return [type] [description]
     */
    public function getReportClassAttribute()
    {
        if($this->meta->has('success')) {
            if($this->meta->get('success')) {
                return 'success';
            } else {
                return 'danger';
            }
        } else {
            return '';
        }
    }

    /**
     * Returns the smtp detail for this message ()
     * @return [type] [description]
     */
    public function getSmtpInfoAttribute()
    {
        $meta = $this->meta;
        $responses = [];
        if($meta->has('smtpResponse')) {
            $response = $meta->get('smtpResponse');
            $delivered_at = $meta->get('delivered_at');
            $responses[] = $response.' - Delivered '.$delivered_at;
        }
        if($meta->has('failures')) {
            foreach($meta->get('failures') as $failure) {
                $responses[] = $failure['status'].' ('.$failure['action'].'): '.$failure['diagnosticCode'].' ('.$failure['emailAddress'].')';
            }
        } else if($meta->has('complaint')) {
            $complaint_time = $meta->get('complaint_time');
            if($meta->get('complaint_type')) {
                $responses[] = 'Complaint: '.$meta->get('complaint_type').' at '.$complaint_time;
            } else {
                $responses[] = 'Complaint at '.$complaint_time->format("n/d/y g:i a");
            }
        }
        return implode(" | ",$responses);
    }

    /**
     * Returns the header requested from our stored header info
     */
    public function getHeader($key)
    {
        $headers = collect(preg_split("/\r\n|\n|\r/", $this->headers))
            ->transform(function($header) {
                list($key,$value) = explode(":",$header.":");
                return collect([
                    'key'=>trim($key),
                    'value'=>trim($value)
                ]);
            })->filter(function($header) {
                return $header->get('key');
            })->keyBy('key')
            ->transform(function($header) {
                return $header->get('value');
            });
        return $headers->get($key);
    }
}
