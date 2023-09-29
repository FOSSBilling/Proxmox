-- --------------------------------------------------------
-- increment all tables to 0.0.6
-- --------------------------------------------------------
ALTER TABLE `service_proxmox_server` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_users` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_storageclass` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_storage` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_lxc_appliance` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_vm_config_template` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_vm_storage_template` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_vm_network_template` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_lxc_config_template` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_lxc_storage_template` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_lxc_network_template` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_qemu_template` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_client_vlan` COMMENT = '0.0.6';
ALTER TABLE `service_proxmox_ip_range` COMMENT = '0.0.6';
-- --------------------------------------------------------