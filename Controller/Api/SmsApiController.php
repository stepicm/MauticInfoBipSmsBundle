<?php

/*
 * @copyright   2016 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticInfoBipSmsBundle\Controller\Api;

use Mautic\ApiBundle\Controller\CommonApiController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterControllerEvent;
use MauticPlugin\MauticInfoBipSmsBundle\Api\ApiResponse;
use MauticPlugin\MauticInfoBipSmsBundle\Entity\Stat;
use MauticPlugin\MauticInfoBipSmsBundle\Entity\DwhStats;
use MauticPlugin\MauticInfoBipSmsBundle\Entity\CustomSimpleCampaign;
use MauticPlugin\MauticInfoBipSmsBundle\Entity\CustomSimpleContact;

/**
 * Class SmsApiController.
 */
class SmsApiController extends CommonApiController
{
    const EVENT_DNC = 'dnc';
    const EVENT_PENDING = 'pending';
    const EVENT_FAIL = 'fail';
    const EVENT_OK = 'sent';

    /**
     * {@inheritdoc}
     */
    public function initialize(FilterControllerEvent $event)
    {
        $this->model           = $this->getModel('sms');
        $this->entityClass     = 'MauticPlugin\MauticInfoBipSmsBundle\Entity\Sms';
        $this->entityNameOne   = 'sms';
        $this->entityNameMulti = 'smses';

        parent::initialize($event);
    }

    /**
     * Obtains a list of emails.
     *
     * @return Response
     */
    public function receiveAction()
    {
        $configParams = $this->container->get('mautic.helper.bundle')->getBundleConfig('MauticInfoBipSmsBundle', 'parameters', true);
        $requestTimeString = '[' . date('Y/m/d H:i:s') . '] - ';
        $body = $this->request->get('Body');
        $from = $this->request->get('From');

        if ($body === 'STOP' && $this->container->get('mautic.infobip.helper.sms')->unsubscribe($from)) {
            return new Response('<Response><Sms>You have been unsubscribed.</Sms></Response>', 200, ['Content-Type' => 'text/xml; charset=utf-8']);
        } else {
            // validate payload before taking action
            try {
                $content = $this->decodeRequest($this->request->getContent());

                // system logging
                if ($configParams['log_enabled']) {
                    $logFileName = sprintf('%s/%s-infobip-responses.log', $configParams['log_path'], date('Y-m-d'));
                    file_put_contents($logFileName, $requestTimeString . json_encode($content) . PHP_EOL, FILE_APPEND);
                }

                $statRepo = $this->getDoctrine()->getManager()->getRepository(Stat::class);
                $stat = $statRepo->findOneBy(['trackingHash' => $content['messageId']]);
            } catch (\Throwable $t) {
                 // something to catch or log
                 // but nothing yet
            }

            try {
                switch ($content['status']) {
                    case ApiResponse::API_PENDING:
                        $stat->setIsPending(true)
                            ->setIsDelivered(false)
                            ->setHasFailed(false);
                        $this->saveDwhStat($content['callbackData'], self::EVENT_PENDING);
                    break;
                    case ApiResponse::API_DELIVERED:
                        $stat->setIsPending(false)
                            ->setIsDelivered(true)
                            ->setHasFailed(false);
                        $this->saveDwhStat($content['callbackData'], self::EVENT_OK);
                    break;
                    case ApiResponse::API_DNC:
                        $stat->setIsPending(false)
                            ->setIsDelivered(false)
                            ->setHasFailed(true);
                        $this->saveDwhStat($content['callbackData'], self::EVENT_DNC);
                    break;
                    default:
                    case ApiResponse::API_ERROR:
                        $stat->setIsPending(false)
                            ->setIsDelivered(false)
                            ->setHasFailed(true);
                        $this->saveDwhStat($content['callbackData'], self::EVENT_FAIL);
                    break;
                }

                $em = $this->getDoctrine()->getManager();
                $em->persist($stat);
                $em->flush();
            } catch (\Throwable $t) {
                if ($configParams['log_enabled']) {
                    $logFileName = sprintf('%s/%s-infobip-error.log', $configParams['log_path'], date('Y-m-d'));
                    $throwable = [
                        'message' => $t->getMessage(),
                        'file' => $t->getFile(),
                        'line' => $t->getLine(),
                        'trace' => $t->getTrace(),
                    ];

                    file_put_contents($logFileName, $requestTimeString . json_encode($throwable) . PHP_EOL, FILE_APPEND);
                }
            }
        }

        // Return an empty response
        return new Response();
    }

    private function decodeRequest($response)
    {
        // get status from response
        try {
            $apiResponse = new ApiResponse($response);
            return $apiResponse->parsed();
        } catch (\Throwable $t) {
            return [];
        }
    }

    private function saveDwhStat($content, $status)
    {
        $campaign = $this->getDoctrine()->getManager()->getRepository(CustomSimpleCampaign::class)->findOneBy(['id' => $content['campaignId']]);
        $lead = $this->getDoctrine()->getManager()->getRepository(CustomSimpleContact::class)->findOneBy(['id' => $content['leadId']]);
        $params = [
            'username' => $lead->getUsername(),
            'player_id' => $lead->getPlayerId(),
            'campaign_id' => $content['campaignId'],
            'campaign_category_id' => $campaign->getCategoryId(),
            'channel_id' => $content['smsId'],
            'channel' => 'sms',
            'event_ts' => time(),
        ];

        $date = new \DateTime();
        $date->setTimestamp($params['event_ts']);

        $dwhStat = new DwhStats();
        $dwhStat->setUsername($params['username']);
        $dwhStat->setPlayerId($params['player_id']);
        $dwhStat->setCampaignId($params['campaign_id']);
        $dwhStat->setChannelId($params['channel_id']);
        $dwhStat->setCampaignCategoryId($params['campaign_category_id']);
        $dwhStat->setEventType($status);
        $dwhStat->setChannel('sms');
        $dwhStat->setEventTs($date);

        $this->getDoctrine()->getManager()->persist($dwhStat);
        $this->getDoctrine()->getManager()->flush();
    }
}
