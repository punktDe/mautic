<?php
declare(strict_types=1);

namespace PunktDe\Mautic\Components;
/*
 *  (c) 2019 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Http\Component\ComponentChain;
use Neos\Flow\Http\Component\ComponentInterface;
use Neos\Flow\Http\Component\ComponentContext;
use Neos\Flow\Http\Cookie;
use Neos\Neos\Domain\Service\ConfigurationContentDimensionPresetSource;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use PunktDe\Mautic\Mautic\MauticConnector;

use Neos\Neos\Routing;
use PunktDe\Mautic\Mautic\MauticFrontendUri;

class RedirectToCorrectDimensionComponent implements ComponentInterface
{

    /**
     * @Flow\Inject
     * @var MauticConnector
     */
    protected $mauticConnector;

    /**
     * @var array
     */
    protected $options;

    /**
     * @Flow\Inject
     * @var ConfigurationContentDimensionPresetSource
     */
    protected $contentDimensionInterface;


    protected function initializeObject(): void
    {
        $this->options = $this->contentDimensionInterface->getAllPresets();
    }


    /**
     * @param ComponentContext $componentContext
     * @throws Routing\Exception\InvalidRequestPathException
     * @throws \Mautic\Exception\ContextNotFoundException
     * @throws \Exception
     */
    public function handle(ComponentContext $componentContext): void
    {
        if (!$this->checkIfUserIsInFrontend($componentContext) || !$this->isMauticDimensionsDefined() || !$this->mauticUserIsIdentifiedByCookie($componentContext)) {
            return;
        }

        $cookieSegmentName = $this->getMauticSegmentFromCookieIfAvailable($componentContext);
        if ($cookieSegmentName !== '') {
            $mauticPresets = $this->getMauticPresetsFromOptions();
            if (key_exists($cookieSegmentName, $mauticPresets)) {
                $segment = $mauticPresets[$cookieSegmentName];
                $redirectPath = $this->createRedirectUri($componentContext, $segment);
                $this->redirectUserToDimension($componentContext, $redirectPath, $cookieSegmentName);
                return;
            }
        }

        $mauticUserId = $this->getMauticUserId($componentContext);

        $mauticPresets = $this->getMauticPresetsFromOptions();
        $defaultMauticPresetName = $this->getDefaultMauticPresetName();
        $userHasSegment = false;
        foreach ($mauticPresets as $segmentName => $segment) {
            if ($segmentName !== $defaultMauticPresetName && $this->isUserInSegment($mauticUserId, $segmentName)) {
                $userHasSegment = true;
                $redirectPath = $this->createRedirectUri($componentContext, $segment);
                $this->redirectUserToDimension($componentContext, $redirectPath, $segmentName);
                break;
            }
        }
        if (!$userHasSegment) {
            $redirectPath = $this->createRedirectUri($componentContext, $mauticPresets[$defaultMauticPresetName]);
            $this->redirectUserToDimension($componentContext, $redirectPath, $defaultMauticPresetName);
        }
    }

    /**
     * @param ComponentContext $componentContext
     * @return bool
     */
    protected function checkIfUserIsInFrontend(ComponentContext $componentContext): bool
    {
        $target = urldecode($this->getHttpRequestFromComponent($componentContext)->getRequestTarget());
        if (strpos($target, '@user-') === false) {
            return true;
        }
        return false;
    }

    /**
     * @param ComponentContext $componentContext
     * @param string $redirectPath
     * @param string $segmentName
     * @throws \Exception
     */
    protected function redirectUserToDimension(ComponentContext $componentContext, string $redirectPath, string $segmentName): void
    {
        if ($redirectPath !== '') {
            $this->setMauticSegmentCookie($componentContext, $segmentName);
            $this->redirectToPath($componentContext, $redirectPath);
        }
    }


    /**
     * @param ComponentContext $componentContext
     * @param string $segmentName
     * @throws \Exception
     */
    protected function setMauticSegmentCookie(ComponentContext $componentContext, string $segmentName): void
    {
        $expirationTime = new \DateTime('now');
        $expirationTime->modify('+1 day');
        $cookie = new Cookie('mautic_segment', $segmentName, $expirationTime);

        $httpResonse = $this->getHttpResponseFromComponent($componentContext);
        $httpResonse->setCookie($cookie);
    }

    /**
     * @param ComponentContext $componentContext
     * @param array $segment
     * @return string
     * @throws Routing\Exception\InvalidRequestPathException
     */
    protected function createRedirectUri(ComponentContext $componentContext, array $segment): string
    {
        $mauticFrontemdUri = new MauticFrontendUri();
        return $mauticFrontemdUri->getRedirectUri($this->getHttpRequestFromComponent($componentContext)->getUri()->getPath(), $segment);
    }


    /**
     * @param ComponentContext $componentContext
     * @param string $redirectPath
     */
    protected function redirectToPath(ComponentContext $componentContext, string $redirectPath): void
    {
        $response = $this->getHttpResponseFromComponent($componentContext);

        $response = $response->withStatus(301);
        $response->setContent('<html><head><meta http-equiv="refresh" content="0; url=' . $redirectPath . '"/></head></html>');
        $componentContext->replaceHttpResponse($response);
        $componentContext->setParameter(ComponentChain::class, 'cancel', TRUE);
    }

    /**
     * @return bool
     */
    protected function isMauticDimensionsDefined(): bool
    {
        return key_exists('mautic', $this->options);
    }

    /**
     * @return string
     */
    protected function getDefaultMauticPresetName(): string
    {
        $contentDimensionInterface = new ConfigurationContentDimensionPresetSource();
        $defaultPreset = $contentDimensionInterface->getDefaultPreset('mautic');
        return $defaultPreset['identifier'];
    }

    /**
     * @return array
     */
    protected function getMauticPresetsFromOptions(): array
    {
        return $this->options['mautic']['presets'] ?? [];
    }


    /**
     * @param int $userID
     * @param string $segmentName
     * @return bool
     * @throws \Mautic\Exception\ContextNotFoundException
     */
    protected function isUserInSegment(int $userID, string $segmentName): bool
    {
        $usersOfSegment = $this->getUsersOfSegment($segmentName);
        foreach ($usersOfSegment['contacts'] as $user) {
            if ((int)$user['id'] === (int)$userID) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $segmentName
     * @return array
     * @throws \Mautic\Exception\ContextNotFoundException
     */
    protected function getUsersOfSegment(string $segmentName): array
    {
        return $this->mauticConnector->getAllUsersOfSegment($segmentName);
    }

    /**
     * @param ComponentContext $componentContext
     * @return RequestInterface
     */
    protected function getHttpRequestFromComponent(ComponentContext $componentContext): RequestInterface
    {
        return $componentContext->getHttpRequest();
    }


    /**
     * @param ComponentContext $componentContext
     * @return ResponseInterface
     */
    protected function getHttpResponseFromComponent(ComponentContext $componentContext): ResponseInterface
    {
        return $componentContext->getHttpResponse();
    }

    /**
     * @param ComponentContext $componentContext
     * @return bool
     */
    protected function mauticUserIsIdentifiedByCookie(ComponentContext $componentContext): bool
    {
        $cookie = $this->getHttpRequestFromComponent($componentContext)->getCookieParams()['mtc_id'];
        return $cookie === null ? false : true;
    }

    /**
     * @param ComponentContext $componentContext
     * @return string
     */
    protected function getMauticSegmentFromCookieIfAvailable(ComponentContext $componentContext): string
    {
        $httpRequest = $this->getHttpRequestFromComponent($componentContext);
        $cookie = $httpRequest->getCookieParams()['mautic_segment'];
        if ($cookie === null) {
            return '';
        }
        return $cookie;
    }

    /**
     * @param ComponentContext $componentContext
     * @return int
     */
    protected function getMauticUserId(ComponentContext $componentContext): int
    {
        $httpRequest = $this->getHttpRequestFromComponent($componentContext);
        $cookie = $httpRequest->getCookieParams()['mtc_id'];
        return $cookie->getValue() !== '' ? (int)$cookie->getValue() : 0;
    }
}

