<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    public function __construct(
        private readonly string $viewPath,
        private readonly UrlGenerator $urls,
        private readonly Flash $flash,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], string $layout = 'layouts/app'): string
    {
        $sharedData = $data + [
            'urls' => $this->urls,
            'flashMessages' => $this->flash->consume(),
        ];
        $content = $this->renderFile($template, $sharedData);
        return $this->renderFile($layout, $sharedData + [
            'content' => $content,
            'title' => $data['title'] ?? '',
        ]);
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $file = $this->viewPath . '/' . trim($template, '/') . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('View not found.');
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}
