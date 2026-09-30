<?php

namespace App\Exception;

/**
 * Thrown for unknown or invalid config slugs. Messages contain only the slug, never a path.
 */
class UnknownConfigException extends \InvalidArgumentException
{
}
