<?php

$gwt->unit('Test \DOF\DDD\Util\TypeCast::entity()', function ($t) {
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::entity([]);
    });
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::entity(0);
    });
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::entity('');
    });
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::entity('0');
    });
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::entity(null);
    });

    $entity = new class extends \DOF\DDD\Entity {
    };
    $t->eq(\DOF\DDD\Util\TypeCast::entity($entity), $entity);
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::entity(new class extends \DOF\DDD\Model {
        });
    });
});

$gwt->unit('Test \DOF\DDD\Util\TypeCast::model()', function ($t) {
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::model([]);
    });
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::model(0);
    });
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::model('');
    });
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::model('0');
    });
    $t->exceptor(function () {
        \DOF\DDD\Util\TypeCast::model(null);
    });

    $entity = new \DOF\DDD\Entity;
    $t->eq(\DOF\DDD\Util\TypeCast::model($entity), $entity);
   
    $model = new class extends \DOF\DDD\Model {
    };
    $t->eq(\DOF\DDD\Util\TypeCast::model($model), $model);
});
