<?php


namespace Box\Mod\Serviceproxmox;


class AdminTest extends \BBTestCase
{

    /**
     * @var \Box\Mod\Serviceproxmox\Api\Admin
     */
    protected $api = null;

    public function setup(): void
    {
        $this->api = new \Box\Mod\Serviceproxmox\Api\Admin();
    }

    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->api->setDi($di);
        $getDi = $this->api->getDi();
        $this->assertEquals($di, $getDi);
    }

}
