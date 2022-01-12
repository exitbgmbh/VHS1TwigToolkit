<?php

namespace App\Twig\Extension;

use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class Barcode extends AbstractExtension
{
    /**
     * {@inheritdoc}
     */
    public function getFilters()
    {
        return [
            new TwigFilter('barcode', [$this, 'barcode'])
        ];
    }

    /**
     * @return string
     */
    public function barcode(
        $text,
        $type = BarcodeGenerator::TYPE_CODE_128,
        $widthFactor = 2,
        $height = 30,
        $color = [0, 0, 0]
    ) {
        $generator = new BarcodeGeneratorPNG();
        $codeData = $generator->getBarcode($text, $type,$widthFactor, $height, $color);
        return sprintf('data:image/png;base64,%s', base64_encode($codeData));
    }
}
