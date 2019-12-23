<?php

$gwt->true('Test if a normal model assembles a fixed format of array result', function ($t) {
    \DOF\DDD\ModelManager::reset();
    \DOF\DDD\ModelManager::addSystem(\DOF\DDD\Test\NormalModel::class);
    \DOF\DDD\ModelManager::compile(false);
    $normal = \DOF\DDD\ModelManager::get(\DOF\DDD\Test\NormalModel::class);
    return (true
        && \is_array($normal)
        && \array_key_exists('doc', $normal['meta'] ?? [])
        && \array_key_exists('doc', $normal['properties']['attr1'] ?? [])
    );
});

$gwt->exceptor('Test an invalid model without class annotation title', function ($t) {
    \DOF\DDD\ModelManager::reset();
    \DOF\DDD\ModelManager::addSystem(\DOF\DDD\Test\ModelWithoutClassTitle::class);
    \DOF\DDD\ModelManager::compile(false);
}, \DOF\DDD\Exceptor\ModelManagerExceptor::class, 'CLASS_ANNOTATION_TITLE_MISSING');
