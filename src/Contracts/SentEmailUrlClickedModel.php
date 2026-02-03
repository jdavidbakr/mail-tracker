<?php

namespace jdavidbakr\MailTracker\Contracts;

interface SentEmailUrlClickedModel
{
    public function getConnectionName();
    public function email();
}