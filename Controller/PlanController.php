<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Campaign\TemplateBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class PlanController extends Controller
{
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
                'page_title' => 'Plan Campaign Template',
                'page_secondary_title' => $campaign->getName(),
                'gantt_tasks' => $this->get('campaignchain.core.model.dhtmlxgantt')->getTasks(
                        self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $id
                    ),
                'gantt_toolbar_status' => 'default',
                'path_embedded' => '',
                'path_fullscreen' =>  '',
                'scale_start_date' => $campaign->getStartDate()->format(\DateTime::ISO8601),
                'scale_end_date' => $campaign->getEndDate()->format(\DateTime::ISO8601),
                'campaign' => $campaign,
            ));
    }
}