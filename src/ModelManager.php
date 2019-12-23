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
use DOF\DDD\Model;
use DOF\DDD\Util\TypeHint;
use DOF\DDD\Model\KeyTitle;
use DOF\DDD\Model\Pagination;
use DOF\DDD\Model\AuthTokenClassic;
use DOF\DDD\Exceptor\ModelManagerExceptor;

final class ModelManager
{
    use Manager;

    public static function init()
    {
        ModelManager::addSystem([
            KeyTitle::class,
            Pagination::class,
            AuthTokenClassic::class,
        ]);

        foreach (DMN::list() as $domain => $dir) {
            if (\is_dir($path = FS::path($dir, Convention::DIR_MODEL))) {
                ModelManager::addDomain($domain, $path);
            }
        }
    }

    protected static function assemble(array $ofClass, array $ofProperties, array $ofMethods, string $type)
    {
        $model = $ofClass['namespace'] ?? false;
        if (! $model) {
            throw new ModelManagerExceptor('CLASS_WITHOUT_NAMESPACE', \compact('ofClass'));
        }
        if (! \is_subclass_of($model, Model::class)) {
            throw new ModelManagerExceptor('INVALID_DDD_MODEL', \compact('model'));
        }
        if (\is_subclass_of($model, Entity::class)) {
            throw new ModelManagerExceptor('MODEL_CANNOT_BE_ENTITY', \compact('model'));
        }
        if ($exists = (self::$data[$model] ?? false)) {
            throw new ModelManagerExceptor('DUPLICATE_MODEL', \compact('model', 'exists'));
        }
        if (IS::empty($ofClass['doc']['TITLE'] ?? null)) {
            throw new ModelManagerExceptor('CLASS_ANNOTATION_TITLE_MISSING', \compact('model'));
        }

        self::$data[$model]['meta'] = $ofClass;

        foreach ($ofProperties as $property => $options) {
            if (IS::confirm($options['doc']['ANNOTATION'] ?? true)) {
                if (! ($options['doc']['TITLE'] ?? null)) {
                    throw new ModelManagerExceptor('PROPERTY_ANNOTATION_TITLE_MISSING', \compact('model', 'property'));
                }
                $type = $options['doc']['TYPE'] ?? null;
                if (! $type) {
                    throw new ModelManagerExceptor('PROPERTY_ANNOTATION_TYPE_MISSING', \compact('model', 'property'));
                }
                if (! TypeHint::support($type)) {
                    throw new ModelManagerExceptor('UNTYPEHINTABLE_TYPE', \compact('type', 'model', 'property'));
                }
                if (IS::ciin($type, ['entity', 'entitylist'])) {
                    $_entity = ($options['doc'][Annotation::EXTRA_KEY]['TYPE']['ENTITY'] ?? null);
                    if ((! $_entity) || (! ($_entity = Reflect::getAnnotationNamespace($_entity, $model))) || (! TypeHint::entity($_entity))) {
                        throw new ModelManagerExceptor('MISSING_OR_INVALID_ENTITY_TYPE', \compact('type', 'model', '_entity', 'property'));
                    }
                } elseif (IS::ciin($type, ['model', 'modellist'])) {
                    $_model = ($options['doc'][Annotation::EXTRA_KEY]['TYPE']['MODEL'] ?? null);
                    if ((! $_model) || (! ($_model = Reflect::getAnnotationNamespace($_model, $model))) || (! TypeHint::model($_model))) {
                        throw new ModelManagerExceptor('MISSING_OR_INVALID_MODEL_TYPE', \compact('type', 'model', '_model', 'property'));
                    }
                }

                self::$data[$model]['properties'][$property] = $options;
            }
        }
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
