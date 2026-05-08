<?php

declare(strict_types=1);

namespace Fight\Common\Domain;

use Fight\Common\Domain\Collection\ArrayList;
use Fight\Common\Domain\Collection\ArrayQueue;
use Fight\Common\Domain\Collection\ArrayStack;
use Fight\Common\Domain\Collection\HashSet;
use Fight\Common\Domain\Collection\HashTable;
use Fight\Common\Domain\Value\Basic\JsonObject;
use Fight\Common\Domain\Value\Basic\MbStringObject;
use Fight\Common\Domain\Value\Basic\StringObject;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Domain\Value\Internet\EmailAddress;
use Fight\Common\Domain\Value\Internet\Uri;
use Fight\Common\Domain\Value\Internet\Url;

function string(string $value): StringObject
{
    return StringObject::create($value);
}

function mb_string(string $value): MbStringObject
{
    return MbStringObject::create($value);
}

function json_string(string $value): JsonObject
{
    return JsonObject::fromString($value);
}

function json_data(mixed $data): JsonObject
{
    return JsonObject::fromData($data);
}

function uuid(?bool $msb = true): Uuid
{
    return Uuid::comb($msb);
}

function uri(string $uri): Uri
{
    return Uri::fromString($uri);
}

function url(string $url): Url
{
    return Url::fromString($url);
}

function email(string $address): EmailAddress
{
    return EmailAddress::fromString($address);
}

function array_list(array $items, ?string $type = null): ArrayList
{
    return ArrayList::of($type)->replace($items);
}

function hash_set(array $items, ?string $type = null): HashSet
{
    $set = HashSet::of($type);

    foreach ($items as $item) {
        $set->add($item);
    }

    return $set;
}

function hash_table(array $entries, ?string $keyType = null, ?string $valueType = null): HashTable
{
    $table = HashTable::of($keyType, $valueType);

    foreach ($entries as $key => $value) {
        $table->set($key, $value);
    }

    return $table;
}

function array_stack(array $items, ?string $type = null): ArrayStack
{
    $stack = ArrayStack::of($type);

    foreach ($items as $item) {
        $stack->push($item);
    }

    return $stack;
}

function array_queue(array $items, ?string $type = null): ArrayQueue
{
    $queue = ArrayQueue::of($type);

    foreach ($items as $item) {
        $queue->enqueue($item);
    }

    return $queue;
}
