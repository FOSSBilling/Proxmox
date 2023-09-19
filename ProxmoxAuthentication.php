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


trait ProxmoxAuthentication
{
	/* ################################################################################################### */
	/* ########################################  Permissions  ############################################ */
	/* ################################################################################################### */

	/**
	 * Function to set up proxmox server for fossbilling
	 * @param $server - server object
	 * @return array - array of permission information
	 */
	public function prepare_pve_setup($server)
	{
		// Retrieve the server access information
		$serveraccess = $this->find_access($server);

		// Create a new instance of the PVE2_API class with the server access details
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);

		// Attempt to log in to the server using the API
		if (!$proxmox->login()) {
			throw new \Box_Exception("Failed to connect to the server.");
		}

		// Create an API token for the Admin user if not logged in via API token
		if (empty($server->tokenname) || empty($server->tokenvalue)) {
			// Check if the connecting user has the 'Realm.AllocateUser' permission
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
				throw new \Box_Exception("User does not have 'Realm.AllocateUser' permission");
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
			}
			// Handle the cases where there are no groups, one group, or multiple groups
			switch ($foundgroups) {
				case 0:
					// Create a new group
					$groupid = 'fossbilling_' . rand(1000, 9999);
					$newgroup = $proxmox->post("/access/groups", array('groupid' => $groupid, 'comment' => 'fossbilling group'));
					break;
				case 1:
					// Use the existing group
					break;
				default:
					throw new \Box_Exception("More than one group found");
					break;
			}
		}



		// Validate if there already is a user and token for fossbilling
		$users = $proxmox->get("/access/users");
		$found = 0;
		// Iterate through the users and check for a user beginning with 'fb'
		foreach ($users as $user) {
			if (strpos($user['userid'], 'fb') === 0) {
				$found += 1;
				$userid = $user['userid'];
			}
			// Handle the cases where there are no users, one user, or multiple users
			switch ($found) {
				case 0:
					// Create a new user
					$userid = 'fb_' . rand(1000, 9999) . '@pve'; // TODO: Make realm configurable in the module settings
					$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->di['tools'], 'enable' => 1, 'comment' => 'fossbilling user', 'groups' => $groupid));

					// Create a token for the new user
					$token = $proxmox->post("/access/users/" . $userid . "/token/fb_access", array());

					// Check if the token was created successfully
					if ($token) {
						$server->tokenname = $token['full-tokenid'];
						$server->tokenvalue = $token['value'];
					} else {
						throw new \Box_Exception("Failed to create token for fossbilling user");
						break;
					}
					break;
				case 1:
					// Create a token for the existing user
					$token = $proxmox->post("/access/users/" . $userid . "/token/fb_access", array());
					if ($token) {
						$server->tokenname = $token['full-tokenid'];
						$server->tokenvalue = $token['value'];
					} else {
						throw new \Box_Exception("Failed to create token for fossbilling user");
						break;
					}
					break;
				default:
					throw new \Box_Exception("There are more than one fossbilling users on the server. Please delete all but one.");
					break;
			}
			// Create permissions for the newly created token
			// Set up permissions for the token (Admin user) to manage users, groups, and other administrative tasks
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEUserAdmin', 'propagate' => 1, 'users' => $userid));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEAuditor', 'propagate' => 1, 'users' => $userid));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVESysAdmin', 'propagate' => 1, 'users' => $userid));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEPoolAdmin', 'propagate' => 1, 'users' => $userid));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEDatastoreAdmin', 'propagate' => 1, 'users' => $userid));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEUserAdmin', 'propagate' => 1, 'tokens' => $server->tokenname));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEAuditor', 'propagate' => 1, 'tokens' => $server->tokenname));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVESysAdmin', 'propagate' => 1, 'tokens' => $server->tokenname));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEPoolAdmin', 'propagate' => 1, 'tokens' => $server->tokenname));
			$permissions = $proxmox->put("/access/acl/", array('path' => '/', 'roles' => 'PVEDatastoreAdmin', 'propagate' => 1, 'tokens' => $server->tokenname));

			// Sleep for 5 seconds
			sleep(5);

			// Check if the permissions were created correctly by logging in and creating another user
			/*
				echo "<script>console.log('".json_encode($serveraccess)."');</script>";
				echo "<script>console.log('".json_encode($userid)."');</script>";
				echo "<script>console.log('".json_encode($server->realm)."');</script>";
				echo "<script>console.log('".json_encode($server->tokenname)."');</script>";
				echo "<script>console.log('".json_encode($server->tokenvalue)."');</script><br /><br />";
				*/

			// Delete the root password and unset the PVE2_API instance
			$server->root_password = null;
			unset($proxmox);

			// Return the test_access result for the server
			return $this->test_access($server);
		}
	}


	/**
	 * Tests the access to the server
	 * 
	 * @param  Server $server The server to test
	 * @return bool           True if the test was successful, false otherwise
	 * @throws Box_Exception
	 */
	public function test_access($server)
	{
		// Retrieve the server access information
		$serveraccess = $this->find_access($server);

		// Create a new instance of the PVE2_API class with the server access details
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);

		// Attempt to log in to the server using the API
		if (!$proxmox->login()) {
			throw new \Box_Exception("Failed to connect to the server. testpmx");
		}

		// Generate a random test user ID
		$userid = 'tfb_' . rand(1000, 9999) . '@pve'; // TODO: Make realm configurable in the module settings

		// Create a new user for testing purposes
		$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->di['tools']->generatePassword(16, 4), 'enable' => '1', 'comment' => 'fossbilling user 2'));

		// Retrieve the newly created user
		$newuser = $proxmox->get("/access/users/" . $userid);

		// Check if the new user was successfully created
		if (!$newuser) {
			throw new \Box_Exception("Failed to create test user for fossbilling");
		} else {
			// Delete the test user
			$deleteuser = $proxmox->delete("/access/users/" . $userid);

			// Check if the test user was successfully deleted
			$deleteuser = $proxmox->get("/access/users/" . $userid);
			if ($deleteuser) {
				throw new \Box_Exception("Failed to delete test user for fossbilling. Check permissions.");
			} else {
				// Remove the root password from the server object
				$server->root_password = null;
				return $server;
			}
		}
	}


	/**
	 * Creates a new client user on the server
	 *
	 * @param  Server $server The server to create the user on
	 * @param  Client $client The client to create the user for
	 * @return void
	 */
	public function create_client_user($server, $client)
	{
		$clientuser = $this->di['db']->dispense('service_proxmox_users');
		$clientuser->client_id = $client->id;
		$this->di['db']->store($clientuser);
		$serveraccess = $this->find_access($server);
		$proxmox = new PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue);
		if (!$proxmox->login()) {
			throw new \Box_Exception("Failed to connect to the server. create_client_user");
		}
		$userid = 'fb_customer_' . $client->id . '@pve'; // TODO: Make realm configurable in the module settings
		$newuser = $proxmox->post("/access/users", array('userid' => $userid, 'password' => $this->di['tools']->generatePassword(16, 4), 'enable' => '1', 'comment' => 'fossbilling user ' . $client->id));
		$newuser = $proxmox->get("/access/users/" . $userid);

		// Create Token for Client
		$clientuser->admin_tokenname = 'fb_admin_' . $client->id;
		$clientuser->server_id = $server->id;
		$admintoken_response = $proxmox->post("/access/users/" . $userid . "/token/" . $clientuser->admin_tokenname, array('comment' => 'fossbilling admin token for client id: ' . $client->id));
		$clientuser->admin_tokenname = $admintoken_response['full-tokenid'];
		$clientuser->admin_tokenvalue = $admintoken_response['value'];
		$clientuser->view_tokenname = 'fb_view_' . $client->id;
		$viewtoken_response = $proxmox->post("/access/users/" . $userid . "/token/" . $clientuser->view_tokenname, array('comment' => 'fossbilling view token for client id: ' . $client->id));
		$clientuser->view_tokenname = $viewtoken_response['full-tokenid'];
		$clientuser->view_tokenvalue = $viewtoken_response['value'];


		$this->di['db']->store($clientuser);

		// Check if the client already has a pool and if not create it.
		$pool = $proxmox->get("/pools/" . $client->id);
		if (!$pool) {
			$pool = $proxmox->post("/pools", array('poolid' => 'fb_client_' . $client->id, 'comment' => 'fossbilling pool for client id: ' . $client->id));
		}
		// Add permissions for client

		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEVMUser,PVEVMAdmin,PVEDatastoreAdmin,PVEDatastoreUser', 'propagate' => 1, 'users' => $userid));

		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEVMUser,PVEDatastoreUser', 'propagate' => 1, 'tokens' => $clientuser->view_tokenname));

		$permissions = $proxmox->put("/access/acl/", array('path' => '/pool/' . 'fb_client_' . $client->id, 'roles' => 'PVEVMAdmin,PVEDatastoreAdmin', 'propagate' => 1, 'tokens' => $clientuser->admin_tokenname));
	}
}
