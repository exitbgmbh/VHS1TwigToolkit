<?php

namespace App\Service;

use Mpdf\Mpdf;
use Mpdf\MpdfException;

class PdfService
{
    /**
     * @param string $html
     * @return string
     * @throws MpdfException
     */
    public function renderPdf(string $html): string
    {
        $mPdf = $this->_getInstance();
        $mPdf->WriteHTML($html);

        return $mPdf->Output(null, 'S');
    }

    /**
     * @return Mpdf
     * @throws MpdfException
     */
    private function _getInstance(): Mpdf
    {
        $mPdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'default_font' => 'arial',
            'default_font_size' => 0,
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'margin_header' => 0,
            'margin_footer' => 0,
            'orientation' => 'P',
            'tempDir' => '/tmp/mpdf', # avoid permission issues in vendor/mpdf/mpdf/tmp
        ]);

        $mPdf->keep_table_proportions = true;
        $mPdf->showWatermarkImage = true;
        $mPdf->showWatermarkText = true;
        $mPdf->watermarkImgBehind = true;

        return $mPdf;
    }
}
