<?php

namespace RavenDB\Samples\Infrastructure\Entity;

use RavenDB\Type\TypedList;

class OrderLineList extends TypedList
{
    public function __construct()
    {
        parent::__construct(OrderLine::class);
    }
}
