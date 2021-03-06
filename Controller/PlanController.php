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

namespace CampaignChain\Campaign\TemplateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class PlanController extends Controller
{
    const CAMPAIGN_DISPLAY_NAME = "Campaign Template";
    const BUNDLE_NAME = 'campaignchain/campaign-template';
    const MODULE_IDENTIFIER = 'campaignchain-template';

    public function timelineDetailAction(Request $request, $id){
        /*
         * Set current campaign in session, e.g. to pre-fill the campaign field
         * in a new activity with it.
         */
        $this->get('session')->set('campaignchain.campaign', $id);

        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        return $this->render(
            'CampaignChainCampaignTemplateBundle:Plan/Timeline:detail.html.twig',
            array(
                'page_title' => 'Plan '.static::CAMPAIGN_DISPLAY_NAME,
                'page_secondary_title' => $campaign->getName(),
                'gantt_tasks' => $this->get('campaignchain.core.model.dhtmlxgantt')->getTasks(
                        static::BUNDLE_NAME, static::MODULE_IDENTIFIER, $id
                    ),
                'gantt_toolbar_status' => 'default',
                'path_embedded' => $this->generateUrl('campaignchain_campaign_template_plan_timeline_detail', array('id' => $id)),
                'path_fullscreen' =>  $this->generateUrl('campaignchain_campaign_template_plan_timeline_detail_fullscreen', array('id' => $id)),
                'scale_start_date' => $campaign->getStartDate()->format(\DateTime::ISO8601),
                'scale_end_date' => $campaign->getEndDate()->format(\DateTime::ISO8601),
                'routes' => $campaign->getCampaignModule()->getRoutes(),
                'campaign' => $campaign,
            ));
    }

    public function timelineDetailFullScreenAction(Request $request, $id){
        /*
         * Set current campaign in session, e.g. to pre-fill the campaign field
         * in a new activity with it.
         */
        $this->get('session')->set('campaignchain.campaign', $id);

        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        return $this->render(
            'CampaignChainCampaignTemplateBundle:Plan/Timeline:detail_fullscreen.html.twig',
            array(
                'page_title' => 'Plan '.static::CAMPAIGN_DISPLAY_NAME,
                'page_secondary_title' => $campaign->getName(),
                'gantt_tasks' => $this->get('campaignchain.core.model.dhtmlxgantt')->getTasks(
                        static::BUNDLE_NAME, static::MODULE_IDENTIFIER, $id
                    ),
                'gantt_toolbar_status' => 'fullscreen',
                'path_fullscreen_close' => $this->generateUrl('campaignchain_campaign_template_plan_timeline_detail', array('id' => $id)),
                'path_fullscreen' =>  $this->generateUrl('campaignchain_campaign_template_plan_timeline_detail_fullscreen', array('id' => $id)),
                'scale_start_date' => $campaign->getStartDate()->format(\DateTime::ISO8601),
                'scale_end_date' => $campaign->getEndDate()->format(\DateTime::ISO8601),
                'routes' => $campaign->getCampaignModule()->getRoutes(),
                'campaign' => $campaign,
            ));
    }
}