<?php

namespace App\Service;

use Exception;

class JsonService
{
    /**
     * @param string $json
     * @return array
     * @throws Exception
     */
    public function parseJson(string $json): array
    {
        $json = json_decode($json, true);
        if (JSON_ERROR_NONE !== ($lastError = json_last_error())) {
            throw new Exception(
                sprintf(
                    'unable to parse json "%s". error: %d',
                    $json,
                    $lastError
                )
            );
        }

        return $json;
    }
}
