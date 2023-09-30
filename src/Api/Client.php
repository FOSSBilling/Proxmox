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

namespace Box\Mod\Serviceproxmox\Api;

/**
 * Custom product management
 * 
 * 
 */
class Client extends \Api_Abstract
{
    /**
     * Universal method to call method from plugin
     * Pass any other params and they will be passed to plugin
     *
     * @param int $order_id - ID of the order
     *
     * @throws Box_Exception
     */
    public function __call($name, $arguments)
    {
        if (!isset($arguments[0])) {
            throw new \Box_Exception('API call is missing arguments', null, 7103);
        }

        $data = $arguments[0];


        $model = $this->getService()->getServiceproxmoxByOrderId($data['order_id']);

        return $this->getService()->customCall($model, $name, $data);
    }

    /**
     * Get server details
     * 
     * @param int $id - server id
     * @return array
     * 
     * @throws \Box_Exception 
     */
    public function vm_get($data)
    {
        $required = array(
            'order_id'    => 'Order ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->findOne(
            'client_order',
            "id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$order) {
            throw new \Box_Exception('Order not found');
        }

        $service = $this->di['db']->findOne(
            'service_proxmox',
            "order_id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$service) {
            throw new \Box_Exception('Proxmox service not found');
        }

        // Retrieve associated 
        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service['server_id']));

        // if a server has been found, output its details, otherwise return an empty array
        if (!$server) {
            return array();
        }
        $vm_info = $this->getService()->vm_info($order, $service);
        $output = array(
            'server'  => $server->hostname,
            'username'  => 'root',
            'cli'       => $this->getService()->vm_cli($order, $service),
            'status'    => $vm_info['status'],
        );
        return $output;
    }

    public function server_get($data)
    {
        $required = array(
            'order_id'    => 'Order ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->findOne(
            'client_order',
            "id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$order) {
            throw new \Box_Exception('Order not found');
        }

        $service = $this->di['db']->findOne(
            'service_proxmox',
            "order_id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$service) {
            throw new \Box_Exception('Proxmox service not found');
        }

        // Retrieve associated 
        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service['server_id']));

        // if a server has been found, output its details, otherwise return an empty array
        if (!$server) {
            return array();
        }
        return $server;
    }

    // function to return proxmox_service information from order
    public function get_proxmox_service($data)
    {
        $required = array(
            'order_id'    => 'Order ID is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->findOne(
            'client_order',
            "id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$order) {
            throw new \Box_Exception('Order not found');
        }

        $service = $this->di['db']->findOne(
            'service_proxmox',
            "order_id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$service) {
            throw new \Box_Exception('Proxmox service not found');
        }
        return $service;
    }


    /**
     * Reboot vm
     */
    public function vm_manage($data)
    {
        $required = array(
            'order_id'    => 'Order ID is missing',
            'method'      => 'Method is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $order = $this->di['db']->findOne(
            'client_order',
            "id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$order) {
            throw new \Box_Exception('Order not found');
        }

        $service = $this->di['db']->findOne(
            'service_proxmox',
            "order_id=:id",
            array(':id' => $data['order_id'])
        );
        if (!$service) {
            throw new \Box_Exception('Proxmox service not found');
        }

        // Retrieve associated server
        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $service['server_id']));

        switch ($data['method']) {
            case 'reboot':
                $this->getService()->vm_reboot($order, $service);
                break;
            case 'start':
                $this->getService()->vm_start($order, $service);
                break;
            case 'shutdown':
                $this->getService()->vm_shutdown($order, $service);
            default:
                return false;
        }

        return true;
    }

    /**
     * Get VNC console from PVE Host
     */
    public function novnc_appjs_get($data)
    {
        $appjs = $this->getService()->get_novnc_appjs($data);
        return $appjs;
    }
}
