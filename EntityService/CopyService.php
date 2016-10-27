<?php
/*
 * Copyright 2016 CampaignChain, Inc. <info@campaignchain.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
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
                $campaignTemplate, new \DateTime(Campaign::RELATIVE_START_DATE),
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