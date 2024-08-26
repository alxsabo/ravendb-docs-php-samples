<?php

namespace RavenDB\Samples\ClientApi\Session;

use PHPUnit\Framework\TestCase;
use RavenDB\Documents\Session\DocumentSessionInterface;
use RavenDB\Documents\Session\SessionOptions;
use RavenDB\Documents\Session\TransactionMode;
use RavenDB\Http\RequestExecutor;
use RavenDB\Samples\Infrastructure\DocumentStoreHolder;
use RavenDB\Samples\Infrastructure\Orders\Employee;

class FooInterface {
    //region open_session_1
    // Open session for a 'default' database configured in 'DocumentStore'
    public function openSession(): DocumentSessionInterface;

    // Open session for a specified database
    public function openSession(string $database): DocumentSessionInterface;

    public function openSession(SessionOptions $sessionOptions): DocumentSessionInterface;
    //endregion


    // REEB Note - We need to speak about this code, and I need to explain it
    public function openSession(null|string|SessionOptions $dbNameOrOptions = null): DocumentSessionInterface {}
}

class FooInterface2 {
    //region session_options
    private ?string $database = null;
    private bool $noTracking = false;
    private bool $noCaching = false;
    private ?RequestExecutor $requestExecutor = null;

    // Initialized to TransactionMode::singleNode() in constructor
    private TransactionMode $transactionMode;

    // getters and setters
    //endregion
}

class OpeningSession extends TestCase
{
    public function testSamples(): void
    {
        $databaseName = "DB1";

        $store = DocumentStoreHolder::getStore();
        try {
            //region open_session_2
            $store->openSession(new SessionOptions());
            //endregion

            {
            //region open_session_3
            $sessionOptions = new SessionOptions();
            $sessionOptions->setDatabase($databaseName);
            $store->openSession($sessionOptions);
            //endregion
            }

            //region open_session_4
            $session = $store->openSession();
            try {
                // code here
            } finally {
                $session->close();
            }
            //endregion

            {
                //region open_session_tracking_1
                $sessionOptions = new SessionOptions();
                $sessionOptions->setNoTracking(true);
                $session = $store->openSession();
                try {
                    $employee1 = $session->load(Employee::class, "employees/1-A");
                    $employee2 = $session->load(Employee::class, "employees/1-A");

                    // because NoTracking is set to 'true'
                    // each load will create a new Employee instance
                    $this->assertNotSame($employee1, $employee2);
                } finally {
                    $session->close();
                }
                //endregion
            }

            // REEB NOTE: Update following sentence in documentation to match PHP style
            // SENTENCE: Always remember to release session allocated resources after usage by invoking the close method or wrapping the session object in the try statement.
            // EXPLANATION: - In PHP you must implement finally segment in try to release session! It wouldn't do it automatically like in C# or Java.

            {
                //region open_session_caching_1
                $sessionOptions = new SessionOptions();
                $sessionOptions->setNoCaching(true);
                $session = $store->openSession();
                try {
                    // code here
                } finally {
                    $session->close();
                }
                //endregion
            }
        } finally {
            $store->close();
        }
    }
}