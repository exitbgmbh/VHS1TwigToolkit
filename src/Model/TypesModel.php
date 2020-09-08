<?php

namespace App\Model;

class TypesModel
{
    /** @var array */
    private $kinds;

    /** @var array */
    private $types;

    /**
     * @param array $kinds
     * @param array $types
     */
    public function __construct(array $kinds, array $types)
    {
        $this->kinds = $kinds;
        $this->types = $types;
    }

    /**
     * @return array
     */
    public function getKinds(): array
    {
        return $this->kinds;
    }

    /**
     * @return array
     */
    public function getTypes(): array
    {
        return $this->types;
    }
}
