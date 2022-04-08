<?php

namespace App\Service;

use App\Twig\Extension\Barcode;
use App\Twig\Loader\TextModuleLoader;
use App\Twig\TokenParser\ExitbTm;
use Priotas\Twig\Extension\QrCode;
use Twig\Environment as TwigEnvironment;
use Twig\Error\LoaderError as TwigLoaderError;
use Twig\Error\RuntimeError as TwigRuntimeError;
use Twig\Error\SyntaxError as TwigSyntaxError;
use Twig\Loader\ChainLoader as TwigChainLoader;
use Twig\Loader\FilesystemLoader as TwigFilesystemLoader;
use Twig\TwigFunction;

class TwigService
{
    /**
     * @param string $templateName
     * @param array $context
     * @param array $mapping
     * @param string $kind
     * @return string
     * @throws TwigLoaderError
     * @throws TwigRuntimeError
     * @throws TwigSyntaxError
     */
    public function renderTemplate(
        string $templateName,
        array $context,
        array $mapping,
        string $kind
    ): string {
        $paths = [
            __DIR__ . '/../Templates',
        ];

        if ($kind === TypesService::TEMPLATE_TYPE_DOCUMENT_NAME) {
            $paths[] = __DIR__ . '/../Templates/slip';
            $paths[] = __DIR__ . '/../Templates/email';
        } else {
            $paths[] = __DIR__ . '/../Templates/email';
            $paths[] = __DIR__ . '/../Templates/slip';
        }

        $loader = new TwigFilesystemLoader($paths);
        $tmLoader = new TextModuleLoader($mapping);
        $chainLoader = new TwigChainLoader([
            $loader,
            $tmLoader,
        ]);

        $twig = new TwigEnvironment($chainLoader, [ 
            'auto_reload' => true,
            'debug' => true,
        ]);
        foreach ($mapping as $key => $value) {
            $twig->addGlobal($key, $value);
        }

        $this->_addExitBTextModuleSupport($twig);
        $this->_addExtensions($twig);
        $this->_addCustomFilters($twig);

        $templateWrapper = $twig->load($templateName);
        return $templateWrapper->render($context);
    }

    /**
     * @param TwigEnvironment $twig
     */
    private function _addExitBTextModuleSupport(TwigEnvironment $twig) : void
    {
        $tmParser = new ExitbTm();
        $twig->addTokenParser($tmParser);
        $twig->addExtension(new \Twig\Extension\DebugExtension());
        $exitbTmTwigFunction = new TwigFunction(
            'exitbTm',
            [
                'needs_environment' => true,
                'needs_context' => true,
                'is_safe' => [ 'all' ],
            ]
        );

        $twig->addFunction($exitbTmTwigFunction);
    }

    /**
     * @param TwigEnvironment $twig
     */
    private function _addExtensions(TwigEnvironment $twig) : void
    {
        $twig->addExtension(new QrCode());
        $twig->addExtension(new Barcode());
    }

    /**
     * @param TwigEnvironment $twig
     */
    private function _addCustomFilters(TwigEnvironment $twig) : void
    {
        $twig->addFilter(new \Twig\TwigFilter('appendToArrayByKey', function($value, $append, $keyValue) {
            if (!array_key_exists($keyValue, $value)) {
                $value[$keyValue] = [];
            }

            $value[$keyValue][] = $append;

            return $value;
        }));
        $twig->addFilter(new \Twig\TwigFilter('calcAddToArrayKey', function($value, $valueToAdd, $keyValue) {
            if (!array_key_exists($keyValue, $value)) {
                $value[$keyValue] = 0;
            }

            $value[$keyValue] += $valueToAdd;

            return $value;
        }));
    }
}
