<?php

namespace batchnz\craftcommercemultivendor\behaviors;

use yii\db\ActiveRecord;
use yii\base\Behavior;

class Template extends Behavior
{
    /**
     * Sets control panel template roots
     * This utilizes the ReflectionObject class to write to a private array in the owner class
     *
     * @author Josh Smith <josh@batch.nz>
     * @param  string $name     Name of the root key to set the basepath in
     * @param  string $basePath Base path of the templates to insert
     * @param  string $method   'append' or 'prepend' the base path
     */
    public function setCpTemplateRoots(string $name, string $basePath, $method = 'append')
    {
        // Generate control panel template routes if we haven't already
        $this->owner->getCpTemplateRoots();

        // ** HACK ALERT **
        // There's currently no way to override CP or plugin templates in Craft,
        // so this approach allows us to inject a new basepath into the template roots array.
        $reflection = new \ReflectionObject($this->owner);
        $property = $reflection->getProperty('_templateRoots');
        $property->setAccessible(true);
        $cpTemplateRoots = $property->getValue($this->owner);

        if( !array_key_exists($name, $cpTemplateRoots['cp']) ){
            $cpTemplateRoots['cp'][$name] = [];
        }

        // Whether to insert the basepath at the beginning or end of the template roots array
        switch ($method) {
            case 'prepend':
                array_unshift($cpTemplateRoots['cp'][$name], $basePath);
                break;

            case 'append':
            default:
                $cpTemplateRoots['cp'][$name][] = $basePath;
                break;
        }

        // Set the value and clean up
        $property->setValue($this->owner, $cpTemplateRoots);
        unset($reflection, $property, $cpTemplateRoots);

        return $this->owner;
    }
}