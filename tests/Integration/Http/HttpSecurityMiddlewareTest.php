<?php

declare(strict_types=1);

namespace EasyPrint\Tests\Integration\Http;

use EasyPrint\Http\Middleware\CsrfProtectionMiddleware;
use EasyPrint\Http\Middleware\RequestLimitsMiddleware;
use EasyPrint\Http\Middleware\SecurityHeadersMiddleware;
use EasyPrint\Http\Security\CsrfTokenManager;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;

use function str_repeat;

final class HttpSecurityMiddlewareTest extends TestCase
{
    /** @var App<ContainerInterface|null> */
    private App $application;

    protected function setUp(): void
    {
        $this->application = AppFactory::create();
        $this->application->get('/token', static function (ServerRequestInterface $request, ResponseInterface $response): ResponseInterface {
            $response->getBody()->write((string) $request->getAttribute(CsrfProtectionMiddleware::TOKEN_ATTRIBUTE));

            return $response;
        });
        $this->application->post('/mutate', static fn(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface => $response->withStatus(204));
        $this->application->addRoutingMiddleware();
        $this->application->add(new CsrfProtectionMiddleware(
            new CsrfTokenManager(str_repeat('s', 32)),
            $this->application->getResponseFactory(),
            '/print',
            true,
        ));
        $this->application->add(new RequestLimitsMiddleware(
            $this->application->getResponseFactory(),
            1_024,
            256,
        ));
        $this->application->addErrorMiddleware(false, true, true);
        $this->application->add(new SecurityHeadersMiddleware());
    }

    public function testItIssuesASecureHostScopedSessionAndAcceptsItsCsrfToken(): void
    {
        $initial = $this->application->handle(new ServerRequestFactory()->createServerRequest('GET', '/token'));
        $cookieHeader = $initial->getHeaderLine('Set-Cookie');
        $cookie = explode(';', $cookieHeader, 2)[0];
        [, $cookieValue] = explode('=', $cookie, 2);
        $token = (string) $initial->getBody();

        self::assertStringContainsString('Path=/print/', $cookieHeader);
        self::assertStringContainsString('HttpOnly; SameSite=Strict; Secure', $cookieHeader);

        $request = new ServerRequestFactory()->createServerRequest('POST', '/mutate')
            ->withCookieParams(['easy_print_session' => $cookieValue])
            ->withHeader('X-CSRF-Token', $token);
        $response = $this->application->handle($request);

        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', $response->getHeaderLine('Set-Cookie'));

        $formResponse = $this->application->handle(
            new ServerRequestFactory()->createServerRequest('POST', '/mutate')
                ->withCookieParams(['easy_print_session' => $cookieValue])
                ->withParsedBody(['_csrf' => $token]),
        );

        self::assertSame(204, $formResponse->getStatusCode());
    }

    public function testItRejectsMissingAndInvalidCsrfTokensBeforeTheMutation(): void
    {
        $missing = $this->application->handle(new ServerRequestFactory()->createServerRequest('POST', '/mutate'));
        $invalid = $this->application->handle(
            new ServerRequestFactory()->createServerRequest('POST', '/mutate')
                ->withCookieParams(['easy_print_session' => 'forged'])
                ->withParsedBody(['_csrf' => 'forged']),
        );

        self::assertSame(403, $missing->getStatusCode());
        self::assertSame(403, $invalid->getStatusCode());
        self::assertSame('Request rejected.', (string) $invalid->getBody());
    }

    public function testItRejectsOversizedBodiesAndHeaders(): void
    {
        $oversizedBody = $this->application->handle(
            new ServerRequestFactory()->createServerRequest('POST', '/mutate')
                ->withHeader('Content-Length', '1025'),
        );
        $oversizedHeaders = $this->application->handle(
            new ServerRequestFactory()->createServerRequest('GET', '/token')
                ->withHeader('X-Oversized', str_repeat('x', 256)),
        );
        $oversizedStream = $this->application->handle(
            new ServerRequestFactory()->createServerRequest('POST', '/mutate')
                ->withBody(new StreamFactory()->createStream(str_repeat('x', 1_025))),
        );
        $malformedLength = $this->application->handle(
            new ServerRequestFactory()->createServerRequest('POST', '/mutate')
                ->withHeader('Content-Length', '1, 2'),
        );

        self::assertSame(413, $oversizedBody->getStatusCode());
        self::assertSame(431, $oversizedHeaders->getStatusCode());
        self::assertSame(413, $oversizedStream->getStatusCode());
        self::assertSame(400, $malformedLength->getStatusCode());
    }

    public function testSecurityHeadersCoverSuccessfulAndRejectedResponses(): void
    {
        $success = $this->application->handle(new ServerRequestFactory()->createServerRequest('GET', '/token'));
        $rejected = $this->application->handle(new ServerRequestFactory()->createServerRequest('POST', '/mutate'));

        foreach ([$success, $rejected] as $response) {
            self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
            self::assertSame('DENY', $response->getHeaderLine('X-Frame-Options'));
            self::assertSame('nosniff', $response->getHeaderLine('X-Content-Type-Options'));
            self::assertStringContainsString("frame-ancestors 'none'", $response->getHeaderLine('Content-Security-Policy'));
        }
    }
}
