<?php

use RavenDB\Documents\Session\DocumentQueryInterface;
use RavenDB\Samples\Infrastructure\DocumentStoreHolder;
use RavenDB\Samples\Infrastructure\Orders\Employee;

interface IFoo {
    //region document_query_1
    public function documentQuery(?string $className, $indexNameOrClass = null, ?string $collectionName = null, bool $isMapReduce = false): DocumentQueryInterface;
    //endregion
}

class WhatIsDocumentQuery
{
    public function samples(): void
    {
        $store = DocumentStoreHolder::getStore();
        try {
            $session = $store->openSession();
            try {
                //region document_query_2
                // load all entities from 'Employees' collection
                $employees = $session
                    ->advanced()
                    ->documentQuery(Employee::class)
                    ->toList();
                //endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                //region document_query_3
                // load all entities from 'Employees' collection
                // where firstName equals 'Robert'
                $employees = $session
                    ->advanced()
                    ->documentQuery(Employee::class)
                    ->whereEquals("FirstName", "Robert")
                    ->toList();
                //endregion
            } finally {
                $session->close();
            }


            $session = $store->openSession();
            try {
                //region document_query_4
                // load all entities from 'Employees' collection
                // where firstName equals 'Robert'
                // using 'My/Custom/Index'
                $employees = $session
                    ->advanced()
                    ->documentQuery(Employee::class, "My/Custom/Index", null, false)
                    ->whereEquals("FirstName", "Robert")
                    ->toList();
                //endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                //region document_query_5
                // load all entities from 'Employees' collection
                // where firstName equals 'Robert'
                // using 'My/Custom/Index'
                $employees = $session
                    ->advanced()
                    ->documentQuery(Employee::class, MyCustomIndex::class)
                    ->whereEquals("FirstName", "Robert")
                    ->toList();
                //endregion
            } finally {
                $session->close();
            }

        } finally {
            $store->close();
        }
    }
}