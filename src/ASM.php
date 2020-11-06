<?php

declare(strict_types=1);

namespace DOF\DDD;

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
            return $result->{$key} ?? null;
        }
        
        if (\is_array($result)) {
            return $result[$key] ?? null;
        }

        return null;
    }
}
