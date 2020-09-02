<?php

namespace App\Service;

class ValidatorService
{
    /** @var string[] */
    private $expectedParams = [
        'kind',
        'type',
        'template',
        'identifiers',
    ];

    /**
     * @param array $params
     * @return array
     */
    public function validateGenerationRequest(array $params): array
    {
        $errors = [];
        foreach ($this->expectedParams as $expectedParam) {
            if (!array_key_exists($expectedParam, $params)) {
                $errors[] = sprintf('Feld "%s" wurde nicht ausgefüllt', $expectedParam);
                continue;
            }

            if (empty($params[$expectedParam])) {
                $errors[] = sprintf('Feld "%s" darf nicht leer sein.', $expectedParam);
            }
        }

        return $errors;
    }
}
