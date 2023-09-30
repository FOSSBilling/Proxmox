-- --------------------------------------------------------
-- alter service_storage_proxmox to change storageclass from VARCHAR to text
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_storage` CHANGE `storageclass` `storageclass` TEXT DEFAULT NULL;

-- --------------------------------------------------------
-- alter  `service_proxmox_vm_storage_template` so `format` is called `controller`
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_vm_storage_template` CHANGE `format` `controller` VARCHAR(255) DEFAULT NULL;


-- --------------------------------------------------------
-- increment all tables to 0.0.8
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_server` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_users` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_storageclass` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_storage` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_lxc_appliance` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_vm_config_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_vm_storage_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_vm_network_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_lxc_config_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_lxc_storage_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_lxc_network_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_qemu_template` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_client_vlan` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_ip_range` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_ipam_settings` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_ipadress` COMMENT = '0.0.8';
ALTER TABLE `service_proxmox_tag` COMMENT = '0.0.8';
-- --------------------------------------------------------

-- --------------------------------------------------------
-- drop table service_proxmox_storageclass
-- --------------------------------------------------------
DROP TABLE IF EXISTS `service_proxmox_storageclass`;