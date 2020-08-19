<?php

namespace Typed;

use ArrayObject;
use JsonSerializable;

/**
 * Class TypedArray
 *
 * @package TypedArray
 */
class TypedArray extends ArrayObject implements JsonSerializable
{
    /**
     * TypedArray constructor.
     * @param array $input
     * @param int $flags
     * @param string $iterator_class
     */
    public function __construct(
        $input = array(), $flags = 0, $iterator_class = "ArrayIterator")
    {
        parent::__construct($input, $flags, $iterator_class);
    }

    public function __clone()
    {
        return self::from_array($this->to_array());
    }

    /**
     * 从 array 值转换成此类
     *
     * @param array $data
     * @return self
     */
    public static function from_array(array $data = [])
    {
        $instance = new static();

        $vars = get_class_vars(get_called_class());

        foreach (array_keys($vars) as $fieldName) {
            if (array_key_exists($fieldName, $data)) {
                // auto set values
                $instance->$fieldName = self::from_one_value($vars[$fieldName], $data[$fieldName]);
                continue;
            }

            // data 中不存在 此 字段 需要设置默认值
            $defaultValue = $vars[$fieldName];
            if (is_string($defaultValue) && class_exists($defaultValue)) {
                if (is_subclass_of($defaultValue, self::class)) {
                    $instance->$fieldName = new $defaultValue();
                } else {
                    $instance->$fieldName = null;
                }
            } else if (is_array($defaultValue) && class_exists(reset($defaultValue))) {
                // 如果是 type[] array类型 则默认为 []
                $instance->$fieldName = [];
            } else { // user typed default value
                $instance->$fieldName = $vars[$fieldName];
            }
        }

        return $instance;
    }

    /**
     * 转换成 array 值
     *
     * @return array
     */
    public function to_array()
    {
        $vars = get_class_vars(get_class($this));

        $data = [];
        foreach (array_keys($vars) as $field) {
            $data[$field] = $this->to_one_value($this->$field);
        }

        return $data;
    }

    /**
     * json encode
     *
     * @return string
     */
    public function jsonSerialize()
    {
        return json_encode($this->to_array());
    }

    private static function from_one_value($clsName, $value)
    {
        /**
         * not set default value
         * if value exists, JUST return
         */
        if (is_numeric($clsName) || is_bool($clsName) || is_null($clsName) || is_resource($clsName)) {
            return $value;
        }

        if (is_array($clsName) && is_array($value)) {
            return self::from_array_value(reset($clsName), $value);
        } else if (is_string($clsName)) {
            if (class_exists($clsName)) {
                /** @var static $clsName */
                return $clsName::from_array($value);
            } else {
                /**
                 * not class, set plain old value
                 * if the value exists, JUST return
                 */
                return $value;
            }
        }

        return $value;
    }

    private static function from_array_value($clsName, array $dataList)
    {
        if (class_exists($clsName) === false) {
            return $dataList;
        }

        if (is_subclass_of(new $clsName(), self::class)) {
            return array_map([$clsName, 'from_array'], $dataList);
        }

        return $dataList;
    }


    private function to_one_value($value)
    {
        if (is_array($value)) {
            return $this->to_array_value($value);
        } else if (is_subclass_of($value, self::class)) {
            /** @var static $value */
            return $value->to_array();
        } else {
            return $value;
        }
    }

    private function to_array_value($arrayValue)
    {
        return array_map([$this, 'to_one_value'], $arrayValue);
    }

    public function __debugInfo()
    {
        return $this->to_array();
    }

    public function offsetExists($index)
    {
        return array_key_exists($index, $this->to_array());
    }

    public function offsetGet($index)
    {
        return $this->$index;
    }

    public function offsetUnset($index)
    {
        $this->$index = null;
    }

    public function offsetSet($index, $newval)
    {
        $this->$index = $newval;
    }

    public function serialize()
    {
        return serialize($this->to_array());
    }

    public function unserialize($serialized)
    {
        return self::from_array(unserialize($serialized));
    }

    public function count()
    {
        return parent::__construct($this->to_array())->count();
    }

    public function getIterator()
    {
        return parent::__construct($this->to_array())->getIterator();
    }

    public function getArrayCopy()
    {
        return parent::__construct($this->to_array())->getArrayCopy();
    }
}
