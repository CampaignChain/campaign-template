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

use CampaignChain\Campaign\TemplateBundle\Validator\TemplateValidator;
use CampaignChain\CoreBundle\EntityService\CampaignService;
use CampaignChain\CoreBundle\EntityService\HookService;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use CampaignChain\CoreBundle\Entity\Campaign;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use CampaignChain\CoreBundle\Entity\Action;

/**
 * Class TemplateController
 * @package CampaignChain\Campaign\TemplateBundle\Controller
 */
class TemplateController extends Controller
{
    const CAMPAIGN_DISPLAY_NAME = "Campaign Template";
    const BUNDLE_NAME = 'campaignchain/campaign-template';
    const MODULE_IDENTIFIER = 'campaignchain-template';
    const TRIGGER_HOOK = 'campaignchain-timespan';

    public function getLogger()
    {
        return $this->has('monolog.logger.external') ? $this->get('monolog.logger.external') : $this->get('monolog.logger');
    }

    public function indexAction()
    {
        // Get the campaign templates
        $repository_campaigns = $this->getDoctrine()->getRepository('CampaignChainCoreBundle:Campaign')->getCampaignsByModule(static::MODULE_IDENTIFIER, static::BUNDLE_NAME);

        return $this->render(
            'CampaignChainCampaignTemplateBundle::index.html.twig',
            array(
                'page_title' => static::CAMPAIGN_DISPLAY_NAME . 's',
                'repository_campaigns' => $repository_campaigns,
            ));
    }

    public function newAction(Request $request)
    {
        // create a campaign and give it some dummy data for this example
        $campaign = new Campaign();
        $campaign->setTimezone($this->get('session')->get('campaignchain.timezone'));
        $campaign->setStatus(Action::STATUS_PAUSED);
        // A campaign template does not have absolute dates.
        $campaign->setHasRelativeDates(true);
        // All campaign templates start Jan 1st, 2012 midnight.
        $campaign->setStartDate(new \DateTime(Campaign::RELATIVE_START_DATE));

        $campaignType = $this->getCampaignType();

        $form = $this->createForm($campaignType, $campaign);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            // Make sure that data stays intact by using transactions.
            try {
                $em->getConnection()->beginTransaction();

                $em->persist($campaign);

                // We need the campaign ID for storing the hooks. Hence we must flush here.
                $em->flush();

                /** @var HookService $hookService */
                $hookService = $this->get('campaignchain.core.hook');
                $hookService->processHooks(static::BUNDLE_NAME, static::MODULE_IDENTIFIER, $campaign, $form,
                    true);
                $campaign = $hookService->getEntity();

                $hookService = $this->get('campaignchain.core.hook');
                $campaign->setTriggerHook(
                    $hookService->getHook(static::TRIGGER_HOOK)
                );

                $em->flush();

                $em->getConnection()->commit();
            } catch (\Exception $e) {
                $em->getConnection()->rollback();
                throw $e;
            }

            $this->addFlash(
                'success',
                'Your new campaign template <a href="' . $this->generateUrl('campaignchain_campaign_template_edit',
                    array('id' => $campaign->getId())) . '">' . $campaign->getName() . '</a> was created successfully.'
            );

            if ($this->getRequest()->isXmlHttpRequest()) {
                return new JsonResponse(array(
                    'step' => 2
                ));
            } else {
                return $this->redirectToRoute('campaignchain_core_plan_campaigns');
            }
        }

        return $this->render(
            $this->getRequest()->isXmlHttpRequest() ? 'CampaignChainCoreBundle:Base:new_modal.html.twig' : 'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'New ' . static::CAMPAIGN_DISPLAY_NAME,
                'form' => $form->createView(),
                'form_submit_label' => 'Save',
            ));
    }

    public function editAction(Request $request, $id)
    {
        /** @var CampaignService $campaignService */
        $campaignService = $this->get('campaignchain.core.campaign');
        /** @var Campaign $campaign */
        $campaign = $campaignService->getCampaign($id);

        $campaignType = $this->getCampaignType();

        $form = $this->createForm($campaignType, $campaign);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $em = $this->getDoctrine()->getManager();

            /*
             * Check whether all Activities can be executed as part of the
             * changed campaign.
             */
            /** @var TemplateValidator $validator */
            $validator = $this->get('campaignchain.validator.campaign.template');
            $isExecutable = $validator->hasExecutableActivities($campaign);
            if(!$isExecutable['status']){
                $this->addFlash(
                    'warning',
                    $isExecutable['message']
                );

                // TODO: https://github.com/CampaignChain/campaignchain/issues/224
                return $this->redirectToRoute('campaignchain_core_campaign_edit', array(
                    'id' => $campaign->getId(),
                ));
            } else {
                /** @var HookService $hookService */
                $hookService = $this->get('campaignchain.core.hook');
                $hookService->processHooks(static::BUNDLE_NAME, static::MODULE_IDENTIFIER, $campaign, $form);
                $campaign = $hookService->getEntity();
                $em->persist($campaign);

                $em->flush();

                $this->addFlash(
                    'success',
                    'Your campaign template <a href="' . $this->generateUrl('campaignchain_core_campaign_edit',
                        array('id' => $campaign->getId())) . '">' . $campaign->getName() . '</a> was edited successfully.'
                );

                return $this->redirectToRoute('campaignchain_core_campaign');
            }
        }

        return $this->render(
            'CampaignChainCampaignTemplateBundle::edit.html.twig',
            array(
                'page_title' => 'Edit ' . static::CAMPAIGN_DISPLAY_NAME,
                'page_secondary_title' => $campaign->getName(),
                'form' => $form->createView(),
                'form_submit_label' => 'Save',
                'campaign' => $campaign,
            ));
    }

    public function editModalAction(Request $request, $id)
    {
        // TODO: If a campaign is ongoing, only the end date can be changed.
        // TODO: If a campaign is done, it cannot be edited.
        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        $campaignType = $this->getCampaignType();
        $campaignType->setView('default');

        $form = $this->createForm($campaignType, $campaign);

        return $this->render(
            'CampaignChainCoreBundle:Campaign:edit_modal.html.twig',
            array(
                'page_title' => 'Edit ' . static::CAMPAIGN_DISPLAY_NAME,
                'form' => $form->createView(),
                'campaign' => $campaign,
                'form_submit_label' => 'Save',
            ));
    }

    public function editApiAction(Request $request, $id)
    {
        $responseData = array();

        $data = $request->get('campaignchain_core_campaign');

        //$responseData['payload'] = $data;

        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);
        $campaign->setName($data['name']);
        $campaign->setTimezone($data['timezone']);

        // Remember original dates.
        $responseData['start_date'] = $campaign->getStartDate()->format(\DateTime::ISO8601);
        $responseData['end_date'] = $campaign->getEndDate()->format(\DateTime::ISO8601);

        // Clear all flash bags.
        $this->get('session')->getFlashBag()->clear();

        $em = $this->getDoctrine()->getManager();

        // Make sure that data stays intact by using transactions.
        try {
            $em->getConnection()->beginTransaction();

            $em->persist($campaign);

            /** @var HookService $hookService */
            $hookService = $this->get('campaignchain.core.hook');
            $hookService->processHooks(static::BUNDLE_NAME, static::MODULE_IDENTIFIER, $campaign, $data);
            $campaign = $hookService->getEntity();
            $em->flush();

            $responseData['start_date'] = $campaign->getStartDate()->format(\DateTime::ISO8601);
            $responseData['end_date'] = $campaign->getEndDate()->format(\DateTime::ISO8601);
            $responseData['success'] = true;

            $em->getConnection()->commit();
        } catch (\Exception $e) {
            $em->getConnection()->rollback();

            if($this->get('kernel')->getEnvironment() == 'dev'){
                $message = $e->getMessage().' '.$e->getFile().' '.$e->getLine().'<br/>'.$e->getTraceAsString();
            } else {
                $message = $e->getMessage();
            }

            $this->addFlash(
                'warning',
                $message
            );

            $this->getLogger()->error($e->getMessage(), array(
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTrace(),
            ));

            $responseData['message'] = $e->getMessage();
            $responseData['success'] = false;
        }

        $responseData['status'] = $campaign->getStatus();

        $serializer = $this->get('campaignchain.core.serializer.default');

        return new Response($serializer->serialize($responseData, 'json'));
    }

    public function copyAction(Request $request, $id)
    {
        $campaignService = $this->get('campaignchain.core.campaign');
        $fromCampaign = $campaignService->getCampaign($id);
        $campaignURI = $campaignService->getCampaignURI($fromCampaign);

        switch ($campaignURI) {
            case 'campaignchain/campaign-template/campaignchain-template':
                $toCampaign = clone $fromCampaign;
                $toCampaign->setName($fromCampaign->getName() . ' (copied)');

                $campaignType = $this->getCampaignType();
                $campaignType->setHooksOptions(
                    array(
                        'campaignchain-timespan' => array(
                            'disabled' => true,
                        )
                    )
                );

                $form = $this->createForm($campaignType, $toCampaign);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $copyService = $this->get('campaignchain.campaign.template.copy');
                    $clonedCampaign = $copyService->template2Template(
                        $fromCampaign, null, $toCampaign->getName());

                    $this->addFlash(
                        'success',
                        'The ' . static::CAMPAIGN_DISPLAY_NAME . ' <a href="' . $this->generateUrl(
                            'campaignchain_core_campaign_edit',
                            array('id' => $clonedCampaign->getId())) . '">' .
                        $clonedCampaign->getName() . '</a> was copied successfully.'
                    );

                    return $this->redirectToRoute('campaignchain_core_campaign');
                }

                return $this->render(
                    'CampaignChainCoreBundle:Base:new.html.twig',
                    array(
                        'page_title' => 'Copy ' . static::CAMPAIGN_DISPLAY_NAME,
                        'form' => $form->createView(),
                    ));
                break;
            case 'campaignchain/campaign-scheduled/campaignchain-scheduled':
                $scheduledCampaign = $fromCampaign;
                $campaignTemplate = clone $scheduledCampaign;

                $campaignTemplate->setName($scheduledCampaign->getName() . ' (copied)');
                $campaignType = $this->getCampaignType();
                $campaignType->setHooksOptions(
                    array(
                        'campaignchain-timespan' => array(
                            'disabled' => true,
                        )
                    )
                );

                $form = $this->createForm($campaignType, $campaignTemplate);

                $form->handleRequest($request);

                if ($form->isValid()) {
                    $copyService = $this->get('campaignchain.campaign.template.copy');
                    $clonedCampaign = $copyService->scheduled2Template(
                        $campaignTemplate, null, $campaignTemplate->getName());

                    $this->addFlash(
                        'success',
                        'The campaign template <a href="' . $this->generateUrl('campaignchain_core_campaign_edit',
                            array('id' => $clonedCampaign->getId())) . '">' . $clonedCampaign->getName() . '</a> was copied successfully.'
                    );

                    return $this->redirectToRoute('campaignchain_core_campaign');
                }

                return $this->render(
                    'CampaignChainCoreBundle:Base:new.html.twig',
                    array(
                        'page_title' => 'Copy Scheduled Campaign as ' . static::CAMPAIGN_DISPLAY_NAME,
                        'form' => $form->createView(),
                    ));

                break;
            case 'campaignchain/campaign-repeating/campaignchain-repeating':
                $this->addFlash(
                    'warning',
                    'Repeating Campaigns cannot be copied as Campaign Templates.'
                );

                return $this->redirectToRoute('campaignchain_core_campaign');
                break;
        }
    }

    protected function getCampaignType() {
        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(static::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(static::MODULE_IDENTIFIER);

        return $campaignType;
    }
}