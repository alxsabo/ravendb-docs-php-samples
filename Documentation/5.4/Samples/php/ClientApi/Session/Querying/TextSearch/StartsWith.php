<?php

use RavenDB\Samples\Infrastructure\DocumentStoreHolder;
use RavenDB\Samples\Infrastructure\Orders\Product;

class StartsWith
{
    public function examples(): void
    {
        $store = DocumentStoreHolder::getStore();
        try {
            $session = $store->openSession();
            try {
                #region startsWith_1
                /** @var array<Product> $products */
                $products = $session
                    ->query(Product::class)
                    // Call 'StartsWith' on the field
                    // Pass the prefix to search by
                    ->whereStartsWith("Name", "Ch")
                    ->toList();

                // Results will contain only Product documents having a 'Name' field
                // that starts with any case variation of 'ch'
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region startsWith_3
                /** @var array<Product> $products */
                $products = $session->advanced()
                    ->documentQuery(Product::class)
                    // Call 'WhereStartsWith'
                    // Pass the document field and the prefix to search by
                    ->whereStartsWith("Name", "Ch")
                    ->toList();

                // Results will contain only Product documents having a 'Name' field
                // that starts with any case variation of 'ch'
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region startsWith_4
                /** @var array<Product> $products */
                $products = $session->advanced()
                    ->query(Product::class)
                    // Pass 'exact: true' to search for an EXACT prefix match
                    ->whereStartsWith("Name", "Ch", true)
                    ->toList();

                // Results will contain only Product documents having a 'Name' field
                // that starts with 'Ch'
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region startsWith_6
                /** @var array<Product> $products */
                $products = $session->advanced()
                    ->documentQuery(Product::class)
                    // Call 'WhereStartsWith'
                    // Pass 'exact: true' to search for an EXACT prefix match
                    ->whereStartsWith("Name", "Ch", true)
                    ->toList();

                // Results will contain only Product documents having a 'Name' field
                // that starts with 'Ch'
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region startsWith_7
                /** @var array<Product> $products */
                $products = $session
                    ->query(Product::class)
                    # Negate next statement
                    ->not()
                    // Call 'StartsWith' on the field
                    // Pass the prefix to search by
                    ->whereStartsWith("Name", "Ch")
                    ->toList();

                // Results will contain only Product documents having a 'Name' field
                // that does NOT start with 'ch' or any other case variations of it
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region startsWith_9
                /** @var array<Product> $products */
                $products = $session->advanced()
                    ->documentQuery(Product::class)
                    // Call 'Not' to negate the next predicate
                    ->not()
                    // Call 'WhereStartsWith'
                    // Pass the document field and the prefix to search by
                    ->whereStartsWith("Name", "Ch")
                    ->toList();

                // Results will contain only Product documents having a 'Name' field
                // that does NOT start with 'ch' or any other case variations of it
                #endregion
            } finally {
                $session->close();
            }
        } finally {
            $store->close();
        }
    }
}