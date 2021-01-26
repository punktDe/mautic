<?php
declare(strict_types=1);

namespace PunktDe\Mautic\Finishers;

/***
 *
 * This file is part of the "Mautic" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020 Florian Wessels <f.wessels@Leuchtfeuer.com>, Leuchtfeuer Digital Marketing
 *
 ***/

use Neos\Flow\Annotations as Flow;
use Neos\Form\Core\Model\AbstractFinisher;
use Neos\Form\Exception\FinisherException;
use PunktDe\Mautic\Mautic\MauticConnector;

class MauticFormFinisher extends AbstractFinisher
{

  /**
   * @var array
   */
  protected $defaultOptions = [
    'mauticFormId' => null,
  ];

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
    $formDefinition = $this->finisherContext->getFormRuntime()->getFormDefinition()->getRenderingOptions();
    $mauticId = !empty($this->parseOption('mauticFormId')) ? (int)$this->parseOption('mauticFormId') : $formDefinition['mauticFormId'];
    $data = $this->get_data();
    $this->mauticConnector->submitForm($mauticId, $data);
  }

  /**
   * @return array
   */
  protected function get_data(): array
  {
    $optionArray = [];
    $pages = $this->finisherContext->getFormRuntime()->getFormDefinition()->getPages();
    foreach ($pages as $page) {
      $renderables = $page->getRenderablesRecursively();
      foreach ($renderables as $renderable) {
        $properties = $renderable->getProperties();
        if (array_key_exists('mauticAlias', $properties) && $properties['mauticAlias'] !== '') {
          $optionArray[$properties['mauticAlias']] = $this->finisherContext->getFormValues()[$renderable->getIdentifier()];
        }
      }
    }
    return $optionArray;
  }
}
