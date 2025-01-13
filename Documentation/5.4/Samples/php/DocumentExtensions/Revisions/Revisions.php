<?php

namespace RavenDB\Samples\DocumentExtensions\Revisions;

use DateInterval;
use DateTime;
use RavenDB\Constants\DocumentsMetadata;
use RavenDB\Documents\DocumentStore;
use RavenDB\Documents\Operations\Revisions\ConfigureRevisionsOperation;
use RavenDB\Documents\Operations\Revisions\RevisionsCollectionConfiguration;
use RavenDB\Documents\Operations\Revisions\RevisionsConfiguration;
use RavenDB\Json\MetadataAsDictionary;
use RavenDB\Samples\Infrastructure\Orders\Address;
use RavenDB\Samples\Infrastructure\Orders\Contact;
use RavenDB\Type\Duration;

class Revisions
{
    public function sample(): void
    {
        $store = new DocumentStore();
        try {
            #region configuration
            $revisionsConfiguration = new RevisionsConfiguration();

            $config = new RevisionsCollectionConfiguration();
            $config->setDisabled(false);
            $config->setPurgeOnDelete(false);
            $config->setMinimumRevisionsToKeep(5);
            $config->setMinimumRevisionAgeToKeep(Duration::ofDays(14));

            $revisionsConfiguration->setDefaultConfig($config);

            $collections = [];

            $configUsers = new RevisionsCollectionConfiguration();
            $configUsers->setDisabled(true);
            $collections['Users'] =  $configUsers;

            $configOrders = new RevisionsCollectionConfiguration();
            $configOrders->setDisabled(false);
            $collections['Orders'] = $configOrders;

            $revisionsConfiguration->setCollections($collections);

            $store->maintenance()->send(new ConfigureRevisionsOperation($revisionsConfiguration));
            #endregion

            #region store
            $session = $store->openSession();
            try {
                $user = new User();
                $user->setName("Ayende Rahien");
                $session->store($user);

                $session->saveChanges();
            } finally {
                $session->close();
            }
            #endregion

            $loan = new Loan();
            $loan->setId('loans/1');

            $session = $store->openSession();
            try {
                #region get_revisions
                // Get all the revisions that were created for a document, by document ID
                /** @var array<User> $revisions */
                $revisions = $session
                    ->advanced()
                    ->revisions()
                    ->getFor(User::class, "users/1", start: 0, pageSize: 25);

                // Get revisions metadata
                /** @var array<MetadataAsDictionary> $revisionsMetadata */
                $revisionsMetadata = $session
                    ->advanced()
                    ->revisions()
                    ->getMetadataFor("users/1", start: 0, pageSize: 25);

                // Get revisions by their change vectors
                $revison = $session
                    ->advanced()
                    ->revisions()
                    ->get(User::class, $revisionsMetadata[0]->getString(DocumentsMetadata::CHANGE_VECTOR));

                // Get a revision by its creation time
                // If no revision was created at that precise time, get the first revision to precede it
                $revisonAtYearAgo = $session
                    ->advanced()
                    ->revisions()
                    ->getBeforeDate(User::class, "users/1", (new DateTime())->sub(new DateInterval("P1Y")));
                #endregion
            } finally {
                $session->close();
            }
        } finally {
            $store->close();
        }
    }

    public function forceRevisionCreationForSample(): void
    {
        $store = new DocumentStore();
        try {
            $companyId = null;
            $session = $store->openSession();
            try {
                #region ForceRevisionCreationByEntity
                // Force revision creation by entity
                $company = new Company();
                $company->setName("CompanyProfile");

                $session->store($company);
                $companyId = $company->getId();
                $session->saveChanges();

                // Forcing the creation of a revision by entity can be performed
                // only when the entity is tracked, after the document is stored.
                $session->advanced()->revisions()->forceRevisionCreationFor(Company::class, $company);
                #endregion
            } finally {
                $session->close();
            }

            $session = $store->openSession();
            try {
                #region ForceRevisionCreationByID
                // Force revision creation by ID
                $session->advanced()->revisions()->forceRevisionCreationFor($companyId);
                $session->saveChanges();
                #endregion

                $revisionsCount = count($session->advanced()->revisions()->getFor(Company::class, $companyId));
            } finally {
                $session->close();
            }
        } finally {
            $store->close();
        }
    }
}

class User
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

class Loan
{
    private ?string $id = null;

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setId(?string $id): void
    {
        $this->id = $id;
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

