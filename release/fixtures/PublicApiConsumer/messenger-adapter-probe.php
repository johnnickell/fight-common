<?php

declare(strict_types=1);

require $argv[1];
require $argv[2];

$paths = [
    'Fight\\Common\\Adapter\\Messaging\\Handler\\CommandMessageHandler',
    'Fight\\Common\\Adapter\\Messaging\\Handler\\EventMessageHandler',
    'Fight\\Common\\Adapter\\Messaging\\Symfony\\MessengerCommandBus',
    'Fight\\Common\\Adapter\\Messaging\\Symfony\\MessengerEventDispatcher',
    'Fight\\Common\\Adapter\\Messaging\\Symfony\\Serializer\\SymfonyMessageSerializer',
    'Fight\\Common\\Adapter\\Messaging\\Handler\\SymfonyCommandMessageHandler',
    'Fight\\Common\\Adapter\\Messaging\\Handler\\SymfonyEventMessageHandler',
    'Fight\\Common\\Adapter\\Messaging\\Command\\Async\\MessengerCommandBus',
    'Fight\\Common\\Adapter\\Messaging\\Event\\Async\\MessengerEventDispatcher',
    'Fight\\Common\\Adapter\\Messaging\\Serializer\\SymfonyMessageSerializer'
];

foreach ($paths as $path) {
    class_exists($path) || throw new RuntimeException(sprintf('Messenger adapter path is unavailable: %s', $path));
}

foreach (
    [
    'Fight\\Common\\Adapter\\Messaging\\Handler\\CommandMessageHandler',
    'Fight\\Common\\Adapter\\Messaging\\Handler\\SymfonyCommandMessageHandler'
    ] as $handler
) {
    $parameter = (new ReflectionMethod($handler, '__invoke'))->getParameters()[0]->getType();
    $parameter instanceof ReflectionNamedType
        && $parameter->getName() === 'Fight\\Common\\Domain\\Messaging\\Command\\CommandMessage'
        || throw new RuntimeException(sprintf('Messenger command handler contract changed: %s', $handler));
}

foreach (
    [
    'Fight\\Common\\Adapter\\Messaging\\Handler\\EventMessageHandler',
    'Fight\\Common\\Adapter\\Messaging\\Handler\\SymfonyEventMessageHandler'
    ] as $handler
) {
    $parameter = (new ReflectionMethod($handler, '__invoke'))->getParameters()[0]->getType();
    $parameter instanceof ReflectionNamedType
        && $parameter->getName() === 'Fight\\Common\\Domain\\Messaging\\Event\\EventMessage'
        || throw new RuntimeException(sprintf('Messenger event handler contract changed: %s', $handler));
}
