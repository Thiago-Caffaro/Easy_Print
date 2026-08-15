<?php

declare(strict_types=1);

namespace EasyPrint\Http\Middleware;

use EasyPrint\Http\Security\CsrfTokenManager;

use function in_array;
use function is_array;
use function is_string;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final readonly class CsrfProtectionMiddleware implements MiddlewareInterface
{
    public const TOKEN_ATTRIBUTE = 'easy_print.csrf_token';

    private const COOKIE_NAME = 'easy_print_session';
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function __construct(
        private CsrfTokenManager $tokens,
        private ResponseFactoryInterface $responses,
        private string $basePath,
        private bool $secureCookie,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $cookie = $request->getCookieParams()[self::COOKIE_NAME] ?? null;
        $cookie = is_string($cookie) ? $cookie : null;

        if (!in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            $submitted = $request->getHeaderLine('X-CSRF-Token');

            if ('' === $submitted) {
                $body = $request->getParsedBody();
                $submitted = is_array($body) && is_string($body['_csrf'] ?? null) ? $body['_csrf'] : null;
            }

            if (!$this->tokens->isValid($cookie, $submitted)) {
                return $this->rejectedResponse();
            }
        }

        $session = $this->tokens->resolve($cookie);
        $response = $handler->handle($request->withAttribute(self::TOKEN_ATTRIBUTE, $session->token));

        if (!$session->new) {
            return $response;
        }

        $path = '' === $this->basePath ? '/' : $this->basePath . '/';
        $attributes = '; Path=' . $path . '; HttpOnly; SameSite=Strict';

        if ($this->secureCookie) {
            $attributes .= '; Secure';
        }

        return $response->withAddedHeader('Set-Cookie', self::COOKIE_NAME . '=' . $session->cookie . $attributes);
    }

    private function rejectedResponse(): ResponseInterface
    {
        $response = $this->responses->createResponse(403)
            ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->getBody()->write('Request rejected.');

        return $response;
    }
}
