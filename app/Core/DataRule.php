<?php
namespace App\Core;

use ReflectionObject;
use ReflectionProperty;

class DataRule
{
    private $field_name;

    protected $type;
    protected $pattern;
    protected $group;
    protected $return;
    protected $callbacks;
    protected $attribute;
    protected $get;
    protected $default_value;
    protected $required;

    public function __construct($field_name, $rule = array())
    {
        $this->field_name = $field_name;

        if (!empty($rule)) {
            $this->setRule($rule);
        }
    }

    public function setRule($rule)
    {
        foreach ($rule as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            $this->$key = $value;
        }
    }

    public function validate()
    {
        return $this->type !== null;
    }

    public function get_field_name()
    {
        return $this->field_name;
    }

    public function create_mapping_field()
    {
        $dataRuleRef = new ReflectionObject($this);
        $mappingField = array();
        foreach ($dataRuleRef->getProperties(ReflectionProperty::IS_PROTECTED) as $property) {
            $property_name = $property->getName();
            if (!is_null($this->$property_name)) {
                $mappingField[$property_name] = $this->$property_name;
            }
        }
        return $mappingField;
    }
}
