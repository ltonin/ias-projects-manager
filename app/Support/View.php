<?php

declare(strict_types=1);

namespace App\Support;

use App\Auth\CurrentUser;
use App\Auth\Csrf;
use App\Services\NavigationService;
use RuntimeException;

final class View
{
    public function __construct(
        private readonly string $viewPath,
        private readonly UrlGenerator $urls,
        private readonly Flash $flash,
        private readonly ?CurrentUser $currentUser = null,
        private readonly ?Csrf $csrf = null,
        private readonly ?NavigationService $navigation = null,
        private readonly string $requestPath = '/',
    ) {
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], string $layout = 'layouts/app'): string
    {
        $sharedData = $data + [
            'urls' => $this->urls,
            'flashMessages' => $this->flash->consume(),
            'currentUser' => $this->currentUser?->get(),
            'globalCsrfToken' => $this->csrf?->token() ?? '',
        ] + ($this->navigation?->context($this->requestPath) ?? [
            'navigationProjects'=>[],'currentProjectId'=>null,'canCreateProject'=>false,'navigationPersonId'=>null,'navigationCapacityGlobal'=>false,'currentPath'=>$this->requestPath,
        ]);
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
