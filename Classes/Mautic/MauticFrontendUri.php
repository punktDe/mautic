<?php
declare(strict_types=1);

namespace PunktDe\Mautic\Mautic;
/*
 *  (c) 2019 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\ContentRepository\Domain\Service\ContentDimensionCombinator;
use Neos\Neos\Domain\Service\ConfigurationContentDimensionPresetSource;
use Neos\Neos\Domain\Service\ContentContext;
use Neos\Neos\Routing\FrontendNodeRoutePartHandler;

/**
 * Class MauticFrontendUri
 * @package PunktDe\Mautic\Mautic
 */
class MauticFrontendUri extends FrontendNodeRoutePartHandler
{
    /**
     * @var ContentContext
     */
    protected $nodeContext;


    /**
     * @param string $path
     * @param array $mauticSegment
     * @return string
     * @throws \Neos\Neos\Routing\Exception\InvalidRequestPathException
     * @throws \Exception
     */
    public function getRedirectUri(string $path, array $mauticSegment): string
    {
        $originalUriArray = explode('/', $path);
        $this->buildContentContext($path);
        $possibleUriSegments = $this->getPossibleUriSegments();

        foreach ($possibleUriSegments as $possibleUriSegment) {
            if ($possibleUriSegment === $originalUriArray[1]) {
                $redirectUriSegment = $this->createRedirectUriSegment($mauticSegment);
                if ($originalUriArray[1] === $redirectUriSegment) {
                    return '';
                }
                $originalUriArray[1] = $redirectUriSegment;
                return implode('/', $originalUriArray);
                break;
            }
        }
        return '';
    }


    /**
     * @return array
     * @throws \Exception
     */
    protected function getPossibleUriSegments(): array
    {
        $urlCombinationsArray = [];

        $contentDimensionCombinator = new ContentDimensionCombinator();
        $combinationsArray = $contentDimensionCombinator->getAllAllowedCombinations();
        foreach ($combinationsArray as $combination) {
            $dimensionUriSegment = substr_replace($this->getUriSegmentForDimensions($combination, true), "", -1);
            array_push($urlCombinationsArray, $dimensionUriSegment);
        }
        return $urlCombinationsArray;
    }

    /**
     * @param array $mauticSegment
     * @return string
     */
    protected function createRedirectUriSegment(array $mauticSegment): string
    {
        $finalUriSegmentArray = [];
        $contentDimensionInterface = new ConfigurationContentDimensionPresetSource();
        $presetOptions = $contentDimensionInterface->getAllPresets();

        $dimensions = $this->nodeContext->getDimensions();
        foreach ($dimensions as $dimension => $preset) {
            if ($dimension === 'mautic') {
                if ($mauticSegment['uriSegment'] !== '') {
                    array_push($finalUriSegmentArray, $mauticSegment['uriSegment']);
                }
                continue;
            }
            $uriSegment = $presetOptions[$dimension]['presets'][$preset[0]]['uriSegment'];
            if ($uriSegment !== '') {
                array_push($finalUriSegmentArray, $uriSegment);
            }
        }
        return implode('_', $finalUriSegmentArray);
    }

    /**
     * @param $path
     * @throws \Neos\Neos\Routing\Exception\InvalidRequestPathException
     */
    protected function buildContentContext($path): void
    {
        $this->nodeContext = $this->buildContextFromPath($path, true);
    }
}
