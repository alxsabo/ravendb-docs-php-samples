<?php

namespace RavenDB\Samples\ClientApi\Operations\Server;

use RavenDB\Documents\DocumentStore;
use RavenDB\Documents\Operations\Operation;

class Compact
{
    public function samples(): void
    {
        $documentStore = new DocumentStore();
//        try {
//            #region compact_0
//
//            // Define the compact settings
//            $settings = new CompactSettings();
//            $settings->setDatabaseName("Northwind");
//            // Set 'Documents' to true to compact all documents in database
//            // Indexes are not set and will not be compacted
//            $settings->setDocuments(true);
//
//
//            // Define the compact operation, pass the settings
//            /** @var IServerOperation<OperationIdResult>  $compactOp */
//            $compactOp = new CompactDatabaseOperation($settings);
//
//            // Execute compaction by passing the operation to Maintenance.Server.Send
//            /** @var Operation $operation */
//            $operation = $documentStore->maintenance()->server()->send($compactOp);
//
//            // Wait for operation to complete, during compaction the database is offline
//            $operation->waitForCompletion();
//            #endregion
//        } finally {
//            $documentStore->close();
//        }

//        using (var documentStore = new DocumentStore())
//        {
//            #region compact_1
//            // Define the compact settings
//            CompactSettings settings = new CompactSettings
//            {
//                // Database to compact
//                DatabaseName = "Northwind",
//
//                // Setting 'Documents' to false will compact only the specified indexes
//                Documents = false,
//
//                // Specify which indexes to compact
//                Indexes = new[] { "Orders/Totals", "Orders/ByCompany" },
//
//                // Optimize indexes is Lucene's feature to gain disk space and efficiency
//                // Set whether to skip this optimization when compacting the indexes
//                SkipOptimizeIndexes = false
//            };
//
//            // Define the compact operation, pass the settings
//            IServerOperation<OperationIdResult> compactOp = new CompactDatabaseOperation(settings);
//
//            // Execute compaction by passing the operation to Maintenance.Server.Send
//            Operation operation = documentStore.Maintenance.Server.Send(compactOp);
//            // Wait for operation to complete
//            operation.WaitForCompletion();
//            #endregion
//        }
//
//        using (var documentStore = new DocumentStore())
//        {
//            #region compact_2
//            // Get all indexes names in the database using the 'GetIndexNamesOperation' operation
//            // Use 'ForDatabase' if the target database is different than the default database defined on the store
//            string[] allIndexNames =
//                documentStore.Maintenance.ForDatabase("Northwind")
//                    .Send(new GetIndexNamesOperation(0, int.MaxValue));
//
//            // Define the compact settings
//            CompactSettings settings = new CompactSettings
//            {
//                DatabaseName = "Northwind", // Database to compact
//
//                Documents = true,           // Compact all documents
//
//                Indexes = allIndexNames,    // All indexes will be compacted
//
//                SkipOptimizeIndexes = true  // Skip Lucene's indexes optimization
//            };
//
//            // Define the compact operation, pass the settings
//            IServerOperation<OperationIdResult> compactOp = new CompactDatabaseOperation(settings);
//
//            // Execute compaction by passing the operation to Maintenance.Server.Send
//            Operation operation = documentStore.Maintenance.Server.Send(compactOp);
//            // Wait for operation to complete
//            operation.WaitForCompletion();
//            #endregion
//        }
//
//        using (var documentStore = new DocumentStore())
//        {
//            #region compact_3
//            // Get all member nodes in the database-group using the 'GetDatabaseRecordOperation' operation
//            List<string> allMemberNodes =
//                documentStore.Maintenance.Server.Send(new GetDatabaseRecordOperation("Northwind"))
//                    .Topology.Members;
//
//            // Define the compact settings as needed
//            CompactSettings settings = new CompactSettings
//            {
//                // Database to compact
//                DatabaseName = "Northwind",
//
//                //Compact all documents in database
//                Documents = true
//            };
//
//            // Execute the compact operation on each member node
//            foreach (string nodeTag in allMemberNodes)
//            {
//                // Define the compact operation, pass the settings
//                IServerOperation<OperationIdResult> compactOp = new CompactDatabaseOperation(settings);
//
//                // Execute the operation on a specific node
//                // Use `ForNode` to specify the node to operate on
//                Operation operation = documentStore.Maintenance.Server.ForNode(nodeTag).Send(compactOp);
//                // Wait for operation to complete
//                operation.WaitForCompletion();
//            }
//            #endregion
//        }
    }
}

/*
private interface IFoo
{
    #region syntax
    CompactDatabaseOperation(?CompactSettings $compactSettings)
    #endregion
}
*/
