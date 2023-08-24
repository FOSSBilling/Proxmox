-- Migration: 0.0.7
-- --------------------------------------------------------
-- Add new table for ipam settings
-- --------------------------------------------------------
-- Fields: Minimum Network size (default 24), Maximum Network size (default 23), DNS Server 1, DNS Server 2
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_ipam_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `min_network_size` int(11) NOT NULL DEFAULT '24',
  `max_network_size` int(11) NOT NULL DEFAULT '23',
  `dns_server_1` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dns_server_2` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `use_proxmox_sdn` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Add new table for ip adresses
-- --------------------------------------------------------
-- Fields: IP address, ip range, dedicated, gateway, vlan

CREATE TABLE IF NOT EXISTS `service_proxmox_ipadress` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip_range_id` int(11) NOT NULL,
  `dedicated` tinyint(1) NOT NULL DEFAULT '0',
  `gateway` tinyint(1) NOT NULL DEFAULT '0',
  `vlan` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `ip_range_id` (`ip_range_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- alter service_proxmox_qemu_template to add auto increment to id, and add vmid field after id
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_qemu_template` CHANGE `id` `id` INT(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `service_proxmox_qemu_template` ADD `vmid` INT(11) NOT NULL AFTER `id`;

-- --------------------------------------------------------
-- alter service_proxmox_ip_range to add network field after gateway
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_ip_range` ADD `network` VARCHAR(255) NOT NULL AFTER `gateway`;

-- --------------------------------------------------------
-- alter service_proxmox_vm_config_template to add state field after id
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_vm_config_template` ADD `state` VARCHAR(255) NOT NULL AFTER `id`;
-- --------------------------------------------------------
-- insert state "draft" for all existing templates

UPDATE `service_proxmox_vm_config_template` SET `state` = 'draft';

-- --------------------------------------------------------
-- add new table for tags and tag relations
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `service_proxmox_tag` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- increment all tables to 0.0.7
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_server` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_users` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_storageclass` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_storage` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_lxc_appliance` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_vm_config_template` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_vm_storage_template` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_vm_network_template` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_lxc_config_template` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_lxc_storage_template` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_lxc_network_template` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_qemu_template` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_client_vlan` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_ip_range` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_ipam_settings` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_ipadress` COMMENT = '0.0.7';
ALTER TABLE `service_proxmox_tag` COMMENT = '0.0.7';
-- --------------------------------------------------------