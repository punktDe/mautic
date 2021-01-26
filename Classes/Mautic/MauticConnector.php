<?php
declare(strict_types=1);

namespace PunktDe\Mautic\Mautic;
/*
 *  (c) 2019 punkt.de GmbH - Karlsruhe, Germany - http://punkt.de
 *  All rights reserved.
 */

use Mautic\Api\Api;
use Mautic\Auth\AuthInterface;
use Mautic\MauticApi;
use Neos\Flow\Annotations as Flow;
use Mautic\Auth\ApiAuth;
use PunktDe\Mautic\Finishers\MauticUpdateUserFinisher;

/**
 * Class MauticConnector
 * @package PunktDe\Mautic\Mautic
 * @Flow\Scope("singleton")
 */
class MauticConnector
{
    /**
     * @Flow\InjectConfiguration
     * @var array
     */
    protected $settings = [];

    /**
     * @var string
     */
    protected $mauticApiUrl;

    /**
     * @var AuthInterface
     */
    protected $auth;

    /**
     * @var MauticApi
     */
    protected $api;


    protected function initializeObject(): void
    {
        $this->mauticApiUrl = $this->settings['mauticServer']['url'] . "/api";
        $this->auth = $this->authenticateWithCredentials();
        $this->api = new MauticApi();
    }


    /**
     * @return AuthInterface
     */
    protected function authenticateWithCredentials(): AuthInterface
    {
        if (!session_id()) {
            session_start();
        }
        $credentials = [
            'userName' => $this->settings['mauticUser']['username'],
            'password' => $this->settings['mauticUser']['password']
        ];


        $initAuth = new ApiAuth();
        $auth = $initAuth->newAuth($credentials, 'BasicAuth');
        return $auth;
    }

    /**
     * @param string $userId
     * @param array $contactData
     * @throws \Mautic\Exception\ContextNotFoundException
     */
    public function sendUserUpdateToMauticAPI(int $userId, array $contactData): void
    {
        $contactApi = $this->getContactsApi();
        $contactData['owner'] = '1';
        $contactApi->edit($userId, $contactData);
    }

    public function addUtmTagsToUser(int $userId, array $utmTags)
    {
        $contactApi = $this->getContactsApi();
        $contactApi->addUtm($userId, $utmTags);
    }

    /**
     * @param string $segmentName
     * @return array
     * @throws \Mautic\Exception\ContextNotFoundException
     */
    public function getAllUsersOfSegment(string $segmentName): array
    {
        $contactApi = $this->getContactsApi();
        $contactList = $contactApi->getIdentified('segment:' . $segmentName);
        return $contactList;
    }

    /**
     * @return Api
     * @throws \Mautic\Exception\ContextNotFoundException
     */
    protected function getContactsApi(): Api
    {
        return $this->api->newApi('contacts', $this->auth, $this->mauticApiUrl);
    }

    public function submitForm($id, $data){
      $data['formId'] = $id;
      $url = rtrim(trim(str_replace('/api','',$this->mauticApiUrl), '/')) . '/form/submit?formId=' . $id;
      $send_form = new SendFormService();
      $send_form->submitForm($url, $data);

    }


}
