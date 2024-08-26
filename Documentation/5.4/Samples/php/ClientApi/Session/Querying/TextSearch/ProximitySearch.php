<?php

use RavenDB\Documents\Session\DocumentQueryInterface;
use RavenDB\Samples\Infrastructure\DocumentStoreHolder;

interface FooInterface
{
    //region proximity_1
    public function proximity(int $proximity): DocumentQueryInterface;
    //endregion
}

class ProximitySearch
{
    public function examples(): void
    {
        $store = DocumentStoreHolder::getStore();
        try {
            $session = $store->openSession();
            try {
                //region proximity_2
                $session->advanced()
                    ->documentQuery(Fox::class)
                    ->search("name", "quick fox")
                    ->proximity(2)
                    ->toList();
                //endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region proximity_1
                /** @var array<Employee> $employees */
                $employees = $session->advanced()
                    ->documentQuery(Employee::class)
                    // Make a full-text search with search terms
                    ->search("Notes", "fluent french")
                    // Call 'Proximity' with 0 distance
                    ->proximity(0)
                    ->toList();

                // Running the above query on the Northwind sample data returns the following Employee documents:
                // * employees/2-A
                // * employees/5-A
                // * employees/9-A

                // Each resulting document has the text 'fluent in French' in its 'Notes' field.
                //
                // The word "in" is not taken into account as it is Not part of the terms list generated
                // by the analyzer. (Search is case-insensitive in this case).
                //
                // Note:
                // A document containing text with the search terms appearing with no words in between them
                // (e.g. "fluent french") would have also been returned.
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region proximity_3
                /** @var array<Employee> $employees */
                $employees = $session->advanced()
                    ->documentQuery(Employee::class)
                    // Make a full-text search with search terms
                    ->search("Notes", "fluent french")
                    // Call 'Proximity' with distance 5
                    ->proximity(4)
                    ->toList();

                // Running the above query on the Northwind sample data returns the following Employee documents:
                // * employees/2-A
                // * employees/5-A
                // * employees/6-A
                // * employees/9-A

                // This time document 'employees/6-A' was added to the previous results since it contains the phrase:
                // "fluent in Japanese and can read and write French"
                // where the search terms are separated by a count of 4 terms.
                //
                // "in" & "and" are not taken into account as they are not part of the terms list generated
                // by the analyzer.(Search is case-insensitive in this case).
                #endregion
            } finally {
                $session->close();
            }

        } finally {
            $store->close();
        }
    }
}

class Fox
{
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }
}