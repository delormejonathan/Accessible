<?php

namespace Accessible\Tests;

class AutoConstructTraitTest extends \PHPUnit_Framework_TestCase
{
    public function testAutoConstructWorks()
    {
        $testCase = new TestsCases\AutoConstructTestCase("foo", true);
        $this->assertEquals("foo", $testCase->getFoo());
        $this->assertEquals(true, $testCase->getBar());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testWrongValueThrowsAnException()
    {
        $testCase = new TestsCases\AutoConstructTestCase("foo", 42);
    }

    /**
     * @expectedException \BadMethodCallException
     */
    public function testWrongNumberOfArgumentsThrowsAnException()
    {
        $testCase = new TestsCases\AutoConstructTestCase("foo");
    }
}
