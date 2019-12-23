<?php

declare(strict_types=1);

namespace DOF\DDD;

use DOF\DMN;
use DOF\Convention;
use DOF\Traits\Manager;
use DOF\Util\IS;
use DOF\Util\FS;
use DOF\Util\Str;
use DOF\Util\Reflect;
use DOF\Util\Annotation;
use DOF\DDD\Entity;
use DOF\DDD\Listener;
use DOF\DDD\Util\TypeHint;
use DOF\DDD\Entity\RequestLog;
use DOF\DDD\Exceptor\EntityManagerExceptor;

final class EntityManager
{
    use Manager;

    public static function init()
    {
        EntityManager::addSystem([
            RequestLog::class,
        ]);

        foreach (DMN::list() as $domain => $dir) {
            if (\is_dir($path = FS::path($dir, Convention::DIR_ENTITY))) {
                EntityManager::addDomain($domain, $path);
            }
        }
    }

    private static function assemble(array $ofClass, array $ofProperties, array $ofMethods, string $type)
    {
        $entity = $ofClass['namespace'] ?? null;
        if (! $entity) {
            throw new EntityManagerExceptor('CLASS_WITHOUT_NAMESPACE', \compact('ofClass'));
        }
        if (! \is_subclass_of($entity, Entity::class)) {
            throw new EntityManagerExceptor('INVALID_DDD_ENTITY', \compact('entity'));
        }
        if ($exists = (self::$data[$entity] ?? false)) {
            throw new EntityManagerExceptor('DUPLICATE_ENTITY', \compact('entity', 'exists'));
        }
        if (IS::empty($ofClass['doc']['TITLE'] ?? null)) {
            throw new EntityManagerExceptor('CLASS_ANNOTATION_TITLE_MISSING', \compact('entity'));
        }

        self::$data[$entity]['meta'] = $ofClass;

        foreach ($ofProperties as $property => $options) {
            if (IS::confirm($options['doc']['ANNOTATION'] ?? true)) {
                if (! ($options['doc']['TITLE'] ?? false)) {
                    throw new EntityManagerExceptor('PROPERTY_ANNOTATION_TITLE_MISSING', \compact('entity', 'property'));
                }
                $type = $options['doc']['TYPE'] ?? null;
                if (! $type) {
                    throw new EntityManagerExceptor('PROPERTY_ANNOTATION_TYPE_MISSING', \compact('entity', 'property'));
                }
                if (! TypeHint::support($type)) {
                    throw new EntityManagerExceptor('UNTYPEHINTABLE_TYPE', \compact('type', 'entity', 'property'));
                }
                if (IS::ciin($type, ['entity', 'entitylist'])) {
                    $_entity = ($options['doc'][Annotation::EXTRA_KEY]['TYPE']['ENTITY'] ?? null);
                    if ((! $_entity) || (! ($_entity = Reflect::getAnnotationNamespace($_entity, $entity))) || (! TypeHint::entity($_entity))) {
                        throw new EntityManagerExceptor('MISSING_OR_INVALID_ENTITY_TYPE', \compact('type', 'entity', '_entity', 'property'));
                    }
                } elseif (IS::ciin($type, ['model', 'modellist'])) {
                    $_model = ($options['doc'][Annotation::EXTRA_KEY]['TYPE']['MODEL'] ?? null);
                    if ((! $_model) || (! ($_model = Reflect::getAnnotationNamespace($_model, $entity))) || (! TypeHint::model($_model))) {
                        throw new EntityManagerExceptor('MISSING_OR_INVALID_MODEL_TYPE', \compact('type', 'entity', '_model', 'property'));
                    }
                }

                self::$data[$entity]['properties'][$property] = $options;
            }
        }
    }

    public static function __annotationValueMetaONCREATED(string $listener, string $entity, &$multiple, &$strict)
    {
        $multiple = 'unique';

        $_listener = Reflect::getAnnotationNamespace($listener, $entity);
        if (! $_listener) {
            throw new EntityManagerExceptor('ONCREATED_LISTENER_NOT_EXISTS', \compact('listener', 'entity'));
        }

        if (!\is_subclass_of($_listener, Listener::class)) {
            throw new EntityManagerExceptor('INVALID_ONCREATED_LISTENER', \compact('listener', 'entity'));
        }

        return $_listener;
    }

    public static function __annotationValueMetaONREMOVED(string $listener, string $entity, &$multiple, &$strict)
    {
        $multiple = 'unique';

        $_listener = Reflect::getAnnotationNamespace($listener, $entity);
        if (! $_listener) {
            throw new EntityManagerExceptor('ONREMOVED_LISTENER_NOT_EXISTS', \compact('listener', 'entity'));
        }
        if (!\is_subclass_of($_listener, Listener::class)) {
            throw new EntityManagerExceptor('INVALID_ONREMOVED_LISTENER', \compact('listener', 'entity'));
        }

        return $_listener;
    }

    public static function __annotationValueMetaONUPDATED(string $listener, string $entity, &$multiple, &$strict)
    {
        $multiple = 'unique';

        $_listener = Reflect::getAnnotationNamespace($listener, $entity);
        if (! $_listener) {
            throw new EntityManagerExceptor('ONUPDATED_LISTENER_NOT_EXISTS', \compact('listener', 'entity'));
        }
        if (!\is_subclass_of($_listener, Listener::class)) {
            throw new EntityManagerExceptor('INVALID_ONUPDATED_LISTENER', \compact('listener', 'entity'));
        }

        return $_listener;
    }

    public static function __annotationValueARGUMENT(string $argument, string $model, &$multiple, &$strict, array $extra)
    {
        $multiple = true;

        return [$argument => $extra];
    }

    public static function __annotationValueCOMPATIBLE(string $compatible, string $model, &$multiple, &$strict, array $extra)
    {
        $multiple = 'unique';

        return Str::arr($compatible);
    }
}
