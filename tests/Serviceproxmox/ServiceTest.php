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

    
    public function test_create()
    {
        // Mocking the order model
        $orderModel = new \Model_ClientOrder();
        $orderModel->loadBean(new \DummyBean());
        $orderModel->config = json_encode(['some_config_key' => 'some_config_value']); // Example config
        $orderModel->product_id = 123; // Example product ID
    
        // Mocking the product model
        $productModel = new \Model_Product();
        $productModel->loadBean(new \DummyBean());
    
        // Mocking the database
        $dbMock = $this->getMockBuilder('\Box_Database')->getMock();
        $dbMock->expects($this->atLeastOnce())
            ->method('getExistingModelById')
            ->with('Product', $orderModel->product_id, 'Product not found')
            ->will($this->returnValue($productModel));
    
        $serviceProxmoxModel = new \RedBeanPHP\SimpleModel();
        $serviceProxmoxModel->loadBean(new \DummyBean());
        $dbMock->expects($this->atLeastOnce())
            ->method('dispense')
            ->with('service_proxmox')
            ->will($this->returnValue($serviceProxmoxModel));
    
        $dbMock->expects($this->atLeastOnce())
            ->method('store')
            ->with($serviceProxmoxModel);
    
        $di = new \Pimple\Container();
        $di['db'] = $dbMock;
    
        // Mocking the find_empty method
        $serviceProxmoxMock = $this->getMockBuilder('\Box\Mod\Serviceproxmox\Service') 
            ->setMethods(['find_empty'])
            ->getMock();
        $serviceProxmoxMock->expects($this->once())
            ->method('find_empty')
            ->with($productModel)
            ->will($this->returnValue(1)); // Example server ID
    
        $serviceProxmoxMock->setDi($di);
        $result = $serviceProxmoxMock->create($orderModel);
    
        $this->assertInstanceOf('\RedBeanPHP\SimpleModel', $result);
        $this->assertEquals($orderModel->client_id, $result->client_id);
        $this->assertEquals(1, $result->server_id); // Asserting the server ID is set correctly
    }   
}
