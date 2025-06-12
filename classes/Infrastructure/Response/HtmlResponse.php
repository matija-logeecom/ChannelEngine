<?php

namespace ChannelEngine\Infrastructure\Response;

/*
 * Class for handling HTML response logic
 */

class HtmlResponse extends Response
{
    private array $variables;
    private string $path;

    /**
     * Constructs HTML response instance
     *
     * @param string $html
     * @param array $variables
     * @param int $statusCode
     * @param array $headers
     */
    public function __construct(string $html, array $variables = [], int $statusCode = 200, array $headers = [])
    {
        parent::__construct($html, $statusCode, $headers);

        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        }

        $this->sendHeaders();
        $this->sendStatusCode();

        $this->variables = $variables;
        $this->path = $html;
    }

    /**
     * @inheritDoc
     */
    public function view(): void
    {
        parent::view();

        extract($this->variables);

        ob_start();
        if (!empty($this->path)) {
            include $this->path;
        }
        $content = ob_get_clean();

        echo $content;
    }

    /**
     * @inheritDoc
     */
    public static function createNotFound(string $message = "Page not found."): self
    {
        $errorPagePath = VIEWS_PATH . "/errors/404.phtml";
        $variables = [
            'errorTitle' => '404 - Page Not Found',
            'errorHeadline' => '404 - Page Not Found',
            'message' => $message,
            'showGoBackLink' => true,
        ];

        return new self($errorPagePath, $variables, 404);
    }

    /**
     * @inheritDoc
     */
    public static function createBadRequest(string $message = "Bad Request."): self
    {
        $errorPagePath = VIEWS_PATH . "/errors/400.phtml";
        $variables = [
            'errorTitle' => '400 - Bad Request',
            'errorHeadline' => '404 - Bad Request',
            'message' => $message,
            'showGoBackLink' => true,
        ];

        return new self($errorPagePath, $variables, 400);
    }

    /**
     * @inheritDoc
     */
    public static function createInternalServerError(string $message = "Bad Request."): self
    {
        $errorPagePath = VIEWS_PATH . "/errors/500.phtml";
        $variables = [
            'errorTitle' => '500 - Internal Server Error',
            'errorHeadline' => '500 - Internal Server Error',
            'message' => $message,
            'showGoBackLink' => false,
        ];

        return new self($errorPagePath, $variables, 500);
    }
}
