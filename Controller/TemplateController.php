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
use CampaignChain\CoreBundle\Entity\Campaign;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use CampaignChain\CoreBundle\Entity\Module;
use CampaignChain\CoreBundle\Entity\Action;

class TemplateController extends Controller
{
    const CAMPAIGN_DISPLAY_NAME = "Campaign Template";
    const BUNDLE_NAME = 'campaignchain/campaign-template';
    const MODULE_IDENTIFIER = 'campaignchain-template';
    const TRIGGER_HOOK = 'campaignchain-timespan';

    public function indexAction(){
        // Get the campaign templates
        $qb = $this->getDoctrine()->getEntityManager()->createQueryBuilder();
        $qb->select('c')
            ->from('CampaignChain\CoreBundle\Entity\Campaign', 'c')
            ->from('CampaignChain\CoreBundle\Entity\Module', 'm')
            ->from('CampaignChain\CoreBundle\Entity\Bundle', 'b')
            ->where('b.name = :bundleName')
            ->andWhere('m.identifier = :moduleIdentifier')
            ->andWhere('m.id = c.campaignModule')
            ->setParameter('bundleName', static::BUNDLE_NAME)
            ->setParameter('moduleIdentifier', static::MODULE_IDENTIFIER)
            ->orderBy('c.name', 'ASC');
        $query = $qb->getQuery();
        $repository_campaigns = $query->getResult();

        return $this->render(
            'CampaignChainCampaignTemplateBundle::index.html.twig',
            array(
                'page_title' => static::CAMPAIGN_DISPLAY_NAME.'s',
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
        $campaign->setStartDate(new \DateTime('2012-01-01 00:00:00'));

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(static::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(static::MODULE_IDENTIFIER);

        $form = $this->createForm($campaignType, $campaign);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $repository = $this->getDoctrine()->getManager();

            // Make sure that data stays intact by using transactions.
            try {
                $repository->getConnection()->beginTransaction();

                $repository->persist($campaign);

                // We need the campaign ID for storing the hooks. Hence we must flush here.
                $repository->flush();

                $hookService = $this->get('campaignchain.core.hook');
                $campaign = $hookService->processHooks(static::BUNDLE_NAME, static::MODULE_IDENTIFIER, $campaign, $form, true);

                $hookService = $this->get('campaignchain.core.hook');
                $campaign->setTriggerHook(
                    $hookService->getHook(static::TRIGGER_HOOK)
                );
                
                $repository->flush();

                $repository->getConnection()->commit();
            } catch (\Exception $e) {
                $repository->getConnection()->rollback();
                throw $e;
            }

            $this->get('session')->getFlashBag()->add(
                'success',
                'Your new campaign template <a href="'.$this->generateUrl('campaignchain_campaign_template_edit', array('id' => $campaign->getId())).'">'.$campaign->getName().'</a> was created successfully.'
            );

            return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
        }

        return $this->render(
            'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'New '.static::CAMPAIGN_DISPLAY_NAME,
                'form' => $form->createView(),
                'form_submit_label' => 'Save',
            ));
    }

    public function editAction(Request $request, $id)
    {
        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(static::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(static::MODULE_IDENTIFIER);

        $form = $this->createForm($campaignType, $campaign);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $repository = $this->getDoctrine()->getManager();

            $hookService = $this->get('campaignchain.core.hook');
            $campaign = $hookService->processHooks(static::BUNDLE_NAME, static::MODULE_IDENTIFIER, $campaign, $form);
            $repository->persist($campaign);

            $repository->flush();

            $this->get('session')->getFlashBag()->add(
                'success',
                'Your campaign template <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $campaign->getId())).'">'.$campaign->getName().'</a> was edited successfully.'
            );

            return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
        }

        return $this->render(
            'CampaignChainCampaignTemplateBundle::edit.html.twig',
            array(
                'page_title' => 'Edit '.static::CAMPAIGN_DISPLAY_NAME,
                'page_secondary_title' => $campaign->getName(),
                'form' => $form->createView(),
                'form_submit_label' => 'Save',
                'campaign' => $campaign,
                'routes' => $campaign->getCampaignModule()->getRoutes(),
            ));
    }

    public function editModalAction(Request $request, $id)
    {
        // TODO: If a campaign is ongoing, only the end date can be changed.
        // TODO: If a campaign is done, it cannot be edited.
        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(static::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(static::MODULE_IDENTIFIER);
        $campaignType->setView('default');

        $form = $this->createForm($campaignType, $campaign);

        return $this->render(
            'CampaignChainCoreBundle:Campaign:edit_modal.html.twig',
            array(
                'page_title' => 'Edit '.static::CAMPAIGN_DISPLAY_NAME,
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

        $repository = $this->getDoctrine()->getManager();
        $repository->persist($campaign);

        $hookService = $this->get('campaignchain.core.hook');
        $hookService->processHooks(static::BUNDLE_NAME, static::MODULE_IDENTIFIER, $campaign, $data);

        $repository->flush();

        $responseData['start_date'] = $campaign->getStartDate()->format(\DateTime::ISO8601);
        $responseData['end_date'] = $campaign->getEndDate()->format(\DateTime::ISO8601);


        $encoders = array(new JsonEncoder());
        $normalizers = array(new GetSetMethodNormalizer());
        $serializer = new Serializer($normalizers, $encoders);

        $response = new Response($serializer->serialize($responseData, 'json'));
        return $response->setStatusCode(Response::HTTP_OK);
    }

    public function copyAction(Request $request, $id)
    {
        $campaignService = $this->get('campaignchain.core.campaign');
        $fromCampaign = $campaignService->getCampaign($id);
        $campaignURI = $campaignService->getCampaignURI($fromCampaign);

        switch($campaignURI){
            case 'campaignchain/campaign-template/campaignchain-template':
                $toCampaign = clone $fromCampaign;
                $toCampaign->setName($fromCampaign->getName().' (copied)');

                $campaignType = $this->get('campaignchain.core.form.type.campaign');
                $campaignType->setBundleName(static::BUNDLE_NAME);
                $campaignType->setModuleIdentifier(static::MODULE_IDENTIFIER);
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
                    $repository = $this->getDoctrine()->getManager();

                    try {
                        $repository->getConnection()->beginTransaction();

                        // Clone the campaign template.
                        $clonedCampaign = $campaignService->cloneCampaign(
                            $fromCampaign
                        );
                        $clonedCampaign->setName($toCampaign->getName());

                        $repository->flush();

                        $this->get('session')->getFlashBag()->add(
                            'success',
                            'The campaign template <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was copied successfully.'
                        );

                        $repository->getConnection()->commit();

                        return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
                    } catch (\Exception $e) {
                        $repository->getConnection()->rollback();
                        throw $e;
                    }
                }

                return $this->render(
                    'CampaignChainCoreBundle:Base:new.html.twig',
                    array(
                        'page_title' => 'Copy '.static::CAMPAIGN_DISPLAY_NAME,
                        'form' => $form->createView(),
                    ));
                break;
            case 'campaignchain/campaign-scheduled/campaignchain-scheduled':
                $scheduledCampaign = $fromCampaign;
                $campaignTemplate = clone $scheduledCampaign;

                $campaignTemplate->setName($scheduledCampaign->getName().' (copied)');
                $campaignType = $this->get('campaignchain.core.form.type.campaign');
                $campaignType->setBundleName(static::BUNDLE_NAME);
                $campaignType->setModuleIdentifier(static::MODULE_IDENTIFIER);
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
                    $repository = $this->getDoctrine()->getManager();

                    try {
                        $repository->getConnection()->beginTransaction();

                        // Clone the campaign template.
                        $clonedCampaign = $campaignService->cloneCampaign(
                            $scheduledCampaign
                        );

                        // Change module relationship of cloned campaign
                        $moduleService = $this->get('campaignchain.core.module');
                        $clonedCampaign->setCampaignModule(
                            $moduleService->getModule(
                                Module::REPOSITORY_CAMPAIGN,
                                static::BUNDLE_NAME,
                                static::MODULE_IDENTIFIER
                            )
                        );
                        // Specify other parameters of copied campaign.
                        $clonedCampaign->setName($campaignTemplate->getName());
                        $clonedCampaign->setHasRelativeDates(true);
                        $clonedCampaign->setStatus(Action::STATUS_PAUSED);
                        $hookService = $this->get('campaignchain.core.hook');
                        $clonedCampaign->setTriggerHook(
                            $hookService->getHook('campaignchain-timespan')
                        );

                        $repository = $this->getDoctrine()->getManager();
                        $repository->flush();

                        // Move the cloned campaign to 2012-01-01 (the default date
                        // for templates).
                        $clonedCampaign = $campaignService->moveCampaign(
                            $clonedCampaign, new \DateTime('2012-01-01'),
                            Action::STATUS_PAUSED
                        );

                        $this->get('session')->getFlashBag()->add(
                            'success',
                            'The campaign template <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $clonedCampaign->getId())).'">'.$clonedCampaign->getName().'</a> was copied successfully.'
                        );

                        $repository->getConnection()->commit();

                        return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
                    } catch (\Exception $e) {
                        $repository->getConnection()->rollback();
                        throw $e;
                    }
                }

                return $this->render(
                    'CampaignChainCoreBundle:Base:new.html.twig',
                    array(
                        'page_title' => 'Copy Scheduled Campaign as '.static::CAMPAIGN_DISPLAY_NAME,
                        'form' => $form->createView(),
                    ));

                break;
        }
    }
}