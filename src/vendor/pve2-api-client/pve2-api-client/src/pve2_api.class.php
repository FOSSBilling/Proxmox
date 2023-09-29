<?php

/*

Proxmox VE APIv2 ( PVE2 ) Client - PHP Class using Symfony HttpClient and Validator

This is currently a pretty simple wrapper, but it does provide the basic functionality needed to interact with the Proxmox VE API.

Copyright ( c ) 2023 Christoph Schläpfer ( Anuril )
Copyright ( c ) 2012-2014 Nathan Sullivan

Permission is hereby granted, free of charge, to any person obtaining a copy of
this software and associated documentation files ( the 'Software' ), to deal in
the Software without restriction, including without limitation the rights to
use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
the Software, and to permit persons to whom the Software is furnished to do so,
subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED 'AS IS', WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

*/

namespace Symfony\Component\PVE2API;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\Validator\Exception\BadMethodCallException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\HttpFoundation\Exception\JsonException;

class PVE2_Exception extends \RuntimeException {
}

/**
* PVE2_API class represents a client for the Proxmox VE API.
*
* This class provides methods for interacting with the Proxmox VE API, allowing users to manage virtual machines, containers, and other resources.
* @method array<mixed>|int get( string $actionPath, array<mixed> $parameters = [] )
* @method array<mixed> post( string $actionPath, array<mixed> $parameters = [] )
* @method array<mixed> put( string $actionPath, array<mixed> $parameters = [] )
* @method array<mixed> delete( string $actionPath, array<mixed> $parameters = [] )
*
* @category Proxmox VE
* @package  PVE2_API_Client
*/

class PVE2_API {
    protected string $hostname;
    protected ?string $username = null;
    protected string $realm;
    protected ?string $password = null;
    protected int $port;
    protected bool $verify_ssl;
    protected ?string $tokenid = null;
    protected ?string $tokensecret = null;
    protected bool $api_token_access = false;
    /**
     * @var array<mixed>|null
     */
    protected ?array $login_ticket = null;
    protected ?int $login_ticket_timestamp = null;
    /**
     * @var array<mixed>|null
     */
    protected ?array $clusterNodeList = null;
    protected bool $debug = false;
    /**
     * @var array<mixed>
     */
    private array $supportedVmActions = [
        'reboot',
        'reset',
        'resume',
        'shutdown',
        'start',
        'stop',
        'suspend'
    ];

    /**
    * Constructor for the PVE2 API client.
    */

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

        $constraints = new Assert\Collection( [
            'hostname' => new Assert\NotBlank(),
            'realm' => new Assert\NotBlank(),
            'port' => new Assert\Range( [ 'min' => 1, 'max' => 65535 ] ),
            'verify_ssl' => new Assert\Type( 'bool' ),
        ] );

        $input = [
            'hostname' => $hostname,
            'realm' => $realm,
            'port' => $port,
            'verify_ssl' => $verify_ssl,
        ];

        $violations = $validator->validate( $input, $constraints );

        $violationsList = [];
        foreach ( $violations as $violation ) {
            $violationsList[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        if ( !empty( $violationsList ) ) {
            throw new BadRequestException( 'Invalid input parameters: ' . implode( ', ', $violationsList ) );
        }

        if ( ( empty( $username ) || empty( $password ) ) && ( empty( $tokenid ) || empty( $tokensecret ) ) ) {
            throw new BadRequestException( 'Either username and password OR tokenid and tokensecret must be provided.' );
        }

        if ( empty( $username ) && empty( $password ) && empty( $tokenid ) && empty( $tokensecret ) ) {
            throw new BadRequestException( 'Both username/password and tokenid/tokensecret cannot be empty. At least one pair must be provided.' );
        }

        if ( gethostbyname( $hostname ) == $hostname && !filter_var( $hostname, FILTER_VALIDATE_IP ) ) {
            throw new BadRequestException( "Cannot resolve {$hostname}." );
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

        $this->api_token_access = !empty( $tokenid ) && !empty( $tokensecret );
    }

    /**
    * Logs in to the Proxmox VE API using the provided credentials.
    *
    * @return bool Returns true if the login was successful, false otherwise.
    */

    public function login(): bool {
        $apiUrlBase = "https://{$this->hostname}:{$this->port}/api2/json";
        $client = HttpClient::create( [ 'verify_peer' => $this->verify_ssl, 'verify_host' => $this->verify_ssl ] );

        if ( $this->api_token_access ) {
            $response = $client->request( 'GET', "$apiUrlBase/version", [
                'headers' => [ 'Authorization' => "PVEAPIToken={$this->tokenid}={$this->tokensecret}" ],
            ] );

            if ( 200 !== $response->getStatusCode() ) {
                // Handle error appropriately
                return false;
            }

            try {
                $data = $response->toArray();
                $this->reloadNodeList();
                return true;
            } catch ( JsonException $e ) {
                // Handle JSON error
                return false;
            }
        }

        $response = $client->request( 'POST', "$apiUrlBase/access/ticket", [
            'body' => [
                'username' => $this->username,
                'password' => $this->password,
                'realm'    => $this->realm,
            ]
        ] );

        if ( 200 !== $response->getStatusCode() ) {
            return false;
        }

        try {
            $data = $response->toArray();
            $this->login_ticket = $data[ 'data' ];
            $this->login_ticket_timestamp = time();
            $this->reloadNodeList();
            return true;
        } catch ( JsonException $e ) {
            return false;
        }
    }

    /**
    * Checks if the login ticket is valid.
    *
    * @return bool Returns true if the login ticket is valid, false otherwise.
    */
    protected function checkLoginTicket(): bool {
        if ( $this->api_token_access ) {
            return true;
        }

        if ( $this->login_ticket === null || $this->login_ticket_timestamp === null ) {
            $this->resetLoginTicket();
            return false;
        }

        // If the current timestamp is greater than the timestamp of the login ticket plus 7200 seconds ( 2 hours ), it is expired.
        if ( time() >= $this->login_ticket_timestamp + 7200 ) {
            $this->resetLoginTicket();
            return false;
        }

        return true;
    }

    /**
    * Resets the login ticket used for authentication with the Proxmox VE API.
    *
    * @return void
    */

    private function resetLoginTicket(): void {
        $this->login_ticket = null;
        $this->login_ticket_timestamp = null;
    }

    /**
    * Performs an action on the Proxmox VE API.
    *
    * @param string $actionPath The path of the action to perform.
    * @param string $httpMethod The HTTP method to use for the action.
    * @param array<mixed> $parameters An optional array of parameters to include in the request.
    * @return mixed|int The response from the API.
    */

    private function action( string $actionPath, string $httpMethod, array $parameters = [] ): mixed {
        $actionPath = $this->normalizeActionPath( $actionPath );

        if ( !$this->checkLoginTicket() ) {
            throw new PVE2_Exception( 'No valid connection to Proxmox host. No Login access ticket found, ticket expired or no API Token set up.', 3 );
        }

        $url = "https://{$this->hostname}:{$this->port}/api2/json{$actionPath}";

        $client = HttpClient::create( [
            'headers' => $this->buildHeaders(),
            'verify_peer' => $this->verify_ssl,
            'verify_host' => $this->verify_ssl,
        ] );

        try {
            $response = $client->request( $httpMethod, $url, [ 'body' => $parameters ] );
            $statusCode = $response->getStatusCode();
            $errorMessage = "API Request failed. HTTP Response - {$statusCode}";
            if ( $this->debug ) {
                $errorMessage .= PHP_EOL . "HTTP Method: {$httpMethod}" . PHP_EOL . "URL: {$url}" . PHP_EOL . 'Parameters: ' . json_encode( $parameters ) . PHP_EOL . 'Response Headers: ' . json_encode( $response->getHeaders( false ) ) . PHP_EOL . 'Response: ' . $response->getContent( false );
            } else {
                $errorMessage = $response->toArray()[ 'errors' ] ?? $errorMessage;
            }

            if ( $statusCode === 200 ) {
                return $response->toArray()[ 'data' ] ?? true;
            }

            // Write the Status code and the ReasonPhrase to the error log
            error_log( "Action Failed. Status Code: {$statusCode} - " . $response->getInfo( 'response_headers' )[ 'reason_phrase' ] );

            if ( $this->debug ) {
                error_log( "Debug Information: {$errorMessage}" );
            }

            if ( $statusCode === 500 || $statusCode === 501 ) {
                return null;
            }

            throw new PVE2_Exception( $errorMessage, $statusCode );
        } catch ( TransportExceptionInterface $e ) {
            $errorMessage = 'Transport Exception: ' . $e->getMessage();
            if ( $this->debug ) {
                $errorMessage .= PHP_EOL . "HTTP Method: {$httpMethod}" . PHP_EOL . "URL: {$url}" . PHP_EOL . 'Parameters: ' . json_encode( $parameters );
                if ( isset( $response )) {
                    $errorMessage .= PHP_EOL . 'Response Headers: ' . json_encode( $response->getHeaders( false ) ) . PHP_EOL . 'Response: ' . $response->getContent(false);
                }
            }
            throw new PVE2_Exception( $errorMessage, 0, $e );
        }
    }

    /**
    * Normalizes the given action path.
    *
    * @param string $actionPath The action path to be normalized.
    * @return string The normalized action path.
    */

    private function normalizeActionPath( string $actionPath ): string {
        return '/' . ltrim( $actionPath, '/' );
    }

    /**
    * Builds an array of headers to be used in API requests.
    *
    * @return array<mixed> An array of headers.
    */

    private function buildHeaders(): array {
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json',
        ];

        if ( $this->api_token_access ) {
            $headers[ 'Authorization' ] = "PVEAPIToken={$this->tokenid}={$this->tokensecret}";
        } else {
            $headers[ 'CSRFPreventionToken' ] = $this->login_ticket[ 'CSRFPreventionToken' ];
            $headers[ 'Cookie' ] = 'PVEAuthCookie=' . $this->login_ticket[ 'ticket' ];
        }

        return $headers;
    }

    /**
    * Magic method that allows calling of PVE API methods dynamically.
    *
    * @param string $name The name of the method being called.
    * @param array<mixed> $arguments An array of arguments passed to the method.
    * @return mixed The result of the method call.
    */

    public function __call( $name, $arguments ) {
        if ( in_array( strtoupper( $name ), [ 'GET', 'POST', 'PUT', 'DELETE' ] ) ) {
            $actionPath = $arguments[ 0 ] ?? '';
            $parameters = $arguments[ 1 ] ?? [];
            return $this->action( $actionPath, strtoupper( $name ), $parameters );
        }

        throw new BadMethodCallException( "Method {$name} not exists in " . __CLASS__ );
    }

    /**
    * Reloads the list of available nodes in the Proxmox VE cluster.
    *
    * @return bool Returns true on success, false otherwise.
    */

    public function reloadNodeList(): bool {
        $nodeList = $this->get( '/nodes' );
        if ( is_array( $nodeList ) ) {
            if ( count( $nodeList ) > 0) {
                $this->clusterNodeList = array_map( static fn ( $node ) => $node[ 'node' ], $nodeList );
                return true;
            }
        }

        // Handle error according to your application’s error handling strategy
        error_log( 'Empty list of nodes returned in this cluster.' );
        return false;
    }

    /**
    * Returns an array of nodes in the Proxmox VE cluster.
    *
    * @return array<mixed>|null An array of nodes in the Proxmox VE cluster, or null if the request fails.
    */

    public function getNodeList(): ?array {
        if ( $this->clusterNodeList === null && !$this->reloadNodeList() ) {
            return null;
        }

        return $this->clusterNodeList;
    }

    /**
    * Returns the next available VM ID.
    *
    * @return int|null The next available VM ID, or null if none are available.
    */
    public function getNextVmid(): ?int {
        /* @phpstan-ignore-next-line */ // The return type of getNextVmid() is int|null, but the return type of get() is array<mixed>|int|null, and for '/cluster/nextid' it will always return int.
        return $this->get( '/cluster/nextid' ) ?: null;
    }

    /**
    * Returns an array of virtual machines.
    *
    * @return array<mixed>|null An array of virtual machines or null if there are no virtual machines.
    */

    public function getVms(): ?array {
        $nodeList = $this->getNodeList();
        if ( !$nodeList ) {
            return null;
        }

        $result = [];
        foreach ( $nodeList as $nodeName ) {
            $vmsList = $this->get( "nodes/$nodeName/qemu/" );
            // if the vmsList is an array, add the node name to each row
            if ( is_array( $vmsList ) ) {
                array_walk( $vmsList, static fn ( &$row ) => $row[ 'node' ] = $nodeName );
                $result = array_merge( $result, $vmsList );
            }
        }
        return $result ?: null;
    }

    /**
    * Manage a virtual machine on a Proxmox VE node.
    *
    * @param string $node The name of the Proxmox VE node.
    * @param int $vmid The ID of the virtual machine.
    * @param string $action The action to perform on the virtual machine ( e.g. start, stop, reset ).
    * @param int $timeout The maximum time in seconds to wait for the action to complete ( default: 60 ).
    *
    * @return bool Returns true if the action was successful, false otherwise.
    */

    public function manageVm( string $node, int $vmid, string $action, int $timeout = 60 ): bool {
        if ( !in_array( $action, $this->supportedVmActions ) ) {
            throw new \InvalidArgumentException( "Unsupported action: $action" );
        }

        $url = "/nodes/$node/qemu/$vmid/status/$action";

        $parameters = [
            'vmid' => $vmid,
            'node' => $node,
            'timeout' => $timeout
        ];

        return ( bool ) $this->post( $url, $parameters );
    }

    /**
    * Clone a virtual machine on a Proxmox VE node.
    *
    * @param string $node The name of the Proxmox VE node.
    * @param int $vmid The ID of the virtual machine to be cloned.
    * @param int|null $newid The ID for the new cloned virtual machine. If not provided, the next available ID will be used.
    * @return bool Returns true if the virtual machine was successfully cloned, false otherwise.
    */

    public function cloneVm( string $node, int $vmid, int $newid = null ): bool {
        $newid = $newid ?? $this->getNextVmid();
        $url = "/nodes/$node/qemu/$vmid/clone";
        $parameters = [ 'vmid' => $vmid, 'node' => $node, 'newid' => $newid, 'full' => true ];

        return ( bool ) $this->post( $url, $parameters );
    }

    /**
    * Create a snapshot of a virtual machine.
    *
    * @param string $node The name of the Proxmox node.
    * @param int $vmid The ID of the virtual machine.
    * @param string|null $snapname The name of the snapshot ( optional ).
    *
    * @return bool Returns true on success, false otherwise.
    */

    public function snapshotVm( string $node, int $vmid, ?string $snapname = null ): bool {
        $url = "/nodes/$node/qemu/$vmid/snapshot";
        $parameters = [ 'vmid' => $vmid, 'node' => $node, 'vmstate' => true, 'snapname' => $snapname ];

        return ( bool ) $this->post( $url, $parameters );
    }

    /**
    * Get the version of the Proxmox VE API.
    *
    * @return string|null The version of the Proxmox VE API, or null if it could not be determined.
    */

    public function getVersion(): ?string {
        $version = $this->get( '/version' );
        return $version[ 'version' ] ?? null;
    }
}
