<?php

declare(strict_types=1);

namespace App\Http;

use Closure;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private readonly string $body = '',
        private readonly int $status = 200,
        private readonly array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
        private readonly ?Closure $stream = null,
    ) {
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public static function redirect(string $url, int $status = 302): self
    {
        if (preg_match('/[\r\n]/', $url) === 1) {
            throw new \InvalidArgumentException('Invalid redirect URL.');
        }
        return new self('', $status, ['Location' => $url]);
    }

    /** @param Closure():void $stream @param array<string,string> $headers */
    public static function stream(Closure $stream, array $headers, int $status = 200): self
    {
        return new self('', $status, $headers, $stream);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($this->stream !== null) {
            ($this->stream)();
            return;
        }
        echo $this->body;
    }

    public function body(): string { return $this->body; }
    public function status(): int { return $this->status; }
    /** @return array<string, string> */
    public function headers(): array { return $this->headers; }
    public function isStreamed(): bool { return $this->stream !== null; }
}
