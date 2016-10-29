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

namespace CampaignChain\Campaign\TemplateBundle\Validator;

use CampaignChain\CoreBundle\Entity\Campaign;
use CampaignChain\CoreBundle\EntityService\ActivityService;
use CampaignChain\CoreBundle\Validator\AbstractCampaignValidator;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * Class TemplateValidator
 * @package CampaignChain\Campaign\TemplateBundle\Validator
 */
class TemplateValidator extends AbstractCampaignValidator
{
    /**
     * @var Registry
     */
    protected $em;

    protected $activityService;

    public function __construct(
        ManagerRegistry $managerRegistry,
        ActivityService $activityService
    )
    {
        $this->em = $managerRegistry->getManager();
        $this->activityService = $activityService;
    }

    /**
     * Checks whether the Activities belonging to a Campaign and marked with
     * mustValidate are executable.
     *
     * @param Campaign $campaign
     * @return array
     */
    public function hasExecutableActivities(Campaign $campaign)
    {
        $activities = $this->em->getRepository('CampaignChainCoreBundle:Activity')
            ->findBy(array(
                'campaign' => $campaign,
                'mustValidate' => true
            ));

        if(count($activities)){
            foreach ($activities as $activity){
                $isExecutable = $this->activityService->isExecutableByCampaign($activity);

                if(!$isExecutable['status']){
                    return $isExecutable;
                }
            }
        }

        return array(
            'status' => true,
        );
    }
}