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
trait ProxmoxTemplates
{
	/* ################################################################################################### */
	/* ####################################  VM Template Mgmt  ########################################### */
	/* ################################################################################################### */

	// Function that gets all the VM templates and returns them as an array
	public function get_vmtemplates()
	{
		// get all the VM templates from the service_proxmox_vm_config_template table
		$templates = $this->di['db']->findAll('service_proxmox_vm_config_template');
		return $templates;
	}


	// Function that gets all the LXC templates and returns them as an array
	public function get_lxctemplates()
	{
		// get all the LXC templates from the service_proxmox_lxc_config_template table
		$templates = $this->di['db']->findAll('service_proxmox_lxc_config_template');
		return $templates;
	}

	// Function that gets all qemu templates and returns them as an array
	public function get_qemutemplates()
	{
		// get all the qemu templates from the service_proxmox_qemu_template table
		$qemu_templates = $this->di['db']->findAll('service_proxmox_qemu_template');
		// Get server name for each template
		foreach ($qemu_templates as $qemu_template) {
			$server = $this->di['db']->getExistingModelById('service_proxmox_server', $qemu_template->server_id);
			$qemu_template->server_name = $server->name;
		}
		return $qemu_templates;
	}

	// Function that gets a vm config template by id
	public function get_vmconfig($id)
	{
		// get the vm config template from the service_proxmox_vm_config_template table
		$template = $this->di['db']->getExistingModelById('service_proxmox_vm_config_template', $id);
		return $template;
	}

	// Function that gets a lxc config template by id
	public function get_lxc_conftempl($id)
	{
		// get the lxc config template from the service_proxmox_lxc_config_template table
		$template = $this->di['db']->getExistingModelById('service_proxmox_lxc_config_template', $id);
		return $template;
	}



	// function to get tags for type
	public function get_tags($data)
	{
		// get list of tags for input type
		$tags = $this->di['db']->find('service_proxmox_tag', 'type=:type', array(':type' => $data['type']));
		// return tags
		return $tags;
	}

	// function to save tags for type
	public function save_tag($data)
	{
		// $data contains 'type' and 'tag' 
		
		// search if the tag already exists
		$tag_exists = $this->di['db']->findOne('service_proxmox_tag', 'type=:type AND name=:name', array(':type' => $data['type'], ':name' => $data['tag']));
		// and if not create it
		if (!$tag_exists) {
			$model = $this->di['db']->dispense('service_proxmox_tag');
			$model->type = $data['type'];
			$model->name = $data['tag'];
			$this->di['db']->store($model);
			// return the tag that was just created.
			return $model;
		}
		// return the tag that already exists
		return $tag_exists;
	}
		

	// Function to return tags for storage (stored in service_proxmox_storage->storageclass) ($data contains storageid)
	public function get_tags_by_storage($data)
	{
		// get storageclass for storage
		// log to debug.log
		error_log('get_tags_by_storage: ' . $data['storageid']);
		$storage = $this->di['db']->findOne('service_proxmox_storage', 'id=:id', array(':id' => $data['storageid']));
		// return tags (saved in json format in $storage->storageclass) (F.ex ["ssd","hdd"])
		// as well as the service_proxmox_tag id for each tag so there is a key value pair with id and name.
		// check if $storage->storageclass is not empty
		if (empty($storage->storageclass)) {
			// if empty return empty array
			$tags = "";
		} else {
			$tags = json_decode($storage->storageclass, true);
		}
		return $tags;

		
	}

}
