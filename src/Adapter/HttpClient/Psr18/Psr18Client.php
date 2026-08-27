<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Psr18;

use Fight\Common\Adapter\HttpClient\Psr18\Exception\ClientException;
use Fight\Common\Adapter\HttpClient\Psr18\Exception\NetworkException;
use Fight\Common\Adapter\HttpClient\Psr18\Exception\RequestException;
use Fight\Common\Application\HttpClient\Exception\NetworkException as FightNetworkException;
use Fight\Common\Application\HttpClient\Exception\RequestException as FightRequestException;
use Fight\Common\Application\HttpClient\Exception\TransferException;
use Fight\Common\Application\HttpClient\Transport\HttpClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class Psr18Client
 */
final readonly class Psr18Client implements ClientInterface
{
    /**
     * Constructs Psr18Client
     */
    public function __construct(private HttpClient $httpClient)
    {
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        try {
            return $this->httpClient->send($request);
        } catch (FightNetworkException $exception) {
            throw new NetworkException($exception->getMessage(), $exception->getRequest(), $exception);
        } catch (FightRequestException $exception) {
            throw new RequestException($exception->getMessage(), $exception->getRequest(), $exception);
        } catch (TransferException $exception) {
            throw new ClientException($exception->getMessage(), 0, $exception);
        }
    }
}
