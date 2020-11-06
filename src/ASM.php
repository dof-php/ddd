<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\Util\Str;
use DOF\Util\Paginator;
use DOF\DDD\Assembler;
use DOF\DDD\Service;
use DOF\DDD\Entity;
use DOF\DDD\Model;
use DOF\DDD\Util\IS;
use DOF\DDD\Exceptor\AssemblerExceptor;

final class ASM
{
    /**
     * Assemble a single result target to satisfy given fields and specific assembler
     *
     * @param mixed{array|object} $result
     * @param array $fields
     * @param mixed{string|object} $assembler
     * @param int $nullable
     */
    public static function assemble(
        $result,
        array $fields,
        $assembler = null,
        int $nullable = 20
    ) {
        if (! $fields) {
            return null;
        }

        if ($result instanceof Paginator) {
            $data = [];
            $list = $result->getList();
            foreach ($list as $item) {
                $data[] = ASM::assemble($item, $fields, $assembler);
            }

            return $data;
        }
        if (IS::array($result, 'index')) {
            $data = [];
            foreach ($result as $item) {
                $data[] = ASM::assemble($item, $fields, $assembler);
            }

            return $data;
        }
        if ($result instanceof Service) {
            $result = $result->execute();
        }

        if (! $result) {
            return null;
        }

        // if (\is_object($result) && \method_exists($result, '__clone__')) {
        //     $result = clone $result;
        // }

        if ($assembler) {
            if (\is_string($assembler)) {
                if (! \class_exists($assembler)) {
                    throw new AssemblerExceptor('CLASS_NOT_EXISTS', \compact('assembler'));
                }
                $assembler = new $assembler($result);
            } elseif (\is_object($assembler) && ($assembler instanceof Assembler)) {
                $assembler->setOrigin($result);
            } else {
                throw new AssemblerExceptor('INVALID_ASSEMBLER', \compact('assembler'));
            }
        }

        $selfs = $fields['fields'] ?? [];
        $refs  = $fields['refs']   ?? [];
        $data  = [];
        $nulls = 0;

        foreach ($selfs as $name => $params) {
            $name  = (string) $name;
            $value = ($assembler && \is_array($params)) ? $assembler->match($name, $params) : ASM::match($name, $result);

            if (\is_null($value)) {
                ++$nulls;
                if ($nulls > $nullable) {
                    break;
                }
            }

            // If value is an object without fields params
            // Then return an empty object to avoid origin object details leak
            // Which means object value MUST need request fields to get its properties
            if (\is_object($value)) {
                if (empty($params)) {
                    $value = new class {
                    };
                } else {
                    $data[$name] = ASM::assemble($value, $params, $assembler);
                    continue;
                }
            }

            $data[$name] = $value;
        }

        foreach ($refs as $name => $ref) {
            $_result = null;
            if (\is_object($result)) {
                $_result = $result->{$name} ?? null;
            } elseif (\is_array($result)) {
                $_result = $result[$name] ?? null;
            }

            $_fields = $ref;
            $_assembler = null;
            if ($assembler) {
                $_assembler = $assembler->assemblers($name);
                if (true
                    && $_assembler
                    && ($_assembler === \get_class($assembler))
                    && ($assembler->recursive($name))
                    && IS::diff($_result, $assembler->getOrigin())
                ) {
                    $_fields = $fields;
                }
            }

            $data[$name] = ASM::assemble($_result, $_fields, $_assembler);
        }

        return $data;
    }

    public static function match(string $key, $result = null)
    {
        if (\is_object($result)) {
            // All object properties in DOF start with `_` are treated as meta properties and are not get-able outside object scope
            return Str::start('_', $key) ? null : ($result->{$key} ?? null);
        }
        
        if (\is_array($result)) {
            return $result[$key] ?? null;
        }

        return null;
    }
}
