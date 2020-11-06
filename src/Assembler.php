<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DDD\ASM;
use DOF\DDD\Exceptor\AssemblerExceptor;

class Assembler
{
    /** @var mixed{array|object} */
    protected $origin;

    /**
     * Compatibles are fields name which will be used to check when requested field not directly exists in origin
     * All key in $compatibles are case insensitive
     *
     * @var array
     */
    protected $compatibles = [];

    /**
     * Reference field recursive or not config rules
     *
     * @var array
     */
    protected $recursive = [];

    /**
     * Assemblers will be used when current assember has reference field
     *
     * @var array
     */
    protected $assemblers = [];

    /**
     * Converters are value converters which will be used when found field in origin or compatible mappings
     *
     * Key of a converter map item is the field name exists exactly in origin
     * Value of a converter map item is the method name of current assembler class, and that method will accept field value and options as method parameters
     *
     * @var array
     */
    protected $converters = [];

    final public function __construct($origin = null)
    {
        $this->origin = $origin;
    }

    final public function match(string $name, array $params = [])
    {
        $key = $name;
        $val = ASM::match($key, $this->origin);
        if (\is_null($val)) {
            $key = \strtolower($name);
            $val = ASM::match($key, $this->origin);
            if (\is_null($val)) {
                $key = \strtoupper($name);
                $val = ASM::match($key, $this->origin);
            }
        }
        if (\is_null($val)) {
            $key = $this->compatibles[$name] ?? null;
            // If target key not exists even in compatibles setting
            // We tried non-standard field name the last two times: all-lowercase and ALL-UPPERCASE
            if (\is_null($key)) {
                $key = $this->compatibles[\strtolower($name)] ?? null;
                if (\is_null($key)) {
                    $key = $this->compatibles[\strtoupper($name)] ?? null;
                    if (\is_null($key)) {
                        return null;
                    }
                }
            }
        }

        $val = ASM::match($key, $this->origin);
        $converter = $this->converters[$key] ?? null;
        if ($converter) {
            if (! \method_exists($this, $converter)) {
                throw new AssemblerExceptor('ASSEMBLING_FIELD_CONVERTER_NOT_EXISTS', [
                    'converter' => $converter,
                    'class' => \get_class($this),
                ]);
            }

            $val = $this->{$converter}($val, $params);
        }

        return $val;
    }

    /**
     * Setter for origin
     *
     * @param mixed $origin
     * @return Assembler
     */
    final public function setOrigin($origin)
    {
        $this->origin = $origin;
    
        return $this;
    }

    /**
     * Getter for origin
     *
     * @return mixed
     */
    final public function getOrigin()
    {
        return $this->origin;
    }

    final public function compatibles(string $field = null)
    {
        return $field ? ($this->compatibles[$field] ?? null) : $this->compatibles;
    }

    final public function converters(string $field = null)
    {
        return $field ? ($this->converters[$field] ?? null) : $this->converters;
    }

    final public function recursive(string $field = null)
    {
        return $field ? ($this->recursive[$field] ?? null) : $this->recursive;
    }

    final public function assemblers(string $field = null)
    {
        return $field ? ($this->assemblers[$field] ?? null) : $this->assemblers;
    }
}
