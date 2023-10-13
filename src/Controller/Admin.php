<?php

/**
 * Proxmox module for FOSSBilling
 *
 * @author   FOSSBilling (https://www.fossbilling.org) & Anuril (https://github.com/anuril) 
 * @license  GNU General Public License version 3 (GPLv3)
 *
 * This software may contain code previously used in the BoxBilling project.
 * Copyright BoxBilling, Inc 2011-2021
 * Original Author: Scitch (https://github.com/scitch)
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3) that is bundled
 * with this source code in the file LICENSE. 
 * This Module has been written originally by Scitch (https://github.com/scitch) and has been forked from the original BoxBilling Module.
 * It has been rewritten extensively.
 */

namespace Box\Mod\Serviceproxmox\Controller;

class Admin implements \FOSSBilling\InjectionAwareInterface
{
    protected $di;

    public function setDi(\Pimple\Container|null $di): void
    {
        $this->di = $di;
    }

    public function getDi(): ?\Pimple\Container
    {
        return $this->di;
    }

    /**
     * Fetches the navigation array for the admin area
     * 
     * @return array
     */
    public function fetchNavigation(): array
    {
        return array(
            'group' => array(
                'index' => 550,
                'location' => 'proxmox',
                'label' => __trans('Proxmox'),
                'class' => 'server',
                'sprite_class' => 'dark-sprite-icon sprite-graph',
            ),
            'subpages' => array(
                [
                    'location' => 'proxmox',
                    'label' => __trans('Proxmox Servers'),
                    'uri' => $this->di['url']->adminLink('serviceproxmox'),
                    'index' => 100,
                    'class' => '',
                ],
                [
                    'location' => 'proxmox',
                    'label' => __trans('Proxmox Templates'),
                    'uri' => $this->di['url']->adminLink('serviceproxmox/templates'),
                    'index' => 200,
                    'class' => '',
                ],
                [
                    'location' => 'proxmox',
                    'label' => __trans('IP Address Management'),
                    'uri' => $this->di['url']->adminLink('serviceproxmox/ipam'),
                    'index' => 300,
                    'class' => '',
                ],
            ),
        );
    }

    /**
     * Registers the admin area routes
     * 
     * @param \Box_App $app
     * @return void
     */
    public function register(\Box_App &$app): void
    {
        $app->get('/serviceproxmox', 'get_index', null, get_class($this));
        $app->get('/serviceproxmox/templates', 'get_templates', null, get_class($this));
        $app->get('/serviceproxmox/ipam', 'get_ipam', null, get_class($this));
        $app->get('/serviceproxmox/maintenance/backup', 'start_backup', null, get_class($this));
        $app->get('/serviceproxmox/server/:id', 'get_server', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/storage', 'get_storage', null, get_class($this));
        $app->get('/serviceproxmox/server/by_group/:id', 'get_server_by_group',  array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/storage/:id', 'get_storage', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/storageclass', 'get_storage', null, get_class($this));
        $app->get('/serviceproxmox/storageclass/:id', 'get_storageclass ', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/ipam/iprange', 'get_ip_range', null, get_class($this));
        $app->get('/serviceproxmox/ipam/iprange/:id', 'get_ip_range', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/ipam/client_vlan', 'get_client_vlan', null, get_class($this));
        $app->get('/serviceproxmox/ipam/client_vlan/:id', 'get_client_vlan', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/templates/lxc_config', 'get_lxc_config_template', null, get_class($this));
        $app->get('/serviceproxmox/templates/lxc_config/:id', 'get_lxc_config_template', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/templates/vm_config', 'get_vm_config_template', null, get_class($this));
        $app->get('/serviceproxmox/templates/vm_config/:id', 'get_vm_config_template', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/templates/enable/:id', 'enable_template', array('id' => '[0-9]+'), get_class($this));
        $app->get('/serviceproxmox/templates/disable/:id', 'disable_template', array('id' => '[0-9]+'), get_class($this));
    }

    /**
     * Renders the admin area index page
     * 
     * @param \Box_App $app
     * @return string
     */
    public function get_index(\Box_App $app): string
    {
        $this->di['is_admin_logged'];
        return $app->render('mod_serviceproxmox_index');
    }

    /**
     * Renders the admin area templates page
     * 
     * @param \Box_App $app
     * @return string
     */
    public function get_templates(\Box_App $app): string
    {
        return $app->render('mod_serviceproxmox_templates');
    }

    /**
     * Enables the QEMU Template
     *
     * @param \Box_App $app
     */
    public function enable_template(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $api->Serviceproxmox_vm_config_template_enable(array('id' => $id));
        return $app->redirect('serviceproxmox/templates');
    }

    /**
     * Disables the QEMU Template
     *
     * @param \Box_App $app
     */
    public function disable_template(\Box_App $app, $id)
    {
        $api = $this->di['api_admin'];
        $api->Serviceproxmox_vm_config_template_disable(array('id' => $id));
        return $app->redirect('serviceproxmox/templates');
    }
    /**
     * Renders the admin area ipam page
     * 
     * @param \Box_App $app
     * @return string
     */
    public function get_ipam(\Box_App $app): string
    {
        return $app->render('mod_serviceproxmox_ipam');
    }

    /**
     * Handles Updates for Proxmox Servers
     * 
     * @param \Box_App $app
     * @param int $id     
     */
    public function get_server(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $server = $api->Serviceproxmox_server_get(array('server_id' => $id));
        return $app->render('mod_serviceproxmox_server', array('server' => $server));
    }

    /**
     * Retrieves the server by group ID.
     *
     * @param \Box_App $app The Box application instance.
     * @param int $id The ID of the group.
     * @return string The server information.
     */
    public function get_server_by_group(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $server = $api->Serviceproxmox_servers_in_group(array('group' => $id));
        return $app->render('mod_serviceproxmox_server', array('server' => $server));
    }

    /** 
     * Handles Updates for Proxmox Storage
     * 
     * @param \Box_App $app
     * @param int $id
     * 
     * @return string
     */
    public function get_storage(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $storage = $api->Serviceproxmox_storage_get(array('storage_id' => $id));
        return $app->render('mod_serviceproxmox_storage', array('storage' => $storage));
    }

    /**
     * Handles Updates for IP Range
     * 
     * @param \Box_App $app
     * @param int $id
     * 
     * @return string
     */
    public function get_ip_range(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $ip_range = $api->Serviceproxmox_ip_range_get(array('id' => $id));
        return $app->render('mod_serviceproxmox_ipam_iprange', array('ip_range' => $ip_range));
    }

    /**
     * Handles Updates for Client VLAN
     * 
     * @param \Box_App $app
     * @param int $id
     */
    public function client_vlan(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $client_vlan = $api->Serviceproxmox_vlan_get(array('id' => $id));
        return $app->render('mod_serviceproxmox_ipam_client_vlan', ['client_vlan' => $client_vlan]);
    }

    /**
     * Handles Updates for LXC Config Templates
     * 
     * @param \Box_App $app
     * @param int $id
     */
    public function get_lxc_config_template(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $lxc_config_template = $api->Serviceproxmox_lxc_config_template_get(array('id' => $id));
        return $app->render('mod_serviceproxmox_templates_lxc', array('lxc_config_template' => $lxc_config_template));
    }

    /**
     * Handles Updates for VM Config Templates
     * 
     * @param \Box_App $app
     * @param int $id
     * 
     * @return string
     */
    public function get_vm_config_template(\Box_App $app, $id): string
    {
        $api = $this->di['api_admin'];
        $vm_config_template = $api->Serviceproxmox_vm_config_template_get(array('id' => $id));
        return $app->render('mod_serviceproxmox_templates_qemu', array('vm_config_template' => $vm_config_template));
    }

    /**
     * Renders the admin area settings page
     * 
     * @param \Box_App $app
     */
    public function start_backup(\Box_App $app)
    {

        $api = $this->di['api_admin'];
        $api->Serviceproxmox_proxmox_backup_config('backup');
        return $app->redirect('extension/settings/serviceproxmox');
    }
}
