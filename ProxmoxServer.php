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


	/*
		Test connection
	*/
	public function test_connection($server)
	{
		// Test if login
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		// check if tokenname and tokenvalue contain values by checking their content
		if (empty($server->tokenname) || empty($server->tokenvalue)) {
			if (!empty($server->root_user) && !empty($server->root_password)) {

				if ($proxmox->login()) {
					return true;
				} else {
					throw new \Box_Exception("Login to Proxmox Host failed");
				}
			} else {
				throw new \Box_Exception("No login information provided");
			}
		} else if ($proxmox->get_version()) {
			return true;
		} else {
			throw new \Box_Exception("Failed to connect to the server.");
		}
	}


	/* Find best Server
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

		// Retrieve only non-full active servers sorted by ratio.
		// priority is given to servers with the largest difference between ram and used ram
		// if avoid_overprovision is set to true, servers with a ratio of >1 are ignored
		$servers = $this->di['db']->find('service_proxmox_server', ['status' => 'active']);
		//echo "<script>console.log('Debug Objects: " . json_encode($servers). "' );</script>";
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

	/*
		Find access to server (hostname, ipv4, ipv6)
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

	/*
		Find server hardware usage information "getHardwareData"
	*/
	public function getHardwareData($server)
	{
		// Retrieve associated server
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);

		if ($proxmox->login()) {
			error_log("ProxmoxServer.php: getHardwareData: Login successful");
			$hardware = $proxmox->get("/nodes/" . $server->name . "/status");
			return $hardware;
		} else {
			throw new \Box_Exception("Failed to connect to the server. hw Token Access Failed");
		}
	}

	public function getStorageData($server)
	{
		// Retrieve associated server
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$storage = $proxmox->get("/nodes/" . $server->name . "/storage");
			return $storage;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}

	// function to get all assigned cpu_cores and ram on a server (used to find free resources)
	public function getAssignedResources($server)
	{
		// Retrieve associated server
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$assigned_resources = $proxmox->get("/nodes/" . $server->name . "/qemu");
			return $assigned_resources;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}

	// function to get available appliances from a server
	public function getAvailableAppliances()
	{
		$server = $this->di['db']->getExistingModelById('service_proxmox_server', 1, 'Server not found');

		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$appliances = $proxmox->get("/nodes/" . $server->name . "/aplinfo");
			return $appliances;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}

	// function to get available template vms from a server
	public function getQemuTemplates($server)
	{
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if ($proxmox->login()) {
			$templates = $proxmox->get("/nodes/" . $server->name . "/qemu");
			return $templates;
		} else {
			throw new \Box_Exception("Failed to connect to the server. st");
		}
	}
}
