<?php

declare(strict_types=1);

namespace EasyPrint\Http;

use EasyPrint\Application\Printer\QueueSelection;
use EasyPrint\Application\Printer\SelectionPersistence;

use function is_string;

use LogicException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

use function rawurlencode;

final readonly class QueueSelectionCookie
{
    private const NAME = 'easy_print_queue';

    public function __construct(private string $basePath) {}

    public function read(ServerRequestInterface $request): ?string
    {
        $value = $request->getCookieParams()[self::NAME] ?? null;

        return is_string($value) && '' !== $value ? $value : null;
    }

    public function apply(ResponseInterface $response, QueueSelection $selection): ResponseInterface
    {
        if (SelectionPersistence::Keep === $selection->persistence) {
            return $response;
        }

        $path = '' === $this->basePath ? '/' : $this->basePath . '/';
        if (SelectionPersistence::Clear === $selection->persistence) {
            $value = '';
            $maxAge = 0;
        } else {
            if (null === $selection->queue) {
                throw new LogicException('A stored queue selection is missing its queue.');
            }

            $value = rawurlencode($selection->queue->identifier);
            $maxAge = 31_536_000;
        }

        return $response->withAddedHeader(
            'Set-Cookie',
            self::NAME . '=' . $value . '; Path=' . $path . '; Max-Age=' . $maxAge . '; HttpOnly; SameSite=Lax',
        );
    }
}
