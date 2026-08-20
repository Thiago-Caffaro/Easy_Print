<?php

declare(strict_types=1);

namespace EasyPrint\Views;

use const ENT_QUOTES;
use const EXTR_SKIP;

use function extract;
use function htmlspecialchars;
use function is_file;
use function ob_get_clean;
use function ob_start;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final readonly class PhpRenderer
{
    public function __construct(private string $templateDirectory) {}

    /**
     * @param array<string,mixed> $data
     */
    public function render(ResponseInterface $response, string $template, array $data = []): ResponseInterface
    {
        $response->getBody()->write($this->renderString($template, $data));

        return $response->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * @param array<string,mixed> $data
     */
    public function renderString(string $template, array $data = []): string
    {
        $path = $this->templateDirectory . '/' . $template . '.php';

        if (!is_file($path)) {
            throw new RuntimeException('The requested view template does not exist.');
        }

        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        extract($data, EXTR_SKIP);

        ob_start();
        require $path;
        $contents = ob_get_clean();

        if (false === $contents) {
            throw new RuntimeException('The view template could not be rendered.');
        }

        return $contents;
    }
}
