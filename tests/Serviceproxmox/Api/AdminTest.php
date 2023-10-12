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

    public function testServerGetList()
    {
        // Mocking the service_proxmox_server model
        $serverModel = new \RedBeanPHP\SimpleModel();
        $serverModel->loadBean(new \DummyBean());
        $serverModel->id = 1;
        $serverModel->ram = 4096; // 4GB in MB
        $serverModel->cpu_cores = 4;
        $serverModel->group = 'testGroup';
    
        // Mocking the service_proxmox model
        $proxmoxModel = new \RedBeanPHP\SimpleModel();
        $proxmoxModel->loadBean(new \DummyBean());
        $proxmoxModel->server_id = 1;
        $proxmoxModel->cpu_cores = 2;
        $proxmoxModel->ram = 20480; // 20GB in MB
    
        // Mocking the database
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->at(0))
            ->method('find')
            ->with('service_proxmox_server')
            ->will($this->returnValue([$serverModel]));
        $dbMock->expects($this->at(1))
            ->method('find')
            ->with('service_proxmox', 'server_id=:id', array(':id' => $serverModel->id))
            ->will($this->returnValue([$proxmoxModel]));
    
        // Mocking mod_config
        $config = [
            'cpu_overprovisioning' => 50, // Example value
            'ram_overprovisioning' => 50  // Example value
        ];
        $modConfigMock = $this->getMockBuilder('\Box_ModConfig')->getMock();
        $modConfigMock->expects($this->once())
            ->method('get')
            ->with('Serviceproxmox')
            ->will($this->returnValue($config));
    
        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
        $di['mod_config'] = $modConfigMock;
    
        $this->api->setDi($di);
        $this->api->setService($serviceMock);

        $result = $this->api->server_get_list(array());
        $this->assertIsArray($result);
    }
    

}
