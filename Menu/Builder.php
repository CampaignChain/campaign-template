<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\Campaign\TemplateBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAware;

class Builder extends ContainerAware
{
    public function detailsTab(FactoryInterface $factory, array $options)
    {
        $id = $this->container->get('request')->get('id');

        $menu = $factory->createItem('root');

        $menu->addChild(
            'Edit',
            array(
                'route' => 'campaignchain_campaign_template_edit',
                'routeParameters' => array(
                    'id' => $id
                )
            )
        );
        $menu->addChild(
            'Timeline',
            array(
                'route' => 'campaignchain_campaign_template_plan_timeline_detail',
                'routeParameters' => array(
                    'id' => $id
                )
            )
        );

        return $menu;
    }
}