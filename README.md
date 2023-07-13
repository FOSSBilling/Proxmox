# Proxmox module for FOSSBilling
Initial Proxmox support for FOSSBilling. 

**This module is still in development and not ready for production use.**

Based on [previous work](https://github.com/scith/BoxBilling_Proxmox) by [Scith](https://github.com/scith).


## Server List
![image](https://github.com/Anuril/Proxmox/assets/1939311/d81a052e-6c00-429b-81aa-7a3cd8dfad71)

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

## Installation
- Copy the "Serviceproxmox" folder in *modules*
- Make sure to chown to your web-servers User (f.ex `chown www-data:www-data Serviceproxmox -Rf`)
- Configure the Proxmox module in the FOSSBilling admin area
- Add new Proxmox servers
- "Prepare Server" on each Proxmox server (this will create the necessary API user and role) (The Plus Button in the Server List)
- Add new Proxmox products with the correct VM settings setup

The Proxmox Addon now has its own Menu Entry:

![image](https://github.com/Anuril/Proxmox/assets/1939311/13ad3290-dda2-403d-be71-a1d06b2390ec)

## Storage List 
![image](https://github.com/Anuril/Proxmox/assets/1939311/01505103-3e76-4f48-89fb-16775e9b6a91)

## Templates

### VM Templates
![image](https://github.com/Anuril/Proxmox/assets/1939311/37ef5104-91fe-4275-a4db-6481f99fc71a)

### LXC Appliances
![image](https://github.com/Anuril/Proxmox/assets/1939311/96c9ec9e-087f-4736-a087-01527d532368)

### LXC Appliances
![image](https://github.com/Anuril/Proxmox/assets/1939311/7b4a780e-c3a9-44ff-87a1-a4814ef883e8)

## IPAM
![image](https://github.com/Anuril/Proxmox/assets/1939311/d8444494-c43b-4791-9bbe-27434754da8c)

![image](https://github.com/Anuril/Proxmox/assets/1939311/b1072dc6-1839-4a1e-b8c2-242d76d8d57d)

![image](https://github.com/Anuril/Proxmox/assets/1939311/738d573e-7c61-4ca0-98b9-7bc644aae353)

![image](https://github.com/Anuril/Proxmox/assets/1939311/1c4860e8-905f-4852-827d-a41d795daf0c)


## Settings
![Admin_General](https://github.com/Anuril/Proxmox/assets/1939311/42a3492b-9df7-48d8-a1c3-98e6ed698758)
![Backup](https://github.com/Anuril/Proxmox/assets/1939311/31d4c1a6-3e46-49cf-935c-af65b0582d2a)
![image](https://github.com/Anuril/Proxmox/assets/1939311/01505103-3e76-4f48-89fb-16775e9b6a91)

## Licensing
This module is licensed under the GNU General Public License v3.0. See the LICENSE file for more information.
