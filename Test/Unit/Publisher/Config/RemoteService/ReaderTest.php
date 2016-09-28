<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\MessageQueue\Test\Unit\Publisher\Config\RemoteService;

use Magento\Framework\Communication\Config\ReflectionGenerator;
use Magento\Framework\MessageQueue\DefaultValueProvider;
use Magento\Framework\MessageQueue\Publisher\Config\RemoteService\Reader;
use Magento\Framework\ObjectManager\ConfigInterface as ObjectManagerConfig;
use Magento\Framework\Reflection\MethodsMap as ServiceMethodsMap;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;

class ReaderTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Reader
     */
    private $reader;

    /**
     * @var DefaultValueProvider|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $defaultValueProvider;

    /**
     * @var ObjectManagerConfig|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $objectManagerConfig;

    /**
     * @var ReflectionGenerator|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $reflectionGenerator;

    /**
     * @var ServiceMethodsMap|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $serviceMethodsMap;

    /**
     * Initialize parameters
     */
    protected function setUp()
    {
        $objectManager = new ObjectManager($this);
        $this->defaultValueProvider = $this->getMock(DefaultValueProvider::class, [], [], '', false, false);
        $this->objectManagerConfig = $this->getMock(ObjectManagerConfig::class, [], [], '', false, false);
        $this->reflectionGenerator = $this->getMock(ReflectionGenerator::class, [], [], '', false, false);
        $this->serviceMethodsMap = $this->getMock(ServiceMethodsMap::class, [], [], '', false, false);
        $this->reader = $objectManager->getObject(
            Reader::class,
            [
                'defaultValueProvider' => $this->defaultValueProvider,
                'objectManagerConfig' => $this->objectManagerConfig,
                'reflectionGenerator' => $this->reflectionGenerator,
                'serviceMethodsMap' => $this->serviceMethodsMap,
            ]
        );
    }

    public function testRead()
    {
        $this->defaultValueProvider->expects($this->any())->method('getConnection')->willReturn('amqp');
        $this->defaultValueProvider->expects($this->any())->method('getExchange')->willReturn('magento');

        $this->objectManagerConfig->expects($this->any())->method('getPreferences')->willReturn(
            [
                'Some\Service\NameInterface' => 'Some\Service\NameInterfaceRemote',
                'Some\Service\NonRemoteInterface' => 'Some\Service\NonRemote'
            ]
        );

        $this->serviceMethodsMap->expects($this->any())->method('getMethodsMap')->willReturn(
            ['methodOne' => 'string', 'methodTwo' => 'string']
        );

        $this->reflectionGenerator->expects($this->exactly(2))->method('generateTopicName')->willReturnMap(
            [
                ['Some\Service\NameInterface', 'methodOne', 'topicOne'],
                ['Some\Service\NameInterface', 'methodTwo', 'topicTwo']
            ]
        );

        $expectedResult = [
            'topicOne' => [
                'topic' => 'topicOne',
                'disabled' => false,
                'connections' => ['amqp' => ['name' => 'amqp', 'exchange' => 'magento', 'disabled' => false]]
            ],
            'topicTwo' => [
                'topic' => 'topicTwo',
                'disabled' => false,
                'connections' => ['amqp' => ['name' => 'amqp', 'exchange' => 'magento', 'disabled' => false]]
            ],
        ];

        $this->assertEquals($expectedResult, $this->reader->read());
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Service interface was expected, "Some\Service\NameInterface" given
     */
    public function testReadInvalidService()
    {
        $this->defaultValueProvider->expects($this->any())->method('getConnection')->willReturn('amqp');
        $this->defaultValueProvider->expects($this->any())->method('getExchange')->willReturn('magento');

        $this->objectManagerConfig->expects($this->any())->method('getPreferences')->willReturn(
            [
                'Some\Service\NameInterface' => 'Some\Service\NameInterfaceRemote',
                'Some\Service\NonRemoteInterface' => 'Some\Service\NonRemote'
            ]
        );

        $this->serviceMethodsMap->expects($this->any())->method('getMethodsMap')
            ->willThrowException(new \Exception(''));

        $this->reflectionGenerator->expects($this->exactly(0))->method('generateTopicName');

        $this->reader->read();
    }
}
