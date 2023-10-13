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

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use PDO;
use PDOException;


/**
 * Provides the Proxmox module for FOSSBilling.
 */
class Service implements \FOSSBilling\InjectionAwareInterface
{
	protected $di;
	private $pdo;
	public function setDi(\Pimple\Container|null $di): void
	{
		$this->di = $di;
	}

	public function getDi(): ?\Pimple\Container
	{
		return $this->di;
	}
	use ProxmoxAuthentication;
	use ProxmoxServer;
	use ProxmoxVM;
	use ProxmoxTemplates;
	use ProxmoxIPAM;


	/**
	 * Returns a PDO instance for the database connection.
	 *
	 * @return PDO The PDO instance.
	 */
	private function getPdo(): PDO
	{
		if (!$this->pdo) {
			// Get db config
			$db_user = $this->di['config']['db']['user'];
			$db_password = $this->di['config']['db']['password'];
			$db_name = $this->di['config']['db']['name'];

			// Create PDO instance
			$this->pdo = new PDO('mysql:host=localhost;dbname=' . $db_name, $db_user, $db_password);
			$this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}

		return $this->pdo;
	}


	/**
	 * Fetches all tables in the database that start with 'service_proxmox'.
	 *
	 * @return array An array of table names.
	 */
	private function fetchServiceProxmoxTables(): array
	{
		$pdo = $this->getPdo();
		$stmt = $pdo->query("SHOW TABLES LIKE 'service_proxmox%'");
		return $stmt->fetchAll(PDO::FETCH_COLUMN);
	}
	/**
	 * Method to install module. In most cases you will provide your own
	 * database table or tables to store extension related data.
	 *
	 * If your extension is not very complicated then extension_meta
	 * database table might be enough.
	 *
	 * @return bool
	 * 
	 * @throws \Box_Exception
	 */
	public function install(): bool
	{
		// read manifest.json to get current version number
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$version = $manifest['version'];

		// check if there is a sqldump backup with "uninstall" in it's name in the pmxconfig folder, if so, restore it
		$filesystem = new Filesystem();
		$finder = new Finder();
		if (!$filesystem->exists(PATH_ROOT . '/pmxconfig')) {
			$filesystem->mkdir(PATH_ROOT . '/pmxconfig');
		}

		$pmxbackup_dir = $finder->in(PATH_ROOT . '/pmxconfig')->files()->name('proxmox_uninstall_*.sql');

		// find newest file in pmxbackup_dir according to timestamp
		$pmxbackup_file = array();
		foreach ($pmxbackup_dir as $file) {
			$pmxbackup_file[$file->getMTime()] = $file->getFilename();
		}
		ksort($pmxbackup_file);
		$pmxbackup_file = array_reverse($pmxbackup_file);
		$pmxbackup_file = reset($pmxbackup_file);

		// if pmxbackup_file is not empty, restore the sql dump to database
		if (!empty($pmxbackup_file)) {
			// Load the backup
			$dump = file_get_contents(PATH_ROOT . '/pmxconfig/' . $pmxbackup_file);

			// Check if dump is not empty
			if (!empty($dump)) {
				// Check version number in first line of dump
				$original_dump = $dump;
				$version_line = strtok($dump, "\n");

				// Get version number from line
				$dump_version = str_replace('-- Proxmox module version: ', '', $version_line);
				$dump = str_replace($version_line . "\n", '', $dump);


				try {
					// Retrieve PDO instance
					$pdo = $this->getPdo();
					// If version number in dump is smaller than current version number, restore dump and run upgrade function
					if ($dump_version < $version) {
						// Split the dump into an array by each sql command
						$query_array = explode(";", $dump);

						// Execute each sql command
						foreach ($query_array as $query) {
							if (!empty(trim($query))) {
								$pdo->exec($query);
							}
						}

						$this->upgrade($dump_version); // Runs all migrations between current and next version
					} elseif ($dump_version == $version) {
						// Split the dump into an array by each sql command
						$query_array = explode(";", $dump);

						// Execute each sql command
						foreach ($query_array as $query) {
							if (!empty(trim($query))) {
								$pdo->exec($query);
							}
						}
					} else {
						throw new \Box_Exception("The version number of the sql dump is bigger than the current version number of the module. Please check the installed Module version.", null, 9684);
					}
				} catch (\Box_Exception $e) {
					throw new \Box_Exception('Error during restoration process: ' . $e->getMessage());
				}
			}
		} else {
			// Get a list of all SQL migration files
			$migrations = glob(__DIR__ . '/migrations/*.sql');

			// Sort the array of migration files by their version numbers (which are in their file names)
			usort($migrations, function ($a, $b) {
				return version_compare(basename($a, '.sql'), basename($b, '.sql'));
			});

			try {
				// Create a new PDO instance, connecting to your MySQL database
				$pdo = new PDO(
					'mysql:host=' . $this->di['config']['db']['host'] . ';dbname=' . $this->di['config']['db']['name'],
					$this->di['config']['db']['user'],
					$this->di['config']['db']['password']
				);

				// Loop through each migration file
				foreach ($migrations as $migration) {
					// Extract the version number from the file name
					$filename = basename($migration, '.sql');
					$version = str_replace('_', '.', $filename);

					// Log the execution of the current migration
					error_log('Running migration ' . $version . ' from ' . $migration);

					// Read the SQL statements from the file into a string
					$sql = file_get_contents($migration);

					// Split the string of SQL statements into an array
					// This uses the ';' character to identify the end of each SQL statement
					$statements = explode(';', $sql);

					// Loop through each SQL statement
					foreach ($statements as $statement) {
						// If the statement is not empty or just whitespace
						if (trim($statement)) {
							// Execute the SQL statement
							$pdo->exec($statement);
						}
					}
				}
			} catch (PDOException $e) {
				// If any errors occur while connecting to the database or executing SQL, log the error message and terminate the script
				error_log('PDO Exception: ' . $e->getMessage());
				exit(1);
			}
		}

		$extensionService = $this->di['mod_service']('extension');
		$extensionService->setConfig(['ext' => 'mod_serviceproxmox', 'cpu_overprovisioning' => '1', 'ram_overprovisioning' => '1', 'storage_overprovisioning' => '1', 'avoid_overprovision' => '0', 'no_overprovision' => '1', 'use_auth_tokens' => '1', 'pmx_debug_logging' =>'0']);


		return true;
	}


	/**
	 * Method to uninstall module.
	 * Now creates a sql dump of the database tables and stores it in the pmxconfig folder
	 * 
	 * @return bool
	 */
	public function uninstall(): bool
	{
		// Retrieve PDO instance
		$pdo = $this->getPdo();
		$tables = $this->fetchServiceProxmoxTables();

		foreach ($tables as $table) {
			$pdo->exec("DROP TABLE IF EXISTS `$table`");
		}
		return true;
	}

	/**
	 * Method to upgrade module.
	 * 
	 * @param string $previous_version
	 * @return bool
	 */
	public function upgrade($previous_version): bool
	{
		// read current module version from manifest.json
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$current_version = $manifest['version'];

		// read migrations directory and find all files between current version and previous version
		$migrations = glob(__DIR__ . '/migrations/*.sql');

		// sort migrations by version number (Filenames: 0.0.1.sql, 0.0.2.sql etc.)
		usort($migrations, function ($a, $b) {
			return version_compare(basename($a, '.sql'), basename($b, '.sql'));
		});

		// Retrieve PDO instance
		$pdo = $this->getPdo();

		foreach ($migrations as $migration) {
			// get version from filename
			error_log('Found migration: ' . $migration);
			$filename = basename($migration, '.sql');
			$version = str_replace('_', '.', $filename);

			if (version_compare($version, $previous_version, '>') && version_compare($version, $current_version, '<=')) {
				error_log('Applying migration: ' . $migration);

				// run migration
				$migration_sql = file_get_contents($migration);
				$pdo->exec($migration_sql);
			} else {
				error_log('Skipping migration: ' . $migration);
			}
		}

		return true;
	}

	/**
	 * Method to update module. When you release new version to
	 * extensions.fossbilling.org then this method will be called
	 * after the new files are placed.
	 *
	 * @param array $manifest - information about the new module version
	 *
	 * @return bool
	 *
	 * @throws \Box_Exception
	 */
	public function update(array $manifest): bool
	{
		// throw new \Box_Exception("Throw exception to terminate module update process with a message", array(), 125);
		return true;
	}




	/**
	 * Method to check if all tables have been migrated to current Module Version.
	 * Not yet used, but will be in the admin settings page for the module
	 * 
	 * @param string $action
	 * @return bool
	 */
	public function check_db_migration()
	{
		// read current module version from manifest.json
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$current_version = $manifest['version'];
		$tables = $this->fetchServiceProxmoxTables();

		foreach ($tables as $table) {
			$sql = "SELECT table_comment FROM INFORMATION_SCHEMA.TABLES WHERE table_schema='" . DB_NAME . "' AND table_name='" . $table . "'"; /* @phpstan-ignore-line */
			$result = $this->di['db']->query($sql);
			$row = $result->fetch();
			// check if version is the same as current version
			if ($row['table_comment'] != $current_version) {
				throw new \Box_Exception('Database migration is not up to date. Please run the database migration script.');
			}
		}
		return true;
	}

	/**
	 * Method to create configuration Backups of Proxmox tables
	 * 
	 * @param string $data - 'uninstall' or 'backup'
	 * @return bool
	 */
	public function pmxdbbackup($data)
	{
		// create backup of all Proxmox tables
		try {
			$filesystem = new Filesystem();
			$filesystem->mkdir([PATH_ROOT . '/pmxconfig'], 0750);
		} catch (IOException $e) {
			error_log('An error occurred while creating backup directory at ' . $e->getMessage());
			throw new \Box_Exception('Unable to create directory pmxconfig');
		}

		if ($data == 'uninstall') {
			$filename = '/pmxconfig/proxmox_uninstall_' . date('Y-m-d_H-i-s') . '.sql';
		} else {
			$filename = '/pmxconfig/proxmox_backup_' . date('Y-m-d_H-i-s') . '.sql';
		}


		try {
			$pdo = $this->getPdo();
			$tables = $tables = $this->fetchServiceProxmoxTables();
			$backup = '';

			// Loop through tables and create SQL statement
			foreach ($tables as $table) {
				$result = $pdo->query('SELECT * FROM ' . $table);
				$num_fields = $result->columnCount();

				$backup .= 'DROP TABLE IF EXISTS ' . $table . ';';
				$row2 = $pdo->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM);
				$backup .= "\n\n" . $row2[1] . ";\n\n";

				while ($row = $result->fetch(PDO::FETCH_NUM)) {
					$backup .= 'INSERT INTO ' . $table . ' VALUES(';
					for ($j = 0; $j < $num_fields; $j++) {
						$row[$j] = addslashes($row[$j]);
						$row[$j] = preg_replace("/\n/", "\\n", $row[$j]);
						if (isset($row[$j])) {
							$backup .= '"' . $row[$j] . '"';
						} else {
							$backup .= '""';
						}
						if ($j < ($num_fields - 1)) {
							$backup .= ',';
						}
					}
					$backup .= ");\n";
				}
				$backup .= "\n\n\n";
			}

			// Save to file
			$handle = fopen(PATH_ROOT . $filename, 'w+');
			fwrite($handle, $backup);
			fclose($handle);

		} catch (\Box_Exception $e) {
			throw new \Box_Exception('Error during backup process: ' . $e->getMessage());
		}

		// read current module version from manifest.json
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$current_version = $manifest['version'];
		// add version comment to backup file
		$version_comment = '-- Proxmox module version: ' . $current_version . "\n";
		$filename = PATH_ROOT . $filename;
		$handle = fopen($filename, 'r+');
		$len = strlen($version_comment);
		$final_len = filesize($filename) + $len;
		$cache_old = fread($handle, $len);
		rewind($handle);
		$i = 1;
		while (ftell($handle) < $final_len) {
			fwrite($handle, $version_comment);
			$version_comment = $cache_old;
			$cache_old = fread($handle, $len);
			fseek($handle, $i * $len);
			$i++;
		}
		fclose($handle);

		return true;
	}


	/**
	 * Method to list all Proxmox backups
	 * 
	 * @return array
	 */
	public function pmxbackuplist()
	{
		$files = glob(PATH_ROOT . '/pmxconfig/*.sql');
		$backups = array();
		foreach ($files as $file) {
			$backups[] = basename($file);
		}
		return $backups;
	}

	/**
	 * Method to restore Proxmox tables from backup
	 * It's a destructive operation, as it will drop & overwrite all existing tables
	 * 
	 * @param string $data - filename of backup
	 * @return bool
	 */
	public function pmxbackuprestore($data)
	{
		$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
		$version = $manifest['version'];
		$dump = file_get_contents(PATH_ROOT . '/pmxconfig/' . $data['backup']);
		if (!empty($dump)) {
			$version_line = strtok($dump, "\n");
			$dump_version = str_replace('-- Proxmox module version: ', '', $version_line);
			$dump = str_replace($version_line . "\n", '', $dump);

			if ($dump_version == $version) {
				$db_user = $this->di['config']['db']['user'];
				$db_password = $this->di['config']['db']['password'];
				$db_name = $this->di['config']['db']['name'];

				try {
					// create PDO instance
					$pdo = $this->getPdo();
					// split the dump into an array by each sql command
					$query_array = explode(";", $dump);

					// execute each sql command
					foreach ($query_array as $query) {
						if (!empty(trim($query))) {
							$pdo->exec($query);
						}
					}

					return true;
				} catch (\Box_Exception $e) {
					throw new \Box_Exception('Error during restoration process: ' . $e->getMessage());
				}
			} else {
				throw new \Box_Exception("The sql dump file (V: $dump_version) is not compatible with the current module version (V: $version). Please check the file.", null);
			}
		} else {
			throw new \Box_Exception("The sql dump file is empty. Please check the file.", null);
		}
	}

	// Create function that runs with cron job hook
	// This function will run every 5 minutes and update all servers
	// Disabled for now
	/*
	public static function onBeforeAdminCronRun(\Box_Event $event)
	{
		 // getting Dependency Injector
		 $di = $event->getDi();

		 // @note almost in all cases you will need Admin API
		 $api = $di['api_admin'];
		 // get all servers from database
		 // like this $vms = $this->di['db']->findAll('service_proxmox', 'server_id = :server_id', array(':server_id' => $data['id']));
 		 $servers = $di['db']->findAll('service_proxmox_server');
		 // rum getHardwareData, getStorageData and getAssignedResources for each server 
		 foreach ($servers as $server) {
			$hardwareData = $api->getHardwareData($server['id']);
			$storageData = $api->getStorageData($server['id']);
			$assignedResources = $api->getAssignedResources($server['id']);
		  }
	} */


	/* ################################################################################################### */
	/* ###########################################  Orders  ############################################## */
	/* ################################################################################################### */

	/**
	 * @param \Model_ClientOrder $order
	 * @return void
	 */
	public function create($order)
	{
		$config = json_decode($order->config, 1);

		$product = $this->di['db']->getExistingModelById('Product', $order->product_id, 'Product not found');

		$model                	= $this->di['db']->dispense('service_proxmox');
		$model->client_id     	= $order->client_id;
		$model->order_id     	= $order->id;
		$model->created_at    	= date('Y-m-d H:i:s');
		$model->updated_at    	= date('Y-m-d H:i:s');

		$model->server_id = $this->find_empty($product);
		$this->di['db']->store($model);

		return $model;
	}

	/**
	 * @param \Model_ClientOrder $order
	 * @return boolean
	 */
	public function activate($order, $model)
	{
		if (!is_object($model)) {
			throw new \Box_Exception('Could not activate order. Service was not created');
		}
		$config = json_decode($order->config, 1);

		$client  = $this->di['db']->load('client', $order->client_id);
		$product = $this->di['db']->load('product', $order->product_id);
		if (!$product) {
			throw new \Box_Exception('Could not activate order because ordered product does not exists');
		}
		$product_config = json_decode($product->config, 1);

		// Allocate to an appropriate server id
		$server = $this->di['db']->load('service_proxmox_server', $model->server_id);

		// Retrieve or create client unser account in service_proxmox_users
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));
		if (!$clientuser) {
			$this->create_client_user($server, $client);
		}

		// Connect to Proxmox API
		$serveraccess = $this->find_access($server);
		// find client permissions for server
		$clientuser = $this->di['db']->findOne('service_proxmox_users', 'server_id = ? and client_id = ?', array($server->id, $client->id));
		$config = $this->di['mod_config']('Serviceproxmox');
		$proxmox = new \PVE2APIClient\PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $clientuser->admin_tokenname, tokensecret: $clientuser->admin_tokenvalue, debug: $config['pmx_debug_logging']);

		// Create Proxmox VM
		if ($proxmox->login()) {
			// compile VMID by combining the server id, the client id, and the order id separated by padded zeroes for thee numbers per variable
			$vmid = $server->id . str_pad($client->id, 3, '0', STR_PAD_LEFT) . str_pad($order->id, 3, '0', STR_PAD_LEFT);

			// check if vmid is already in use
			$vmid_in_use = $proxmox->get("/nodes/" . $server->node . "/qemu/" . $vmid);
			if ($vmid_in_use) {
				$vmid = $vmid . '1';
			}

			$proxmoxuser_password = $this->di['tools']->generatePassword(16, 4); // Generate password

			// Create VM
			$clone = '';
			$container_settings = array();
			$description = 'Service package ' . $model->id . ' belonging to client id: ' . $client->id;

			if ($product_config['clone'] == true) {
				$clone = '/' . $product_config['cloneid'] . '/clone';
				$container_settings = array(
					'newid' => $vmid,
					'name' => $model->username,
					'description' => $description,
					'full' => true
				);
			} else { // TODO: Implement Container templates 
				if ($product_config['virt'] == 'qemu') {
					$container_settings = array(
						'vmid' => $vmid,
						'name' => 'vm' . $vmid,              
						'node' => $server->name, 
						'description' => $description,
						'storage' => $product_config['storage'],
						'memory' => $product_config['memory'],
						'scsihw' => 'virtio-scsi-single',
						'scsi0' => "Storage01:10",
						'ostype' => "other",
						'kvm' => "0",
						'ide2' => $product_config['cdrom'] . ',media=cdrom',
						'sockets' => $product_config['cpu'],
						'cores' => $product_config['cpu'],
						'numa' => "0",
						'pool' => 'fb_client_' . $client->id,
					);
				} else {
					$container_settings = array(
						'vmid' => $vmid,
						'hostname' => 'vm' . $vmid,
						'description' => $description,
						'storage' => $product_config['storage'],
						'memory' => $product_config['memory'],
						'ostemplate' => $product_config['ostemplate'],
						'password' => $proxmoxuser_password,
						'net0' => $product_config['network']
					);
					// TODO: Storage for LXC
				}
			}

			// If the VM is properly created
			$vmurl = "/nodes/" . $server->name . "/" . $product_config['virt'] . $clone;

			$vmcreate = $proxmox->post($vmurl, $container_settings);
			if ($vmcreate) {

				// Start the vm
				sleep(20);
				$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $vmid . "/status/start", array());
				$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $vmid . "/status/current");

				if (!empty($status)) {
					sleep(10);
					// Wait until it has been started
					while ($status['status'] != 'running') {
						$proxmox->post("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $vmid . "/status/start", array());
						sleep(10);
						$status = $proxmox->get("/nodes/" . $server->name . "/" . $product_config['virt'] . "/" . $vmid . "/status/current");
						// TODO: Check Startup
					}
				} else {
					throw new \Box_Exception("VMID cannot be found");
				}
			} else {
				throw new \Box_Exception("VPS has not been created");
			}
		} else {
			throw new \Box_Exception('Login to Proxmox Host failed with client credentials', null, 7457);
		}

		// Retrieve VM IP

		$model->server_id 		= $model->server_id;
		$model->updated_at    	= date('Y-m-d H:i:s');
		$model->vmid			= $vmid;
		$model->password		= $proxmoxuser_password;
		//$model->ipv4			= $ipv4;      	// TODO: Retrieve IP address of the VM from the PMX IPAM module
		//$model->ipv6			= $ipv6;		// TODO: Retrieve IP address of the VM from the PMX IPAM module
		//$model->hostname		= $hostname;	// TODO: Retrieve hostname from the Order form
		$this->di['db']->store($model);

		return array(
			'ip'	=> 	'to be sent by us shortly',		// $model->ipv4 - Return IP address of the VM
			'username'  =>  'root',
			'password'  =>  'See Admin Area', // Password won't be sen't by E-Mail. It will be stored in the database and can be retrieved from the client area
		);
	}

	/**
	 * Get the API array representation of a model
	 * Important to interact with the Order
	 * @param  object $model
	 * @return array
	 */
	public function toApiArray($model)
	{
		// Retrieve associated server
		$server = $this->di['db']->findOne('service_proxmox_server', 'id=:id', array(':id' => $model->server_id));

		return array(
			'id'              => $model->id,
			'client_id'       => $model->client_id,
			'server_id'       => $model->server_id,
			'username'        => $model->username,
			'mailbox_quota'   => $model->mailbox_quota,
			'server' 		  => $server,
		);
	}

	/**
	 * Retrieves the novnc appjs file from a Proxmox server.
	 *
	 * @param array $data An array containing the version of the appjs file to retrieve.
	 * @return object The contents of the appjs file.
	 */
	public function get_novnc_appjs($data)
	{
		// get list of servers

		$servers = $this->di['db']->find('service_proxmox_server');
		// select first server
		$server = $servers['2'];

		$hostname = $server->hostname;
		// build url

		$url = "https://$hostname:8006/novnc/" . $data; //$data['ver'];
		// set options
		$client = $this->getHttpClient()->withOptions([
			'verify_peer' => false,
			'verify_host' => false,
			'timeout' => 60,
		]);
		$result = $client->request('GET', $url);
		// return file
		return $result;
	}

	/**
	 * Returns an instance of the Symfony HttpClient.
	 *
	 * @return \Symfony\Component\HttpClient\HttpClient
	 */
	public function getHttpClient()
	{
		return \Symfony\Component\HttpClient\HttpClient::create();
	}



	/**
	 * Validates custom form data against a product's form fields.
	 * TODO: This needs to be fixes / changed
	 * @param array &$data The form data to validate.
	 * @param array $product The product containing the form fields to validate against.
	 * @throws \Box_Exception If a required field is missing or a read-only field is modified.
	 */
	public function validateCustomForm(array &$data, array $product)
	{
		if ($product['form_id']) {
			$formbuilderService = $this->di['mod_service']('formbuilder');
			$form = $formbuilderService->getForm($product['form_id']);

			foreach ($form['fields'] as $field) {
				if ($field['required'] == 1) {
					$field_name = $field['name'];
					if ((!isset($data[$field_name]) || empty($data[$field_name]))) {
						throw new \Box_Exception("You must fill in all required fields. " . $field['label'] . " is missing", null, 9684);
					}
				}

				if ($field['readonly'] == 1) {
					$field_name = $field['name'];
					if ($data[$field_name] != $field['default_value']) {
						throw new \Box_Exception("Field " . $field['label'] . " is read only. You can not change its value", null, 5468);
					}
				}
			}
		}
	}
	/**
	 * Returns the salt value from the configuration.
	 *
	 * @return string The salt value.
	 */
	private function _getSalt()
	{
		return $this->di['config']['salt'];
	}

	private function getProxmoxInstance($server)
	{
		$serveraccess = $this->find_access($server);
		$config = $this->di['mod_config']('Serviceproxmox');
		return new \PVE2APIClient\PVE2_API($serveraccess, $server->root_user, $server->realm, $server->root_password, port: $server->port, tokenid: $server->tokenname, tokensecret: $server->tokenvalue, debug: $config['pmx_debug_logging']);
	}
}
