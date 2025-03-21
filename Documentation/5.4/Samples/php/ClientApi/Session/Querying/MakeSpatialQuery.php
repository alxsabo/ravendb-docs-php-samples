<?php

namespace RavenDB\Samples\ClientApi\Session\Querying;

use Closure;
use RavenDB\Constants\DocumentsIndexingSpatial;
use RavenDB\Constants\DocumentsMetadata;
use RavenDB\Documents\DocumentStore;
use RavenDB\Documents\Indexes\Spatial\SpatialRelation;
use RavenDB\Documents\Indexes\Spatial\SpatialUnits;
use RavenDB\Documents\Queries\Spatial\DynamicSpatialField;
use RavenDB\Documents\Queries\Spatial\PointField;
use RavenDB\Documents\Queries\Spatial\SpatialCriteria;
use RavenDB\Documents\Session\DocumentQueryInterface;
use RavenDB\Samples\Infrastructure\Orders\Company;
use RavenDB\Samples\Infrastructure\Orders\Employee;

class MakeSpatialQuery
{
    public function samples(): void
    {
        $store = new DocumentStore();
        try {
            $session = $store->openSession();
            try {
                #region spatial_1
                // This query will return all matching employee entities
                // that are located within 20 kilometers radius
                // from point (47.623473 latitude, -122.3060097 longitude).

                // Define a dynamic query on Employees collection
                /** @var array<Employee> $employeesWithinRadius */
                $employeesWithinRadius = $session
                    ->query(Employee::class)
                     // Call 'Spatial' method
                    ->spatial(
                        // Call 'Point'
                        // Pass the path to the document fields containing the spatial data
                         new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Set the geographical area in which to search for matching documents
                        // Call 'WithinRadius', pass the radius and the center points coordinates
                        function($criteria) { return $criteria->withinRadius(20, 47.623473, -122.3060097); }
                    )
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_1_2
                // This query will return all matching employee entities
                // that are located within 20 kilometers radius
                // from point (47.623473 latitude, -122.3060097 longitude).

                // Define a dynamic query on Employees collection
                /** @var array<Employee> $employeesWithinRadius */
                $employeesWithinRadius = $session->advanced()
                    ->documentQuery(Employee::class)
                    // Call 'Spatial' method
                    ->spatial(
                        // Call 'Point'
                        // Pass the path to the document fields containing the spatial data
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Set the geographical area in which to search for matching documents
                        // Call 'WithinRadius', pass the radius and the center points coordinates
                        function($criteria) { return $criteria->withinRadius(20, 47.623473, -122.3060097); })
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_2
                // This query will return all matching employee entities
                // that are located within 20 kilometers radius
                // from point (47.623473 latitude, -122.3060097 longitude).

                // Define a dynamic query on Employees collection
                /** @var array<Employee> $employeesWithinRadius */
                $employeesWithinShape = $session
                    ->query(Employee::class)
                     // Call 'Spatial' method
                    ->spatial(
                        // Call 'Point'
                        // Pass the path to the document fields containing the spatial data
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Set the geographical search criteria, call 'RelatesToShape'
                        function($criteria) { return $criteria->relatesToShape(
                            // Specify the WKT string. Note: longitude is written FIRST
                             "CIRCLE(-122.3060097 47.623473 d=20)",
                            // Specify the relation between the WKT shape and the documents spatial data
                             SpatialRelation::within(),
                            // Optional: customize radius units (default is Kilometers)
                             SpatialUnits::miles()
                         ); })
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_2_2
                // This query will return all matching employee entities
                // that are located within 20 kilometers radius
                // from point (47.623473 latitude, -122.3060097 longitude).

                // Define a dynamic query on Employees collection
                /** @var array<Employee> $employeesWithinRadius */
                $employeesWithinShape = $session->advanced()
                    ->documentQuery(Employee::class)
                     // Call 'Spatial' method
                    ->spatial(
                        // Call 'Point'
                        // Pass the path to the document fields containing the spatial data
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Set the geographical search criteria, call 'RelatesToShape'
                        function($criteria) { return $criteria->relatesToShape(
                            // Specify the WKT string. Note: longitude is written FIRST
                            "CIRCLE(-122.3060097 47.623473 d=20)",
                            // Specify the relation between the WKT shape and the documents spatial data
                             SpatialRelation::within(),
                             // Optional: customize radius units (default is Kilometers)
                             SpatialUnits::miles()
                        ); })
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_3
                // This query will return all matching company entities
                // that are located within the specified polygon.

                // Define a dynamic query on Companies collection
                /** @var array<Company> $companiesWithinShape */
                $companiesWithinShape = $session
                    ->query(Company::class)
                     // Call 'Spatial' method
                    ->spatial(
                        // Call 'Point'
                        // Pass the path to the document fields containing the spatial data
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Set the geographical search criteria, call 'RelatesToShape'
                        function($criteria) { return $criteria->relatesToShape(
                        // Specify the WKT string
                            "POLYGON ((
                                -118.6527948 32.7114894, 
                                -95.8040242 37.5929338, 
                                -102.8344151 53.3349629, 
                                -127.5286633 48.3485664, 
                                -129.4620208 38.0786067, 
                                -118.7406746 32.7853769, 
                                -118.6527948 32.7114894 
                            ))",
                            // Specify the relation between the WKT shape and the documents spatial data
                            SpatialRelation::within(),
                        ); })
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_3_2
                // This query will return all matching company entities
                // that are located within the specified polygon.

                // Define a dynamic query on Companies collection
                /** @var array<Company> $companiesWithinShape */
                $companiesWithinShape = $session->advanced()
                    ->documentQuery(Company::class)
                    // Call 'Spatial' method
                    ->spatial(
                        // Call 'Point'
                        // Pass the path to the document fields containing the spatial data
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Set the geographical search criteria, call 'RelatesToShape'
                        function($criteria) { return $criteria->relatesToShape(
                        // Specify the WKT string
                            "POLYGON ((
                                -118.6527948 32.7114894, 
                                -95.8040242 37.5929338, 
                                -102.8344151 53.3349629, 
                                -127.5286633 48.3485664, 
                                -129.4620208 38.0786067, 
                                -118.7406746 32.7853769, 
                                -118.6527948 32.7114894 
                            ))",
                            // Specify the relation between the WKT shape and the documents spatial data
                            SpatialRelation::within(),
                        ); })
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_4
                // Return all matching employee entities located within 20 kilometers radius
                // from point (47.623473 latitude, -122.3060097 longitude).

                // Sort the results by their distance from a specified point,
                // the closest results will be listed first.

                /** @var array<Employee> $employeesSortedByDistance */
                $employeesSortedByDistance = $session
                    ->query(Employee::class)
                    // Provide the query criteria:
                    ->spatial(
                        new PointField(
                            'Address.Location.Latitude',
                            'Address.Location.Longitude'
                        ),
                        function($criteria) { return $criteria->withinRadius(20, 47.623473, -122.3060097); }
                    )
                    // Call 'OrderByDistance'
                    ->orderByDistance(
                        new PointField(
                            'Address.Location.Latitude',
                            'Address.Location.Longitude'
                        ),
                       // Sort the results by their distance from this point:
                        47.623473,
                        -122.3060097
                    )
                    ->toList();
                #endregion

                #region spatial_4_getDistance
                // Get the distance of the results:
                // ================================

                // Call 'GetMetadataFor', pass an entity from the resulting employees list
                $metadata = $session->advanced()->getMetadataFor($employeesSortedByDistance[0]);

                // The distance is available in the '@spatial' metadata property
                $spatialResults = $metadata[DocumentsMetadata::SPATIAL_RESULT];

                $distance = $spatialResults["Distance"];   // The distance of the entity from the queried location
                $latitude = $spatialResults["Latitude"];   // The entity's longitude value
                $longitude = $spatialResults["Longitude"]; // The entity's longitude value
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_4_2
                // Return all matching employee entities located within 20 kilometers radius
                // from point (47.623473 latitude, -122.3060097 longitude).

                // Sort the results by their distance from a specified point,
                // the closest results will be listed first.

                /** @var array<Employee> $employeesSortedByDistance */
                $employeesSortedByDistance = $session->advanced()
                    ->documentQuery(Employee::class)
                     // Provide the query criteria:
                    ->spatial(
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                         function($criteria) { return $criteria->withinRadius(20, 47.623473, -122.3060097); })
                     // Call 'OrderByDistance'
                    ->orderByDistance(
                        // Pass the path to the document fields containing the spatial data
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Sort the results by their distance from this point:
                        47.623473, -122.3060097)
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_5
                // Return all employee entities sorted by their distance from a specified point.
                // The farthest results will be listed first.

                /** @var array<Employee> $employeesSortedByDistanceDesc */
                $employeesSortedByDistanceDesc = $session
                    ->query(Employee::class)
                     // Call 'OrderByDistanceDescending'
                    ->orderByDistanceDescending(
                        // Pass the path to the document fields containing the spatial data
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Sort the results by their distance (descending) from this point:
                        47.623473, -122.3060097)
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_5_2
                // Return all employee entities sorted by their distance from a specified point.
                // The farthest results will be listed first.

                /** @var array<Employee> $employeesSortedByDistanceDesc */
                $employeesSortedByDistanceDesc = $session->advanced()
                    ->documentQuery(Employee::class)
                     // Call 'OrderByDistanceDescending'
                    ->orderByDistanceDescending(
                        // Pass the path to the document fields containing the spatial data
                        new PointField('Address.Location.Latitude', 'Address.Location.Longitude'),
                        // Sort the results by their distance (descending) from this point:
                        47.623473, -122.3060097)
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_6
                // Return all employee entities.
                // Results are sorted by their distance to a specified point rounded to the nearest 100 km interval.
                // A secondary sort can be applied within the 100 km range, e.g. by field LastName.

                /** @var array<Employee> $employeesSortedByRoundedDistance */
                $employeesSortedByRoundedDistance = $session
                    ->query(Employee::class)
                     // Call 'OrderByDistance'
                    ->orderByDistance(
                        // Pass the path to the document fields containing the spatial data
                         (new PointField(
                            'Address.Location.Latitude',
                            'Address.Location.Longitude'
                        // Round up distance to 100 km
                        ))->roundTo(100),
                        // Sort the results by their distance from this point:
                        47.623473, -122.3060097)
                    // A secondary sort can be applied
                    ->orderBy("LastName")
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region spatial_6_2
                // Return all employee entities.
                // Results are sorted by their distance to a specified point rounded to the nearest 100 km interval.
                // A secondary sort can be applied within the 100 km range, e.g. by field LastName.

                /** @var array<Employee> $employeesSortedByRoundedDistance */
                $employeesSortedByRoundedDistance = $session->advanced()
                    ->documentQuery(Employee::class)
                     // Call 'OrderByDistance'
                    ->orderByDistance(
                        (new PointField(
                            'Address.Location.Latitude',
                            'Address.Location.Longitude'
                         ))
                         // Round up distance to 100 km
                        ->RoundTo(100),
                        // Sort the results by their distance from this point:
                        47.623473, -122.3060097)
                     // A secondary sort can be applied
                    ->orderBy("LastName")
                    ->toList();
                #endregion
            } finally {
                $session->close();
            }
        } finally {
            $store->close();
        }
    }
}


interface IFoo
{
#region spatial_7
    public function spatial(string|DynamicSpatialField $field, Closure $clause): DocumentQueryInterface;

    // Call examples:
    // ->spatial(new PointField("latitude", "longitude"), function($f) { return $f->withinRadius(1000, 10, 10); })
    // ->spatial("coordinates", function($f) { return $f->withinRadius(5, 38.9103000, -77.3942); })
    // ->spatial("shape", function($x) use ($rectangle1) { return $x->intersects($rectangle1); })
    // ->spatial("WKT", function($f) { return $f->relatesToShape("LINESTRING (1 0, 1 1, 1 2)", SpatialRelation::intersects()); })
    // ->spatial(new WktField("shapeWkt"), function($f) { return $f->withinRadius(10, 10, 20); });
#endregion

/*
#region spatial_8
    class PointField {
        public function __construct(?string $latitude, ?string $longitude) {}
    }

    class WktField {
        public function __construct(?string $wktPath) {}
    }
#endregion
*/

    #region spatial_9
    public function relatesToShape(?string $shapeWkt, ?SpatialRelation $relation, ?SpatialUnits $units = null, float $distErrorPercent = DocumentsIndexingSpatial::DEFAULT_DISTANCE_ERROR_PCT): SpatialCriteria;
    public function intersects(?string $shapeWkt, ?SpatialUnits $units = null, float $distErrorPercent = DocumentsIndexingSpatial::DEFAULT_DISTANCE_ERROR_PCT): SpatialCriteria;
    public function contains(?string $shapeWkt, ?SpatialUnits $units = null, float $distErrorPercent = DocumentsIndexingSpatial::DEFAULT_DISTANCE_ERROR_PCT): SpatialCriteria;
    public function disjoint(?string $shapeWkt, ?SpatialUnits $units = null, float $distErrorPercent = DocumentsIndexingSpatial::DEFAULT_DISTANCE_ERROR_PCT): SpatialCriteria;
    public function within(?string $shapeWkt, ?SpatialUnits $units = null, float $distErrorPercent = DocumentsIndexingSpatial::DEFAULT_DISTANCE_ERROR_PCT): SpatialCriteria;
    public function withinRadius(float $radius, float $latitude, float $longitude, ?SpatialUnits $radiusUnits = null, float $distErrorPercent = DocumentsIndexingSpatial::DEFAULT_DISTANCE_ERROR_PCT): SpatialCriteria;

#endregion

#region spatial_10
    function orderByDistance(DynamicSpatialField|string $field, float|string $latitudeOrShapeWkt, ?float $longitude = null, float $roundFactor = 0): DocumentQueryInterface;
#endregion

#region spatial_11
    function orderByDistanceDescending(DynamicSpatialField|string $field, float|string $latitudeOrShapeWkt, ?float $longitude = null, float $roundFactor = 0): DocumentQueryInterface;
#endregion
}
