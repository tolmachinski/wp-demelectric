<?php

namespace _PhpScoper99e9e79e8301\GuzzleHttp;

use _PhpScoper99e9e79e8301\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message) : ?string;
}
