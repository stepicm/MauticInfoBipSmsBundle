<?php

namespace MauticPlugin\MauticInfoBipSmsBundle\Api;

class ApiResponse
{
    const API_ERROR = 'error';
    const API_DELIVERED = 'delivered';
    const API_PENDING = 'pending';
    const API_DNC = 'dnc';

    const GROUP_PENDING = 1;
    const GROUP_UNDELIVERABLE = 2;
    const GROUP_DELIVERED = 3;
    const GROUP_EXPIRED = 4;
    const GROUP_REJECTED = 5;

    const STATUS_PENDING_WAITING_DELIVERY = 3;
    const STATUS_PENDING_ENROUTE = 7;
    const STATUS_PENDING_ACCEPTED = 26;
    const STATUS_UNDELIVERABLE_REJECTED_OPERATOR = 4;
    const STATUS_UNDELIVERABLE_NOT_DELIVERED = 9;
    const STATUS_DELIVERED_TO_OPERATOR = 2;
    const STATUS_DELIVERED_TO_HANDSET = 5;
    const STATUS_EXPIRED_EXPIRED = 15;
    const STATUS_EXPIRED_DLR_UNKNOWN = 29;
    const STATUS_REJECTED_NETWORK = 6;
    const STATUS_REJECTED_PREFIX_MISSING = 8;
    const STATUS_REJECTED_DND = 10;
    const STATUS_REJECTED_SOURCE = 11;
    const STATUS_REJECTED_NOT_ENOUGH_CREDITS = 12;
    const STATUS_REJECTED_SENDER = 13;
    const STATUS_REJECTED_DESTINATION = 14;
    const STATUS_REJECTED_PREPAID_PACKAGE_EXPIRED = 17;
    const STATUS_REJECTED_DESTINATION_NOT_REGISTERED = 18;
    const STATUS_REJECTED_ROUTE_NOT_AVAILABLE = 19;
    const STATUS_REJECTED_FLOODING_FILTER = 20;
    const STATUS_REJECTED_SYSTEM_ERROR = 21;
    const STATUS_REJECTED_DUPLICATE_MESSAGE_ID = 23;
    const STATUS_REJECTED_INVALID_UDH = 24;
    const STATUS_REJECTED_MESSAGE_TOO_LONG = 25;
    const STATUS_MISSING_TO = 51;
    const STATUS_REJECTED_DESTINATION_PREFIX = 52;

    protected $pending = [
        self::STATUS_PENDING_WAITING_DELIVERY,
        self::STATUS_PENDING_ENROUTE,
        self::STATUS_PENDING_ACCEPTED,
        self::STATUS_DELIVERED_TO_OPERATOR,
    ];

    protected $delivered = [
        self::STATUS_DELIVERED_TO_HANDSET,
    ];

    protected $error = [
        self::STATUS_UNDELIVERABLE_REJECTED_OPERATOR,
        self::STATUS_UNDELIVERABLE_NOT_DELIVERED,
        self::STATUS_EXPIRED_EXPIRED,
        self::STATUS_EXPIRED_DLR_UNKNOWN,
        self::STATUS_REJECTED_NETWORK,
        self::STATUS_REJECTED_PREFIX_MISSING,
        self::STATUS_REJECTED_SOURCE,
        self::STATUS_REJECTED_NOT_ENOUGH_CREDITS,
        self::STATUS_REJECTED_SENDER,
        self::STATUS_REJECTED_DESTINATION,
        self::STATUS_REJECTED_PREPAID_PACKAGE_EXPIRED,
        self::STATUS_REJECTED_DESTINATION_NOT_REGISTERED,
        self::STATUS_REJECTED_ROUTE_NOT_AVAILABLE,
        self::STATUS_REJECTED_FLOODING_FILTER,
        self::STATUS_REJECTED_SYSTEM_ERROR,
        self::STATUS_REJECTED_DUPLICATE_MESSAGE_ID,
        self::STATUS_REJECTED_INVALID_UDH,
        self::STATUS_REJECTED_MESSAGE_TOO_LONG,
        self::STATUS_MISSING_TO,
        self::STATUS_REJECTED_DESTINATION_PREFIX,
    ];

    // do not contact
    protected $dnc = [
        self::STATUS_REJECTED_DND,
    ];

    protected $payload;

    protected $groupId;
    protected $statusId;
    protected $messageId;
    protected $callbackData;

    public function __construct($json)
    {
        $this->payload = json_decode($json, true);
        $this->parseResponse($this->payload);
    }

    public function status()
    {
        $result = self::API_ERROR;

        switch ($this->groupId) {
            case self::GROUP_PENDING:
                if ($this->isPending($this->statusId)) {
                    $result = self::API_PENDING;
                }
            break;
            case self::GROUP_DELIVERED:
                if ($this->isDelivered($this->statusId)) {
                    $result = self::API_DELIVERED;
                }

                if ((int) $this->statusId === self::STATUS_DELIVERED_TO_OPERATOR)
                {
                    $result = self::API_PENDING;
                }
            break;
            case self::GROUP_UNDELIVERABLE:
            case self::GROUP_EXPIRED:
            case self::GROUP_REJECTED:
            default:
                if ($this->hasError($this->statusId)) {
                    $result = self::API_ERROR;
                }

                if ($this->isDnc($this->statusId)) {
                    $result = self::API_DNC;
                }
            break;
        }

        return $result;
    }

    public function parsed()
    {
        return [
            'messageId' => $this->messageId,
            'groupId' => $this->groupId,
            'statusId' => $this->statusId,
            'status' => $this->status(),
            'callbackData' => $this->callbackData,
        ];
    }

    protected function isPending($statusId)
    {
        return in_array((int) $statusId, $this->pending);
    }

    protected function isDelivered($statusId)
    {
        return (int) $statusId === self::STATUS_DELIVERED_TO_HANDSET;
    }

    protected function hasError($statusId)
    {
        return in_array((int) $statusId, $this->error);
    }

    protected function isDnc($statusId)
    {
        return (int) $statusId === self::STATUS_REJECTED_DND;
    }

    private function parseResponse(array $response)
    {
        // first type response
        if (isset($response['messages'])) {
            $this->messageId = $response['messages'][0]['messageId'];
            $this->groupId = $response['messages'][0]['status']['groupId'];
            $this->statusId = $response['messages'][0]['status']['id'];
        }

        // second type response
        if (isset($response['results'])) {
            $this->messageId = $response['results'][0]['messageId'];
            $this->groupId = $response['results'][0]['status']['groupId'];
            $this->statusId = $response['results'][0]['status']['id'];
            $this->callbackData = json_decode($response['results'][0]['callbackData'], true);
        }
    }
}
