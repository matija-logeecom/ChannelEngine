<?php

namespace ChannelEngine\Infrastructure\Response;

/*
 * Class for handling redirect logic
 */

class RedirectionResponse extends Response
{
    private string $url;

    /**
     * Constructs Redirection Response instance
     *
     * @param string $url
     * @param int $statusCode
     * @param array $headers
     */
    public function __construct(string $url, int $statusCode = 303, array $headers = [])
    {
        $finalHeaders = array_merge(['Location' => $url], $headers);
        parent::__construct('', $statusCode, $finalHeaders);
        $this->url = $url;
    }

    /**
     * URL getter
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @inheritDoc
     */
    static public function createNotFound(string $message = "Page not found."): Response
    {
        return new self('index.php', 404);
    }

    /**
     * @inheritDoc
     */
    public static function createBadRequest(string $message = "Bad Request."): Response
    {
        return new self('index.php', 400);
    }

    /**
     * @inheritDoc
     */
    public static function createInternalServerError(string $message = "An internal server error occurred."): Response
    {
        return new self('index.php', 500);
    }
}
