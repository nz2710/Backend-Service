<?php
namespace App\Traits;

trait SetAttribute
{
    public function set($name, $value)
    {
        $this->attributes[$name] = $value;
        return $this;
    }

}
