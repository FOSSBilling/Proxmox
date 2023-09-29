<?php

/*

Proxmox VE APIv2 (PVE2) Client - PHP Class

Copyright (c) 2012-2014 Nathan Sullivan

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files (the "Software"), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

namespace Box\Mod\Serviceproxmox;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class PVE2_Exception extends \RuntimeException
{
}

/**
 * PVE2_API class represents a client for the Proxmox VE API.
 *
 * This class provides methods for interacting with the Proxmox VE API, allowing users to manage virtual machines, containers, and other resources.
 *
 * @category Proxmox VE
 * @package  PVE2_API_Client
 */
class PVE2_API
{
	protected string $hostname;
	protected ?string $username = null;
	protected string $realm;
	protected ?string $password = null;
	protected int $port;
	protected bool $verify_ssl;
	protected ?string $tokenid = null;
	protected ?string $tokensecret = null;
	protected bool $api_token_access = false;
	protected ?array $login_ticket = null;
    protected ?int $login_ticket_timestamp = null;
    protected ?array $clusterNodeList = null;
	protected bool $debug = false;

	public function __construct(
		string $hostname,
		?string $username = null,
		string $realm,
		?string $password = null,
		int $port = 8006,
		bool $verify_ssl = false,
		?string $tokenid = null,
		?string $tokensecret = null,
		bool $debug = false
	) {
		$validator = Validation::createValidator();

		$constraints = new Assert\Collection([
			'hostname' => new Assert\NotBlank(),
			'realm' => new Assert\NotBlank(),
			'port' => new Assert\Range(['min' => 1, 'max' => 65535]),
			'verify_ssl' => new Assert\Type('bool'),
		]);

		$input = [
			'hostname' => $hostname,
			'realm' => $realm,
			'port' => $port,
			'verify_ssl' => $verify_ssl,
		];

		$violations = $validator->validate($input, $constraints);

		$violationsList = [];
		foreach ($violations as $violation) {
			$violationsList[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
		}

		if (!empty($violationsList)) {
			throw new BadRequestException('Invalid input parameters: ' . implode(', ', $violationsList));
		}

		if ((empty($username) || empty($password)) && (empty($tokenid) || empty($tokensecret))) {
			throw new BadRequestException('Either username and password OR tokenid and tokensecret must be provided.');
		}

		if (empty($username) && empty($password) && empty($tokenid) && empty($tokensecret)) {
			throw new BadRequestException('Both username/password and tokenid/tokensecret cannot be empty. At least one pair must be provided.');
		}


		if (gethostbyname($hostname) == $hostname && !filter_var($hostname, FILTER_VALIDATE_IP)) {
			throw new BadRequestException("Cannot resolve {$hostname}.");
		}

		$this->hostname = $hostname;
		$this->username = $username;
		$this->realm = $realm;
		$this->password = $password;
		$this->port = $port;
		$this->verify_ssl = $verify_ssl;
		$this->tokenid = $tokenid;
		$this->tokensecret = $tokensecret;
		$this->debug = $debug;

		$this->api_token_access = !empty($tokenid) && !empty($tokensecret);
	}


	public function login(): bool
	{
		$apiUrlBase = "https://{$this->hostname}:{$this->port}/api2/json";
		$client = HttpClient::create(['verify_peer' => $this->verify_ssl, 'verify_host' => $this->verify_ssl]);

		if ($this->api_token_access) {
			$response = $client->request('GET', "$apiUrlBase/version", [
				'headers' => ["Authorization" => "PVEAPIToken={$this->tokenid}={$this->tokensecret}"],
			]);

			if (200 !== $response->getStatusCode()) {
				// Handle error appropriately
				return false;
			}

			try {
				$data = $response->toArray();
				$this->reloadNodeList();
				return true;
			} catch (JsonException $e) {
				// Handle JSON error
				return false;
			}
		}

		$response = $client->request('POST', "$apiUrlBase/access/ticket", [
			'body' => [
				'username' => $this->username,
				'password' => $this->password,
				'realm'    => $this->realm,
			]
		]);

		if (200 !== $response->getStatusCode()) {
			// Handle error appropriately
			return false;
		}

		try {
			$data = $response->toArray();
			$this->login_ticket = $data['data'];
			$this->login_ticket_timestamp = time();
			$this->reloadNodeList();
			return true;
		} catch (JsonException $e) {
			// Handle JSON error
			return false;
		}
	}


	protected function checkLoginTicket(): bool
	{
		if ($this->api_token_access) {
			return true;
		}

		if ($this->login_ticket === null || $this->login_ticket_timestamp === null) {
			$this->resetLoginTicket();
			return false;
		}

		// If the current timestamp is greater than the timestamp of the login ticket plus 7200 seconds (2 hours), it is expired.
		if (time() >= $this->login_ticket_timestamp + 7200) {
			$this->resetLoginTicket();
			return false;
		}

		return true;
	}

	private function resetLoginTicket(): void
	{
		$this->login_ticket = null;
		$this->login_ticket_timestamp = null;
	}


	private function action(string $actionPath, string $httpMethod, array $parameters = []): mixed
	{
		$actionPath = $this->normalizeActionPath($actionPath);

		if (!$this->checkLoginTicket()) {
			throw new PVE2_Exception("No valid connection to Proxmox host. No Login access ticket found, ticket expired or no API Token set up.", 3);
		}

		$url = "https://{$this->hostname}:{$this->port}/api2/json{$actionPath}";

		$client = HttpClient::create([
			'headers' => $this->buildHeaders(),
			'verify_peer' => $this->verify_ssl,
			'verify_host' => $this->verify_ssl,
		]);

		try {
			$response = $client->request($httpMethod, $url, ['body' => $parameters]);
			$statusCode = $response->getStatusCode();
			$errorMessage = "API Request failed. HTTP Response - {$statusCode}";
			if ($this->debug) {
				$errorMessage .= PHP_EOL . "HTTP Method: {$httpMethod}" . PHP_EOL . "URL: {$url}" . PHP_EOL . "Parameters: " . json_encode($parameters) . PHP_EOL . "Response Headers: " . json_encode($response->getHeaders(false)) . PHP_EOL . "Response: " . $response->getContent(false);
			} else {
				$errorMessage = $response->toArray()['errors'] ?? $errorMessage;
			}
			if ($statusCode === 200) {
				return $response->toArray()['data'] ?? true;
			}
			
			// Handle the 500 status and check ReasonPhrase
			if ($statusCode === 500) {
				return null;
			}
			
			

			throw new PVE2_Exception($errorMessage, $statusCode);
			
		} catch (TransportExceptionInterface $e) {
			$errorMessage = "Transport Exception: " . $e->getMessage();
			if ($this->debug) {
				$errorMessage .= PHP_EOL . "HTTP Method: {$httpMethod}" . PHP_EOL . "URL: {$url}" . PHP_EOL . "Parameters: " . json_encode($parameters) . PHP_EOL . "Response Headers: " . json_encode($response->getHeaders(false)) . PHP_EOL . "Response: " . $response->getContent(false);
			}
			throw new PVE2_Exception($errorMessage, 0, $e);
		}
	}

	private function normalizeActionPath(string $actionPath): string
	{
		return '/' . ltrim($actionPath, '/');
	}

	private function buildHeaders(): array
	{
		$headers = [
			'Content-Type' => 'application/x-www-form-urlencoded',
			'Accept' => 'application/json',
		];

		if ($this->api_token_access) {
			$headers['Authorization'] = "PVEAPIToken={$this->tokenid}={$this->tokensecret}";
		} else {
			$headers['CSRFPreventionToken'] = $this->login_ticket['CSRFPreventionToken'];
			$headers['Cookie'] = "PVEAuthCookie=" . $this->login_ticket['ticket'];
		}

		return $headers;
	}
	public function __call($name, $arguments)
	{
		if (in_array(strtoupper($name), ['GET', 'POST', 'PUT', 'DELETE'])) {
			$actionPath = $arguments[0] ?? '';
			$parameters = $arguments[1] ?? [];
			return $this->action($actionPath, strtoupper($name), $parameters);
		}
	
		throw new BadMethodCallException("Method {$name} not exists in " . __CLASS__);
	}

	public function reloadNodeList(): bool
	{
		$nodeList = $this->get("/nodes");

		if ($nodeList && count($nodeList) > 0) {
			$this->clusterNodeList = array_map(static fn ($node) => $node['node'], $nodeList);
			return true;
		}

		// Handle error according to your application’s error handling strategy
		error_log("Empty list of nodes returned in this cluster.");
		return false;
	}


	public function getNodeList(): ?array
	{
		if ($this->clusterNodeList === null && !$this->reloadNodeList()) {
			return null;
		}

		return $this->clusterNodeList;
	}

	public function getNextVmid(): ?int
	{
		return $this->get("/cluster/nextid") ?: null;
	}

	public function getVms(): ?array
	{
		$nodeList = $this->getNodeList();
		if (!$nodeList) {
			return null;
		}

		$result = [];
		foreach ($nodeList as $nodeName) {
			$vmsList = $this->get("nodes/$nodeName/qemu/");
			if ($vmsList) {
				array_walk($vmsList, static fn (&$row) => $row['node'] = $nodeName);
				$result = array_merge($result, $vmsList);
			}
		}
		return $result ?: null;
	}

	public function manageVm(string $node, int $vmid, string $action, array $parameters): bool
	{
		$url = "/nodes/$node/qemu/$vmid/status/$action";
		return (bool) $this->post($url, $parameters);
	}

	public function startVm(string $node, int $vmid): bool
	{
		return $this->manageVm($node, $vmid, 'start', ['vmid' => $vmid, 'node' => $node]);
	}

	public function shutdownVm(string $node, int $vmid): bool
	{
		return $this->manageVm($node, $vmid, 'shutdown', ['vmid' => $vmid, 'node' => $node, 'timeout' => 60]);
	}

	public function stopVm(string $node, int $vmid): bool
	{
		return $this->manageVm($node, $vmid, 'stop', ['vmid' => $vmid, 'node' => $node, 'timeout' => 60]);
	}

	public function resumeVm(string $node, int $vmid): bool
	{
		return $this->manageVm($node, $vmid, 'resume', ['vmid' => $vmid, 'node' => $node, 'timeout' => 60]);
	}

	public function suspendVm(string $node, int $vmid): bool
	{
		return $this->manageVm($node, $vmid, 'suspend', ['vmid' => $vmid, 'node' => $node, 'timeout' => 60]);
	}

	public function cloneVm(string $node, int $vmid): bool
	{
		$newid = $this->getNextVmid();
		$url = "/nodes/$node/qemu/$vmid/clone";
		$parameters = ['vmid' => $vmid, 'node' => $node, 'newid' => $newid, 'full' => true];

		return (bool) $this->post($url, $parameters);
	}

	public function snapshotVm(string $node, int $vmid, ?string $snapname = null): bool
	{
		$url = "/nodes/$node/qemu/$vmid/snapshot";
		$parameters = ['vmid' => $vmid, 'node' => $node, 'vmstate' => true, 'snapname' => $snapname];

		return (bool) $this->post($url, $parameters);
	}

	public function getVersion(): ?string
	{
		$version = $this->get("/version");
		return $version['version'] ?? null;
	}
}
