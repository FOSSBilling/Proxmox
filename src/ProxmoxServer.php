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
trait ProxmoxServer
{
	/* ################################################################################################### */
	/* #####################################  Server Management  ######################################### */
	/* ################################################################################################### */



	/**
	 * Test the connection to the Proxmox server.
	 *
	 * @param object $server The server object containing login information.
	 *
	 * @return bool Returns true if the connection is successful, otherwise throws an exception.
	 *
	 * @throws \Box_Exception If login information is missing or incorrect, or if the connection fails.
	 */
	public function test_connection($server)
	{
		$proxmox = $this->getProxmoxInstance($server);
		// check if tokenname and tokenvalue contain values by checking their content
		if (empty($server->tokenname) || empty($server->tokenvalue)) {
			if (!empty($server->root_user) && !empty($server->root_password)) {

				if ($proxmox->login()) {
					error_log("Serviceproxmox: Login with username and password successful");
					return true;
				} else {
					throw new \Box_Exception("Login to Proxmox Host failed");
				}
			} else {
				throw new \Box_Exception("No login information provided");
			}
		} else if ($proxmox->getVersion()) {
			error_log("Serviceproxmox: Login with token successful!");
			return true;
		} else {
			throw new \Box_Exception("Failed to connect to the server.");
		}
	}


	/**
	 * This function tests the token connection to the Proxmox server.
	 * It checks if the tokenname and tokenvalue contain values by checking their content.
	 * If they are empty, it throws an exception.
	 * If the login with token is successful, it retrieves the permissions and checks for 'Realm.AllocateUser' permission.
	 * If the permission is not found, it throws an exception.
	 * It also validates if there already is a group for fossbilling and checks if the groupid is the same as the id of the token.
	 * 
	 * @param object $server The server object containing the tokenname and tokenvalue.
	 * @return bool Returns true if the token connection is successful.
	 * @throws \Box_Exception Throws an exception if the token access fails, the connection to the server fails, or the token does not have 'Realm.AllocateUser' permission.
	 */
	public function test_token_connection($server)
	{
		$proxmox = $this->getProxmoxInstance($server);
		// check if tokenname and tokenvalue contain values by checking their content
		if (empty($server->tokenname) || empty($server->tokenvalue)) {
			throw new \Box_Exception("Token Access Failed: No tokenname or tokenvalue provided");
		} else if ($proxmox->get_version()) {
			error_log("Serviceproxmox: Login with token successful!");
			$permissions = $proxmox->get("/access/permissions");
			$found_permission = 0;
			// Iterate through the permissions and check for 'Realm.AllocateUser' permission
			foreach ($permissions as $permission) {
				if ($permission['Realm.AllocateUser'] == 1) {
					$found_permission += 1;
				}
			}
			// Throw an exception if the 'Realm.AllocateUser' permission is not found
			if (!$found_permission) {
				throw new \Box_Exception("Token does not have 'Realm.AllocateUser' permission");
			}

			// Validate if there already is a group for fossbilling
			$groups = $proxmox->get("/access/groups");
			$foundgroups = 0;
			// Iterate through the groups and check for a group beginning with 'fossbilling'
			foreach ($groups as $group) {
				if (strpos($group['groupid'], 'fossbilling') === 0) {
					$foundgroups += 1;
					$groupid = $group['groupid'];
				}
				// check if groupid is the same as the id of the token (fb_1234@pve!fb_access) 
				$fb_token_instanceid = explode('@', $server->tokenname)[1];


				if ($group['groupid'] == $server->tokenname) {
					$foundgroups += 1;
					$groupid = $group['groupid'];
				}
			}
			return true;
		} else {
			throw new \Box_Exception("Failed to connect to the server.");
		}
	}




	/**
	 * Finds an empty server with the best CPU and RAM usage ratio based on the given product's group and filling.
	 *
	 * @param object $product The product to find an empty server for.
	 * @return int|null The ID of the server with the best CPU and RAM usage ratio, or null if no server is found.
	 */
	public function find_empty($product)
	{
		$productconfig = json_decode($product->config, 1);
		$group = $productconfig['group'];
		$filling = $productconfig['filling'];

		// retrieve overprovisioning information from extension settings
		$config = $this->di['mod_config']('Serviceproxmox');
		$cpu_overprovion_percent = $config['cpu_overprovisioning'];
		$ram_overprovion_percent = $config['ram_overprovisioning'];
		$avoid_overprovision = $config['avoid_overprovision'];


		$servers = $this->di['db']->find('service_proxmox_server', ['status' => 'active']);

		$server = null;
		$server_ratio = 0;
		// use values from database to calculate ratio and store the server id, cpu and ram usage ratio if it's better than the previous one
		foreach ($servers as $s) {
			$cpu_usage = $s['cpu_cores_allocated'];
			$ram_usage = $s['ram_allocated'];
			$cpu_cores = $s['cpu_cores'];
			$ram = $s['ram'];

			$cpu_ratio = $cpu_usage / $cpu_cores;
			$ram_ratio = $ram_usage / $ram;

			// if avoid_overprovision is set to true, servers with a ratio of >1 are ignored
			if ($avoid_overprovision && ($cpu_ratio > 1 || $ram_ratio > 1)) {
				continue;
			}
			// calculate ratio with overprovisioning
			$cpu_ratio = $cpu_ratio * (1 + $cpu_overprovion_percent / 100);
			$ram_ratio = $ram_ratio * (1 + $ram_overprovion_percent / 100);
			// check current best ratio
			if ($cpu_ratio + $ram_ratio > $server_ratio) {
				$server_ratio = $cpu_ratio + $ram_ratio;
				$server = $s['id'];
			}
		}
		// if no server is found, return null
		if ($server == null) {
			return null;
		}
		// if a server is found, return the id
		return $server;
	}

	/**
	 * Find the access of the server based on its hostname, IPv4 or IPv6 address.
	 *
	 * @param object $server The server object containing hostname, IPv4 and/or IPv6 address.
	 *
	 * @return string The hostname, IPv4 or IPv6 address of the server.
	 *
	 * @throws \Box_Exception If no hostname, IPv4 or IPv6 address is found for the server.
	 */
	public function find_access($server)
	{
		if (!empty($server->hostname)) {
			return $server->hostname;
		} else  if (!empty($server->ipv4)) {
			return $server->ipv4;
		} else if (!empty($server->ipv6)) {
			return $server->ipv6;
		} else {
			throw new \Box_Exception('No IPv6, IPv4 or Hostname found for server ' . $server->id);
		}
	}

	/**
	 * Returns hardware data for a given server.
	 *
	 * @param object $server The server object.
	 * @return array The hardware data.
	 * @throws \Box_Exception If failed to connect to the server.
	 */
	public function getHardwareData($server)
	{
		$proxmox = $this->getProxmoxInstance($server);
		if ($proxmox->login()) {
			error_log("ProxmoxServer.php: getHardwareData: Login successful");
			$hardware = $proxmox->get("/nodes/" . $server->name . "/status");
			return $hardware;
		} else {
			throw new \Box_Exception("Failed to connect to the server. hw Token Access Failed");
		}
	}

	/**
	 * Returns storage data for a given server.
	 *
	 * @param object $server The server object.
	 * @return array The storage data.
	 * @throws \Box_Exception If failed to connect to the server.
	 */
	public function getStorageData($server)
	{
		$proxmox = $this->getProxmoxInstance($server);
		if ($proxmox->login()) {
			$storage = $proxmox->get("/nodes/" . $server->name . "/storage");
			return $storage;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}

	/**
	 * Returns assigned resources for a given server.
	 *
	 * @param object $server The server object.
	 * @return array The assigned resources.
	 * @throws \Box_Exception If failed to connect to the server.
	 */
	public function getAssignedResources($server)
	{
		$proxmox = $this->getProxmoxInstance($server);
		if ($proxmox->login()) {
			$assigned_resources = $proxmox->get("/nodes/" . $server->name . "/qemu");
			return $assigned_resources;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}

	/**
	 * Returns available appliances.
	 *
	 * @return array The available appliances.
	 * @throws \Box_Exception If failed to connect to the server.
	 */
	public function getAvailableAppliances()
	{
		$server = $this->di['db']->getExistingModelById('service_proxmox_server', 1, 'Server not found');

		$proxmox = $this->getProxmoxInstance($server);
		if ($proxmox->login()) {
			$appliances = $proxmox->get("/nodes/" . $server->name . "/aplinfo");
			return $appliances;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}

	/**
	 * Returns an array of QEMU virtual machines for the given Proxmox server.
	 *
	 * @param object $server The server object containing the server name.
	 * @return array An array of QEMU virtual machines.
	 * @throws \Box_Exception If failed to connect to the server.
	 */
	public function getQemuVMs($server)
	{
		$proxmox = $this->getProxmoxInstance($server);
		if ($proxmox->login()) {
			$templates = $proxmox->get("/nodes/" . $server->name . "/qemu");
			return $templates;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}
}
