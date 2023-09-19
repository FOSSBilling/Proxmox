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

/* Manage the Proxmox Hosting Service */

class Admin extends \Api_Abstract
{
    /* ################################################################################################### */
    /* ##########################################   Lists   ############################################## */
    /* ################################################################################################### */

    /**
     * Get list of servers
     * 
     * @return array
     */
    public function server_get_list($data)
    {
        // Retrieve all servers from the database
        $servers = $this->di['db']->find('service_proxmox_server');
        $servers_grouped = array();

        // Iterate through each server
        foreach ($servers as $server) {
            // Find all virtual machines (VMs) on this server and calculate CPU cores and RAM usage
            $vms = $this->di['db']->find('service_proxmox', 'server_id=:id', array(':id' => $server->id));
            $server_cpu_cores = 0;
            $server_ram = 0;

            // Count the number of VMs
            $vm_count = 0;
            foreach ($vms as $vm) {
                // Sum up the CPU cores and RAM of each VM
                $server_cpu_cores += $vm->cpu_cores;
                $server_ram += $vm->ram;
                $vm_count++;
            }

            // Calculate the percentage of RAM usage if the server's RAM is not zero
            if ($server->ram != 0) {
                $server_ram_percent = round($server_ram / $server->ram * 100, 0, PHP_ROUND_HALF_DOWN);
            } else {
                $server_ram_percent = 0;
            }

            // Retrieve the overprovisioning factor from the extension's configuration and calculate overprovisioned CPU cores
            $config = $this->di['mod_config']('Serviceproxmox');
            $overprovion_percent = $config['cpu_overprovisioning'];
            $cpu_cores_overprovision = $server->cpu_cores + round($server->cpu_cores * $overprovion_percent / 100, 0, PHP_ROUND_HALF_DOWN);

            // Retrieve the RAM overprovisioning factor from the extension's configuration and calculate overprovisioned RAM
            $ram_overprovion_percent = $config['ram_overprovisioning'];
            $ram_overprovision = round($server->ram / 1024 / 1024 / 1024, 0, PHP_ROUND_HALF_DOWN) + round(round($server->ram / 1024 / 1024 / 1024, 0, PHP_ROUND_HALF_DOWN) * $ram_overprovion_percent / 100, 0, PHP_ROUND_HALF_DOWN);

            // Store the server's group information in the grouped servers array
            $servers_grouped[$server['group']]['group'] = $server->group;

            // Prepare the grouped Server's array for the Template to render.
            $servers_grouped[$server['group']]['servers'][$server['id']] = array(
                'id'                        => $server->id,
                'name'                      => $server->name,
                'group'                     => $server->group,
                'ipv4'                      => $server->ipv4,
                'hostname'                  => $server->hostname,
                'port'                      => $server->port,
                'vm_count'                  => $vm_count,
                'access'                    => $this->getService()->find_access($server),
                'cpu_cores'                 => $server->cpu_cores,
                'cpu_cores_allocated'       => $server->cpu_cores_allocated,
                'cpu_cores_overprovision'   => $cpu_cores_overprovision,
                'cpu_cores_provisioned'     => $server_cpu_cores,
                'ram_provisioned'           => $server_ram,
                'ram_overprovision'         => $ram_overprovision,
                'ram_used'                  => round($server->ram_allocated / 1024 / 1024 / 1024, 0, PHP_ROUND_HALF_DOWN), // TODO: Make nicer?
                'ram'                       => round($server->ram / 1024 / 1024 / 1024, 0, PHP_ROUND_HALF_DOWN), // TODO: Make nicer?
                'ram_percent'               => $server_ram_percent,
                'active'                    => $server->active,
            );
        }
        return $servers_grouped;
    }

    /**
     * Get list of storage
     * 
     * @return array
     */
    public function storage_get_list($data)
    {
        $storages = $this->di['db']->find('service_proxmox_storage');
        $storages_grouped = array();
        foreach ($storages as $storage) {
            $server = $this->di['db']->getExistingModelById('service_proxmox_server', $storage->server_id, 'Server not found');
            switch ($storage->type) {
                case 'local':
                    $storage->type = 'Local';
                    break;
                case 'nfs':
                    $storage->type = 'NFS';
                    break;
                case 'dir':
                    $storage->type = 'Directory';
                    break;
                case 'iscsi':
                    $storage->type = 'iSCSI';
                    break;
                case 'lvm':
                    $storage->type = 'LVM';
                    break;
                case 'lvmthin':
                    $storage->type = 'LVM thinpool';
                    break;
                case 'rbd':
                    $storage->type = 'Ceph';
                    break;
                case 'sheepdog':
                    $storage->type = 'Sheepdog';
                    break;
                case 'glusterfs':
                    $storage->type = 'GlusterFS';
                    break;
                case 'cephfs':
                    $storage->type = 'CephFS';
                    break;
                case 'zfs':
                    $storage->type = 'ZFS';
                    break;
                case 'zfspool':
                    $storage->type = 'ZFS Pool';
                    break;
                case 'iscsidirect':
                    $storage->type = 'iSCSI Direct';
                    break;
                case 'drbd':
                    $storage->type = 'DRBD';
                    break;
                case 'dev':
                    $storage->type = 'Device';
                    break;
                case 'pbs':
                    $storage->type = 'Proxmox Backup Server';
                    break;
            }
            $storages_grouped[$storage['type']]['group'] = $storage->type;
            // Map storage group types to better descriptions for display
            // TODO: Add translations


            $storages_grouped[$storage['type']]['storages'][$storage['id']] = array(
                'id'            => $storage->id,
                'servername'    => $server->name,
                'storageclass'  => $storage->storageclass,
                'name'          => $storage->storage,
                'content'       => $storage->content,
                'type'          => $storage->type,
                'active'        => $storage->active,
                'size'          => $storage->size,
                'used'          => $storage->used,
                'free'          => $storage->free,
                'percent_used'  => round($storage->used / $storage->size * 100, 2),
            );
        }
        return $storages_grouped;
    }

    /**
     * Get list of storageclasses
     * 
     * @return array
     */

    public function storageclass_get_list($data)
    {
        $storageclasses = $this->di['db']->find('service_proxmox_storageclass');
        return $storageclasses;
    }

    /**
     * Get list of storage controllers
     * 
     * @return array
     */
    public function storage_controller_get_list($data)
    {
        // Return Array of storage controllers: 	
        // lsi | lsi53c810 | virtio-scsi-pci | virtio-scsi-single | megasas | pvscsi
        $storage_controllers = array(
            'lsi' => 'LSI',
            'lsi53c810' => 'LSI 53C810',
            'virtio-scsi-pci' => 'VirtIO SCSI PCI',
            'virtio-scsi-single' => 'VirtIO SCSI Single',
            'megasas' => 'MegaSAS',
            'pvscsi' => 'PVSCSI',
            'sata' => 'SATA',
            'ide' => 'IDE',
        );
        return $storage_controllers;
    }

    /** *
     * Get list of Active Services
     * 
     * @return array
     */
    public function service_proxmox_get_list($data)
    {
        $services = $this->di['db']->find('service_proxmox');
        return $services;
    }

    /**
     * Create a new storageclass
     * 
     * @return array
     */
    public function storageclass_create($data)
    {
        $storageclass = $this->di['db']->dispense('service_proxmox_storageclass');
        $storageclass->storageclass = $data['storageClassName'];
        $this->di['db']->store($storageclass);
        return $storageclass;
    }

    /**
     * Retrieve a single storageclass
     * 
     * @return array
     */
    public function storageclass_get($data)
    {
        $storageclass = $this->di['db']->getExistingModelById('service_proxmox_storageclass', $data['id'], 'Storageclass not found');
        return $storageclass;
    }

    /** 
     *	Get list of server groups
     *
     *   @return array
     */
    public function server_groups()
    {
        $sql = "SELECT DISTINCT `group` FROM `service_proxmox_server` WHERE `active` = 1";
        $groups = $this->di['db']->getAll($sql);
        return $groups;
    }

    /** 
     * get list of servers in a group
     * 
     */
    public function servers_in_group($data)
    {
        $sql = "SELECT * FROM `service_proxmox_server` WHERE `group` = '" . $data['group'] . "' AND `active` = 1";

        $servers = $this->di['db']->getAll($sql);

        // remove password & api keys from results
        foreach ($servers as $key => $server) {
            $servers[$key]['root_password'] = '';
            $servers[$key]['tokenvalue'] = '';
        }

        return $servers;
    }

    /**
     * Get list of qemu templates for a server
     * 
     */
    public function qemu_templates_on_server($data)
    {
        $sql = "SELECT * FROM `service_proxmox_qemu_template` WHERE `server_id` = '" . $data['server_id'] . "'";
        $templates = $this->di['db']->getAll($sql);
        return $templates;
    }


    /**
     * Get list of OS types
     * 
     * @return array
     */

    public function os_get_list()
    {
        $os_types = array(
            'other' => 'Other',
            'wxp' => 'Windows XP',
            'w2k' => 'Windows 2000',
            'w2k3' => 'Windows 2003',
            'w2k8' => 'Windows 2008',
            'wvista' => 'Windows Vista',
            'win7' => 'Windows 7',
            'win8' => 'Windows 8',
            'win10' => 'Windows 10',
            'win11' => 'Windows 11',
            'l24' => 'Linux 2.4 Kernel',
            'l26' => 'Linux 2.6 Kernel',
            'solaris' => 'Solaris',
        );
        return $os_types;
    }

    /**
     * Get list of BIOS types
     * 
     * @return array
     */
    public function bios_get_list()
    {
        $bios_types = array(
            'seabios' => 'SeaBIOS',
            'ovmf' => 'OVMF (UEFI)',
        );
        return $bios_types;
    }

    /**
     * Get list of VNC types
     * 
     * @return array
     */
    public function lxc_appliance_get_list()
    {
        // get all service_proxmox_lxc_appliances
        $lxc_appliance = $this->di['db']->find('service_proxmox_lxc_appliance');
        // sort alphabetically by headline
        usort($lxc_appliance, function ($a, $b) {
            return strcmp($a->headline, $b->headline);
        });
        return $lxc_appliance;
    }

    // Function to get list of lxc config templates
    public function get_lxc_config_template()
    {
        $lxc_tmpl = $this->di['db']->find('service_proxmox_lxc_config_template');
        return $lxc_tmpl;
    }

    /* ################################################################################################### */
    /* ##########################################  Servers  ############################################## */
    /* ################################################################################################### */

    /**
     * Create new hosting server 
     * 
     * @param string $name - server name
     * @param string $ipv4 - server ipv4
     * @param string $hostname - server hostname
     * @param string $port - server port
     * @param string $root_user - server root user
     * @param string $root_password - server root password
     * @param string $realm - server realm
     *
     * @return bool - server id 
     * 
     * @throws \Box_Exception 
     */
    public function server_create($data)
    {
        // enable api token & secret
        $required = array(
            'name'              => 'Server name is missing',
            'ipv4'              => 'Server ipv4 is missing',
            'hostname'          => 'Server hostname is missing',
            'port'              => 'Server port is missing',
            'root_user'         => 'Root user is missing',
            'root_password'     => 'Root password is missing',
            'realm'             => 'Proxmox user realm is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server                     = $this->di['db']->dispense('service_proxmox_server');
        $server->name               = $data['name'];
        $server->group              = $data['group'];
        $server->ipv4               = $data['ipv4'];
        $server->ipv6               = $data['ipv6'];
        $server->hostname           = $data['hostname'];
        $server->port               = $data['port'];
        $server->realm              = $data['realm'];
        $server->root_user          = $data['root_user'];
        $server->root_password      = $data['root_password'];
        $server->config             = $data['config'];
        $server->active             = $data['active'];
        $server->created_at         = date('Y-m-d H:i:s');
        $server->updated_at         = date('Y-m-d H:i:s');

        $this->di['db']->store($server);

        $this->di['logger']->info('Created Proxmox server %s', $server->id);
        // Validate server by testing connection
        $this->getService()->test_connection;

        return true;
    }

    /**
     * Get server details
     * 
     * @param int $id - server id
     * @return array
     * 
     * @throws \Box_Exception 
     */
    public function server_get($data)
    {
        // Retrieve associated server
        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $data['server_id']));
        if (!$server) {
            throw new \Box_Exception('Server not found');
        }

        $output = array(
            'id'                => $server->id,
            'name'              => $server->name,
            'group'             => $server->group,
            'ipv4'              => $server->ipv4,
            'ipv6'              => $server->ipv6,
            'hostname'          => $server->hostname,
            'port'              => $server->port,
            'realm'             => $server->realm,
            'tokenname'         => $server->tokenname,
            'tokenvalue'        => str_repeat("*", 26),
            'root_user'         => $server->root_user,
            'root_password'     => $server->root_password,
            'admin_password'    => $server->admin_password,
            'active'            => $server->active,
        );
        return $output;
    }

    /**
     * Update server configuration
     * 
     * @param int $id - server id
     * @param string $name - server name
     * @param string $ipv4 - server ipv4
     * @param string $hostname - server hostname
     * @param string $port - server port
     * @param string $root_user - server root user
     * @param string $realm - server realm
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function server_update($data)
    {
        $required = array(
            'name'    => 'Server name is missing',
            'root_user'      => 'Root user is missing',
            'ipv4'      => 'Server ipv4 is missing',
            'hostname'      => 'Server hostname is missing',
            'port'      => 'Server port is missing',
            'realm'      => 'Proxmox user realm is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server  = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $data['server_id']));

        $server->name             = $data['name'];
        $server->group            = $data['group'];
        $server->ipv4             = $data['ipv4'];
        $server->ipv6             = $data['ipv6'];
        $server->hostname         = $data['hostname'];
        $server->port             = $data['port'];
        $server->realm            = $data['realm'];
        $server->cpu_cores        = $data['cpu_cores'];
        $server->ram              = $data['ram'];
        $server->root_user        = $data['root_user'];
        $server->root_password   = $data['root_password'];
        $server->tokenname        = $data['tokenname'];
        $server->config           = $data['config'];
        $server->active           = $data['active'];
        $server->created_at       = date('Y-m-d H:i:s');
        $server->updated_at       = date('Y-m-d H:i:s');

        $this->di['db']->store($server);

        $this->di['logger']->info('Update Proxmox server %s', $server->id);

        return true;
    }

    /**
     * Delete server
     * 
     * @param int $id - server id
     * @return bool
     * @throws \Box_Exception 
     */
    public function server_delete($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // check if there are services provisioned on this server
        $vms = $this->di['db']->find('service_proxmox', 'server_id=:server_id', array(':server_id' => $data['id']));

        // if there are vms provisioned on this server, throw an exception
        if (!empty($vms)) {
            throw new \Box_Exception('VMs are still provisioned on this server');
        } else {
            // delete storages
            $storages = $this->di['db']->find('service_proxmox_storage', 'server_id=:server_id', array(':server_id' => $data['id']));
            foreach ($storages as $storage) {
                $this->di['db']->trash($storage);
            }

            // delete server
            $server = $this->di['db']->getExistingModelById('service_proxmox_server', $data['id'], 'Server not found');
            $this->di['db']->trash($server);
        }
    }

    /**
     *   Get server details from order id
     *   This is used to manage the service from the order. 
     *   TODO: Remove this function and use server_get instead 
     *   @param int $order_id
     *   @return array
     */

    public function server_get_from_order($data)
    {
        $required = array(
            'order_id'    => 'Order id is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $service = $this->di['db']->findOne(
            'service_proxmox',
            "order_id=:id",
            array(':id' => $data['order_id'])
        );

        if (!$service) {
            return null;
        }

        $data = array('server_id' => $service['server_id']);
        $output = $this->server_get($data);
        return $output;
    }


    /**
     * Receive Hardware Data from proxmox server
     *
     * @param int $server_id
     * @return array
     */
    public function get_hardware_data($server_id)
    {
        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $server_id, 'Server not found');
        $service = $this->getService();
        $hardware_data = $service->getHardwareData($server);
        $server->cpu_cores = $hardware_data['cpuinfo']['cores'];
        $server->ram = $hardware_data['memory']['total'];

        $serverstorage = $service->getStorageData($server);

        foreach ($serverstorage as $key => $value) {
            $sql = "SELECT * FROM `service_proxmox_storage` WHERE server_id = " . $server_id . " AND storage = '" . $value['storage'] . "'";
            $storage = $this->di['db']->getAll($sql);

            // if the storage exists, update it, otherwise create it
            if (!empty($storage)) {
                $storage = $this->di['db']->findOne('service_proxmox_storage', 'server_id=:server_id AND storage=:storage', array(':server_id' => $server_id, ':storage' => $value['storage']));
            } else {
                $storage = $this->di['db']->dispense('service_proxmox_storage');
            }

            $storage->server_id = $server_id;
            $storage->storage = $value['storage'];
            $storage->type = $value['type'];
            $storage->content = $value['content'];

            $storage->used = $value['used'] / 1000 / 1000 / 1000;
            $storage->size = $value['total'] / 1000 / 1000 / 1000;
            $storage->free = $value['avail'] / 1000 / 1000 / 1000;

            $storage->active = $value['active'];
            $this->di['db']->store($storage);
        }
        $this->di['db']->store($server);
        $allresources = $service->getAssignedResources($server);
        // summarzie the fields cpus and maxmem for each vm and store it in the server table
        $server->cpu_cores_allocated = 0;
        $server->ram_allocated = 0;
        foreach ($allresources as $key => $value) {
            $server->cpu_cores_allocated += $value['cpus'];
            $server->ram_allocated += $value['maxmem'];
        }
        $this->di['db']->store($server);
        $qemu_templates = $service->getQemuTemplates($server);
        foreach ($qemu_templates as $key => $value) {
            if ($value['template'] == 1) {
                $sql = "SELECT * FROM `service_proxmox_qemu_template` WHERE server_id = " . $server_id . " AND vmid = " . $value['vmid'];
                $template = $this->di['db']->getAll($sql);

                // if the template exists, update it, otherwise create it
                if (!empty($template)) {
                    $template = $this->di['db']->findOne('service_proxmox_qemu_template', 'server_id=:server_id AND vmid=:vmid', array(':server_id' => $server_id, ':vmid' => $value['vmid']));
                } else {
                    $template = $this->di['db']->dispense('service_proxmox_qemu_template');
                }
                $template->vmid = $value['vmid'];
                $template->server_id = $server_id;
                $template->name = $value['name'];
                $template->created_at = date('Y-m-d H:i:s', $value['ctime']);
                $template->updated_at = date('Y-m-d H:i:s', $value['ctime']);

                $stored = $this->di['db']->store($template);
                error_log('template saved: ' . print_r($stored, true));
            }
        }

        return $hardware_data;
    }


    /**
     * Test connection to server
     * 
     * @param int $id - server id
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function server_test_connection($data)
    {

        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $data['id'], 'Server not found');

        if ($this->getService()->test_connection($server)) {
            $this->get_hardware_data($data['id']);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Prepare Server for PVE
     * 
     * @param int $id - server id
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function server_prepare_pve_setup($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $data['id'], 'Server not found');
        $updatedserver = $this->getService()->prepare_pve_setup($server);
        if ($updatedserver) {
            $this->di['db']->store($updatedserver);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Test access to server
     * This method can be removed at a later date as it is primarily a debugging tool.
     * @param int $id - server id
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function test_access($data)
    {
        $required = array(
            'id'    => 'Server id is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        $server = $this->di['db']->getExistingModelById('service_proxmox_server', $data['id'], 'Server not found');

        if ($this->getService()->test_access($server)) {
            return true;
        } else {
            return false;
        }
    }


    /**
     * Get all available templates from any proxmox server
     * 
     * @return array
     * @throws \Box_Exception 
     */
    public function pull_lxc_appliances()
    {
        $appliances = $this->getService()->getAvailableAppliances();


        foreach ($appliances as $appliance) {
            // check if the appliance already exists
            //$appliance = $this->di['db']->findOne('service_proxmox_lxc_appliance', 'sha512sum=:sha512sum', array(':sha512sum' => $appliance['sha512sum']));
            // if the appliance exists, update it, otherwise create it

            $template = $this->di['db']->dispense('service_proxmox_lxc_appliance');
            $template->headline = $appliance['headline'];
            $template->package = $appliance['package'];
            $template->section = $appliance['section'];
            $template->type = $appliance['type'];
            $template->source = $appliance['source'];
            $template->headline = $appliance['headline'];
            $template->location = $appliance['location'];
            // if description is empty, use the headline
            if (empty($appliance['description'])) {
                $appliance['description'] = $appliance['headline'];
            }
            $template->description = $appliance['description'];
            $template->template = $appliance['template'];
            $template->os = $appliance['os'];
            $template->infopage = $appliance['infopage'];
            $template->version = $appliance['version'];
            $template->sha512sum = $appliance['sha512sum'];
            $template->architecture = $appliance['architecture'];
            $this->di['db']->store($template);
        }
        return true;
    }

    /* ################################################################################################### */
    /* ##########################################  Storage  ############################################## */
    /* ################################################################################################### */


    /**
     * Get a storage
     * 
     * @param int $id - storage id
     * 
     * @return array
     * @throws \Box_Exception 
     */
    public function storage_get($data)
    {
        // Retrieve associated storage
        $storage  = $this->di['db']->findOne('service_proxmox_storage', 'id=:id', array(':id' => $data['storage_id']));

        $output = array(
            'id'                => $storage->id,
            'name'              => $storage->name,
            'server_id'         => $storage->server_id,
            'storageclass'      => $storage->storageclass,
            'storage'           => $storage->storage,
            'content'           => $storage->content,
            'type'              => $storage->type,
            'active'            => $storage->active,
            'size'              => $storage->size,
            'used'              => $storage->used,
            'free'              => $storage->free,
            'percent_used'      => round($storage->used / $storage->size * 100, 2),
            // add list of storage classes
            'storageclasses'    => $this->storageclass_get_list($data),
        );

        return $output;
    }


    /**
     * Update a storage with storageclasses
     * TODO: Implement & Fix functionality
     * @param int $id - server id
     * 
     * @return array
     * @throws \Box_Exception 
     */
    public function storage_update($data)
    {
        $required = array(
            'id'    => 'Storage id is missing',
            'storageclass'    => 'Storage class is missing',
        );
        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Retrieve associated storage
        $storage = $this->di['db']->findOne('service_proxmox_storage', 'id=:id', array(':id' => $data['id']));
        $storage->storageclass = $data['storageclass'];
        $this->di['db']->store($storage);

        return true;
    }


    /**
     * Update Product
     * 
     * @param array $data
     * 
     * @return bool
     * @throws \Box_Exception 
     */
    public function product_update($data)
    {
        $required = array(
            'id'            => 'Product id is missing',
            'group'         => 'Server group is missing',
            'filling'       => 'Filling method is missing',
            'show_stock'    => 'Stock display (Show Stock) is missing',
            'server'        => 'Server is missing',
            'virt'          => 'Virtualization type is missing',

        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Check if virt is lxc or qemu, and if lxc, check if lxc-templ is set. if qemu, check if vm-templ is set.
        if ($data['virt'] == 'lxc' && empty($data['lxc-templ'])) {
            throw new \Box_Exception('LXC Template is missing');
        } elseif ($data['virt'] == 'qemu' && empty($data['vm-templ'])) {
            throw new \Box_Exception('VM Template is missing');
        }

        // Retrieve associated product
        $product  = $this->di['db']->findOne('product', 'id=:id', array(':id' => $data['id']));

        $config = array(
            'group'         => $data['group'],
            'filling'       => $data['filling'],
            'show_stock'    => $data['show_stock'],
            'virt'          => $data['virt'],
            'server'        => $data['server'],
            'lxc-templ'     => $data['lxc-templ'],
            'vm-templ'      => $data['vm-templ'],
            'vmconftempl' => $data['vmconftempl'],
        );

        $product->config         = json_encode($config);
        $product->updated_at    = date('Y-m-d H:i:s');
        $this->di['db']->store($product);

        $this->di['logger']->info('Update Proxmox product %s', $product->id);
        return true;
    }

    /* ################################################################################################### */
    /* ####################################  Resource Management  ######################################## */
    /* ################################################################################################### */

    /** 
     * Get list of vm templates
     * 
     * @return array
     */
    public function service_get_vmtemplates()
    {
        $output = $this->getService()->get_vmtemplates();
        return $output;
    }

    /**
     * Get list of qemu templates
     * 
     * @return array
     */
    public function service_get_qemutemplates()
    {
        $output = $this->getService()->get_qemutemplates();
        return $output;
    }

    /**
     * Get list of lxc templates
     * 
     * @return array
     */
    public function service_get_lxctemplates()
    {
        $output = $this->getService()->get_lxctemplates();
        return $output;
    }

    /**
     * Get list of ip ranges
     * 
     * @return array
     */
    public function service_get_ip_ranges()
    {
        $output = $this->getService()->get_ip_ranges();
        return $output;
    }

    /**
     * Get list of ip adresses
     * 
     */
    public function service_get_ip_adresses()
    {
        $output = $this->getService()->get_ip_adresses();
        return $output;
    }

    /**
     * Get list of vlans
     * 
     * @return array
     */
    public function service_get_vlans()
    {
        $output = $this->getService()->get_vlans();
        return $output;
    }

    /**
     * Get list of tags by input
     * 
     * @return array
     */
    public function service_get_tags($data)
    {
        $output = $this->getService()->get_tags($data);
        return $output;
    }


    /**
     * Get a vm configuration templates
     * 
     * @return array
     */
    public function vm_config_template_get($data)
    {
        error_log("vm_config_template_get");
        $vm_config_template = $this->di['db']->findOne('service_proxmox_vm_config_template', 'id=:id', array(':id' => $data['id']));
        if (!$vm_config_template) {
            throw new \Box_Exception('VM template not found');
        }
        $output = array(
            'id'            => $vm_config_template->id,
            'name'          => $vm_config_template->name,
            'cores'         => $vm_config_template->cores,
            'description'   => $vm_config_template->description,
            'memory'        => $vm_config_template->memory,
            'balloon'       => $vm_config_template->balloon,
            'balloon_size'  => $vm_config_template->balloon_size,
            'os'            => $vm_config_template->os,
            'bios'          => $vm_config_template->bios,
            'onboot'        => $vm_config_template->onboot,
            'agent'         => $vm_config_template->agent,
            'created_at'    => $vm_config_template->created_at,
            'updated_at'    => $vm_config_template->updated_at,
        );

        return $output;
    }

    /**
     * Function to get storages for vm config template
     * 
     * @return array
     */

    public function vm_config_template_get_storages($data)
    {
        $vm_config_template = $this->di['db']->find('service_proxmox_vm_storage_template', 'template_id=:id', array(':id' => $data['id']));

        return $vm_config_template;
    }

    /**
     * Get list of lxc configuration templates
     * 
     * @return array
     */
    public function lxc_config_template_get($data)
    {
        $lxc_config_template = $this->di['db']->findOne('service_proxmox_lxc_config_template', 'id=:id', array(':id' => $data['id']));
        if (!$lxc_config_template) {
            throw new \Box_Exception('LXC template not found');
        }

        $output = array(
            'id'            => $lxc_config_template->id,
            'name'          => $lxc_config_template->name,
            'template_id'   => $lxc_config_template->template_id,
            'cores'         => $lxc_config_template->cores,
            'description'   => $lxc_config_template->description,
            'memory'        => $lxc_config_template->memory,
            'swap'          => $lxc_config_template->swap,
            'ostemplate'    => $lxc_config_template->ostemplate,
            'onboot'        => $lxc_config_template->onboot,
            'created_at'    => $lxc_config_template->created_at,
            'updated_at'    => $lxc_config_template->updated_at,
        );

        return $output;
    }


    /**
     * Get ip range
     * 
     * @return array
     */
    public function ip_range_get($data)
    {
        $ip_range = $this->di['db']->findOne('service_proxmox_ip_range', 'id=:id', array(':id' => $data['id']));
        if (!$ip_range) {
            throw new \Box_Exception('IP range not found');
        }
        $output = array(
            'id'            => $ip_range->id,
            'cidr'          => $ip_range->cidr,
            'gateway'       => $ip_range->gateway,
            'broadcast'     => $ip_range->broadcast,
            'type'          => $ip_range->type,
            'created_at'    => $ip_range->created_at,
            'updated_at'    => $ip_range->updated_at,
        );

        return $output;
    }

    /**
     * Get vlan
     */
    public function vlan_get($data)
    {
        $vlan = $this->di['db']->findOne('service_proxmox_client_vlan', 'id=:id', array(':id' => $data['id']));
        if (!$vlan) {
            throw new \Box_Exception('VLAN not found');
        }

        // fill client_name field
        $client = $this->di['db']->findOne('client', 'id=:id', array(':id' => $vlan->client_id));
        if (!$client) {
            throw new \Box_Exception('Client not found');
        }

        // get IP Range cidr 
        $iprange = $this->di['db']->findOne('service_proxmox_ip_range', 'id=:id', array(':id' => $vlan->ip_range));
        $output = array(
            'id'            => $vlan->id,
            'client_id'     => $vlan->client_id,
            'client_name'   => $client->first_name . " " . $client->last_name,
            'vlan'          => $vlan->vlan,
            'ip_range'      => $vlan->ip_range,
            'cidr'          => $iprange->cidr,
            'created_at'    => $vlan->created_at,
            'updated_at'    => $vlan->updated_at,
        );

        return $output;
    }


    /**
     * Create vm configuration template
     * 
     * @return bool
     */
    public function vm_config_template_create($data)
    {
        $required = array(
            'name'     => 'Server name is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // dispense new vm_config_template
        $vm_config_template = $this->di['db']->dispense('service_proxmox_vm_config_template');
        // Fill vm_config_template
        $vm_config_template->name = $data['name'];
        $vm_config_template->state = "draft";
        /* $vm_config_template->description = $data['description'];
        $vm_config_template->cores = $data['cpu_cores'];
        $vm_config_template->memory = $data['vmmemory'];
        $vm_config_template->balloon = $data['balloon'];
        // if $data['ballon'] is true, check if $data['ballon_size'] is set, otherwise use $data['memory']
        if ($data['balloon'] == true) {
            if (isset($data['balloon_size'])) {
                $vm_config_template->balloon_size = $data['balloon_size'];
            } else {
                $vm_config_template->balloon_size = $data['memory'];
            }
        }
        $vm_config_template->os = $data['os'];
        $vm_config_template->bios = $data['bios'];
        $vm_config_template->onboot = $data['onboot'];
        $vm_config_template->agent = $data['agent'];
        $vm_config_template->created_at = date('Y-m-d H:i:s');
        $vm_config_template->updated_at = date('Y-m-d H:i:s'); */
        $this->di['db']->store($vm_config_template);


        $this->di['logger']->info('Create VM config Template %s', $vm_config_template->id);
        return $vm_config_template;
    }

    /**
     * Update vm configuration template
     * 
     * @return bool
     */
    public function vm_template_update($data)
    {
        $required = array(
            'name'     => 'Server name is missing',
            'description'          => 'Server description is missing',
            'cpu_cores'          => 'CPU cores are missing',
            'vmmemory'    => 'memory is missing',
            'balloon'        => 'Balloon is missing',
            'os'          => 'OS is missing',
            'bios'       => 'Bios Type is missing',
            'onboot'          => 'Start on Boot is missing',
            'agent'         => 'Run Agent is missing'
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Retrieve associated vm_config_template
        $vm_config_template  = $this->di['db']->findOne('service_proxmox_vm_config_template', 'id=:id', array(':id' => $data['id']));

        // Fill vm_config_template
        $vm_config_template->name = $data['name'];
        $vm_config_template->description = $data['description'];
        $vm_config_template->cores = $data['cpu_cores'];
        $vm_config_template->memory = $data['vmmemory'];
        $vm_config_template->balloon = $data['balloon'];
        // if $data['ballon'] is true, check if $data['ballon_size'] is set, otherwise use $data['memory']
        if ($data['balloon'] == true) {
            if (isset($data['balloon_size'])) {
                $vm_config_template->balloon_size = $data['balloon_size'];
            } else {
                $vm_config_template->balloon_size = $data['memory'];
            }
        }
        $vm_config_template->os = $data['os'];
        $vm_config_template->bios = $data['bios'];
        $vm_config_template->onboot = $data['onboot'];
        $vm_config_template->agent = $data['agent'];
        $vm_config_template->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($vm_config_template);

        $this->di['logger']->info('Update VM config Template %s', $vm_config_template->id);
        return true;
    }

    /**
     * Delete vm configuration template
     * 
     * @return bool
     */
    public function vm_template_delete($id)
    {
        $vm_config_template = $this->di['db']->findOne('service_proxmox_vm_config_template', 'id = ?', [$id]);

        // TODO: Check if vm_config_template is used by any product

        $this->di['db']->trash($vm_config_template);
        $this->di['logger']->info('Delete VM config Template %s', $id);
        return true;
    }

    /**
     * Create lxc configuration template
     * 
     * @return bool
     */
    public function lxc_template_create($data)
    {
        $required = array(
            'description'          => 'Template description is missing',
            'cpu_cores'          => 'CPU cores are missing',
            'memory'    => 'memory is missing',
            'swap'        => 'Swap is missing',
            'ostemplate'          => 'OS template is missing',
            'onboot'       => 'Start on Boot is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // dispense new vm_config_template
        $lxc_config_template = $this->di['db']->dispense('service_proxmox_lxc_config_template');
        // Fill vm_config_template
        $lxc_config_template->description = $data['description'];
        $lxc_config_template->cores = $data['cpu_cores'];
        $lxc_config_template->memory = $data['memory'];
        $lxc_config_template->swap = $data['swap'];
        $lxc_config_template->template_id = $data['ostemplate'];
        // get os template headline from $di['db'] and set it to $lxc_config_template->ostemplate
        $ostemplate = $this->di['db']->findOne('service_proxmox_lxc_appliance', 'id = ?', [$data['ostemplate']]);
        $lxc_config_template->ostemplate = $ostemplate->headline;
        $lxc_config_template->onboot = $data['onboot'];
        $lxc_config_template->created_at = date('Y-m-d H:i:s');
        $lxc_config_template->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($lxc_config_template);

        $this->di['db']->store($lxc_config_template);


        $this->di['logger']->info('Create LXC config Template %s', $lxc_config_template->id);
        return true;
    }


    /**
     * Update lxc configuration template
     * 
     * @return bool
     */
    public function lxc_template_update($data)
    {
        $required = array(
            'id'          => 'Template ID is missing',
            'description'          => 'Template description is missing',
            'name'     => 'Server name is missing',
            'cpu_cores'          => 'CPU cores are missing',
            'memory'    => 'memory is missing',
            'swap'        => 'Swap is missing',
            'ostemplate'          => 'OS template is missing',
            'onboot'       => 'Start on Boot is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Retrieve associated lxc_config_template
        $lxc_config_template = $this->di['db']->findOne('service_proxmox_lxc_config_template', 'id=:id', array(':id' => $data['id']));

        // Fill lxc_config_template
        $lxc_config_template->description = $data['description'];
        $lxc_config_template->name = $data['name'];
        $lxc_config_template->cores = $data['cpu_cores'];
        $lxc_config_template->memory = $data['memory'];
        $lxc_config_template->swap = $data['swap'];
        $lxc_config_template->template_id = $data['ostemplate'];
        // get os template headline from $di['db'] and set it to $lxc_config_template->ostemplate
        $ostemplate = $this->di['db']->findOne('service_proxmox_lxc_appliance', 'id = ?', [$data['ostemplate']]);
        $lxc_config_template->ostemplate = $ostemplate->headline;
        $lxc_config_template->onboot = $data['onboot'];
        $lxc_config_template->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($lxc_config_template);

        $this->di['logger']->info('Update LXC config Template %s', $lxc_config_template->id);
        return true;
    }

    /**
     * Delete lxc configuration template
     * 
     * @return bool
     */
    public function lxc_template_delete($id)
    {
        $lxc_config_template = $this->di['db']->findOne('service_proxmox_lxc_config_template', 'id = ?', [$id]);
        $this->di['db']->trash($lxc_config_template);
        $this->di['logger']->info('Delete LXC config Template %s', $id);
        return true;
    }

    /**
     * Create ip range
     * 
     * @return bool
     */
    public function ip_range_create($data)
    {
        $required = array(
            'cidr'          => 'CIDR is missing',
            'gateway'    => 'Gateway is missing',
            'broadcast'        => 'Broadcast is missing',
            'type'          => 'Type is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // dispense new ip_network
        $ip_range = $this->di['db']->dispense('service_proxmox_ip_range');
        // Fill ip_network
        $ip_range->cidr = $data['cidr'];
        $ip_range->gateway = $data['gateway'];
        $ip_range->broadcast = $data['broadcast'];
        $ip_range->type = $data['type'];
        $ip_range->created_at = date('Y-m-d H:i:s');
        $ip_range->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ip_range);


        $this->di['logger']->info('Create IP Network %s', $ip_range->id);
        return true;
    }

    /**
     * Update ip range
     * 
     * @return bool
     */
    public function ip_range_update($data)
    {
        $required = array(
            'id'          => 'ID is missing',
            'cidr'          => 'CIDR is missing',
            'gateway'    => 'Gateway is missing',
            'broadcast'        => 'Broadcast is missing',
            'type'          => 'Type is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Retrieve associated ip_network
        $ip_range  = $this->di['db']->findOne('service_proxmox_ip_range', 'id=:id', array(':id' => $id));

        // Fill ip_network
        $ip_range->cidr = $data['cidr'];
        $ip_range->gateway = $data['gateway'];
        $ip_range->broadcast = $data['broadcast'];
        $ip_range->type = $data['type'];
        $ip_range->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($ip_range);

        $this->di['logger']->info('Update IP Network %s', $ip_range->id);
        return true;
    }

    /**
     * Delete ip range
     * 
     * @param  int $id
     * @return array
     */
    public function ip_range_delete($id)
    {
        $ip_network = $this->di['db']->findOne('service_proxmox_ip_range', 'id = ?', [$id]);
        $this->di['db']->trash($ip_network);
        $this->di['logger']->info('Delete IP Network %s', $id);
        return true;
    }


    /**
     * Create client vlan
     * 
     * @return bool
     */
    public function client_vlan_create($data)
    {
        $required = array(
            'client_id'          => 'Client ID is missing',
            'vlan'    => 'VLAN ID is missing',
            'ip_range'        => 'IP_range is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // dispense new client_network
        $client_network = $this->di['db']->dispense('service_proxmox_client_vlan');
        // Fill client_network
        $client_network->client_id = $data['client_id'];
        $client_network->vlan = $data['vlan'];
        $client_network->ip_range = $data['ip_range'];
        $client_network->created_at = date('Y-m-d H:i:s');
        $client_network->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($client_network);


        $this->di['logger']->info('Create Client Network %s', $client_network->id);
        return true;
    }

    /**
     * Update client vlan
     * 
     * @return bool
     */
    public function client_vlan_update($data)
    {
        $required = array(
            'id'          => 'ID is missing',
            'client_id'          => 'Client ID is missing',
            'vlan'    => 'VLAN ID is missing',
            'ip_range'        => 'ip_range is missing',
        );

        $this->di['validator']->checkRequiredParamsForArray($required, $data);

        // Retrieve associated client_network
        $client_network  = $this->di['db']->findOne('service_proxmox_client_vlan', 'id=:id', array(':id' => $data['id']));

        // Fill client_network
        $client_network->client_id = $data['client_id'];
        $client_network->vlan = $data['vlan'];
        $client_network->ip_range = $data['ip_range'];
        $client_network->updated_at = date('Y-m-d H:i:s');
        $this->di['db']->store($client_network);

        $this->di['logger']->info('Update Client Network %s', $client_network->id);
        return true;
    }


    /**
     * Delete client vlanx
     * 
     * @param  int $id
     * @return bool
     */
    public function client_vlan_delete($data)
    {
        $client_network = $this->di['db']->findOne('service_proxmox_client_vlan', 'id = ?', [$data['id']]);
        $this->di['db']->trash($client_network);
        $this->di['logger']->info('Delete Client Network %s', $id);
        return true;
    }

    /* ################################################################################################### */
    /* ########################################  Permissions  ############################################ */
    /* ################################################################################################### */


    /* ################################################################################################### */
    /* ########################################  Maintenance  ############################################ */
    /* ################################################################################################### */

    /**
     * Backup Module Configuration Tables
     * 
     * @return bool
     */
    public function proxmox_backup_config($data)
    {
        if ($this->getService()->pmxdbbackup($data)) {
            return true;
        } else {
            return false;
        }
    }

    // Function to list backups
    /**
     * List existing Backups
     * 
     * @return array
     */
    public function proxmox_list_backups()
    {
        $output = $this->getService()->pmxbackuplist();
        return $output;
    }

    /**
     * Restore Backup
     * 
     * @return bool
     */
    public function proxmox_restore_backup($data)
    {
        $this->proxmox_backup_config('backup');
        $this->di['logger']->info('Restoring Proxmox server Backup: %s', $data['backup']);
        if ($this->getService()->pmxbackuprestore($data)) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Get module version
     * 
     * @return string
     */
    public function get_module_version()
    {
        $config = $this->di['mod_config']('Serviceproxmox');
        return $config['version'];
    }
} // EOF