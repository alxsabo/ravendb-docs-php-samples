<?php

use RavenDB\Documents\Queries\Explanation\ExplanationOptions;
use RavenDB\Documents\Queries\Explanation\Explanations;
use RavenDB\Documents\Session\DocumentQueryInterface;
use RavenDB\Samples\Infrastructure\DocumentStoreHolder;
use RavenDB\Samples\Infrastructure\Orders\Product;

interface IFoo {
    //region explain_1
    public function includeExplanations(?ExplanationOptions $options, Explanations &$explanations): DocumentQueryInterface;
    //endregion
}

class IncludeExplanations
{
    public function example(): void
    {
        $store = DocumentStoreHolder::getStore();
        try {
            $session = $store->openSession();
            try {
                // REEB note: following should be somehow included in documentation text
                // First parameter is optional. If we intend to use default options, we should just paste null instead of options object

                //region explain_2
                $explanations = new Explanations();

                /** @var array<Product> $syrups */
                $syrups = $session->advanced()->documentQuery(Product::class)
                    ->includeExplanations(null, $explanations)
                    ->search("Name", "Syrup")
                    ->toList();

                $scoreDetails = $explanations->getExplanations($syrups[0]->getId());
                //endregion
            } finally {
                $session->close();
            }
        } finally {
            $store->close();
        }
    }
}