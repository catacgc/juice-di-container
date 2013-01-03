<?php

class ContainerTest extends PHPUnit_Framework_TestCase
{

    public function testContainerParameters()
    {
        $container = new JuiceContainer(array('param1' => 1, 'param2' => 2));
        $container['param2'] = 3;

        $this->assertEquals(1, $container['param1']);
        $this->assertEquals(3, $container['param2']);
    }

    public function testsContainerParametersWithCallableName()
    {
        $container = new JuiceContainer();
        $container['param'] = new JuiceParam('strpos');

        $this->assertEquals('strpos', $container['param']);
    }

    public function testContainerServiceFromCallable()
    {
        $container = new JuiceContainer();
        $container['a'] = 1;
        $container['b'] = 2;

        $container['service'] = array('SumTestService', 'buildInstance');

        $this->assertInstanceOf('SumTestService', $container['service']);
        $this->assertEquals(3, $container['service']->result);

        return $container;
    }

    public function testInfiniteRecursion()
    {
        $container = new JuiceContainer();
        $container['sum1'] = JuiceDefinition::create('SumTestService')->call('sum', array('@sum2'));
        $container['sum2'] = JuiceDefinition::create('SumTestService')->call('sum', array('@sum3'));
        $container['sum3'] = JuiceDefinition::create('SumTestService')->call('sum', array('@sum1'));

        try {
            $service = $container['sum1'];
            $this->fail('Exception not thrown');
        } catch (InvalidArgumentException $ex) {
            $this->assertEquals('Circular dependency found: sum1 -> sum2 -> sum3 -> sum1', $ex->getMessage());
        }
    }

    /**
     * @depends testContainerServiceFromCallable
     */
    public function testContainerServiceCache($container)
    {
        $container['service']->add();

        $this->assertEquals(4, $container['service']->result);
    }

    /**
     * @depends testContainerServiceFromCallable
     */
    public function testContainerAliases($container)
    {
        $container['service_alias'] = '@service';

        $this->assertEquals($container['service_alias'], $container['service']);
    }

    public function testContainerServiceFromDefinition()
    {
        $container = new JuiceContainer();
        $container['service'] = JuiceDefinition::create('SumTestService', array(1, 2))->call('add', array(3));

        $this->assertEquals(6, $container['service']->result);
    }

    public function testContainerReferencesInDefinition()
    {
        $container = new JuiceContainer(array('one' => 1));
        $container['two'] = 2;

        $container['sum1'] = JuiceDefinition::create('SumTestService', array('@one'))->call('sum', array('@sum2'));

        $container['sum2'] = JuiceDefinition::create('SumTestService')->call('add', array('@two'));

        $this->assertEquals(3, $container['sum1']->result);
    }

    public function testDefinition()
    {
        $definition = new JuiceDefinition('Test', array(1, 2));
        $definition->call('add', array(1))
            ->call('add');

        $this->assertEquals('Test', $definition->class);
        $this->assertEquals(array(1, 2), $definition->constructorArguments);
        $this->assertEquals(array('add', array(1)), $definition->methodCalls[0]);
        $this->assertEquals(array('add', array()), $definition->methodCalls[1]);
    }

    public function testReplaceArgument()
    {
        $def = new JuiceDefinition('Test', array(2, 2));
        $def->argument(0, 1);
        $this->assertEquals(array(1, 2), $def->constructorArguments);
    }

    public function testDefinitionFactory()
    {
        $def = JuiceDefinition::create('Test', array(1))
            ->call('set', array(1));

        $this->assertEquals('Test', $def->class);
    }

    public function testDefinitionBuilderConstructor()
    {
        $def = new JuiceDefinition('SumTestService', array(1, 2));
        $container = new JuiceContainer();
        $service = $container->build($def);

        $this->assertInstanceOf('SumTestService', $service);
        $this->assertEquals(3, $service->result);
    }

    public function testDefinitionBuilderMethodCalls()
    {
        $def = new JuiceDefinition('SumTestService');
        $def->call('add');
        $def->call('add', array(2));

        $container = new JuiceContainer();
        $service = $container->build($def);

        $this->assertEquals(3, $service->result);
    }
}

class SumTestService
{
    public $result = 0;

    public function __construct()
    {
        $this->result = array_sum(func_get_args());
    }

    public function sum(SumTestService $sum)
    {
        $this->result += $sum->result;
    }

    public function add($inc = 1)
    {
        $this->result += $inc;
    }

    public static function buildInstance(JuiceContainer $container)
    {
        return new self($container['a'], $container['b']);
    }
}