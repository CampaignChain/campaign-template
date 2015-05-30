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

use CampaignChain\CoreBundle\Util\DateTimeUtil;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use CampaignChain\CoreBundle\Entity\Campaign;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\GetSetMethodNormalizer;
use CampaignChain\CoreBundle\Entity\Action;

class TemplateController extends Controller
{
    const FORMAT_DATEINTERVAL = 'Years: %Y, months: %m, days: %d, hours: %h, minutes: %i, seconds: %s';
    const BUNDLE_NAME = 'campaignchain/campaign-template';
    const MODULE_IDENTIFIER = 'campaignchain-template';

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
            ->setParameter('bundleName', self::BUNDLE_NAME)
            ->setParameter('moduleIdentifier', self::MODULE_IDENTIFIER)
            ->orderBy('c.name', 'ASC');
        $query = $qb->getQuery();
        $repository_campaigns = $query->getResult();

        return $this->render(
            'CampaignChainCampaignTemplateBundle::index.html.twig',
            array(
                'page_title' => 'Campaign Templates',
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
        $campaignType->setBundleName(self::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);

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
                $campaign = $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $form, true);

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
                'page_title' => 'Create New Campaign Template',
                'form' => $form->createView(),
            ));
    }

    public function editAction(Request $request, $id)
    {
        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(self::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);

        $form = $this->createForm($campaignType, $campaign);

        $form->handleRequest($request);

        if ($form->isValid()) {
            $repository = $this->getDoctrine()->getManager();

            $hookService = $this->get('campaignchain.core.hook');
            $campaign = $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $form);
            $repository->persist($campaign);

            $repository->flush();

            $this->get('session')->getFlashBag()->add(
                'success',
                'Your campaign template <a href="'.$this->generateUrl('campaignchain_core_campaign_edit', array('id' => $campaign->getId())).'">'.$campaign->getName().'</a> was edited successfully.'
            );

            return $this->redirect($this->generateUrl('campaignchain_core_campaign'));
        }

        return $this->render(
            'CampaignChainCoreBundle:Base:new.html.twig',
            array(
                'page_title' => 'Edit Campaign Template',
                'form' => $form->createView(),
            ));
    }

    public function editModalAction(Request $request, $id)
    {
        // TODO: If a campaign is ongoing, only the end date can be changed.
        // TODO: If a campaign is done, it cannot be edited.
        $campaignService = $this->get('campaignchain.core.campaign');
        $campaign = $campaignService->getCampaign($id);

        $campaignType = $this->get('campaignchain.core.form.type.campaign');
        $campaignType->setBundleName(self::BUNDLE_NAME);
        $campaignType->setModuleIdentifier(self::MODULE_IDENTIFIER);
        $campaignType->setView('default');

        $form = $this->createForm($campaignType, $campaign);

        return $this->render(
            'CampaignChainCoreBundle:Base:new_modal.html.twig',
            array(
                'page_title' => 'Edit Campaign Template',
                'form' => $form->createView(),
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
        $hookService->processHooks(self::BUNDLE_NAME, self::MODULE_IDENTIFIER, $campaign, $data);

        $repository->flush();

        $responseData['start_date'] = $campaign->getStartDate()->format(\DateTime::ISO8601);
        $responseData['end_date'] = $campaign->getEndDate()->format(\DateTime::ISO8601);


        $encoders = array(new JsonEncoder());
        $normalizers = array(new GetSetMethodNormalizer());
        $serializer = new Serializer($normalizers, $encoders);

        $response = new Response($serializer->serialize($responseData, 'json'));
        return $response->setStatusCode(Response::HTTP_OK);
    }

    public function getCampaign($id){
        $campaign = $this->getDoctrine()
            ->getRepository('CampaignChainCoreBundle:Campaign')
            ->find($id);

        if (!$campaign) {
            // TODO: Make sure we return this error as a response if it is an API call.
            throw new \Exception(
                'No product found for id '.$id
            );
        }

        return $campaign;
    }
}