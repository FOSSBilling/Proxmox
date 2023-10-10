<?php


namespace Box\Mod\Serviceproxmox\Api;


class ClientTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Serviceproxmox\Api\Client
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api= new \Box\Mod\Serviceproxmox\Api\Client();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }
}
 