<?php
declare(strict_types=1);

namespace PunktDe\Mautic\Finishers;

/*
 *  (c) 2019 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Neos\Flow\Annotations as Flow;
use Neos\Form\Core\Model\AbstractFinisher;
use Neos\Form\Exception\FinisherException;
use PunktDe\Mautic\Mautic\MauticConnector;


class MauticUpdateUserFinisher extends AbstractFinisher
{
    /**
     * @Flow\Inject
     * @var MauticConnector
     */
    protected $mauticConnector;


    /**
     * Executes this finisher
     * @return void
     * @throws \Mautic\Exception\ContextNotFoundException
     * @throws FinisherException
     * @see AbstractFinisher::execute()
     */
    protected function executeInternal(): void
    {
        if ($this->mauticUserIsIdentifiedByCookie()) {
            $userId = $this->getUserIdFromCookie();
            if ($userId !== 0) {
                $optionsArray = $this->mapElementsWithMauticIdentifier();
                $this->mauticConnector->sendUserUpdateToMauticAPI($userId, $optionsArray);
                $utmTags = $this->getUtmTags();
                if (!empty($utmTags)) {
                    $this->mauticConnector->addUtmTagsToUser($userId, $utmTags);
                }
            }
        }

    }

    /**
     * @return bool
     */
    protected function mauticUserIsIdentifiedByCookie(): bool
    {
        return $this->getMauticCookie() === null ? false : true;
    }

    /**
     * @return int
     */
    protected function getUserIdFromCookie(): int
    {
        $leadMauticId = $this->getMauticCookie();
        return $leadMauticId !== '' ? (int)$leadMauticId : 0;
    }

    /**
     * @return ?string
     */
    protected function getMauticCookie(): ?string
    {
        $request = $this->finisherContext->getFormRuntime()->getRequest()->getHttpRequest();
        $cookies = $request->getCookieParams();
        return isset($cookies['mtc_id']) ? $cookies['mtc_id'] : '';
    }

    /**
     * @return array
     */
    protected function mapElementsWithMauticIdentifier(): array
    {
        $optionArray = [];
        $pages = $this->finisherContext->getFormRuntime()->getFormDefinition()->getPages();
        foreach ($pages as $page) {
            $renderables = $page->getRenderablesRecursively();
            foreach ($renderables as $renderable) {
                $properties = $renderable->getProperties();
                if (array_key_exists('mauticIdentifier', $properties) && $properties['mauticIdentifier'] !== '') {
                    $optionArray[$properties['mauticIdentifier']] = $this->finisherContext->getFormValues()[$renderable->getIdentifier()];
                }
            }
        }
        return $optionArray;
    }


    protected function getUtmTags(): array
    {
        $request = $this->finisherContext->getFormRuntime()->getRequest()->getHttpRequest();
        parse_str($request->getUri()->getQuery(), $params);
        $utmTags = [];
        foreach ($params as $paramName => $paramValue) {
            if (strpos($paramName, 'utm_') !== false) {
                $utmTags[$paramName] = $paramValue;
            }
        }
        return $utmTags;
    }
}
