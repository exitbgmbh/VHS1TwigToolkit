<?php

namespace App\Service;

class MapperService
{
    /**
     * @param array $context
     * @param array $mappingContext
     * @return array
     */
    public function map(array $context, array $mappingContext): array
    {
        if (empty($mappingContext)) {
            return $context;
        }

        return array_replace_recursive($context, $mappingContext);
    }
}
