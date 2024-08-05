<?php

namespace RavenDB\Samples\Infrastructure\Entity;

use RavenDB\Type\TypedArray;

class PostArray extends TypedArray
{
    public function __construct()
    {
        parent::__construct(PostArray::class);
    }
}
