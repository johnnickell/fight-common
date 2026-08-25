<?php

declare(strict_types=1);

namespace Symfony\Component\EventDispatcher {
    interface EventSubscriberInterface
    {
        /** @return array<string, string> */
        public static function getSubscribedEvents(): array;
    }
}

namespace Symfony\Component\HttpFoundation {
    class Request
    {
    }

    class Response
    {
        public const int HTTP_INTERNAL_SERVER_ERROR = 500;
    }
}

namespace Symfony\Component\HttpKernel {
    interface HttpKernelInterface
    {
        public const int MAIN_REQUEST = 1;
    }

    interface TerminableInterface
    {
    }

    final class KernelEvents
    {
        public const string EXCEPTION = 'kernel.exception';
        public const string CONTROLLER = 'kernel.controller';
    }
}

namespace Symfony\Component\HttpKernel\Event {
    final class ExceptionEvent
    {
    }

    final class ControllerEvent
    {
    }
}

namespace Symfony\Component\HttpKernel\Exception {
    interface HttpExceptionInterface
    {
        public function getStatusCode(): int;
    }
}

namespace Symfony\Component\Filesystem {
    class Filesystem
    {
    }
}

namespace Symfony\Component\Filesystem\Exception {
    class IOException extends \RuntimeException
    {
        public function getPath(): ?string
        {
            return null;
        }
    }
}

namespace Symfony\Component\Routing\Generator {
    interface UrlGeneratorInterface
    {
        public const int ABSOLUTE_PATH = 1;
        public const int ABSOLUTE_URL = 0;
    }
}

namespace Symfony\Component\Messenger\Transport\Serialization {
    interface SerializerInterface
    {
    }
}

namespace Symfony\Component\Messenger {
    final class Envelope
    {
        public function __construct(object $message, array $stamps = [])
        {
        }
    }
}

namespace Symfony\Component\Messenger\Transport\Sender {
    use Symfony\Component\Messenger\Envelope;

    interface SenderInterface
    {
        public function send(Envelope $envelope): Envelope;
    }
}

namespace Symfony\Component\Messenger\Exception {
    class MessageDecodingFailedException extends \RuntimeException
    {
    }
}
