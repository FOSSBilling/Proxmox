<?php


namespace Box\Mod\Serviceproxmox;

class ServiceTest extends \BBTestCase {
    /**
     * @var \Box\Mod\Serviceproxmox\Service
     */
    protected $service = null;

    public function setup(): void
    {
        $this->service= new \Box\Mod\Serviceproxmox\Service();
    }


    public function testgetDi()
    {
        $di = new \Pimple\Container();
        $this->service->setDi($di);
        $getDi = $this->service->getDi();
        $this->assertEquals($di, $getDi);
    }

 


}
