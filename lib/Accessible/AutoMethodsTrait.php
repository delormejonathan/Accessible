<?php

namespace Accessible;

use \Accessible\MethodManager\MethodCallManager;
use \Accessible\MethodManager\ListManager;
use \Accessible\MethodManager\MapManager;
use \Accessible\MethodManager\SetManager;

trait AutoMethodsTrait
{
    /**
     * This function will be called each time a getter or a setter that is not
     * already defined in the class is called.
     *
     * @param  string $name The name of the called function.
     *                      It must be a getter or a setter.
     * @param  array  $args The array of arguments for the called function.
     *                      It should be empty for a getter call,
     *                      and should have one item for a setter call.
     *
     * @return mixed    The value that should be returned by the function called if it is a getter,
     *                  the object itself if the function called is a setter.
     *
     * @throws \BadMethodCallException      When the method called is neither a getter nor a setter,
     *         						   		or if the access right has not be given for this method,
     *         						     	or if the method is a setter called without argument.
     * @throws \InvalidArgumentException    When the argument given to the method called (as a setter)
     *         								does not satisfy the constraints attached to the property
     *         								to modify.
     */
    public function __call($name, array $args)
    {
        $this->getPropertiesInfo();

        $methodCallInfo = $this->getMethodCallInfo($name);
        $method = $methodCallInfo['method'];
        $property = $methodCallInfo['property'];
        $collectionProperties = $methodCallInfo['collectionProperties'];

        $valuesToUpdate = array();

        switch ($method) {
            case 'get':
            case 'is':
                return $this->$property;

            case 'set':
                // a setter should have exactly one argument
                MethodCallManager::assertArgsNumber(1, $args);
                // we set a collection here if there is an association with it
                if (
                    !empty($this->_collectionsItemNames['byProperty'][$property])
                    && !(empty($this->_associationsList[$property]))
                ) {
                    $itemName = $this->_collectionsItemNames['byProperty'][$property]['itemName'];
                    $propertyAddMethod = 'add' . strtoupper(substr($itemName, 0, 1)) . substr($itemName, 1);
                    $propertyRemoveMethod = 'remove' . strtoupper(substr($itemName, 0, 1)) . substr($itemName, 1);

                    foreach ($this->$property as $item) {
                        $this->$propertyRemoveMethod($item);
                    }
                    foreach ($args[0] as $item) {
                        $this->$propertyAddMethod($item);
                    }
                }
                // we set a regular property here
                else {
                    $oldValue = $this->$property;
                    $newValue = $args[0];
                    $valuesToUpdate = array(
                        'oldValue' => $oldValue,
                        'newValue' => $newValue
                    );
                    // check that the setter argument respects the property constraints
                    $this->assertPropertyValue($property, $newValue);

                    if ($oldValue !== $newValue) {
                        $this->$property = $newValue;
                    }
                }
                break;

            case 'add':
            case 'remove':
                $valueToUpdate = ($method === 'add') ? 'newValue' : 'oldValue';
                switch ($collectionProperties['behavior']) {
                    case 'list':
                        ListManager::$method($this->$property, $args);
                        $valuesToUpdate[$valueToUpdate] = $args[0];
                        break;
                    case 'map':
                        MapManager::$method($this->$property, $args);
                        break;
                    case 'set':
                        SetManager::$method($this->$property, $args);
                        $valuesToUpdate[$valueToUpdate] = $args[0];
                        break;
                }
                break;
        }

        // manage associations
        if (in_array($method, array('set', 'add', 'remove'))) {
            $this->updatePropertyAssociation($property, $valuesToUpdate);
        }

        return $this;
    }

    /**
     * Extract the info about the method called.
     *
     * @param string $name
     *
     * @return array
     */
    private function getMethodCallInfo($name)
    {
        // check that the called method is a valid method name
        // also get the call type and the property to access
        $callIsValid = preg_match("/(set|get|is|add|remove)([A-Z].*)/", $name, $pregMatches);
        if (!$callIsValid) {
            throw new \BadMethodCallException("Method $name does not exist.");
        }

        $method = $pregMatches[1];
        $property = strtolower(substr($pregMatches[2], 0, 1)) . substr($pregMatches[2], 1);
        $collectionProperties = null;
        if (in_array($method, array('add', 'remove'))) {
            $collectionProperties = $this->_collectionsItemNames['byItemName'][$property];
            $property = $collectionProperties['property'];
        }

        // check that the method is accepted by the targeted property
        if (
            empty($this->_accessProperties[$property])
            || !in_array($method, $this->_accessProperties[$property])
        ) {
            throw new \BadMethodCallException("Method $name does not exist.");
        }

        return array(
            'method' => $method,
            'property' => $property,
            'collectionProperties' => $collectionProperties
        );
    }
}
