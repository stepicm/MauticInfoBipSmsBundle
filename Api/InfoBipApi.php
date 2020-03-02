<?php
/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      André Rocha
 *
 * @link        http://mjlogan.com
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticInfoBipSmsBundle\Api;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Mautic\PageBundle\Model\TrackableModel;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Mautic\CoreBundle\Helper\BundleHelper;
use Monolog\Logger;

class InfoBipApi extends AbstractSmsApi
{
    private $username;
    private $password;

    /**
     * @var \Services_InfoBip
     */
    protected $client;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var BundleHelper
     */
    protected $bundlehelper;

    /**
     * @var string
     */
    protected $sendingPhoneNumber;

    /**
     * InfoBipApi constructor.
     *
     * @param TrackableModel    $pageTrackableModel
     * @param IntegrationHelper $integrationHelper
     * @param Logger            $logger
     */
    public function __construct(TrackableModel $pageTrackableModel, IntegrationHelper $integrationHelper, BundleHelper $bundlehelper, Logger $logger)
    {
        $this->logger = $logger;
        $this->bundlehelper = $bundlehelper;

        $integration = $integrationHelper->getIntegrationObject('InfoBip');

        if ($integration && $integration->getIntegrationSettings()->getIsPublished()) {
            $this->sendingPhoneNumber = $integration->getIntegrationSettings()->getFeatureSettings()['sending_phone_number'];

            $keys = $integration->getDecryptedApiKeys();

            $this->username = $keys['username'];
            $this->password = $keys['password'];
        }

        parent::__construct($pageTrackableModel);
    }

    /**
     * @param string $number
     *
     * @return string
     */
    protected function sanitizeNumber($number)
    {
        $util   = PhoneNumberUtil::getInstance();
        $parsed = $util->parse($number, null);

        return $util->format($parsed, PhoneNumberFormat::E164);
    }

    /**
     * @param string $number
     * @param string $messageBody
     * @param string $trackingHash
     * @param string $leadId
     * @param string $smsId
     * @param string $campaignId
     * @return bool|string
     */
    public function sendSms($number, $messageBody, $trackingHash = null, $leadId = null, $smsId = null, $campaignId = null)
    {
        if ($number === null) {
            return false;
        }

        $url = "http://gv198.api.infobip.com/sms/2/text/advanced";
        $curl = curl_init();
        $configParams = $this->bundlehelper->getBundleConfig('MauticInfoBipSmsBundle', 'parameters', true);

        $headers = [
            'Authorization: Basic '. base64_encode("{$this->username}:{$this->password}"),
            'Content-Type:application/json',
            'Accept: application/json'
        ];

        $callbackData = json_encode([
            'smsId' => $smsId,
            'leadId' => $leadId,
            'campaignId' => $campaignId,
            'trackingHash' => $trackingHash,
        ]);

        $sender = $configParams['sms_sender_addr'] ?? $this->sendingPhoneNumber;

        $data = [
            'bulkId' => sprintf('CNO-%s', $campaignId),
            'messages' => [
                'from' => $sender,
                'destinations' => [
                    [
                        'to' => $number,
                        'messageId' => $trackingHash,
                    ],
                ],
                'text' => $messageBody,
                'flash' => false,
                'intermediateReport' => true,
            ],
        ];

        if (isset($configParams['sms_callback_url'])) {
            $data['messages']['notifyUrl'] = $configParams['sms_callback_url'];
            $data['messages']['notifyContentType'] = 'application/json';
            $data['messages']['callbackData'] = $callbackData;
        }

        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));

        $result = curl_exec($curl);

        if (curl_errno($curl)) {
            $this->logger->addWarning(
                sprintf('Sms send error: %s', curl_error($curl)),
                ['exception' => json_encode($result)]
            );

            curl_close($curl);
            return false;
        }

        curl_close($curl);
		return $result;
    }
}
