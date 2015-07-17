<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Campaign\TemplateBundle\EntityService;

use CampaignChain\CoreBundle\Entity\Campaign;
use CampaignChain\CoreBundle\Entity\CampaignModule;
use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;
use CampaignChain\CoreBundle\Entity\Action;
use CampaignChain\CoreBundle\Entity\Module;

class CopyService
{
    const BUNDLE_NAME = 'campaignchain/campaign-template';
    const MODULE_IDENTIFIER = 'campaignchain-template';

    protected $em;
    protected $container;
    protected $logger;

    public function __construct(EntityManager $em, ContainerInterface $container)
    {
        $this->em = $em;
        $this->container = $container;
        $this->logger = $this->container->get('logger');
    }

    public function template2Template(Campaign $campaignTemplate, $status = null, $name = null)
    {
        $campaignService = $this->container->get('campaignchain.core.campaign');

        try {
            $this->em->getConnection()->beginTransaction();

            // Clone the campaign template.
            $copiedCampaignTemplate = $campaignService->cloneCampaign(
                $campaignTemplate,
                $status
            );

            if($name != null) {
                $copiedCampaignTemplate->setName($name);
            }

            $this->em->flush();

            $this->em->getConnection()->commit();

            return $copiedCampaignTemplate;

        } catch (\Exception $e) {
            $this->em->getConnection()->rollback();
            throw $e;
        }
    }

    public function scheduled2Template(
        Campaign $scheduledCampaign, $status = null, $name = null)
    {
        $campaignService = $this->container->get('campaignchain.core.campaign');

        try {
            $this->em->getConnection()->beginTransaction();

            // Clone the campaign template.
            $campaignTemplate = $campaignService->cloneCampaign(
                $scheduledCampaign
            );

            // Change module relationship of cloned campaign.
            $moduleService = $this->container->get('campaignchain.core.module');
            $module = $moduleService->getModule(
                Module::REPOSITORY_CAMPAIGN,
                static::BUNDLE_NAME,
                static::MODULE_IDENTIFIER
            );
            $campaignTemplate->setCampaignModule($module);
            // Specify other parameters of copied campaign.
            if($name != null) {
                $campaignTemplate->setName($name);
            }
            $campaignTemplate->setHasRelativeDates(true);
            $campaignTemplate->setStatus(Action::STATUS_PAUSED);
            $hookService = $this->container->get('campaignchain.core.hook');
            $campaignTemplate->setTriggerHook(
                $hookService->getHook('campaignchain-timespan')
            );

            $this->em->flush();

            // Move the cloned campaign to 2012-01-01 (the default date
            // for templates).
            $campaignTemplate = $campaignService->moveCampaign(
                $campaignTemplate, new \DateTime('2012-01-01'),
                Action::STATUS_PAUSED
            );

            $this->em->getConnection()->commit();

            return $campaignTemplate;
        } catch (\Exception $e) {
            $this->em->getConnection()->rollback();
            throw $e;
        }
    }
}