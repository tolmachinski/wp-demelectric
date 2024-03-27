<?php

namespace _PhpScoper99e9e79e8301\GuzzleHttp;

use _PhpScoper99e9e79e8301\Psr\Http\Message\MessageInterface;
final class BodySummarizer implements BodySummarizerInterface
{
    /**
     * @var int|null
     */
    private $truncateAt;
    public function __construct(int $truncateAt = null)
    {
        $this->truncateAt = $truncateAt;
    }
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message) : ?string
    {
        return $this->truncateAt === null ? \_PhpScoper99e9e79e8301\GuzzleHttp\Psr7\Message::bodySummary($message) : \_PhpScoper99e9e79e8301\GuzzleHttp\Psr7\Message::bodySummary($message, $this->truncateAt);
    }
}
