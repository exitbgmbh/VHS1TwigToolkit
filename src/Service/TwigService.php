<?php

namespace App\Service;

use App\Twig\Loader\TextModuleLoader;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Error\RuntimeError as TwigRuntimeError;
use Twig\Error\SyntaxError as TwigSyntaxError;
use Twig\Loader\ArrayLoader;
use Twig\Loader\ChainLoader as TwigChainLoader;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;

class TwigService
{
    /** @var ConfigService */
    private $_configService;

    /**
     * @param ConfigService $configService
     */
    public function __construct(ConfigService $configService)
    {
        $this->_configService = $configService;
    }

    /**
     * @param string $templateName
     * @param array $context
     * @param array $mapping
     * @return string
     * @throws TwigLoaderError
     * @throws TwigRuntimeError
     * @throws TwigSyntaxError
     */
    public function renderTemplate(
        string $templateName,
        array $context,
        array $mapping
    ): string {
        $loader = new TwigFilesystemLoader([
            __DIR__ . '/../Templates/email',
            __DIR__ . '/../Templates/slip',
        ]);

        $tmLoader = new TextModuleLoader($mapping);

        $chainLoader = new TwigChainLoader([
            $loader,
            $tmLoader,
        ]);

        $twig = new TwigEnvironment($chainLoader);
        $templateWrapper = $twig->load($templateName);

        return $templateWrapper->render($context);
    }

    public function render(array $context)
    {
        $loader = new TwigFilesystemLoader([
            __DIR__ . '/../Templates',
        ]);

        $twig = new TwigEnvironment($loader);
        $template = $twig->load('index.html.twig');

        return $template->render($context);
    }
}
