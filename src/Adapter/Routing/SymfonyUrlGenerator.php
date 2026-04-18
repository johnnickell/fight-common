<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Routing;

use Fight\Common\Application\Routing\Exception\InvalidParameterException;
use Fight\Common\Application\Routing\Exception\MissingParametersException;
use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use Symfony\Component\Routing\Exception\InvalidParameterException as ParameterException;
use Symfony\Component\Routing\Exception\MissingMandatoryParametersException as MissingException;
use Symfony\Component\Routing\Exception\RouteNotFoundException as RouteException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Throwable;

/**
 * Class SymfonyUrlGenerator
 */
final class SymfonyUrlGenerator implements UrlGenerator
{
    /**
     * Constructs SymfonyUrlGenerator
     */
    public function __construct(protected UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * @inheritDoc
     */
    public function generate(string $name, array $parameters = [], array $query = [], bool $absolute = false): string
    {
        try {
            if ($absolute) {
                $referenceType = UrlGeneratorInterface::ABSOLUTE_URL;
            } else {
                $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH;
            }

            $url = $this->urlGenerator->generate(
                $name,
                $parameters,
                $referenceType
            );

            if (!empty($query)) {
                $url .= '?'.http_build_query($query);
            }

            return $url;
        } catch (RouteException $e) {
            throw new RouteNotFoundException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (MissingException $e) {
            throw new MissingParametersException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (ParameterException $e) {
            throw new InvalidParameterException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        } catch (Throwable $e) {
            throw new UrlGenerationException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }
}
