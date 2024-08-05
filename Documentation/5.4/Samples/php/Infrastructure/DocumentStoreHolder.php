<?php

namespace RavenDB\Samples\Infrastructure;

use RavenDB\Documents\DocumentStore;
use RavenDB\Extensions\PropertyNamingStrategy;

class DocumentStoreHolder
{
    public static ?DocumentStore $store = null;

    public static ?DocumentStore $mediaStore = null;

    public static function getStore(): DocumentStore
    {
        if (self::$store == null) {
            $url = self::getDatabaseUrl();
            $database = self::getDatabase();

            // replace params below with whatever you run with...
            self::$store = new DocumentStore($url, $database);

            // Since we target the Sample Data,
            // must use the below to convert between the camelCase Java classes props and the PascalCase json documents.
            self::$store->getConventions()->getEntityMapper()->setPropertyNamingStrategy(PropertyNamingStrategy::DotNetNamingStrategy());

            self::$store->initialize();
        }

        return self::$store;
    }

    public static function getMediaStore(): DocumentStore
    {
        if (self::$mediaStore == null) {
            $url = self::getDatabaseUrl();
            $mediaDatabase = self::getMediaDatabase();

            self::$mediaStore = new DocumentStore($url, $mediaDatabase);
            self::$mediaStore->getConventions()->getEntityMapper()->setPropertyNamingStrategy(PropertyNamingStrategy::DotNetNamingStrategy());
            self::$mediaStore->initialize();
        }

        return self::$mediaStore;
    }

    private static function getDatabaseUrl(): string
    {
        return self::getEnvVariable('DATABASE_URL', 'http://localhost:8080');
    }

    private static function getDatabase(): string
    {
        return self::getEnvVariable('DATABASE_NAME', 'example');
    }

    private static function getMediaDatabase(): string
    {
        return self::getEnvVariable('DATABASE_MEDIA', 'media');
    }

    private static function getEnvVariable($variableName, $defaultValue = null): string
    {
        $value = getenv($variableName);

        if (empty($value)) {
            return $defaultValue;
        }

        return $value;
    }
}
