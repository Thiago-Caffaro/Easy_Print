<?php

declare(strict_types=1);

namespace EasyPrint\Http\Action;

use EasyPrint\Infrastructure\Configuration\AppConfig;
use EasyPrint\Translation\LocaleResolver;
use EasyPrint\Translation\Translator;
use EasyPrint\Views\PhpRenderer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

final readonly class HomeAction
{
    public function __construct(
        private AppConfig $config,
        private LocaleResolver $localeResolver,
        private Translator $translator,
        private PhpRenderer $renderer,
    ) {}

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $locale = $this->localeResolver->resolve($request);
        $t = fn(string $key): string => $this->translator->translate($locale, $key);

        return $this->renderer->render($response, 'home', [
            'locale' => $locale,
            'pageTitle' => $t('home.page_title'),
            'heading' => $t('home.heading'),
            'description' => $t('home.description'),
            'statusLabel' => $t('home.status_label'),
            'statusValue' => $t('home.status_ready'),
            'environmentLabel' => $t('home.environment_label'),
            'environmentValue' => $t('environment.' . $this->config->environment),
            'languageLabel' => $t('home.language_label'),
            'portugueseLabel' => $t('locale.pt-BR'),
            'englishLabel' => $t('locale.en'),
        ]);
    }
}
