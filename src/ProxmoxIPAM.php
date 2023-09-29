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

namespace Box\Mod\Serviceproxmox;

/**
 * Proxmox module for FOSSBilling
 */
trait ProxmoxIPAM
{
	/* ################################################################################################### */
	/* ####################################  IPAM Management   ########################################### */
	/* ################################################################################################### */

	// Function that gets all the VM templates and returns them as an array
	public function get_ip_ranges()
	{
		// get all the VM templates from the service_proxmox_vm_config_template table
		$ip_ranges = $this->di['db']->find('service_proxmox_ip_range');
		return $ip_ranges;
	}

	// Function that gets all IP Adresses
	public function get_ip_adresses()
	{
		// get all the VM templates from the service_proxmox_vm_config_template table
		$ip_addresses = $this->di['db']->find('service_proxmox_ipadress');
		return $ip_addresses;
	}

	// Function that gets all the LXC templates and returns them as an array
	public function get_vlans()
	{
		// get all the LXC templates from the service_proxmox_lxc_config_template table
		$vlans = $this->di['db']->find('service_proxmox_client_vlan');
		// Fill in the client name
		foreach ($vlans as $vlan) {
			$client = $this->di['db']->getExistingModelById('client', $vlan->client_id);
			$vlan->client_name = $client->first_name . " " . $client->last_name;
		}

		return $vlans;
	}

	/* ################################################################################################### */
	/* ###################################  Manage PVE Network   ######################################### */
	/* ################################################################################################### */
}
