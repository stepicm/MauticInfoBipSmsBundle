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

/**
 * Class SmsApiController.
 */
class SmsApiController extends CommonApiController
{
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

        if ($body === 'STOP' && $this->factory->getHelper('sms')->unsubscribe($from)) {
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
                    break;
                    case ApiResponse::API_DELIVERED:
                        $stat->setIsPending(false)
                            ->setIsDelivered(true)
                            ->setHasFailed(false);
                    break;
                    case ApiResponse::API_DNC:
                        $stat->setIsPending(false)
                            ->setIsDelivered(false)
                            ->setHasFailed(true);
                    break;
                    default:
                    case ApiResponse::API_ERROR:
                        $stat->setIsPending(false)
                            ->setIsDelivered(false)
                            ->setHasFailed(true);
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
}
