<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use EasyPrint\Http\PrintFormDataFactory;
use EasyPrint\Http\QueueSelectionCookie;
use EasyPrint\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class PrintFormAction
{
    public function __construct(
        private PrintFormDataFactory $factory,
        private QueueSelectionCookie $selectionCookie,
        private PhpRenderer $renderer,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $form = $this->factory->create($request);
        $response = $this->renderer->render($response, 'print-form', $form['data']);

        return $this->selectionCookie->apply($response, $form['selection']);
    }
}
