<?php

declare(strict_types=1);

namespace DOF\DDD;

use Throwable;
use DOF\DMN;
use DOF\Convention;
use DOF\Traits\Manager;
use DOF\Util\FS;
use DOF\Util\Reflect;
use DOF\Util\TypeCast;
use DOF\DDD\Model;
use DOF\DDD\Entity;
use DOF\DDD\Storage;
use DOF\DDD\RepositoryInterface;
use DOF\DDD\Exceptor\RepositoryManagerExceptor;

final class RepositoryManager
{
    use Manager;

    public static function init()
    {
        foreach (DMN::list() as $domain => $dir) {
            if (\is_dir($path = FS::path($dir, Convention::DIR_REPOSITORY))) {
                RepositoryManager::addDomain($domain, $path);
            }
        }
    }

    public static function assemble(array $ofClass, array $ofProperties, array $ofMethods, string $type)
    {
        $namespace = $ofClass['namespace'] ?? false;
        if (! $namespace) {
            return;
        }
        if (! \is_subclass_of($namespace, RepositoryInterface::class)) {
            throw new RepositoryManagerExceptor('INVALID_REPOSITORY_INTERFACE', \compact('namespace'));
        }
        if ($exists = (self::$data[$namespace] ?? false)) {
            throw new RepositoryManagerExceptor('DUPLICATE_REPOSITORY_INTERFACE', \compact('exists', 'namespace'));
        }

        $entity = $ofClass['doc']['ENTITY'] ?? null;
        $model = $ofClass['doc']['MODEL'] ?? null;
        if ((! $entity) && (! $model)) {
            throw new RepositoryManagerExceptor('REPOSITORY_WITHOUT_DATA_MODEL', \compact('namespace'));
        }
        if ($entity && (! \is_subclass_of($entity, Entity::class))) {
            throw new RepositoryManagerExceptor('INVALID_ENTITY_FOR_REPOSITORY', \compact('namespace', 'entity'));
        } elseif ($model && (! \is_subclass_of($model, Model::class))) {
            throw new RepositoryManagerExceptor('INVALID_MODEL_FOR_REPOSITORY', \compact('namespace', 'model'));
        }

        if (! ($ofClass['doc']['IMPLEMENTOR'] ?? false)) {
            throw new RepositoryManagerExceptor('REPOSITORY_WITHOUT_IMPLEMENTOR', \compact('namespace'));
        }

        self::$data[$namespace] = $ofClass['doc'] ?? [];
    }

    public static function __annotationValueMetaModel(string $model, string $repository)
    {
        $_model = Reflect::getAnnotationNamespace($model, $repository);

        if ((! $_model) || (! \class_exists($_model))) {
            throw new RepositoryManagerExceptor('MISSING_OR_MODEL_NOT_EXISTS', \compact('model', 'repository'));
        }

        if (! \is_subclass_of($_model, Model::class)) {
            throw new RepositoryManagerExceptor('INVALID_DDD_MODEL', \compact('model', 'repository'));
        }

        return $_model;
    }

    public static function __annotationValueMetaEntity(string $entity, string $repository)
    {
        $_entity = Reflect::getAnnotationNamespace($entity, $repository);
        if ((! $_entity) || (! \class_exists($_entity))) {
            throw new RepositoryManagerExceptor('MISSING_OR_ENTITY_NOT_EXISTS', \compact('entity', 'repository'));
        }

        if (! \is_subclass_of($_entity, Entity::class)) {
            throw new RepositoryManagerExceptor('INVALID_ENTITY_CLASS', \compact('entity', 'repository'));
        }

        return $_entity;
    }

    public static function __annotationValueMetaImplementor(string $storage, string $repository)
    {
        $_storage = Reflect::getAnnotationNamespace($storage, $repository);

        if ((! $_storage) || (! \class_exists($_storage))) {
            throw new RepositoryManagerExceptor('MISSING_OR_STORAGE_NOT_EXISTS', \compact('storage', 'repository'));
        }
        if (! \is_subclass_of($_storage, Storage::class)) {
            throw new RepositoryManagerExceptor('INVALID_STORAGE_CLASS', \compact('storage', 'repository'));
        }
        if (! \is_subclass_of($_storage, RepositoryInterface::class)) {
            throw new RepositoryManagerExceptor('INVALID_STORAGE_CLASS', \compact('storage', 'repository'));
        }

        return $_storage;
    }
}
