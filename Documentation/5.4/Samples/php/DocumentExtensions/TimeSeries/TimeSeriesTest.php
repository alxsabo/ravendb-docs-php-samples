<?php

namespace RavenDB\Samples\DocumentExtensions\TimeSeries;

use Cassandra\Date;
use DateInterval;
use DateTime;
use RavenDB\Documents\DocumentStore;
use RavenDB\Documents\Indexes\TimeSeries\AbstractTimeSeriesIndexCreationTask;
use RavenDB\Documents\Queries\TimeSeries\TimeSeriesAggregationResult;
use RavenDB\Documents\Queries\TimeSeries\TimeSeriesRawResult;
use RavenDB\Documents\Session\TimeSeries\TimeSeriesEntryArray;
use RavenDB\Documents\Session\TimeSeries\TimeSeriesValue;
use RavenDB\ServerWide\DatabaseRecord;
use RavenDB\ServerWide\Operations\CreateDatabaseOperation;
use RavenDB\ServerWide\Operations\DeleteDatabaseCommandParameters;
use RavenDB\ServerWide\Operations\DeleteDatabasesOperation;
use RavenDB\Type\StringArray;
use RavenDB\Utils\DateUtils;

class TimeSeriesTest
{
    public function getDocumentStore(): DocumentStore
    {
        $store = new DocumentStore("http://localhost:8080", "TestDatabase");
        $store->initialize();

        $parameters = new DeleteDatabaseCommandParameters();
        $parameters->setDatabaseNames(["TestDatabase"]);
        $parameters->setHardDelete(true);

        $store->maintenance()->server()->send(new DeleteDatabasesOperation($parameters));
        $store->maintenance()->server()->send(new CreateDatabaseOperation(new DatabaseRecord("TestDatabase")));

        return $store;
    }

    public function canCreateSimpleTimeSeries(): void
    {
        $store = $this->getDocumentStore();
        try {
            $baseline =  DateUtils::today();

            // Open a session
            $session = $store->openSession();
            try {
                // Use the session to create a document
                $user = new User();
                $user->setName("John")
                $session->store($user, "users/john");

                // Create an instance of TimeSeriesFor
                // Pass an explicit document ID to the TimeSeriesFor constructor
                // Append a HeartRate of 70 at the first-minute timestamp
                $session
                    ->timeSeriesFor("users/john", "HeartRates")
                    ->append($baseline->add(new DateInterval("PT1M")), 70, "watches/fitbit");

                $session->saveChanges();
            } finally {
                $session->close();
            }

            // Get time series names
            #region timeseries_region_Retrieve-TimeSeries-Names
            // Open a session
            $session = $store->openSession();
            try {
                // Load a document entity to the session
                $user = $session->load(User::class, "users/john");

                // Call GetTimeSeriesFor, pass the entity
                $tsNames = $session->advanced()->getTimeSeriesFor($user);

                // Results will include the names of all time series associated with document 'users/john'
            } finally {
                $session->close();
            }
            #endregion

            $session = $store->openSession();
            try {
                // Use the session to load a document
                $user = $session->load(User::class, "users/john");

                // Pass the document object returned from session.Load as a param
                // Retrieve a single value from the "HeartRates" time series
                /** @var array<TimeSeriesEntry> $val */
                $val = $session->timeSeriesFor($user, "HeartRates")
                    ->get();
            } finally {
                $session->close();
            }

            #region timeseries_region_Delete-TimeSeriesFor-Single-Time-Point
            // Delete a single entry
            $session = $store->openSession();
            try {
                $session->timeSeriesFor("users/john", "HeartRates")
                ->delete($baseline->add(new DateInterval("PT1M")));

                $session->saveChanges();
            } finally {
                $session->close();
            }
            #endregion
        } finally {
            $store->close();
        }
    }

    public function stronglyTypes(): void
    {
        $store = $this->getDocumentStore();
        try {
            $store->timeSeries()->register(User::class, HeartRate::class);
            #region timeseries_region_Named-Values-Register
            // Register the StockPrice class type on the server
            $store->timeSeries()->register(Company::class, StockPrice::class);
            #endregion
            $store->timeSeries()->register(User::class, RoutePoint::class);

            $baseTime = DateUtils::today();

            // Append entries
            $session = $store->openSession();
            try {
                $user = new User();
                $user->setName("John");
                $session->store($user, "users/john");

                #region timeseries_region_Append-Named-Values-1
                // Append coordinates
                $session->typedTimeSeriesFor(RoutePoint::class, "users/john")
                    ->append(
                        $baseTime->add(new DateInterval("PT1H")),
                        new RoutePoint(latitude: 40.712776, longitude: -74.005974),
                        "devices/Navigator"
                    );
                #endregion

                $session->typedTimeSeriesFor(RoutePoint::class, "users/john")
                    ->append(
                        $baseTime->add(new DateInterval("PT2H")),
                        new RoutePoint(latitude: 40.712781, longitude: -74.005979),
                        "devices/Navigator"
                    );

                $session->typedTimeSeriesFor(RoutePoint::class, "users/john")
                    ->append(
                        $baseTime->add(new DateInterval("PT3H")),
                        new RoutePoint(latitude: 40.712789, longitude: -74.005987),
                        "devices/Navigator"
                    );

                $session->typedTimeSeriesFor(RoutePoint::class, "users/john")
                    ->append(
                        $baseTime->add(new DateInterval("PT4H")),
                        new RoutePoint(latitude: 40.712792, longitude: -74.006002),
                        "devices/Navigator"
                    );

                $session->saveChanges();
            } finally {
                $session->close();
            }


            // Get entries
            $session = $store->openSession();
            try {
                // Use the session to load a document
                $user = $session->load(User::class, "users/john");

                // Pass the document object returned from session.Load as a param
                // Retrieve a single value from the "HeartRates" time series

                /** @var TimeSeriesEntryArray<RoutePoint> $results */
                $results = $session
                    ->typedTimeSeriesFor(RoutePoint::class, "users/john")
                    ->get();
            } finally {
                $session->close();
            }

            //append entries
            $session = $store->openSession();
            try {
                $user = new User();
                $user->setName("John");
                $session->store($user, "users/john");

                // Append a HeartRate entry
                $session->timeSeriesFor("users/john", "HeartRates")
                    ->append($baseTime->add(new DateInterval("PT1M")), 70, "watches/fitbit");

                $session->saveChanges();
            } finally {
                $session->close();
            }

            // append entries using a registered time series type
            $session = $store->openSession();
            try {
                $user = new User();
                $user->setName("John");
                $session->store($user, "users/john");

                //store.TimeSeries.Register<User, HeartRate>();

                $hr = new HeartRate;
                $hr->setHeartRateMeasure(80);

                $session->typedTimeSeriesFor(HeartRate::class, "users/john")
                    ->append(new DateTime(), $hr, "watches/anotherFirm");

                $session->saveChanges();
            } finally {
                $session->close();
            }

            // append multi-value entries by name
            #region timeseries_region_Append-Named-Values-2
            $session = $store->openSession();
            try {
                $user = new User();
                $user->setName("John");
                $session->store($user, "users/john");

                // Call 'Append' with the custom StockPrice class
                $sp = new StockPrice();
                $sp->setOpen(52);
                $sp->setClose(54);
                $sp->setHigh(63.5);
                $sp->setLow(51.4);
                $sp->setVolume(9824);

                $session->typedTimeSeriesFor(StockPrice::class, "users/john")
                ->append(
                    $baseTime->add(new DateInterval("P1D")),
                    $sp,
                    "companies/kitchenAppliances"
                );

                $sp = new StockPrice();
                $sp->setOpen(54);
                $sp->setClose(55);
                $sp->setHigh(61.5);
                $sp->setLow(49.4);
                $sp->setVolume(8400);
                $session->typedTimeSeriesFor(StockPrice::class, "users/john")
                    ->append(
                        $baseTime->add(new DateInterval("P2D")),
                        $sp,
                        "companies/kitchenAppliances"
                    );

                $sp = new StockPrice();
                $sp->setOpen(55);
                $sp->setClose(57);
                $sp->setHigh(65.5);
                $sp->setLow(50);
                $sp->setVolume(9020);
                $session->typedTimeSeriesFor(StockPrice::class, "users/john")
                    ->append(
                        $baseTime->add(new DateInterval("P3D")),
                        $sp,
                        "companies/kitchenAppliances"
                    );

                $session->saveChanges();
            } finally {
                $session->close();
            }
            #endregion

            #region timeseries_region_Append-Unnamed-Values-2
            $session = $store->openSession();
            try {
                $user = new User();
                $user->setName("John");
                $session->store($user, "users/john");

                $session->timeSeriesFor("users/john", "StockPrices")
                ->append(
                    $baseTime->add(new DateInterval("P1D")),
                    [ 52, 54, 63.5, 51.4, 9824 ],
                    "companies/kitchenAppliances"
                );

                $session->timeSeriesFor("users/john", "StockPrices")
                    ->append(
                        $baseTime->add(new DateInterval("P2D")),
                        [ 54, 55, 61.5, 49.4, 8400 ],
                        "companies/kitchenAppliances"
                    );

                $session->timeSeriesFor("users/john", "StockPrices")
                ->append(
                    $baseTime->add(new DateInterval("P3D")),
                    [ 55, 57, 65.5, 50, 9020 ],
                    "companies/kitchenAppliances"
                );

                $session->saveChanges();
            } finally {
                $session->close();
            }
            #endregion

            // append multi-value entries using a registered time series type
            $session = $store->openSession();
            try {
                $company = new Company();
                $company->setName("kitchenAppliances");

                $address = new Address();
                $address->setCity("New York");
                $company->setAddress($address);
                $session->store($company, "companies/kitchenAppliances");

                $sp = new StockPrice();
                $sp->setOpen(52);
                $sp->setClose(54);
                $sp->setHigh(63.5);
                $sp->setLow(51.4);
                $sp->setVolume(9824);
                $session->typedTimeSeriesFor(StockPrice::class, "companies/kitchenAppliances")
                    ->append(
                        $baseTime->add(new DateInterval("P1D")),
                        $sp,
                        "companies/kitchenAppliances"
                    );

                $sp = new StockPrice();
                $sp->setOpen(54);
                $sp->setClose(55);
                $sp->setHigh(61.5);
                $sp->setLow(49.4);
                $sp->setVolume(8400);
                $session->typedTimeSeriesFor(StockPrice::class, "companies/kitchenAppliances")
                    ->append(
                        $baseTime->add(new DateInterval("P2D")),
                        $sp,
                        "companies/kitchenAppliances"
                    );

                $sp = new StockPrice();
                $sp->setOpen(55);
                $sp->setClose(57);
                $sp->setHigh(65.5);
                $sp->setLow(50);
                $sp->setVolume(9020);
                $session->typedTimeSeriesFor(StockPrice::class, "companies/kitchenAppliances")
                    ->append(
                        $baseTime->add(new DateInterval("P3D")),
                        $sp,
                        "companies/kitchenAppliances"
                    );

                $session->saveChanges();
            } finally {
                $session->close();
            }

            #region timeseries_region_Named-Values-Query
            $session = $store->openSession();
            try {
                $startTime = new DateTime();
                $endTime = (new DateTime())->add(new DateInterval("P3D"));

                $tsQueryText = "
                    from StockPrices
                    between \$start and \$end
                    where Tag == \"AppleTech\"";

                $query = $session->query(Company::class)
                    ->whereEquals("Address.City", "New York")
                    ->selectTimeSeries(TimeSeriesAggregationResult::class, function ($b) use ($tsQueryText) {
                        return $b->raw($tsQueryText);
                    })
                    ->addParameter("start", $startTime)
                    ->addParameter("end", $endTime);

                /** @var array<TimeSeriesAggregationResult> $queryResults */
                $queryResults = $query->toList();

                /** @var TimeSeriesEntryArray<StockPrice> $tsEntries */
                $tsEntries = $queryResults[0]->asTypedResult(StockPrice::class);

                $volumeDay1 = $tsEntries[0]->getValue()->getVolume();
                $volumeDay2 = $tsEntries[1]->getValue()->getVolume();
                $volumeDay3 = $tsEntries[2]->getValue()->getVolume();
            } finally {
                $session->close();
            }
            #endregion

            #region timeseries_region_Unnamed-Values-Query
            $session = $store->openSession();
            try {
                $startTime = new DateTime();
                $endTime = (new DateTime())->add(new DateInterval("P3D"));

                $tsQueryText = "
                    from StockPrices
                    between \$start and \$end
                    where Tag == \"AppleTech\"";

                $query = $session->query(Company::class)
                    ->whereEquals("Address.City", "New York")
                    ->selectTimeSeries(TimeSeriesRawResult::class, function ($b) use ($tsQueryText) {
                        return $b->raw($tsQueryText);
                    })
                    ->addParameter("start", $startTime)
                    ->addParameter("end", $endTime);

                /** @var array<TimeSeriesRawResult> $queryResults */
                $queryResults = $query->toList();

                /** @var TimeSeriesEntryArray $tsEntries */
                $tsEntries = $queryResults[0]->getResults();

                $volumeDay1 = $tsEntries[0]->getValues()[4];
                $volumeDay2 = $tsEntries[1]->getValues()[4];
                $volumeDay3 = $tsEntries[2]->getValues()[4];
            } finally {
                $session->close();
            }
            #endregion

            // get entries
            $session = $store->openSession();
            try {
                // Use the session to load a document
                $user = $session->load(User::class, "users/john");

                // Pass the document object returned from session.Load as a param
                // Retrieve a single value from the "HeartRates" time series
                /** @var TimeSeriesEntryArray $val */
                $val = $session->timeSeriesFor($user, "HeartRates")
                    ->get();
            } finally {
                $session->close();
            }


            #region timeseries_region_Get-NO-Named-Values
            // Use Get without a named type
            // Is the stock's closing-price rising?
            $goingUp = false;

            $session = $store->openSession();
            try {
                /** @var TimeSeriesEntryArray $val */
                $val = $session->timeSeriesFor("users/john", "StockPrices")
                    ->get();

                $closePriceDay1 = $val[0]->getValues()[1];
                $closePriceDay2 = $val[1]->getValues()[1];
                $closePriceDay3 = $val[2]->getValues()[1];

                if (($closePriceDay2 > $closePriceDay1)
                    &&
                    ($closePriceDay3 > $closePriceDay2))
                    $goingUp = true;
            } finally {
                $session->close();
            }
            #endregion

            #region timeseries_region_Get-Named-Values
            $goingUp = false;

            $session = $store->openSession();
            try {
                // Call 'Get' with the custom StockPrice class type
                /** @var TimeSeriesEntryArray<StockPrice> $val */
                $val = $session->typedTimeSeriesFor(StockPrice::class, "users/john")
                    ->get();

                $closePriceDay1 = $val[0]->getValue()->getClose();
                $closePriceDay2 = $val[1]->getValue()->getClose();
                $closePriceDay3 = $val[2]->getValue()->getClose();

                if (($closePriceDay2 > $closePriceDay1)
                    &&
                    ($closePriceDay3 > $closePriceDay2))
                    $goingUp = true;
            } finally {
                $session->close();
            }
            #endregion


            // remove entries
            /*
            $session = $store->openSession();
            try {
                $session->timeSeriesFor("users/john", "HeartRates")
                    ->delete($baseline->add(new DateInterval("PT1M"));

                $session->saveChanges();
            } finally {
                $session->close();
            }*/

            // remove entries using a registered time series type
            $session = $store->openSession();
            try {
                $session->timeSeriesFor("users/john", "HeartRates")
                    ->delete($baseTime->add(new DateInterval("PT1M")));

                $session->typedTimeSeriesFor(StockPrice::class, "users/john")
                    ->delete($baseTime->add(new DateInterval("P1D")), $baseTime->add(new DateInterval("P2D")));

                $session->saveChanges();
            } finally {
                $session->close();
            }
        } finally {
            $store->close();
        }
    }

    public function canAppendAndGetUsingQuery(): void
    {
        $store = $this->getDocumentStore();
        try {
            // Create a document
            $session = $store->openSession();
            try {
                $user = new User();
                $user->setName("John");
                
                $session->store($user);
                $session->saveChanges();
            } finally {
                $session->close();
            }

            // Query for a document with the Name property "John" and append it a time point
            $session = $store->openSession();
            try {
                $baseline = DateUtils::today();

                $query = $session->query(User::class)
                    ->whereEquals("Name", "John");

                $result = $query->toList();

                $session->timeSeriesFor($result[0], "HeartRates")
                    ->append($baseline->add(new DateInterval("PT1M")), 72, "watches/fitbit");

                $session->saveChanges();
            } finally {
                $session->close();
            }

            #region timeseries_region_Pass-TimeSeriesFor-Get-Query-Results
            // Query for a document with the Name property "John"
            // and get its HeartRates time-series values
            $session = $store->openSession();
            try {
                $baseline = DateUtils::today();

                $query = $session->query(User::class)
                    ->whereEquals("Name", "John");

                $result = $query->toList();

                /** @var TimeSeriesEntryArray $val */
                $val = $session->timeSeriesFor($result[0], "HeartRates")
                    ->get();

                $session->saveChanges();
            } finally {
                $session->close();
            }
            #endregion
      } finally {
          $store->close();
      }
    }

    public function canIncludeTimeSeriesData(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Query for a document with the Name property "John" and append it a time point
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                IRavenQueryable<User> query = $session->query(User::class)
//                    ->whereEquals("Name", "John");
//
//                var result = $query->toList();
//
//                for (var cnt = 0; cnt < 10; cnt++)
//                {
//                    session.TimeSeriesFor(result[0], "HeartRates")
//                        ->append(baseline.AddMinutes(cnt), 72d, "watches/fitbit");
//                }
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            #region timeseries_region_Load-Document-And-Include-TimeSeries
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                // Load a document
//                User user = $session.Load<User>("users/john", includeBuilder =>
//                    // Call 'IncludeTimeSeries' to include time series entries, pass:
//                    // * The time series name
//                    // * Start and end timestamps indicating the range of entries to include
//                    includeBuilder.IncludeTimeSeries("HeartRates", baseline.AddMinutes(3), baseline.AddMinutes(8)));
//
//                // The following call to 'Get' will Not trigger a server request,
//                // the entries will be retrieved from the session's cache.
//                IEnumerable<TimeSeriesEntry> entries = $session.TimeSeriesFor("users/john", "HeartRates")
//                    .Get(baseline.AddMinutes(3), baseline.AddMinutes(8));
//            } finally {
//                $session->close();
//            }
//            #endregion
//
//            #region timeseries_region_Query-Document-And-Include-TimeSeries
//            $session = $store->openSession();
//            try {
//                // Query for a document and include a whole time-series
//                User user = $session->query(User::class)
//                    ->whereEquals("Name", "John")
//                    .Include(includeBuilder => includeBuilder.IncludeTimeSeries("HeartRates"))
//                    .FirstOrDefault();
//
//                // The following call to 'Get' will Not trigger a server request,
//                // the entries will be retrieved from the session's cache.
//                IEnumerable<TimeSeriesEntry> entries = $session.TimeSeriesFor(user, "HeartRates")
//                    .Get();
//            } finally {
//                $session->close();
//            }
//            #endregion
//
//            #region timeseries_region_Raw-Query-Document-And-Include-TimeSeries
//            $session = $store->openSession();
//            try {
//                var baseTime = DateTime.Today;
//
//                var from = baseTime;
//                var to = baseTime.AddMinutes(5);
//
//                // Define the Raw Query:
//                IRawDocumentQuery<User> query = $session.Advanced.RawQuery<User>
//                           // Use 'include timeseries' in the RQL
//                          ("from Users include timeseries('HeartRates', $from, $to)")
//                           // Pass optional parameters
//                          .AddParameter("from", from)
//                          .AddParameter("to", to);
//
//                // Execute the query:
//                // For each document in the query results,
//                // the time series entries will be 'loaded' to the session along with the document
//                var users = $query->toList();
//
//                // The following call to 'Get' will Not trigger a server request,
//                // the entries will be retrieved from the session's cache.
//                IEnumerable<TimeSeriesEntry> entries = $session.TimeSeriesFor(users[0], "HeartRates")
//                    .Get(from, to);
//            } finally {
//                $session->close();
//            }
//            #endregion
//      } finally {
//          $store->close();
//      }
    }

    public function appendWithIEnumerable(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            var baseline = DateTime.Today;
//
//            // Open a session
//            $session = $store->openSession();
//            try {
//                // Use the session to create a document
//                $user = new User();
//                $user->setName("John");
//                $session->store($user, "users/john");
//
//                $session.TimeSeriesFor("users/john", "HeartRates")
//                ->append(baseline.AddMinutes(1),
//                        new[] { 65d, 52d, 72d },
//                        "watches/fitbit");
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                // Use the session to load a document
//                User user = $session.Load<User>("users/john");
//
//                // Pass the document object returned from session.Load as a param
//                // Retrieve a single value from the "HeartRates" time series
//                TimeSeriesEntry[] val = $session.TimeSeriesFor(user, "HeartRates")
//                    .Get(DateTime.MinValue, DateTime.MaxValue);
//
//            } finally {
//                $session->close();
//            }
//
//            // Get time series HeartRates' time points data
//            $session = $store->openSession();
//            try {
//
//                #region timeseries_region_Get-All-Entries-Using-Document-ID
//                // Get all time series entries
//                TimeSeriesEntry[] val = $session.TimeSeriesFor("users/john", "HeartRates")
//                    .Get(DateTime.MinValue, DateTime.MaxValue);
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            // Get time series HeartRates's time points data
//            $session = $store->openSession();
//            try {
//                #region IncludeParentAndTaggedDocuments
//                // Get all time series entries
//                TimeSeriesEntry[] entries =
//                    session.TimeSeriesFor("users/john", "HeartRates")
//                        .Get(DateTime.MinValue, DateTime.MaxValue,
//                            includes: builder => builder
//                        // Include documents referred-to by entry tags
//                        .IncludeTags()
//                        // Include Parent Document
//                        .IncludeDocument());
//                #endregion
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
    }

    public function removeRange(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            #region timeseries_region_TimeSeriesFor-Append-TimeSeries-Range
//            var baseline = DateTime.Today;
//
//            // Append 10 HeartRate values
//            $session = $store->openSession();
//            try {
//                $user = new User();
//                $user->setName("John");
//                $session->store($user, "users/john");
//
//                ISessionDocumentTimeSeries tsf = $session.TimeSeriesFor("users/john", "HeartRates");
//
//                for (int i = 0; i < 10; i++)
//                {
//                    tsf->append(baseline.AddSeconds(i), new[] { 67d }, "watches/fitbit");
//                }
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//            #endregion
//
//            #region timeseries_region_TimeSeriesFor-Delete-Time-Points-Range
//            // Delete a range of entries from the time series
//            $session = $store->openSession();
//            try {
//                $session.TimeSeriesFor("users/john", "HeartRates")
//                    .Delete(baseline.AddSeconds(0), baseline.AddSeconds(9));
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//            #endregion
//      } finally {
//          $store->close();
//      }
    }

    public function useGetTimeSeriesOperation(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var employee1 = new Employee
//                {
//                    FirstName = "John"
//                };
//                $session->store(employee1);
//
//                var employee2 = new Employee
//                {
//                    FirstName = "Mia"
//                };
//                $session->store(employee2);
//
//                var employee3 = new Employee
//                {
//                    FirstName = "Emil"
//                };
//                $session->store(employee3);
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // get employees Id list
//            List<string> employeesIdList;
//            $session = $store->openSession();
//            try {
//                employeesIdList = session
//                    .Query<Employee>()
//                    .Select(e => e.Id)
//                    ->toList();
//            } finally {
//                $session->close();
//            }
//
//            // Append each employee a week (168 hours) of random exercise HeartRate values
//            // and a week (168 hours) of random rest HeartRate values
//            var baseTime = new DateTime(2020, 5, 17);
//            Random randomValues = new Random();
//            $session = $store->openSession();
//            try {
//                for (var emp = 0; emp < employeesIdList.Count; emp++)
//                {
//                    for (var tse = 0; tse < 168; tse++)
//                    {
//                        session.TimeSeriesFor(employeesIdList[emp], "ExerciseHeartRate")
//                        ->append(baseTime.AddHours(tse),
//                                (68 + Math.Round(19 * randomValues.NextDouble())),
//                                "watches/fitbit");
//
//                        session.TimeSeriesFor(employeesIdList[emp], "RestHeartRate")
//                        ->append(baseTime.AddHours(tse),
//                                (52 + Math.Round(19 * randomValues.NextDouble())),
//                                "watches/fitbit");
//                    }
//                }
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            #region timeseries_region_Get-Single-Time-Series
//            // Define the get operation
//            var getTimeSeriesOp = new GetTimeSeriesOperation(
//                "employees/1-A", // The document ID
//                "HeartRates", // The time series name
//                DateTime.MinValue, // Entries range start
//                DateTime.MaxValue); // Entries range end
//
//            // Execute the operation by passing it to 'Operations.Send'
//            TimeSeriesRangeResult timeSeriesEntries = store.Operations.Send(getTimeSeriesOp);
//
//            // Access entries
//            var firstEntryReturned = timeSeriesEntries.Entries[0];
//            #endregion
//
//            #region timeseries_region_Get-Multiple-Time-Series
//            // Define the get operation
//            var getMultipleTimeSeriesOp = new GetMultipleTimeSeriesOperation("employees/1-A",
//                new List<TimeSeriesRange>
//                {
//                    new TimeSeriesRange
//                    {
//                        Name = "ExerciseHeartRates", From = $baseTime->add(new DateInterval("PT1H")), To = baseTime.AddHours(10)
//                    },
//                    new TimeSeriesRange
//                    {
//                        Name = "RestHeartRates", From = baseTime.AddHours(11), To = baseTime.AddHours(20)
//                    }
//                });
//
//            // Execute the operation by passing it to 'Operations.Send'
//            TimeSeriesDetails timesSeriesEntries = store.Operations.Send(getMultipleTimeSeriesOp);
//
//            // Access entries
//            var timeSeriesEntry = timesSeriesEntries.Values["ExerciseHeartRates"][0].Entries[0];
//            #endregion
//      } finally {
//          $store->close();
//      }
    }

    public function useTimeSeriesBatchOperation(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            #region timeseries_region_Append-Using-TimeSeriesBatchOperation
//            var baseTime = DateTime.Today;
//
//            // Define the Append operations:
//            // =============================
//            var appendOp1 = new TimeSeriesOperation.AppendOperation
//            {
//                Timestamp = $baseTime->add(new DateInterval("PT1M")), Values = new[] {79d}, Tag = "watches/fitbit"
//            };
//
//            var appendOp2 = new TimeSeriesOperation.AppendOperation
//            {
//                Timestamp = baseTime.AddMinutes(2), Values = new[] {82d}, Tag = "watches/fitbit"
//            };
//
//            var appendOp3 = new TimeSeriesOperation.AppendOperation
//            {
//                Timestamp = baseTime.AddMinutes(3), Values = new[] {80d}, Tag = "watches/fitbit"
//            };
//
//            var appendOp4 = new TimeSeriesOperation.AppendOperation
//            {
//                Timestamp = baseTime.AddMinutes(4), Values = new[] {78d}, Tag = "watches/fitbit"
//            };
//
//            // Define 'TimeSeriesOperation' and add the Append operations:
//            // ===========================================================
//            var timeSeriesOp = new TimeSeriesOperation
//            {
//                Name = "HeartRates"
//            };
//
//            timeSeriesOp->append(appendOp1);
//            timeSeriesOp->append(appendOp2);
//            timeSeriesOp->append(appendOp3);
//            timeSeriesOp->append(appendOp4);
//
//
//            // Define 'TimeSeriesBatchOperation' and execute:
//            // ==============================================
//            var timeSeriesBatchOp = new TimeSeriesBatchOperation("users/john", timeSeriesOp);
//            $store.Operations.Send(timeSeriesBatchOp);
//            #endregion
//      } finally {
//          $store->close();
//      }
//
//        $store = $this->getDocumentStore();
//        try {
//            #region timeseries_region_Delete-Range-Using-TimeSeriesBatchOperation
//            var baseTime = DateTime.Today;
//
//            var deleteOp = new TimeSeriesOperation.DeleteOperation
//            {
//                From = baseTime.AddMinutes(2), To = baseTime.AddMinutes(3)
//            };
//
//            var timeSeriesOp = new TimeSeriesOperation
//            {
//                Name = "HeartRates"
//            };
//
//            timeSeriesOp.Delete(deleteOp);
//
//            var timeSeriesBatchOp = new TimeSeriesBatchOperation("users/john", timeSeriesOp);
//
//            $store.Operations.Send(timeSeriesBatchOp);
//            #endregion
//      } finally {
//          $store->close();
//      }
//
//        $store = $this->getDocumentStore();
//        try {
//            #region timeseries_region-Append-and-Delete-TimeSeriesBatchOperation
//            var baseTime = DateTime.Today;
//
//            // Define some Append operations:
//            var appendOp1 = new TimeSeriesOperation.AppendOperation
//            {
//                Timestamp = $baseTime->add(new DateInterval("PT1M")), Values = new[] {79d}, Tag = "watches/fitbit"
//            };
//
//            var appendOp2 = new TimeSeriesOperation.AppendOperation
//            {
//                Timestamp = baseTime.AddMinutes(2), Values = new[] {82d}, Tag = "watches/fitbit"
//            };
//
//            var appendOp3 = new TimeSeriesOperation.AppendOperation
//            {
//                Timestamp = baseTime.AddMinutes(3), Values = new[] {80d}, Tag = "watches/fitbit"
//            };
//
//            // Define a Delete operation:
//            var deleteOp = new TimeSeriesOperation.DeleteOperation
//            {
//                From = baseTime.AddMinutes(2), To = baseTime.AddMinutes(3)
//            };
//
//            var timeSeriesOp = new TimeSeriesOperation
//            {
//                Name = "HeartRates"
//            };
//
//            // Add the Append & Delete operations to the list of actions
//            // Note: the Delete action will be executed BEFORE all the Append actions
//            //       even though it is added last
//            timeSeriesOp->append(appendOp1);
//            timeSeriesOp->append(appendOp2);
//            timeSeriesOp->append(appendOp3);
//            timeSeriesOp.Delete(deleteOp);
//
//            var timeSeriesBatchOp = new TimeSeriesBatchOperation("users/john", timeSeriesOp);
//
//            $store.Operations.Send(timeSeriesBatchOp);
//
//            // Results:
//            // All 3 entries that were appended will exist and are not deleted.
//            // This is because the Delete action occurs first, before all Append actions.
//            #endregion
//      } finally {
//          $store->close();
//      }
    }

    public function appendUsingBulkInsert(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user1 = new User { Name = "John" };
//                $session->store(user1, "users/john");
//
//                var user2 = new User { Name = "Jane" };
//                $session->store(user2, "users/jane");
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region timeseries_region_Use-BulkInsert-To-Append-single-entry
//                var baseTime = DateTime.Today;
//
//                // Create a BulkInsertOperation instance
//                using (BulkInsertOperation bulkInsert = store.BulkInsert())
//                {
//                    // Create a TimeSeriesBulkInsert instance
//                    using (TimeSeriesBulkInsert timeSeriesBulkInsert =
//                           // Call 'TimeSeriesFor', pass it:
//                           // * The document ID
//                           // * The time series name
//                           bulkInsert.TimeSeriesFor("users/john", "HeartRates"))
//                    {
//                        // Call 'Append' to add an entry, pass it:
//                        // * The entry's Timestamp
//                        // * The entry's Value or Values
//                        // * The entry's Tag (optional)
//                        timeSeriesBulkInsert->append($baseTime->add(new DateInterval("PT1M")), 61d, "watches/fitbit");
//                    }
//                }
//                #endregion
//
//                #region timeseries_region_Use-BulkInsert-To-Append-100-Entries
//                using (BulkInsertOperation bulkInsert = store.BulkInsert())
//                {
//                    using (TimeSeriesBulkInsert timeSeriesBulkInsert =
//                           bulkInsert.TimeSeriesFor("users/john", "HeartRates"))
//                    {
//                        Random rand = new Random();
//
//                        for (int i = 0; i < 100; i++)
//                        {
//                            double randomValue = rand.Next(60, 91);
//                            timeSeriesBulkInsert->append(baseTime.AddMinutes(i), randomValue, "watches/fitbit");
//                        }
//                    }
//                }
//                #endregion
//
//                #region timeseries_region_Use-BulkInsert-To-Append-multiple-timeseries
//                using (BulkInsertOperation bulkInsert = store.BulkInsert())
//                {
//                    // Append first time series
//                    using (TimeSeriesBulkInsert timeSeriesBulkInsert =
//                           bulkInsert.TimeSeriesFor("users/john", "HeartRates"))
//                    {
//                        timeSeriesBulkInsert->append($baseTime->add(new DateInterval("PT1M")), 61d, "watches/fitbit");
//                        timeSeriesBulkInsert->append(baseTime.AddMinutes(2), 62d, "watches/fitbit");
//                    }
//
//                    // Append another time series
//                    using (TimeSeriesBulkInsert timeSeriesBulkInsert =
//                           bulkInsert.TimeSeriesFor("users/john", "ExerciseHeartRates"))
//                    {
//                        timeSeriesBulkInsert->append(baseTime.AddMinutes(3), 81d, "watches/apple-watch");
//                        timeSeriesBulkInsert->append(baseTime.AddMinutes(4), 82d, "watches/apple-watch");
//                    }
//
//                    // Append time series in another document
//                    using (TimeSeriesBulkInsert timeSeriesBulkInsert =
//                           bulkInsert.TimeSeriesFor("users/jane", "HeartRates"))
//                    {
//                        timeSeriesBulkInsert->append($baseTime->add(new DateInterval("PT1M")), 59d, "watches/fitbit");
//                        timeSeriesBulkInsert->append(baseTime.AddMinutes(2), 60d, "watches/fitbit");
//                    }
//                }
//                #endregion
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
    }

    // bulk insert
    // Use BulkInsert.TimeSeriesBulkInsert.Append with IEnumerable
    public function appendUsingBulkInsertIEnumerable(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store(user, "users/john");
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                #region BulkInsert-overload-2-Two-HeartRate-Sets
//                using (BulkInsertOperation bulkInsert = store.BulkInsert())
//                {
//                    using (TimeSeriesBulkInsert timeSeriesBulkInsert =
//                           bulkInsert.TimeSeriesFor("users/john", "HeartRates"))
//                    {
//                        var exerciseHeartRates = new List<double> { 89d, 82d, 85d };
//                        timeSeriesBulkInsert->append(baseline.AddMinutes(1), exerciseHeartRates, "watches/fitbit");
//
//                        var restingHeartRates = new List<double> { 59d, 63d, 61d, 64d, 65d };
//                        timeSeriesBulkInsert->append(baseline.AddMinutes(2), restingHeartRates, "watches/apple-watch");
//                    }
//                }
//                #endregion
//
//                ICollection<double> values = new List<double> { 59d, 63d, 71d, 69d, 64d, 65d };
//
//                // Use BulkInsert to append 100 multi-values time-series entries
//                using (BulkInsertOperation bulkInsert = store.BulkInsert())
//                {
//                    using (TimeSeriesBulkInsert timeSeriesBulkInsert = bulkInsert.TimeSeriesFor("users/john", "HeartRates"))
//                    {
//                        for (int i = 0; i < 100; i++)
//                        {
//                            timeSeriesBulkInsert->append(baseline.AddMinutes(i), values, "watches/fitbit");
//                        }
//                    }
//                }
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
    }

    public function patchTimeSeries(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Patch a time-series to a document whose Name property is "John"
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                IRavenQueryable<User> query = $session->query(User::class)
//                    ->whereEquals("Name", "John");
//                var result = $query->toList();
//                string documentId = result[0].Id;
//
//                double[] values = { 59d };
//                const string tag = "watches/fitbit";
//                const string timeseries = "HeartRates";
//
//                $session.Advanced.Defer(new PatchCommandData(documentId, null,
//                    new PatchRequest
//                    {
//                        Script = @"timeseries(this, $timeseries)
//                                 ->append(
//                                    $timestamp,
//                                    $values,
//                                    $tag
//                                  );", // 'tag' should appear last
//                        Values =
//                        {
//                            { "timeseries", timeseries },
//                            { "timestamp", baseline.AddMinutes(1) },
//                            { "values", values },
//                            { "tag", tag }
//                        }
//                    }, null));
//                $session->saveChanges();
//
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
    }

    // patching a document a single time-series entry
    // using session.Advanced.Defer
    public function patchSingleEntryUsingSessionDefer(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Patch a document a single time-series entry
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                $session.Advanced.Defer(new PatchCommandData("users/1-A", null,
//                    new PatchRequest
//                    {
//                        Script = @"timeseries(this, $timeseries)
//                                 ->append(
//                                     $timestamp,
//                                     $values,
//                                     $tag
//                                   );",
//                        Values =
//                        {
//                            { "timeseries", "HeartRates" },
//                            { "timestamp", baseline.AddMinutes(1) },
//                            { "values", 59d },
//                            { "tag", "watches/fitbit" }
//                        }
//                    }, null));
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
    }

    // patching a document a single time-series entry
    // using PatchOperation
    public function patchSingleEntryUsingPatchOperation(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            #region TS_region-Operation_Patch-Append-Single-TS-Entry
//            var baseTime = DateTime.UtcNow;
//
//            var patchRequest = new PatchRequest
//            {
//                // Define the patch request using JavaScript:
//                Script = "timeseries(this, $timeseries)->append($timestamp, $values, $tag);",
//
//                // Provide values for the parameters in the script:
//                Values =
//                {
//                    { "timeseries", "HeartRates" },
//                    { "timestamp", $baseTime->add(new DateInterval("PT1M")) },
//                    { "values", 59d },
//                    { "tag", "watches/fitbit" }
//                }
//            };
//
//            // Define the patch operation;
//            var patchOp = new PatchOperation("users/john", null, patchRequest);
//
//            // Execute the operation:
//            $store.Operations.Send(patchOp);
//            #endregion
//      } finally {
//          $store->close();
//      }
    }

    // Patching: Append and Remove multiple time-series entries
    // Using session.Advanced.Defer
    public function patcAndhDeleteMultipleEntriesSession(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region TS_region-Session_Patch-Append-100-Random-TS-Entries
//                var baseline = DateTime.Today;
//
//                // Create arrays of timestamps and random values to patch
//                List<double> values = new List<double>();
//                List<DateTime> timeStamps = new List<DateTime>();
//
//                for (var i = 0; i < 100; i++)
//                {
//                    values.Add(68 + Math.Round(19 * new Random().NextDouble()));
//                    timeStamps.Add(baseline.AddSeconds(i));
//                }
//
//                $session.Advanced.Defer(new PatchCommandData("users/1-A", null,
//                    new PatchRequest
//                    {
//                        Script = @"
//                            var i = 0;
//                            for(i = 0; i < $values.length; i++)
//                            {
//                                timeseries(id(this), $timeseries)
//                                .append (
//                                  new Date($timeStamps[i]),
//                                  $values[i],
//                                  $tag);
//                            }",
//
//                        Values =
//                        {
//                            { "timeseries", "HeartRates" },
//                            { "timeStamps", timeStamps},
//                            { "values", values },
//                            { "tag", "watches/fitbit" }
//                        }
//                    }, null));
//
//                $session->saveChanges();
//                #endregion
//
//                #region TS_region-Session_Patch-Delete-50-TS-Entries
//                // Delete time-series entries
//                $session.Advanced.Defer(new PatchCommandData("users/1-A", null,
//                    new PatchRequest
//                    {
//                        Script = @"timeseries(this, $timeseries)
//                                 .delete(
//                                    $from,
//                                    $to
//                                  );",
//                        Values =
//                        {
//                            { "timeseries", "HeartRates" },
//                            { "from", baseline.AddSeconds(0) },
//                            { "to", baseline.AddSeconds(49) }
//                        }
//                    }, null));
//
//                $session->saveChanges();
//                #endregion
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
    }

    // Patching:multiple time-series entries Using session.Advanced.Defer
    public function patcMultipleEntriesSession(): void
    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store(user, "users/john");
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region TS_region-Session_Patch-Append-TS-Entries
//                var baseTime = DateTime.UtcNow;
//
//                // Create arrays of timestamps and random values to patch
//                var values = new List<double>();
//                var timeStamps = new List<DateTime>();
//
//                for (var i = 0; i < 100; i++)
//                {
//                    values.Add(68 + Math.Round(19 * new Random().NextDouble()));
//                    timeStamps.Add(baseTime.AddMinutes(i));
//                }
//
//                $session.Advanced.Defer(new PatchCommandData("users/john", null,
//                    new PatchRequest
//                    {
//                        Script = @"
//                            var i = 0;
//                            for(i = 0; i < $values.length; i++)
//                            {
//                                timeseries(id(this), $timeseries)
//                                .append (
//                                    new Date($timeStamps[i]),
//                                    $values[i],
//                                    $tag);
//                            }",
//
//                        Values =
//                        {
//                            { "timeseries", "HeartRates" },
//                            { "timeStamps", timeStamps },
//                            { "values", values },
//                            { "tag", "watches/fitbit" }
//                        }
//                    }, null));
//
//                $session->saveChanges();
//                #endregion
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
    }

//    // Patching: Append and Remove multiple time-series entries
//    // Using PatchOperation
//    [Fact]
//    public void PatcAndhDeleteMultipleEntriesOperation()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Patch a document 100 time-series entries
//            $session = $store->openSession();
//            try {
//                #region TS_region-Operation_Patch-Append-100-TS-Entries
//                var baseTime = DateTime.UtcNow;
//
//                // Create arrays of timestamps and random values to patch
//                var values = new List<double>();
//                var timeStamps = new List<DateTime>();
//
//                for (var i = 0; i < 100; i++)
//                {
//                    values.Add(68 + Math.Round(19 * new Random().NextDouble()));
//                    timeStamps.Add(baseTime.AddMinutes(i));
//                }
//
//                var patchRequest = new PatchRequest
//                {
//                    Script = @"var i = 0;
//                               for (i = 0; i < $values.length; i++) {
//                                   timeseries(id(this), $timeseries).append (
//                                       $timeStamps[i],
//                                       $values[i],
//                                       $tag);
//                               }",
//                    Values =
//                    {
//                        { "timeseries", "HeartRates" },
//                        { "timeStamps", timeStamps },
//                        { "values", values },
//                        { "tag", "watches/fitbit" }
//                    }
//                };
//
//                var patchOp = new PatchOperation("users/john", null, patchRequest);
//                store.Operations.Send(patchOp);
//                #endregion
//
//                #region TS_region-Operation_Patch-Delete-50-TS-Entries
//                store.Operations.Send(new PatchOperation("users/john", null,
//                    new PatchRequest
//                    {
//                        Script = "timeseries(this, $timeseries).delete($from, $to);",
//                        Values =
//                        {
//                            { "timeseries", "HeartRates" },
//                            { "from", baseTime },
//                            { "to", baseTime.AddMinutes(49) }
//                        }
//                    }));
//                #endregion
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
//    }
//
//    //Query Time-Series Using Raw RQL
//    [Fact]
//    public void QueryTimeSeriesUsingRawRQL()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John"
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Query for a document with the Name property "John" and append it a time point
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                IRavenQueryable<User> query = $session->query(User::class)
//                    ->whereEquals("Name", "John");
//
//                var result = $query->toList();
//
//                for (var cnt = 0; cnt < 120; cnt++)
//                {
//                    session.TimeSeriesFor(result[0], "HeartRates")
//                        ->append(baseline.AddDays(cnt), 72d, "watches/fitbit");
//                }
//
//                $session->saveChanges();
//
//            } finally {
//                $session->close();
//            }
//
//            // Query - LINQ format - Aggregation
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                #region ts_region_LINQ-6-Aggregation
//                var query = $session->query(User::class)
//                    .Where(u => u.Age > 72)
//                    .Select(q => RavenQuery.TimeSeries(q, "HeartRates", baseline, baseline.AddDays(10))
//                        .Where(ts => ts.Tag == "watches/fitbit")
//                        .GroupBy(g => g.Days(1))
//                        .Select(g => new
//                        {
//                            Avg = g.Average(),
//                            Cnt = g.Count()
//                        })
//                        ->toList());
//
//                List<TimeSeriesAggregationResult> result = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            // Raw Query
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                var start = baseline;
//                var end = baseline.AddHours(1);
//
//                IRawDocumentQuery<User> query = $session.Advanced.RawQuery<User>
//                          ("from Users include timeseries('HeartRates', $start, $end)")
//                    .AddParameter("start", start)
//                    .AddParameter("end", end);
//
//                // Raw Query with aggregation
//                IRawDocumentQuery<TimeSeriesAggregationResult> aggregatedRawQuery =
//                    session.Advanced.RawQuery<TimeSeriesAggregationResult>(@"
//                        from Users as u where Age < 30
//                        select timeseries(
//                            from HeartRates between
//                                '2020-05-27T00:00:00.0000000Z'
//                                    and '2020-06-23T00:00:00.0000000Z'
//                            group by '7 days'
//                            select min(), max())
//                        ");
//
//                var aggregatedRawQueryResult = aggregatedRawQuery->toList();
//            } finally {
//                $session->close();
//            }
//
//      } finally {
//          $store->close();
//      }
//    }
//
//
//    //Raw RQL and LINQ aggregation and projection queries
//    [Fact]
//    public void AggregationQueries()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create user documents and time-series
//            $session = $store->openSession();
//            try {
//                var employee1 = new User
//                {
//                    Name = "John",
//                    Age = 22
//                };
//                $session->store(employee1);
//
//                var employee2 = new User
//                {
//                    Name = "Mia",
//                    Age = 26
//                };
//                $session->store(employee2);
//
//                var employee3 = new User
//                {
//                    Name = "Emil",
//                    Age = 29
//                };
//                $session->store(employee3);
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // get employees Id list
//            List<string> UsersIdList;
//            $session = $store->openSession();
//            try {
//                UsersIdList = session
//                    .Query<User>()
//                    .Select(e => e.Id)
//                    ->toList();
//            } finally {
//                $session->close();
//            }
//
//            // Append each employee a week (168 hours) of random HeartRate values
//            var baseTime = new DateTime(2020, 5, 17);
//            Random randomValues = new Random();
//            $session = $store->openSession();
//            try {
//                for (var emp = 0; emp < UsersIdList.Count; emp++)
//                {
//                    for (var tse = 0; tse < 168; tse++)
//                    {
//                        session.TimeSeriesFor(UsersIdList[emp], "HeartRates")
//                        ->append(baseTime.AddHours(tse),
//                                (68 + Math.Round(19 * randomValues.NextDouble())),
//                                "watches/fitbit");
//                    }
//                }
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Query - LINQ format - HeartRate
//            $session = $store->openSession();
//            try {
//                var baseline = new DateTime(2020, 5, 17);
//
//                IRavenQueryable<TimeSeriesAggregationResult> query = $session->query(User::class)
//                    .Where(u => u.Age < 30)
//                    .Select(q => RavenQuery.TimeSeries(q, "HeartRates", baseline, baseline.AddDays(7))
//                        .Where(ts => ts.Tag == "watches/fitbit")
//                        .GroupBy(g => g.Days(1))
//                        .Select(g => new
//                        {
//                            Min = g.Min(),
//                            Max = g.Max()
//                        })
//                        ->toList());
//
//                var result = $query->toList();
//            } finally {
//                $session->close();
//            }
//
//            // Query - LINQ format - StockPrice
//            $session = $store->openSession();
//            try {
//                var baseline = new DateTime(2020, 5, 17);
//
//                #region ts_region_LINQ-Aggregation-and-Projections-StockPrice
//                IRavenQueryable<TimeSeriesAggregationResult> query = $session->query(Company::class)
//                    .Where(c => c.Address.Country == "USA")
//                    .Select(q => RavenQuery.TimeSeries(q, "StockPrice")
//                        .Where(ts => ts.Values[4] > 500000)
//                        .GroupBy(g => g.Days(7))
//                        .Select(g => new
//                        {
//                            Min = g.Min(),
//                            Max = g.Max()
//                        })
//                        ->toList());
//
//                var result = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            // Raw Query - HeartRates using "where Tag in"
//            $session = $store->openSession();
//            try {
//                var baseline = new DateTime(2020, 5, 17);
//
//                var start = baseline;
//                var end = baseline.AddHours(1);
//
//                // Raw Query with aggregation
//                IRawDocumentQuery<TimeSeriesAggregationResult> aggregatedRawQuery =
//                    session.Advanced.RawQuery<TimeSeriesAggregationResult>(@"
//                        from Users as u where Age < 30
//                        select timeseries(
//                            from HeartRates between
//                                '2020-05-17T00:00:00.0000000Z'
//                                and '2020-05-23T00:00:00.0000000Z'
//                                where Tag in ('watches/Letsfit', 'watches/Willful', 'watches/Lintelek')
//                            group by '1 days'
//                            select min(), max()
//                        )
//                        ");
//
//                var aggregatedRawQueryResult = aggregatedRawQuery->toList();
//            } finally {
//                $session->close();
//            }
//
//
//            // Raw Query - HeartRates using "where Tag =="
//            $session = $store->openSession();
//            try {
//                var baseline = new DateTime(2020, 5, 17);
//
//                var start = baseline;
//                var end = baseline.AddHours(1);
//
//                // Raw Query with aggregation
//                IRawDocumentQuery<TimeSeriesAggregationResult> aggregatedRawQuery =
//                    session.Advanced.RawQuery<TimeSeriesAggregationResult>(@"
//                        from Users as u where Age < 30
//                        select timeseries(
//                            from HeartRates between
//                                '2020-05-17T00:00:00.0000000Z'
//                                and '2020-05-23T00:00:00.0000000Z'
//                                where Tag == 'watches/fitbit'
//                            group by '1 days'
//                            select min(), max()
//                        )
//                        ");
//
//                var aggregatedRawQueryResult = aggregatedRawQuery->toList();
//
//            } finally {
//                $session->close();
//            }
//
//
//            // Raw Query - StockPrice - Select Syntax
//            $session = $store->openSession();
//            try {
//                var baseline = new DateTime(2020, 5, 17);
//
//                var start = baseline;
//                var end = baseline.AddHours(1);
//
//                // Select Syntax
//                #region ts_region_Raw-RQL-Select-Syntax-Aggregation-and-Projections-StockPrice
//                IRawDocumentQuery<TimeSeriesAggregationResult> aggregatedRawQuery =
//                    session.Advanced.RawQuery<TimeSeriesAggregationResult>(@"
//                        from Companies as c
//                            where c.Address.Country = 'USA'
//                            select timeseries (
//                                from StockPrices
//                                where Values[4] > 500000
//                                    group by '7 day'
//                                    select max(), min()
//                            )
//                        ");
//
//                var aggregatedRawQueryResult = aggregatedRawQuery->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            // LINQ query group by tag
//            $session = $store->openSession();
//            try {
//                #region LINQ_GroupBy_Tag
//                var query = $session->query(User::class)
//                    .Select(u => RavenQuery.TimeSeries(u, "HeartRates")
//                        .GroupBy(g => g
//                                .Hours(1)
//                                .ByTag()
//                               )
//                        .Select(g => new
//                        {
//                            Max = g.Max(),
//                            Min = g.Min()
//                        }));
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            // Raw Query - StockPrice
//            $session = $store->openSession();
//            try {
//                var baseline = new DateTime(2020, 5, 17);
//
//                var start = baseline;
//                var end = baseline.AddHours(1);
//
//                // Select Syntax
//                #region ts_region_Raw-RQL-Declare-Syntax-Aggregation-and-Projections-StockPrice
//                IRawDocumentQuery<TimeSeriesAggregationResult> aggregatedRawQuery =
//                    session.Advanced.RawQuery<TimeSeriesAggregationResult>(@"
//                        declare timeseries SP(c) {
//                            from c.StockPrices
//                            where Values[4] > 500000
//                            group by '7 day'
//                            select max(), min()
//                        }
//                        from Companies as c
//                        where c.Address.Country = 'USA'
//                        select c.Name, SP(c)"
//                        );
//
//                var aggregatedRawQueryResult = aggregatedRawQuery->toList();
//                #endregion
//
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
//    }
//
//    //Query Time-Series Using Raw RQL
//    [Fact]
//    public void QueryRawRQLNoAggregation()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John",
//                    Age = 27
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Query for a document with the Name property "John" and append it a time point
//            $session = $store->openSession();
//            try {
//                // May 17 2020, 18:00:00
//                var baseline = new DateTime(2020, 5, 17, 00, 00, 00);
//
//                IRavenQueryable<User> query = $session->query(User::class)
//                    ->whereEquals("Name", "John");
//
//                var result = $query->toList();
//
//                // Two weeks of hourly HeartRate values
//                for (var cnt = 0; cnt < 336; cnt++)
//                {
//                    session.TimeSeriesFor(result[0], "HeartRates")
//                        ->append(baseline.AddHours(cnt), 72d, "watches/fitbit");
//                }
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Raw Query
//            $session = $store->openSession();
//            try {
//                #region ts_region_Raw-Query-Non-Aggregated-Declare-Syntax
//                var baseTime = new DateTime(2020, 5, 17, 00, 00, 00); // May 17 2020, 00:00:00
//
//                // Raw query with no aggregation - Declare syntax
//                var query =
//                    session.Advanced.RawQuery<TimeSeriesRawResult>(@"
//                        declare timeseries getHeartRates(user)
//                        {
//                            from user.HeartRates
//                                between $from and $to
//                                offset '02:00'
//                        }
//                        from Users as u where Age < 30
//                        select getHeartRates(u)
//                        ")
//                    .AddParameter("from", baseTime)
//                    .AddParameter("to", baseTime.AddHours(24));
//
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region ts_region_Raw-Query-Non-Aggregated-Select-Syntax
//                var baseline = new DateTime(2020, 5, 17, 00, 00, 00); // May 17 2020, 00:00:00
//
//                // Raw query with no aggregation - Select syntax
//                var query =
//                    session.Advanced.RawQuery<TimeSeriesRawResult>(@"
//                        from Users as u where Age < 30
//                        select timeseries (
//                            from HeartRates
//                                between $from and $to
//                                offset '02:00'
//                        )")
//                    .AddParameter("from", baseline)
//                    .AddParameter("to", baseline.AddHours(24));
//
//                var results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region ts_region_Raw-Query-Aggregated
//                var baseline = new DateTime(2020, 5, 17, 00, 00, 00); // May 17 2020, 00:00:00
//
//                // Raw Query with aggregation
//                var query =
//                    session.Advanced.RawQuery<TimeSeriesAggregationResult>(@"
//                        from Users as u
//                        select timeseries(
//                            from HeartRates
//                                between $start and $end
//                            group by '1 day'
//                            select min(), max()
//                            offset '03:00')
//                        ")
//                    .AddParameter("start", baseline)
//                    .AddParameter("end", baseline.AddDays(7));
//
//                List<TimeSeriesAggregationResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
//    }
//
//    // simple RQL query and its LINQ equivalent
//    [Fact]
//    public void RawRqlAndLinqqueries()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John",
//                    Age = 28
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Query for a document with the Name property "John" and append it a time point
//            $session = $store->openSession();
//            try {
//                // May 17 2020, 18:00:00
//                var baseline = new DateTime(2020, 5, 17, 00, 00, 00);
//
//                IRavenQueryable<User> query = $session->query(User::class)
//                    ->whereEquals("Name", "John");
//
//                var result = $query->toList();
//
//                // Two weeks of hourly HeartRate values
//                for (var cnt = 0; cnt < 336; cnt++)
//                {
//                    session.TimeSeriesFor(result[0], "HeartRates")
//                        ->append(baseline.AddHours(cnt), 72d, "watches/fitbit");
//                }
//
//                $session->saveChanges();
//
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region ts_region_LINQ-2-RQL-Equivalent
//                // Raw query with no aggregation - Select syntax
//                var query = $session.Advanced.RawQuery<TimeSeriesRawResult>(@"
//                        from Users where Age < 30
//                        select timeseries (
//                            from HeartRates
//                        )");
//
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            #region ts_region_LINQ-1-Select-Timeseries
//            $session = $store->openSession();
//            try {
//                // Define the query:
//                var query = $session->query(User::class)
//                         // Filter the user documents
//                        .Where(u => u.Age < 30)
//                         // Call 'Select' to project the time series entries
//                        .Select(q => RavenQuery.TimeSeries(q, "HeartRates")
//                             // Filter the time series entries
//                            .Where(ts => ts.Tag == "watches/fitbit")
//                             // 'ToList' must be applied here to the inner time series query definition
//                             // This will not trigger query execution at this point
//                            ->toList());
//
//                // Execute the query:
//                // The following call to 'ToList' will trigger query execution
//                List<TimeSeriesRawResult> result = $query->toList();
//            } finally {
//                $session->close();
//            }
//            #endregion
//
//            // Query - LINQ format with Range selection 1
//            $session = $store->openSession();
//            try {
//                #region ts_region_LINQ-3-Range-Selection
//                var baseTime = new DateTime(2020, 5, 17, 00, 00, 00);
//
//                var query = $session->query(User::class)
//                    .Select(q => RavenQuery
//                        .TimeSeries(q, "HeartRates", baseTime, $baseTime->add(new DateInterval("P3D")))
//                        ->toList());
//
//                List<TimeSeriesRawResult> result = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region choose_range_1
//                var baseTime = new DateTime(2020, 5, 17, 00, 00, 00);
//                var from = baseTime;
//                var to = baseTime.AddMinutes(10);
//
//                var query = session
//                    .Query<Employee>()
//                    .Where(employee => employee.Address.Country == "UK")
//                    .Select(employee => RavenQuery
//                         // Specify the range:
//                         // pass a 'from' and a 'to' DateTime values to the 'TimeSeries' method
//                        .TimeSeries(employee, "HeartRates", from, to)
//                         // Call 'Offset' to adjust the timestamps in the returned results to your local time (optional)
//                        .Offset(TimeSpan.FromHours(3))
//                        ->toList());
//
//                // Execute the query
//                List<TimeSeriesRawResult> result = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region choose_range_2
//                var baseTime = new DateTime(2020, 5, 17, 00, 00, 00);
//                var from = baseTime;
//                var to = baseTime.AddMinutes(10);
//
//                var query = $session.Advanced
//                    .DocumentQuery<Employee>()
//                    .WhereEquals(employee => employee.Address.Country, "UK")
//                    .SelectTimeSeries(builder => builder.From("HeartRates")
//                         // Specify the range:
//                         // pass a 'from' and a 'to' DateTime values to the 'Between' method
//                        .Between(from, to)
//                         // Call 'Offset' to adjust the timestamps in the returned results to your local time (optional)
//                        .Offset(TimeSpan.FromHours(3))
//                        ->toList());
//
//                // Execute the query
//                List<TimeSeriesRawResult> result = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region choose_range_3
//                var baseTime = new DateTime(2020, 5, 17, 00, 00, 00);
//
//                var query = $session.Advanced
//                    .RawQuery<TimeSeriesRawResult>(@"
//                        from Employees
//                        where Address.Country == 'UK'
//                        select timeseries (
//                            from HeartRates
//                            between $from and $to
//                            offset '03:00'
//                        )")
//                    .AddParameter("from", baseTime)
//                    .AddParameter("to", baseTime.AddMinutes(10));
//
//                // Execute the query
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region choose_range_4
//                var query = session
//                    .Query<Employee>()
//                    .Select(p => RavenQuery
//                        .TimeSeries(p, "HeartRates")
//                         // Call 'FromLast'
//                         // specify the time frame from the end of the time series
//                        .FromLast(x => x.Minutes(30))
//                        .Offset(TimeSpan.FromHours(3))
//                        ->toList());
//
//                // Execute the query
//                List<TimeSeriesRawResult> result = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region choose_range_5
//                var query = $session.Advanced
//                    .DocumentQuery<Employee>()
//                    .SelectTimeSeries(builder => builder.From("HeartRates")
//                         // Call 'FromLast'
//                         // specify the time frame from the end of the time series
//                        .FromLast(x => x.Minutes(30))
//                        .Offset(TimeSpan.FromHours(3))
//                        ->toList());
//
//                // Execute the query
//                List<TimeSeriesRawResult> result = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region choose_range_6
//                var query = $session.Advanced
//                     // Provide the raw RQL to the RawQuery method:
//                    .RawQuery<TimeSeriesRawResult>(@"
//                        from Employees
//                        select timeseries (
//                            from HeartRates
//                            last 30 min
//                            offset '03:00'
//                        )");
//
//                // Execute the query
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            // Query - LINQ format - LoadByTag to find employee address
//            $session = $store->openSession();
//            try {
//                var baseline = new DateTime(2020, 5, 17, 00, 00, 00);
//
//                IRavenQueryable<TimeSeriesRawResult> query =
//                    (IRavenQueryable<TimeSeriesRawResult>)session->query(Company::class)
//
//                        // Choose profiles of US companies
//                        .Where(c => c.Address.Country == "USA")
//
//                        .Select(q => RavenQuery.TimeSeries(q, "StockPrices")
//
//                        .LoadByTag<Employee>()
//                        .Where((ts, src) => src.Address.Country == "USA")
//
//                        ->toList());
//
//                var result = $query->toList();
//            } finally {
//                $session->close();
//            }
//
//            /*
//                            // Query - LINQ format
//                            using (var session = store.OpenSession())
//                            {
//                                //var baseline = DateTime.Today;
//                                var baseline = new DateTime(2020, 5, 17, 00, 00, 00);
//
//                                IRavenQueryable<TimeSeriesRawResult> query =
//                                    (IRavenQueryable <TimeSeriesRawResult>)session.Query<User>()
//                                    .Where(u => u.Age < 30)
//                                    .Select(q => RavenQuery.TimeSeries(q, "HeartRates", baseline, baseline.AddMonths(3))
//                                        .Where(ts => ts.Tag == "watches/fitbit")
//                                        //.GroupBy(g => g.Months(1))
//                                        //.Select(g => new
//                                        //{
//                                            //Avg = g.Average(),
//                                            //Cnt = g.Count()
//                                        //})
//                                        ->toList());
//
//                                var result = $query->toList();
//                            }
//            */
//      } finally {
//          $store->close();
//      }
//    }
//
//    // Time series Document Query examples
//    public void TSDocumentQueries()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            $session = $store->openSession();
//            try {
//                #region TS_DocQuery_1
//                // Define the query:
//                var query = $session.Advanced.DocumentQuery<User>()
//                    .SelectTimeSeries(builder => builder
//                        .From("HeartRates")
//                         // 'ToList' must be applied here to the inner time series query definition
//                         // This will not trigger query execution at this point
//                        ->toList());
//
//
//                // Execute the query:
//                // The following call to 'ToList' will trigger query execution
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region TS_DocQuery_2
//                var query = $session.Advanced.DocumentQuery<User>()
//                    .SelectTimeSeries(builder => builder
//                        .From("HeartRates")
//                        .Between(DateTime.Now, DateTime.Now.AddDays(1))
//                        ->toList());
//
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region TS_DocQuery_3
//                var query = $session.Advanced.DocumentQuery<User>()
//                    .SelectTimeSeries(builder => builder
//                        .From("HeartRates")
//                        .FromFirst(x => x.Days(3))
//                        ->toList());
//
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//            $session = $store->openSession();
//            try {
//                #region TS_DocQuery_4
//                var query = $session.Advanced.DocumentQuery<User>()
//                    .SelectTimeSeries(builder => builder
//                        .From("HeartRates")
//                        .FromLast(x => x.Days(3))
//                        ->toList());
//
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//
//            $session = $store->openSession();
//            try {
//                #region TS_DocQuery_5
//                var query = $session.Advanced.DocumentQuery<User>()
//                    .SelectTimeSeries(builder => builder
//                        .From("HeartRates")
//                        .LoadByTag<Monitor>()
//                        .Where((entry, monitor) => entry.Value <= monitor.Accuracy)
//                        ->toList());
//
//                List<TimeSeriesRawResult> results = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
//    }
//
//    //Various raw RQL queries
//    [Fact]
//    public void QueryRawRQLQueries()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user = new User
//                {
//                    Name = "John",
//                    Age = 27
//                };
//                $session->store($user);
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // Query for a document with the Name property "John" and append it a time point
//            $session = $store->openSession();
//            try {
//                var baseline = new DateTime(2020, 5, 17);
//
//                IRavenQueryable<User> query = $session->query(User::class)
//                    ->whereEquals("Name", "John");
//
//                var result = $query->toList();
//                Random randomValues = new Random();
//
//                for (var cnt = 0; cnt < 120; cnt++)
//                {
//                    session.TimeSeriesFor(result[0], "HeartRates")
//                        ->append(baseline.AddDays(cnt), (68 + Math.Round(19 * randomValues.NextDouble())), "watches/fitbit");
//                }
//
//                $session->saveChanges();
//
//            } finally {
//                $session->close();
//            }
//
//            // Raw Query
//            $session = $store->openSession();
//            try {
//                var baseline = DateTime.Today;
//
//                // Raw query with a range selection
//                IRawDocumentQuery<TimeSeriesRawResult> nonAggregatedRawQuery =
//                    session.Advanced.RawQuery<TimeSeriesRawResult>(@"
//                        declare timeseries ts(jogger)
//                        {
//                            from jogger.HeartRates
//                                between $start and $end
//                        }
//                        from Users as jog where Age < 30
//                        select ts(jog)
//                        ")
//                    .AddParameter("start", new DateTime(2020, 5, 17))
//                    .AddParameter("end", new DateTime(2020, 5, 23));
//
//                var nonAggregatedRawQueryResult = nonAggregatedRawQuery->toList();
//
//            } finally {
//                $session->close();
//            }
//
//      } finally {
//          $store->close();
//      }
//    }
//
//
//
//    // patching a document a single time-series entry
//    // using PatchByQueryOperation
//    [Fact]
//    public async Task PatchTimeSerieshByQuery()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user1 = new User
//                {
//                    Name = "John"
//                };
//                $session->store(user1);
//
//                var user2 = new User
//                {
//                    Name = "Mia"
//                };
//                $session->store(user2);
//
//                var user3 = new User
//                {
//                    Name = "Emil"
//                };
//                $session->store(user3);
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            var baseline = DateTime.Today;
//
//            #region TS_region-PatchByQueryOperation-Append-To-Multiple-Docs
//            var indexQuery = new IndexQuery
//            {
//                // Define the query and the patching action that follows the 'update' keyword:
//                Query = @"from Users as u
//                          update
//                          {
//                              timeseries(u, $name)->append($time, $values, $tag)
//                          }",
//
//                // Provide values for the parameters in the script:
//                QueryParameters = new Parameters
//                {
//                    { "name", "HeartRates" },
//                    { "time", baseline.AddMinutes(1) },
//                    { "values", new[] {59d} },
//                    { "tag", "watches/fitbit" }
//                }
//            };
//
//            // Define the patch operation:
//            var patchByQueryOp = new PatchByQueryOperation(indexQuery);
//
//            // Execute the operation:
//            $store.Operations.Send(patchByQueryOp);
//            #endregion
//
//            // Append time series to multiple documents
//            PatchByQueryOperation appendExerciseHeartRateOperation = new PatchByQueryOperation(new IndexQuery
//            {
//                Query = @"from Users as u update
//                            {
//                                timeseries(u, $name)->append($time, $values, $tag)
//                            }",
//                QueryParameters = new Parameters
//                        {
//                            { "name", "ExerciseHeartRate" },
//                            { "time", baseline.AddMinutes(1) },
//                            { "values", new[]{89d} },
//                            { "tag", "watches/fitbit2" }
//                        }
//            });
//            $store.Operations.Send(appendExerciseHeartRateOperation);
//
//            // Get time-series data from all users
//            PatchByQueryOperation getOperation = new PatchByQueryOperation(new IndexQuery
//            {
//                Query = @"from users as u update
//                            {
//                                timeseries(u, $name).get($from, $to)
//                            }",
//                QueryParameters = new Parameters
//                        {
//                            { "name", "HeartRates" },
//                            { "from", DateTime.MinValue },
//                            { "to", DateTime.MaxValue }
//                        }
//            });
//            Operation getop = store.Operations.Send(getOperation);
//            var getResult = getop.WaitForCompletion();
//
//            // Get and project chosen time-series data from all users
//            PatchByQueryOperation getExerciseHeartRateOperation = new PatchByQueryOperation(new IndexQuery
//            {
//                Query = @"
//                    declare function foo(doc){
//                        var entries = timeseries(doc, $name).get($from, $to);
//                        var differentTags = [];
//                        for (var i = 0; i < entries.length; i++)
//                        {
//                            var e = entries[i];
//                            if (e.Tag !== null)
//                            {
//                                if (!differentTags.includes(e.Tag))
//                                {
//                                    differentTags.push(e.Tag);
//                                }
//                            }
//                        }
//                        doc.NumberOfUniqueTagsInTS = differentTags.length;
//                        return doc;
//                    }
//
//                    from Users as u
//                    update
//                    {
//                        put(id(u), foo(u))
//                    }",
//
//                QueryParameters = new Parameters
//                {
//                    { "name", "ExerciseHeartRate" },
//                    { "from", DateTime.MinValue },
//                    { "to", DateTime.MaxValue }
//                }
//            });
//
//            var result = store.Operations.Send(getExerciseHeartRateOperation).WaitForCompletion();
//
//            #region TS_region-PatchByQueryOperation-Delete-From-Multiple-Docs
//            PatchByQueryOperation deleteByQueryOp = new PatchByQueryOperation(new IndexQuery
//            {
//                Query = @"from Users as u
//                          where u.Age < 30
//                          update
//                          {
//                              timeseries(u, $name).delete($from, $to)
//                          }",
//
//                QueryParameters = new Parameters
//                        {
//                            { "name", "HeartRates" },
//                            { "from", DateTime.MinValue },
//                            { "to", DateTime.MaxValue }
//                        }
//            });
//
//            // Execute the operation:
//            // Time series "HeartRates" will be deleted for all users with age < 30
//            $store.Operations.Send(deleteByQueryOp);
//            #endregion
//      } finally {
//          $store->close();
//      }
//    }
//
//    // patching a document a single time-series entry
//    // using PatchByQueryOperation
//    [Fact]
//    public async Task PatchTimeSerieshByQueryWithGet()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var user1 = new User
//                {
//                    Name = "John"
//                };
//                $session->store(user1);
//
//                var user2 = new User
//                {
//                    Name = "Mia"
//                };
//                $session->store(user2);
//
//                var user3 = new User
//                {
//                    Name = "Emil"
//                };
//                $session->store(user3);
//
//                var user4 = new User
//                {
//                    Name = "shaya"
//                };
//                $session->store(user4);
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            var baseline = DateTime.Today;
//
//            // get employees Id list
//            List<string> usersIdList;
//            $session = $store->openSession();
//            try {
//                usersIdList = session
//                    .Query<User>()
//                    .Select(u => u.Id)
//                    ->toList();
//            } finally {
//                $session->close();
//            }
//
//            // Append each employee a week (168 hours) of random HeartRate values
//            var baseTime = new DateTime(2020, 5, 17);
//            Random randomValues = new Random();
//            $session = $store->openSession();
//            try {
//                for (var user = 0; user < usersIdList.Count; user++)
//                {
//                    for (var tse = 0; tse < 168; tse++)
//                    {
//                        session.TimeSeriesFor(usersIdList[user], "ExerciseHeartRate")
//                        ->append(baseTime.AddHours(tse),
//                                (68 + Math.Round(19 * randomValues.NextDouble())),
//                                "watches/fitbit" + tse.ToString());
//                    }
//                }
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            #region TS_region-PatchByQueryOperation-Get
//            PatchByQueryOperation patchNumberOfUniqueTags = new PatchByQueryOperation(new IndexQuery
//            {
//                Query = @"
//                    declare function patchDocumentField(doc) {
//                        var differentTags = [];
//                        var entries = timeseries(doc, $name).get($from, $to);
//
//                        for (var i = 0; i < entries.length; i++) {
//                            var e = entries[i];
//
//                            if (e.Tag !== null) {
//                                if (!differentTags.includes(e.Tag)) {
//                                    differentTags.push(e.Tag);
//                                }
//                            }
//                        }
//
//                        doc.NumberOfUniqueTagsInTS = differentTags.length;
//                        return doc;
//                    }
//
//                    from Users as u
//                    update {
//                        put(id(u), patchDocumentField(u))
//                    }",
//
//                QueryParameters = new Parameters
//                {
//                    { "name", "HeartRates" },
//                    { "from", DateTime.MinValue },
//                    { "to", DateTime.MaxValue }
//                }
//            });
//
//            // Execute the operation and Wait for completion:
//            var result = store.Operations.Send(patchNumberOfUniqueTags).WaitForCompletion();
//            #endregion
//
//            // Delete time-series from all users
//            PatchByQueryOperation removeOperation = new PatchByQueryOperation(new IndexQuery
//            {
//                Query = @"from Users as u
//                            update
//                            {
//                                timeseries(u, $name).delete($from, $to)
//                            }",
//                QueryParameters = new Parameters
//                        {
//                            { "name", "HeartRates" },
//                            { "from", DateTime.MinValue },
//                            { "to", DateTime.MaxValue }
//                        }
//            });
//
//            $store.Operations.Send(removeOperation);
//      } finally {
//          $store->close();
//      }
//    }
//
//    // patch HeartRate TS to all employees
//    // using PatchByQueryOperation
//    // not that all employees get the same times-series entries.
//    [Fact]
//    public async Task PatchEmployeesHeartRateTS1()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var employee1 = new Employee
//                {
//                    FirstName = "John"
//                };
//                $session->store(employee1);
//
//                var employee2 = new Employee
//                {
//                    FirstName = "Mia"
//                };
//                $session->store(employee2);
//
//                var employee3 = new Employee
//                {
//                    FirstName = "Emil"
//                };
//                $session->store(employee3);
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            var baseTime = new DateTime(2020, 5, 17);
//            Random randomValues = new Random();
//
//            // an array with a week of random hourly HeartRate values
//            var valuesToAppend = Enumerable.Range(0, 168) // 168 hours a week
//                .Select(i =>
//                {
//                    return new TimeSeriesEntry
//                    {
//                        Tag = "watches/fitbit",
//                        Timestamp = baseTime.AddHours(i),
//                        Values = new[] { 68 + Math.Round(19 * randomValues.NextDouble()) }
//                    };
//                }).ToArray();
//
//            // Append time-series to all employees
//            PatchByQueryOperation appendHeartRate = new PatchByQueryOperation(new IndexQuery
//            {
//                Query = @"from Employees as e update
//                            {
//                                for(var i = 0; i < $valuesToAppend.length; i++){
//                                    timeseries(e, $name)
//                                    ->append(
//                                        $valuesToAppend[i].Timestamp,
//                                        $valuesToAppend[i].Values,
//                                        $valuesToAppend[i].Tag);
//                                }
//                            }",
//                QueryParameters = new Parameters
//                        {
//                            {"valuesToAppend", valuesToAppend},
//                            { "name", "HeartRates" },
//                        }
//            });
//
//            $store.Operations.Send(appendHeartRate);
//      } finally {
//          $store->close();
//      }
//    }
//
//
//    // Appending random time-series entries to all employees
//    [Fact]
//    public async Task PatchEmployeesHeartRateTS2()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var employee1 = new Employee
//                {
//                    FirstName = "John"
//                };
//                $session->store(employee1);
//
//                var employee2 = new Employee
//                {
//                    FirstName = "Mia"
//                };
//                $session->store(employee2);
//
//                var employee3 = new Employee
//                {
//                    FirstName = "Emil"
//                };
//                $session->store(employee3);
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // get employees Id list
//            List<string> employeesIdList;
//            $session = $store->openSession();
//            try {
//                employeesIdList = session
//                    .Query<Employee>()
//                    .Select(e => e.Id)
//                    ->toList();
//            } finally {
//                $session->close();
//            }
//
//            // Append each employee a week (168 hours) of random HeartRate values
//            var baseTime = new DateTime(2020, 5, 17);
//            Random randomValues = new Random();
//            $session = $store->openSession();
//            try {
//                for (var emp = 0; emp < employeesIdList.Count; emp++)
//                {
//                    for (var tse = 0; tse < 168; tse++)
//                    {
//                        session.TimeSeriesFor(employeesIdList[emp], "HeartRates")
//                        ->append(baseTime.AddHours(tse),
//                                (68 + Math.Round(19 * randomValues.NextDouble())),
//                                "watches/fitbit");
//                    }
//                }
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
//    }
//
//
//    // Query an index
//    [Fact]
//    public async Task IndexQuery()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            // Create a document
//            $session = $store->openSession();
//            try {
//                var employee1 = new Employee
//                {
//                    FirstName = "John"
//                };
//                $session->store(employee1);
//
//                var employee2 = new Employee
//                {
//                    FirstName = "Mia"
//                };
//                $session->store(employee2);
//
//                var employee3 = new Employee
//                {
//                    FirstName = "Emil"
//                };
//                $session->store(employee3);
//
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            // get employees Id list
//            List<string> employeesIdList;
//            $session = $store->openSession();
//            try {
//                employeesIdList = session
//                    .Query<Employee>()
//                    .Select(e => e.Id)
//                    ->toList();
//            } finally {
//                $session->close();
//            }
//
//            // Append each employee a week (168 hours) of random HeartRate values
//            var baseTime = new DateTime(2020, 5, 17);
//            Random randomValues = new Random();
//            $session = $store->openSession();
//            try {
//                for (var emp = 0; emp < employeesIdList.Count; emp++)
//                {
//                    for (var tse = 0; tse < 168; tse++)
//                    {
//                        session.TimeSeriesFor(employeesIdList[emp], "HeartRates")
//                        ->append(baseTime.AddHours(tse),
//                                (68 + Math.Round(19 * randomValues.NextDouble())),
//                                "watches/fitbit");
//                    }
//                }
//                $session->saveChanges();
//            } finally {
//                $session->close();
//            }
//
//            $store.Maintenance.Send(new StopIndexingOperation());
//
//            var timeSeriesIndex = new TsIndex();
//            var indexDefinition = timeSeriesIndex.CreateIndexDefinition();
//
//            timeSeriesIndex.Execute(store);
//
//            $store.Maintenance.Send(new StartIndexingOperation());
//
//            //WaitForIndexing(store);
//      } finally {
//          $store->close();
//      }
//    }
//
//    [Fact]
//    public void QueryWithJavascriptAndTimeseriesFunctions()
//    {
//        $store = $this->getDocumentStore();
//        try {
//            $session = $store->openSession();
//            try {
//                #region DefineCustomFunctions
//                var query = from user in session.Query<User>()
//
//                    // The custom function
//                    let customFunc = new Func<IEnumerable<TimeSeriesEntry>, IEnumerable<ModifiedTimeSeriesEntry>>(
//                        entries =>
//                            entries.Select(e => new ModifiedTimeSeriesEntry
//                            {
//                                Timestamp = e.Timestamp,
//                                Value = e.Values.Max(),
//                                Tag = e.Tag ?? "none"
//                            }))
//
//                    // The time series query
//                    let tsQuery = RavenQuery.TimeSeries(user, "HeartRates")
//                        .Where(entry => entry.Values[0] > 100)
//                        ->toList()
//
//                    // Project query results
//                    select new
//                    {
//                        Name = user.Name,
//                        // Call the custom function
//                        TimeSeriesEntries = customFunc(tsQuery.Results)
//                    };
//
//                var queryResults = $query->toList();
//                #endregion
//            } finally {
//                $session->close();
//            }
//      } finally {
//          $store->close();
//      }
//    }
}

#region sample_ts_index
class TsIndex_IndexEntry
{
    // The index-fields:
    private ?float $BPM = null;
    private ?DateTime $date = null;
    private ?string $tag = null;
    private ?string $employeeID = null;
    private ?string $employeeName = null;

    public function getBPM(): ?float
    {
        return $this->BPM;
    }

    public function setBPM(?float $BPM): void
    {
        $this->BPM = $BPM;
    }

    public function getDate(): ?DateTime
    {
        return $this->date;
    }

    public function setDate(?DateTime $date): void
    {
        $this->date = $date;
    }

    public function getTag(): ?string
    {
        return $this->tag;
    }

    public function setTag(?string $tag): void
    {
        $this->tag = $tag;
    }

    public function getEmployeeID(): ?string
    {
        return $this->employeeID;
    }

    public function setEmployeeID(?string $employeeID): void
    {
        $this->employeeID = $employeeID;
    }

    public function getEmployeeName(): ?string
    {
        return $this->employeeName;
    }

    public function setEmployeeName(?string $employeeName): void
    {
        $this->employeeName = $employeeName;
    }
}

class TsIndex extends AbstractTimeSeriesIndexCreationTask
{
    public function __construct()
    {
        parent::__construct();

        $this->map =  "
            from ts in timeSeries.Employees.HeartRates
            from entry in ts.Entries
            let employee = LoadDocument(ts.DocumentId, \"Employees\")
            select new 
            {
                BPM = entry.Values[0],
                Date = entry.Timestamp.Date,
                Tag = entry.Tag,
                EmployeeId = ts.DocumentId,
                EmployeeName = employee.FirstName + ' ' + employee.LastName
            }
        ";
    }
}
#endregion

#region DefineCustomFunctions_ModifiedTimeSeriesEntry
class ModifiedTimeSeriesEntry
{
    private ?DateTime $timestamp = null;
    private ?float $value = null;
    private ?string $tag = null;
}
#endregion

class HeartRate
{
    #[TimeSeriesValue(0)]
    private float $heartRateMeasure = null;

    public function getHeartRateMeasure(): ?float
    {
        return $this->heartRateMeasure;
    }

    public function setHeartRateMeasure(?float $heartRateMeasure): void
    {
        $this->heartRateMeasure = $heartRateMeasure;
    }
}

#region Custom-Data-Type-1
class StockPrice
{
    #[TimeSeriesValue(0)]
    private ?float $open = null;

    #[TimeSeriesValue(1)]
    private ?float $close = null;

    #[TimeSeriesValue(2)]
    private ?float $high = null;

    #[TimeSeriesValue(3)]
    private ?float $low = null;

    #[TimeSeriesValue(4)]
    private ?float $volume = null;

    public function getOpen(): ?float
    {
        return $this->open;
    }

    public function setOpen(?float $open): void
    {
        $this->open = $open;
    }

    public function getClose(): ?float
    {
        return $this->close;
    }

    public function setClose(?float $close): void
    {
        $this->close = $close;
    }

    public function getHigh(): ?float
    {
        return $this->high;
    }

    public function setHigh(?float $high): void
    {
        $this->high = $high;
    }

    public function getLow(): ?float
    {
        return $this->low;
    }

    public function setLow(?float $low): void
    {
        $this->low = $low;
    }

    public function getVolume(): ?float
    {
        return $this->volume;
    }

    public function setVolume(?float $volume): void
    {
        $this->volume = $volume;
    }
}
#endregion

#region Custom-Data-Type-2
class RoutePoint
{
    // The Latitude and Longitude properties will contain the time series entry values.
    // The names for these values will be "Latitude" and "Longitude" respectively.
    #[TimeSeriesValue(0)]
    private ?float $latitude = null;
    #[TimeSeriesValue(1)]
    private ?float $longitude = null;

    public function __construct(?float $latitude, ?float $longitude)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): void
    {
        $this->latitude = $latitude;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): void
    {
        $this->longitude = $longitude;
    }
}
#endregion

class User
{
    private ?string $id = null;
    private ?string $name = null;
    private ?string $lastName = null;
    private ?string $addressId = null;
    private ?int $count = null;
    private ?int $age = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getAddressId(): ?string
    {
        return $this->addressId;
    }

    public function setAddressId(?string $addressId): void
    {
        $this->addressId = $addressId;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(?int $count): void
    {
        $this->count = $count;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): void
    {
        $this->age = $age;
    }
}

class Person
{
    private ?string $id = null;
    private ?string $name = null;
    private ?string $lastName = null;
    private ?int $age = null;
    private ?string $worksAt = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): void
    {
        $this->age = $age;
    }

    public function getWorksAt(): ?string
    {
        return $this->worksAt;
    }

    public function setWorksAt(?string $worksAt): void
    {
        $this->worksAt = $worksAt;
    }
}

class Company
{
    private ?string $id = null;
    private ?string $externalId = null;
    private ?string $name = null;
    private ?Contact $contact = null;
    private ?Address $address = null;
    private ?string $phone = null;
    private ?string $fax = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): void
    {
        $this->externalId = $externalId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): void
    {
        $this->contact = $contact;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): void
    {
        $this->address = $address;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): void
    {
        $this->phone = $phone;
    }

    public function getFax(): ?string
    {
        return $this->fax;
    }

    public function setFax(?string $fax): void
    {
        $this->fax = $fax;
    }
}

class Address
{
    private ?string $line1 = null;
    private ?string $line2 = null;
    private ?string $city = null;
    private ?string $region = null;
    private ?string $postalCode = null;
    private ?string $country = null;

    public function getLine1(): ?string
    {
        return $this->line1;
    }

    public function setLine1(?string $line1): void
    {
        $this->line1 = $line1;
    }

    public function getLine2(): ?string
    {
        return $this->line2;
    }

    public function setLine2(?string $line2): void
    {
        $this->line2 = $line2;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): void
    {
        $this->city = $city;
    }

    public function getRegion(): ?string
    {
        return $this->region;
    }

    public function setRegion(?string $region): void
    {
        $this->region = $region;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): void
    {
        $this->postalCode = $postalCode;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): void
    {
        $this->country = $country;
    }
}

class Contact
{
    private ?string $name = null;
    private ?string $title = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }
}

class Employee
{
    private ?string $id = null;
    private ?string $lastName = null;
    private ?string $firstName = null;
    private ?string $title = null;
    private ?Address $address = null;
    private ?DateTime $hiredAt = null;
    private ?DateTime $birthday = null;
    private ?string $homePhone = null;
    private ?string $extension = null;
    private ?string $reportsTo = null;
    private ?StringArray $notes = null;
    private ?StringArray $territories = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function setAddress(?Address $address): void
    {
        $this->address = $address;
    }

    public function getHiredAt(): ?DateTime
    {
        return $this->hiredAt;
    }

    public function setHiredAt(?DateTime $hiredAt): void
    {
        $this->hiredAt = $hiredAt;
    }

    public function getBirthday(): ?DateTime
    {
        return $this->birthday;
    }

    public function setBirthday(?DateTime $birthday): void
    {
        $this->birthday = $birthday;
    }

    public function getHomePhone(): ?string
    {
        return $this->homePhone;
    }

    public function setHomePhone(?string $homePhone): void
    {
        $this->homePhone = $homePhone;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): void
    {
        $this->extension = $extension;
    }

    public function getReportsTo(): ?string
    {
        return $this->reportsTo;
    }

    public function setReportsTo(?string $reportsTo): void
    {
        $this->reportsTo = $reportsTo;
    }

    public function getNotes(): ?StringArray
    {
        return $this->notes;
    }

    public function setNotes(?StringArray $notes): void
    {
        $this->notes = $notes;
    }

    public function getTerritories(): ?StringArray
    {
        return $this->territories;
    }

    public function setTerritories(?StringArray $territories): void
    {
        $this->territories = $territories;
    }
}

//public class SampleTimeSeriesDefinitions
//{
//#region RavenQuery-TimeSeries-Definition-With-Range
//public static ITimeSeriesQueryable TimeSeries(object documentInstance,
//    string name, DateTime from, DateTime to)
//#endregion
//{
//    throw new NotSupportedException("This method is here for strongly type support of server side call during Linq queries and should never be directly called");
//}

//#region RavenQuery-TimeSeries-Definition-Without-Range
//public static ITimeSeriesQueryable TimeSeries(object documentInstance,
//    string name)
//#endregion
//{
//    throw new NotSupportedException("This method is here for strongly type support of server side call during Linq queries and should never be directly called");
//}

#region TimeSeriesEntry-Definition
class TimeSeriesEntry
{
    private ?DateTime $timestamp = null;
    private ?string $tag = null;
    private ?array $values = null;
    private bool $rollup = false;

    private ?array $nodeValues = null; // Map<String, Double[]>


    //..
}
#endregion

//private interface Foo
//{
//    #region TimeSeriesFor-Append-definition-double
//    // Append an entry with a single value (double)
//    void Append(DateTime timestamp, double value, string tag = null);
//    #endregion
//
//    #region TimeSeriesFor-Append-definition-inum
//    // Append an entry with multiple values (IEnumerable)
//    void Append(DateTime timestamp, IEnumerable<double> values, string tag = null);
//    #endregion
//
//    #region TimeSeriesFor-Delete-definition-single-timepoint
//    // Delete a single time-series entry
//    void Delete(DateTime at);
//    #endregion
//
//    #region TimeSeriesFor-Delete-definition-range-of-timepoints
//    // Delete a range of time-series entries
//    void Delete(DateTime? from = null, DateTime? to = null);
//    #endregion
//
//    #region TimeSeriesFor-Get-definition
//    TimeSeriesEntry[] Get(DateTime? from = null, DateTime? to = null,
//        int start = 0, int pageSize = int.MaxValue);
//    #endregion
//
//    private interface ISessionDocumentTypedTimeSeries<TValues> : ISessionDocumentTypedAppendTimeSeriesBase<TValues>, ISessionDocumentDeleteTimeSeriesBase where TValues : new()
//    {
//        #region TimeSeriesFor-Get-Named-Values
//        //The stongly-typed API is used, to address time series values by name.
//        TimeSeriesEntry<TValues>[] Get(DateTime? from = null, DateTime? to = null,
//        int start = 0, int pageSize = int.MaxValue);
//        #endregion
//    }
//
//    internal interface IIncludeBuilder<T>
//    {
//    }
//    #region Load-definition
//    T Load<T>(string id, Action<IIncludeBuilder<T>> includes);
//    #endregion
//
//    public interface ITimeSeriesIncludeBuilder<T, out TBuilder>
//    {
//        #region IncludeTimeSeries-definition
//        TBuilder IncludeTimeSeries(string name, DateTime? from = null, DateTime? to = null);
//        #endregion
//    }
//
//    #region RawQuery-definition
//    IRawDocumentQuery<T> RawQuery<T>(string query);
//    #endregion
//
//    private class PatchCommandData
//    {
//        #region PatchCommandData-definition
//        public PatchCommandData(string id, string changeVector,
//            PatchRequest patch, PatchRequest patchIfMissing)
//        #endregion
//        { }
//    }
//
//    #region PatchRequest-definition
//    public class PatchRequest
//    {
//        // The patching script
//        public string Script { get; set; }
//
//        // Values for the parameters used by the patching script
//        public Dictionary<string, object> Values { get; set; }
//    }
//    #endregion
//
//    private class TimeSeriesBatchOperation
//    {
//        #region TimeSeriesBatchOperation-definition
//        public TimeSeriesBatchOperation(string documentId, TimeSeriesOperation operation)
//        #endregion
//        { }
//    }
//
//    public class GetTimeSeriesOperation
//    {
//        #region GetTimeSeriesOperation-Definition
//        public GetTimeSeriesOperation(
//            string docId,
//            string timeseries,
//            DateTime? from = null,
//            DateTime? to = null,
//            int start = 0,
//            int pageSize = int.MaxValue,
//            bool returnFullResults = false)
//        #endregion
//        { }
//    }
//
//    #region TimeSeriesRangeResult-class
//    public class TimeSeriesRangeResult
//    {
//        // Timestamp of first entry returned
//        public DateTime From;
//
//        // Timestamp of last entry returned
//        public DateTime To;
//
//        // The resulting entries
//        // Will be empty if requesting an entries range that does Not exist
//        public TimeSeriesEntry[] Entries;
//
//        // The number of entries returned
//        // Will be undefined if not all entries of this time series were returned
//        public long? TotalResults;
//    }
//    #endregion
//
//    public class GetMultipleTimeSeriesOperation
//    {
//        #region GetMultipleTimeSeriesOperation-Definition
//        public GetMultipleTimeSeriesOperation(
//                string docId,
//                IEnumerable<TimeSeriesRange> ranges,
//                int start = 0,
//                int pageSize = int.MaxValue,
//                bool returnFullResults = false)
//        #endregion
//        { }
//    }
//
//    #region TimeSeriesRange-class
//    public class TimeSeriesRange
//    {
//        public string Name;   // Name of time series
//        public DateTime From; // Get time series entries starting from this timestamp (inclusive).
//        public DateTime To;   // Get time series entries ending at this timestamp (inclusive).
//    }
//    #endregion
//
//    #region TimeSeriesDetails-class
//    public class TimeSeriesDetails
//    {
//        // The document ID
//        public string Id { get; set; }
//
//        // Dictionary of time series name to the time series results
//        public Dictionary<string, List<TimeSeriesRangeResult>> Values { get; set; }
//    }
//    #endregion
//
//    private class PatchOperation
//    {
//        #region PatchOperation-Definition
//        public PatchOperation(
//                string id,
//                string changeVector,
//                PatchRequest patch,
//                PatchRequest patchIfMissing = null,
//                bool skipPatchIfChangeVectorMismatch = false)
//        #endregion
//        { }
//    }
//
//    private class PatchByQueryOperation
//    {
//        #region PatchByQueryOperation-Definition-1
//        public PatchByQueryOperation(string queryToUpdate)
//            #endregion
//        { }
//
//        #region PatchByQueryOperation-Definition-2
//        public PatchByQueryOperation(IndexQuery queryToUpdate, QueryOperationOptions options = null)
//            #endregion
//        { }
//    }
//
//    private class TimeSeriesBulkInsert
//    {
//        #region Append-Operation-Definition-1
//        // Append a single value
//        public void Append(DateTime timestamp, double value, string tag = null)
//        #endregion
//        { }
//
//        #region Append-Operation-Definition-2
//        // Append multiple values
//        public void Append(DateTime timestamp, ICollection<double> values, string tag = null)
//        #endregion
//        { }
//    }
//
//
//    private class TimeSeriesOperations
//    {
//        #region Register-Definition-1
//        public void Register<TCollection, TTimeSeriesEntry>(string name = null)
//        #endregion
//        { }
//        #region Register-Definition-2
//        public void Register<TCollection>(string name, string[] valueNames)
//        #endregion
//        { }
//        #region Register-Definition-3
//        public void Register(string collection, string name, string[] valueNames)
//        #endregion
//        { }
//    }
//
//    #region Query-definition
//    IRavenQueryable<T> Query<T>(string indexName = null,
//            string collectionName = null, bool isMapReduce = false);
//    #endregion
//}

//Watch class for TS Document Query documentation
#region TS_DocQuery_class
class Monitor
{
    private ?float $accuracy = null;

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }

    public function setAccuracy(?float $accuracy): void
    {
        $this->accuracy = $accuracy;
    }
}
#endregion
