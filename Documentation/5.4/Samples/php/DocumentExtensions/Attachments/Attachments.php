<?php

namespace RavenDB\Samples\DocumentExtensions\Attachments;

use RavenDB\Documents\DocumentStore;
use RavenDB\Documents\Operations\Attachments\AttachmentName;
use RavenDB\Documents\Operations\Attachments\AttachmentNameArray;
use RavenDB\Documents\Operations\Attachments\CloseableAttachmentResult;
use RavenDB\ServerWide\DatabaseRecord;
use RavenDB\ServerWide\Operations\DeleteDatabaseCommandParameters;
use RavenDB\ServerWide\Operations\DeleteDatabasesOperation;
use RavenDB\ServerWide\Operations\CreateDatabaseOperation;
use RavenDB\Type\StringArray;

interface IFoo
{
    #region StoreSyntax
    public function store(object|string $idOrEntity, ?string $name, mixed $stream, ?string $contentType = null): void;

    public function storeFile(object|string $idOrEntity, ?string $name, string $filePath): void;
    #endregion

    #region GetSyntax
    function get(object|string $idOrEntity, ?string $name): CloseableAttachmentResult;
    function getNames(?object $entity): AttachmentNameArray;
    function exists(?string $documentId, ?string $name): bool;
    function getRevision(?string $documentId, ?string $name, ?string $changeVector): CloseableAttachmentResult;
    #endregion

    // REEB note: async sessions are not supported in PHP
//    #region GetSyntaxAsync
//    Task<AttachmentResult> GetAsync(string documentId, string name, CancellationToken token = default);
//    Task<AttachmentResult> GetAsync(object entity, string name, CancellationToken token = default);
//    Task<IEnumerator<AttachmentEnumeratorResult>> GetAsync(IEnumerable<AttachmentRequest> attachments, CancellationToken token = default);
//    Task<AttachmentResult> GetRevisionAsync(string documentId, string name, string changeVector, CancellationToken token = default);
//    Task<bool> ExistsAsync(string documentId, string name, CancellationToken token = default);
//    #endregion

    #region DeleteSyntax
    /**
     * Marks the specified document's attachment for deletion.
     * The attachment will be deleted when saveChanges is called.
     */
    public function delete(object|string $idOrEntity, ?string $name): void;
    #endregion
}


class Attachments
{
    public function storeAttachment(): void
    {
        $store = new DocumentStore();
        try {

        #region StoreAttachment
            $session = $store->openSession();
            try {
                $file1 = file_get_contents("001.jpg");
                $file2 = file_get_contents("002.jpg");
                $file3 = file_get_contents("003.jpg");
                $file4 = file_get_contents("004.mp4");

                $album = new Album();
                $album->setName("Holidays");
                $album->setDescription("Holidays travel pictures of the all family");
                $album->setTags(["Holidays Travel", "All Family"]);

                $session->store($album, "albums/1");

                $session->advanced()->attachments()->store("albums/1", "001.jpg", $file1, "image/jpeg");
                $session->advanced()->attachments()->store("albums/1", "002.jpg", $file2, "image/jpeg");
                $session->advanced()->attachments()->store("albums/1", "003.jpg", $file3, "image/jpeg");
                $session->advanced()->attachments()->store("albums/1", "004.mp4", $file4, "video/mp4");

                $session->saveChanges();
            } finally {
                $session->close();
            }
            #endregion

        } finally {
            $store->close();
        }
    }

    // REEB note: async sessions are not supported in PHP

//        public async Task StoreAttachmentAsync()
//        {
//            using (var store = new DocumentStore())
//            {
//                #region StoreAttachmentAsync
//                using (var asyncSession = store.OpenAsyncSession())
//                using (var file1 = File.Open("001.jpg", FileMode.Open))
//                using (var file2 = File.Open("002.jpg", FileMode.Open))
//                using (var file3 = File.Open("003.jpg", FileMode.Open))
//                using (var file4 = File.Open("004.mp4", FileMode.Open))
//                {
//                    var album = new Album
//                    {
//                        Name = "Holidays",
//                        Description = "Holidays travel pictures of the all family",
//                        Tags = new[] { "Holidays Travel", "All Family" },
//                    };
//                    await asyncSession.StoreAsync(album, "albums/1");
//
//                    asyncSession.Advanced.Attachments.Store("albums/1", "001.jpg", file1, "image/jpeg");
//                    asyncSession.Advanced.Attachments.Store("albums/1", "002.jpg", file2, "image/jpeg");
//                    asyncSession.Advanced.Attachments.Store("albums/1", "003.jpg", file3, "image/jpeg");
//                    asyncSession.Advanced.Attachments.Store("albums/1", "004.mp4", file4, "video/mp4");
//
//                    await asyncSession.SaveChangesAsync();
//                }
//                #endregion
//            }
//        }

    public function GetAttachment(): void
    {
        $store = new DocumentStore();
        try {
            #region GetAttachment
            $session = $store->openSession();
            try {
                $album = $session->load(Album::class, "albums/1");

                $file1 = $session->advanced()->attachments()->get($album, "001.jpg");
                $file2 = $session->advanced()->attachments()->get("albums/1", "002.jpg");

                $data = $file1->getData();

                $attachmentDetails = $file1->getDetails();
                $name = $attachmentDetails->getName();
                $contentType = $attachmentDetails->getContentType();
                $hash = $attachmentDetails->getHash();
                $size = $attachmentDetails->getSize();
                $documentId = $attachmentDetails->getDocumentId();
                $changeVector = $attachmentDetails->getChangeVector();

                $attachmentNames = $session->advanced()->attachments()->getNames($album);
                /** @var AttachmentName $attachmentName */
                foreach ($attachmentNames as $attachmentName)
                {
                    $name = $attachmentName->getName();
                    $contentType = $attachmentName->getContentType();
                    $hash = $attachmentName->getHash();
                    $size = $attachmentName->getSize();
                }

                $exists = $session->advanced()->attachments()->exists("albums/1", "003.jpg");
            } finally {
                $session->close();
            }
            #endregion
        } finally {
            $store->close();
        }
    }

    // REEB note: async sessions are not supported in PHP

//        public async Task GetAttachmentAsync()
//        {
//            using (var store = new DocumentStore())
//            {
//                #region GetAttachmentAsync
//                using (var asyncSession = store.OpenAsyncSession())
//                {
//                    Album album = await asyncSession.LoadAsync<Album>("albums/1");
//
//                    using (AttachmentResult file1 = await asyncSession.Advanced.Attachments.GetAsync(album, "001.jpg"))
//                    using (AttachmentResult file2 = await asyncSession.Advanced.Attachments.GetAsync("albums/1", "002.jpg"))
//                    {
//                        Stream stream = file1.Stream;
//
//                        AttachmentDetails attachmentDetails = file1.Details;
//                        string name = attachmentDetails.Name;
//                        string contentType = attachmentDetails.ContentType;
//                        string hash = attachmentDetails.Hash;
//                        long size = attachmentDetails.Size;
//                        string documentId = attachmentDetails.DocumentId;
//                        string changeVector = attachmentDetails.ChangeVector;
//                    }
//
//                    AttachmentName[] attachmentNames = asyncSession.Advanced.Attachments.GetNames(album);
//                    foreach (AttachmentName attachmentName in attachmentNames)
//                    {
//                        string name = attachmentName.Name;
//                        string contentType = attachmentName.ContentType;
//                        string hash = attachmentName.Hash;
//                        long size = attachmentName.Size;
//                    }
//
//                    bool exists = await asyncSession.Advanced.Attachments.ExistsAsync("albums/1", "003.jpg");
//                }
//                #endregion
//            }
//        }


    public function deleteAttachment(): void
    {
        $store = new DocumentStore();
        try {
            #region DeleteAttachment
            $session = $store->openSession();
            try {
                $album = $session->load(Album::class, "albums/1");
                $session->advanced()->attachments()->delete($album, "001.jpg");
                $session->advanced()->attachments()->delete("albums/1", "002.jpg");

                $session->saveChanges();
            } finally {
                $session->close();
            }
            #endregion
        } finally {
            $store->close();
        }
    }

    // REEB note: async sessions are not supported in PHP

//        public async Task DeleteAttachmentAsync()
//        {
//            using (var store = new DocumentStore())
//            {
//                #region DeleteAttachmentAsync
//                using (var asyncSession = store.OpenAsyncSession())
//                {
//                    Album album = await asyncSession.LoadAsync<Album>("albums/1");
//                    asyncSession.Advanced.Attachments.Delete(album, "001.jpg");
//                    asyncSession.Advanced.Attachments.Delete("albums/1", "002.jpg");
//
//                    await asyncSession.SaveChangesAsync();
//                }
//                #endregion
//            }
//        }

    // REEB note: multi get is not yet implemented in PHP,
    // and BULK insert is not even planned in PHP

//        // attachments multi-get
//        // BulkInsert.a few attachments and then get them with a single request
//        public async void AttachmentsMultiGet()
//        {
//            using (var store = getDocumentStore())
//            {
//                // Create documents to add attachments to
//                using (var session = store.OpenSession())
//                {
//                    var user1 = new User
//                    {
//                        Name = "Lilly",
//                        Age = 20
//                    };
//                    session.Store(user1);
//
//                    var user2 = new User
//                    {
//                        Name = "Betty",
//                        Age = 25
//                    };
//                    session.Store(user2);
//
//                    var user3 = new User
//                    {
//                        Name = "Robert",
//                        Age = 29
//                    };
//                    session.Store(user3);
//
//                    session.SaveChanges();
//                }
//
//                List<User> result;
//
//                using (var session = store.OpenSession())
//                {
//                    IRavenQueryable<User> query = session.Query<User>()
//                        .Where(u => u.Age < 30);
//
//                    result = query.ToList();
//                }
//
//                // Query for users younger than 30, add an attachment
//                using (var bulkInsert = store.BulkInsert())
//                {
//                    for (var user = 0; user < result.Count; user++)
//                    {
//                        byte[] byteArray = Encoding.UTF8.GetBytes("some contents here");
//                        var stream = new MemoryStream(byteArray);
//
//                        string userId = result[user].Id;
//                        var attachmentsFor = bulkInsert.AttachmentsFor(userId);
//
//                        for (var attNum = 0; attNum < 10; attNum++)
//                        {
//                            stream.Position = 0;
//                            await attachmentsFor.StoreAsync(result[user].Name + attNum, stream);
//                        }
//
//                    }
//                }
//
//                // attachments multi-get (sync)
//                using (var session = store.OpenSession())
//                {
//                    for (var userCnt = 0; userCnt < result.Count; userCnt++)
//                    {
//                        string userId = result[userCnt].Id;
//                        #region GetAllAttachments
//                        // Load a user profile
//                        var user = session.Load<User>(userId);
//
//                        // Get the names of files attached to this document
//                        IEnumerable<AttachmentRequest> attachmentNames = session.Advanced.Attachments.GetNames(user).Select(x => new AttachmentRequest(userId, x.Name));
//
//                        // Get the attached files
//                        IEnumerator<AttachmentEnumeratorResult> attachmentsEnumerator = session.Advanced.Attachments.Get(attachmentNames);
//
//                        // Go through the document's attachments
//                        while (attachmentsEnumerator.MoveNext())
//                        {
//                            AttachmentEnumeratorResult res = attachmentsEnumerator.Current;
//
//                            AttachmentDetails attachmentDetails = res.Details; // attachment details
//
//                            Stream attachmentStream = res.Stream; // attachment contents
//
//                            // In this case it is a string attachment, that can be decoded back to text
//                            var ms = new MemoryStream();
//                            attachmentStream.CopyTo(ms);
//                            string decodedStream = Encoding.UTF8.GetString(ms.ToArray());
//                        }
//                        #endregion
//                    }
//                }
//
//                // attachments multi-get (Async)
//                using (var session = store.OpenAsyncSession())
//                {
//                    for (var userCnt = 0; userCnt < result.Count; userCnt++)
//                    {
//                        string userId = result[userCnt].Id;
//                        #region GetAllAttachmentsAsync
//                        // Load a user profile
//                        var user = await session.LoadAsync<User>(userId);
//
//                        // Get the names of files attached to this document
//                        IEnumerable<AttachmentRequest> attachmentNames = session.Advanced.Attachments.GetNames(user).Select(x => new AttachmentRequest(userId, x.Name));
//
//                        // Get the attached files
//                        IEnumerator<AttachmentEnumeratorResult> attachmentsEnumerator = await session.Advanced.Attachments.GetAsync(attachmentNames);
//
//                        // Go through the document's attachments
//                        while (attachmentsEnumerator.MoveNext())
//                        {
//                            AttachmentEnumeratorResult res = attachmentsEnumerator.Current;
//
//                            AttachmentDetails attachmentDetails = res.Details; // attachment details
//
//                            Stream attachmentStream = res.Stream; // attachment contents
//
//                            // In this case it is a string attachment, that can be decoded back to text
//                            var ms = new MemoryStream();
//                            attachmentStream.CopyTo(ms);
//                            string decodedStream = Encoding.UTF8.GetString(ms.ToArray());
//                        }
//                        #endregion
//                    }
//                }
//
//            }
//        }

    public function getDocumentStore(): DocumentStore
    {
        $store = new DocumentStore(["http://localhost:8080"], "TestDatabase");
        $store->initialize();

        $parameters = new DeleteDatabaseCommandParameters();
        $parameters->setDatabaseNames(["TestDatabase"]);
        $parameters->setHardDelete(true);

        $store->maintenance()->server()->send(new DeleteDatabasesOperation($parameters));
        $store->maintenance()->server()->send(new CreateDatabaseOperation(new DatabaseRecord("TestDatabase")));

        return $store;
    }
}

class Album
{
    private ?string $id = null;
    private ?string $name = null;
    private ?string $description = null;
    private ?StringArray $tags = null;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getTags(): ?StringArray
    {
        return $this->tags;
    }

    public function setTags(StringArray|array|null $tags): void
    {
        if (is_array($tags)) {
            $tags = StringArray::fromArray($tags);
        }
        $this->tags = $tags;
    }
}

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
