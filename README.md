# Proxmox module for FOSSBilling
Initial Proxmox support for FOSSBilling. 

**This module is still in development and not ready for production use.**

Based on [previous work](https://github.com/scith/BoxBilling_Proxmox) by [Scith](https://github.com/scith).


## Server List
![Serveroverview](https://github.com/Anuril/Proxmox/assets/1939311/96629395-e9a5-4029-a86f-fbd86b34b42c)

## Features
- Manage pools of Proxmox servers (orders can be allocated to servers automatically based on their capacity)
- Complete Privilege Separation (each client can only see their own VMs),
- Admin can't see inside client's VMs (Only the VM ID, Name, Status, IP, RAM, CPU, Disk, Bandwidth, etc.)
- Provision LXC containers (not tested / might not yet work)
- Provision QEMU KVM machines (tested, but needs very specific product setup)
- Clients can start, shutdown and reboot their VMs (online console not working right now)
- Proxmox Servers do not have to be reachable from the Internet (only the FOSSBilling server needs to be able to reach the Proxmox server)
- Rudimentary Backup of Module Data (Module data is not lost anymore after reinstalling.)


## TODOs:
- Better Error Handling when creating unexpected things happen & get returned from pve host.
- Better VM Allocation procedure
- Consistent Naming: Templates might be confusing...
- VM & LXC Template setup needs to be translated into actually creating VMs from it.
- Provisioning of VMs with Cloudinit (https://pve.proxmox.com/wiki/Cloud-Init_Support)
- Work on Usability to configure products and manage customer's products


## Requirements
- Tested on Proxmox VE 7 or higher, PVE 6 should work too


## Installation ( For 0.1.0 Preview!)

### Prerequisites
- Make sure you uninstall the Module first by going to Extensions -> Overview
- Then, make sure that you move the pmxconfig folder in your Fossbilling rootfolder so the new installer doesn't accidentially restore an old backup.
```mv /var/www/pmxconfig /var/www/pmxold```
- Make sure you don't have any tables beginning with service_promxox in your database anymore:
```
mysql -u root
MariaDB [(none)]>use fossbilling;
MariaDB [fossbilling]> show tables;
MariaDB [fossbilling]> drop table service_proxmox_tag;
```

### Installation for 0.1.0 Preview
- Go to the Modules folder (f.ex: ```cd /var/www/modules```)
- Run ```git clone https://github.com/FOSSBilling/Proxmox.git Serviceproxmox```
- Go Inside the directory ```cd Serviceproxmox/```
- Checkout the 0.1.0 branch ```git checkout 0.1.0```
- Install the Module in the Web interface.
- Add new Proxmox servers.
- "Prepare Server" on each Proxmox server (this will create the necessary API user and role) (The Plus Button in the Server List)
- Add new Proxmox products with the correct VM settings setup


## (New) Storage List 
![Storageoverview](https://github.com/Anuril/Proxmox/assets/1939311/139d7d32-3fe3-45b3-b0cd-e2e7e6f5af0e)

## (New) Settings
![Admin_General](https://github.com/Anuril/Proxmox/assets/1939311/42a3492b-9df7-48d8-a1c3-98e6ed698758)
![Backup](https://github.com/Anuril/Proxmox/assets/1939311/31d4c1a6-3e46-49cf-935c-af65b0582d2a)
![Storages](https://github.com/Anuril/Proxmox/assets/1939311/08a994ca-d38b-4cbb-ac01-eb9a3fa582fa)


## Licensing
This module is licensed under the GNU General Public License v3.0. See the LICENSE file for more information.
