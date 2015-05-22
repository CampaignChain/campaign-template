<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Campaign\TemplateBundle\Twig;

use Doctrine\ORM\EntityManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

class CampaignChainCampaignTemplateExtension extends \Twig_Extension
{
    protected $em;
    protected $container;

    public function __construct(EntityManager $em, ContainerInterface $container)
    {
        $this->em = $em;
        $this->container = $container;
    }

    public function getName()
    {
        return 'campaignchain_campaign_template_extension';
    }

    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('campaignchain_btn_convert_tpl', array($this, 'btnConvertTpl'), array('is_safe' => array('html'))),
        );
    }

    public function btnConvertTpl($templateId)
    {
        // Get the available campaign types for conversion
        $qb = $this->em->createQueryBuilder();
        $qb->select('m')
            ->from('CampaignChain\CoreBundle\Entity\Module', 'm')
            ->from('CampaignChain\CoreBundle\Entity\Bundle', 'b')
            ->where('b.name != \'campaignchain/campaign-template\'')
            ->andWhere('b.type = \'campaignchain-campaign\'')
            ->andWhere('m.bundle = b.id')
            ->orderBy('m.displayName', 'ASC');
        $query = $qb->getQuery();
        $campaignTypes = $query->getResult();

        return $this->container->get('templating')->render(
            'CampaignChainCampaignTemplateBundle::btn_convert_tpl_widget.html.twig',
            array(
                'campaign_types' => $campaignTypes,
                'template_id' => $templateId,
            )
        );
    }
}
