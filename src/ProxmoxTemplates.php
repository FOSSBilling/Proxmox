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

	/**
	 * Returns an array of all VM templates.
	 *
	 * @return array
	 */
	public function get_vmtemplates()
	{
		$templates = $this->di['db']->findAll('service_proxmox_vm_config_template');
		return $templates;
	}

	/**
	 * Returns an array of all LXC templates.
	 *
	 * @return array
	 */
	public function get_lxctemplates()
	{
		$templates = $this->di['db']->findAll('service_proxmox_lxc_config_template');
		return $templates;
	}

	/**
	 * Returns an array of all QEMU templates, with the server name for each template.
	 *
	 * @return array
	 */
	public function get_qemutemplates()
	{
		$qemu_templates = $this->di['db']->findAll('service_proxmox_qemu_template');
		// Get server name for each template
		foreach ($qemu_templates as $qemu_template) {
			$server = $this->di['db']->getExistingModelById('service_proxmox_server', $qemu_template->server_id);
			$qemu_template->server_name = $server->name;
		}
		return $qemu_templates;
	}

	/**
	 * Get the virtual machine configuration template by ID.
	 *
	 * @param int $id The ID of the template to retrieve.
	 * @return Model The virtual machine configuration template.
	 */
	public function get_vmconfig($id)
	{
		$template = $this->di['db']->getExistingModelById('service_proxmox_vm_config_template', $id);
		return $template;
	}

	/**
	 * Get the Linux container configuration template by ID.
	 *
	 * @param int $id The ID of the template to retrieve.
	 * @return Model The Linux container configuration template.
	 */
	public function get_lxc_conftempl($id)
	{
		$template = $this->di['db']->getExistingModelById('service_proxmox_lxc_config_template', $id);
		return $template;
	}

	/**
	 * Get all tags of a certain type.
	 *
	 * @param array $data An array containing the type of tags to retrieve.
	 * @return array An array of tags of the specified type.
	 */
	public function get_tags($data)
	{
		$tags = $this->di['db']->find('service_proxmox_tag', 'type=:type', array(':type' => $data['type']));
		return $tags;
	}

	/**
	 * Saves a tag to the database, creating it if it doesn't already exist.
	 *
	 * @param array $data An array containing the tag type and name.
	 * @return object The tag that was just created or the tag that already exists.
	 */
	public function save_tag($data)
	{
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

	/**
	 * Gets the tags associated with a given storage ID.
	 *
	 * @param array $data An array containing the storage ID.
	 * @return mixed An array of tags or an empty string if the storage has no tags.
	 */
	public function get_tags_by_storage($data)
	{
		$storage = $this->di['db']->findOne('service_proxmox_storage', 'id=:id', array(':id' => $data['storageid']));

		if (empty($storage->storageclass)) {
			// if empty return empty string
			$tags = "";
		} else {
			$tags = json_decode($storage->storageclass, true);
		}
		return $tags;
	}

}
