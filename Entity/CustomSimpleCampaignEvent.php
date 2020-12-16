<?php

namespace MauticPlugin\MauticInfoBipSmsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

class CustomSimpleCampaignEvent
{
    /**
     * @var int
     */
    private $id;

    /**
     * @var int
     */
    private $campaignId;

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata)
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('campaign_events');

        $builder->addId();
        $builder->addNamedField('campaignId', 'integer', 'campaign_id');
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return int
     */
    public function getCampaignId()
    {
        return $this->campaignId;
    }
}
