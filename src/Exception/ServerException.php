<?php

/**
 * Slim Framework (https://slimframework.com)
 *
 * @license https://github.com/slimphp/Slim/blob/4.x/LICENSE.md (MIT License)
 */

declare(strict_types=1);

namespace ReactApp\Exception;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;
use Throwable;

class ServerException extends Exception implements \JsonSerializable
{
    /**
     * @var string
     */
    protected $title = '';

    /**
     * @var string
     */
    protected $description = '';

    /**
     * @param string                 $message
     * @param int                    $code
     * @param Throwable|null         $previous
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return self
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function response()
    {
        return new Response($this->getCode(), ['Content-Type' => 'application/json'], json_encode($this));
    }

    public function jsonSerialize()
    {
        return [
            'code' => $this->getCode(),
            'title' => $this->getTitle(),
            'description' => $this->getDescription(),
        ];
    }
}
