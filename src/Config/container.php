<?php

use App\DependencyInjection\ContainerFactory;

// Read and validate active config name from request
$configName = '';
$rawConfig = $_REQUEST['config'] ?? '';
if (preg_match('/^[a-z0-9_-]+$/', $rawConfig)) {
    $configName = $rawConfig;
}

return ContainerFactory::create($configName);
