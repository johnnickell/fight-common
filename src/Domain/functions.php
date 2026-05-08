<?php

declare(strict_types=1);

namespace Fight\Common\Domain;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Collection\ArrayQueue;
use Fight\Common\Domain\Collection\ArrayStack;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Common\Domain\Value\Basic\MbStringObject;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Fight\Common\Domain\Value\Internet\Uri;
use Fight\Common\Domain\Value\Internet\Url;

/**
 * Creates a StringObject instance from a string value
 */
function string(string $value): StringObject
{
    return StringObject::create($value);
}

/**
 * Creates an MbStringObject instance from a string value
 */
function mb_string(string $value): MbStringObject
{
    return MbStringObject::create($value);
}

/**
 * Creates a JsonObject instance from a JSON string
 *
 * @throws DomainException When the string is not valid JSON
 */
function json_string(string $value): JsonObject
{
    return JsonObject::fromString($value);
}

/**
 * Creates a JsonObject instance from a data value
 *
 * @throws DomainException When the data is not JSON encodable
 */
function json_data(mixed $data): JsonObject
{
    return JsonObject::fromData($data);
}

/**
 * Creates a COMB UUID instance
 */
function uuid(?bool $msb = true): Uuid
{
    return Uuid::comb($msb);
}

/**
 * Creates a Uri instance from a URI string
 *
 * @throws DomainException When the URI is not valid
 */
function uri(string $uri): Uri
{
    return Uri::fromString($uri);
}

/**
 * Creates a Url instance from a URL string
 *
 * @throws DomainException When the URL is not valid
 */
function url(string $url): Url
{
    return Url::fromString($url);
}

/**
 * Creates an EmailAddress instance from an email string
 *
 * @throws DomainException When the email address is not valid
 */
function email(string $address): EmailAddress
{
    return EmailAddress::fromString($address);
}

/**
 * Creates a typed ArrayList from an array of items
 */
function array_list(array $items, ?string $type = null): ArrayList
{
    return ArrayList::of($type)->replace($items);
}

/**
 * Creates a typed HashSet from an array of items
 */
function hash_set(array $items, ?string $type = null): HashSet
{
    $set = HashSet::of($type);

    foreach ($items as $item) {
        $set->add($item);
    }

    return $set;
}

/**
 * Creates a typed HashTable from an array of key-value entries
 */
function hash_table(array $entries, ?string $keyType = null, ?string $valueType = null): HashTable
{
    $table = HashTable::of($keyType, $valueType);

    foreach ($entries as $key => $value) {
        $table->set($key, $value);
    }

    return $table;
}

/**
 * Creates a typed ArrayStack from an array of items
 */
function array_stack(array $items, ?string $type = null): ArrayStack
{
    $stack = ArrayStack::of($type);

    foreach ($items as $item) {
        $stack->push($item);
    }

    return $stack;
}

/**
 * Creates a typed ArrayQueue from an array of items
 */
function array_queue(array $items, ?string $type = null): ArrayQueue
{
    $queue = ArrayQueue::of($type);

    foreach ($items as $item) {
        $queue->enqueue($item);
    }

    return $queue;
}
