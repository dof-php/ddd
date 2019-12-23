<?php

$gwt->unit('Test \DOF\DDD\Util\IS::model()', function ($t) {
    $t->true(\DOF\DDD\Util\IS::model(new class extends \DOF\DDD\Model {
    }));
    $t->true(\DOF\DDD\Util\IS::model(new \DOF\DDD\Entity));
    $t->false(\DOF\DDD\Util\IS::model(\DOF\DDD\Model::class));
});

$gwt->unit('Test \DOF\DDD\Util\IS::entity()', function ($t) {
    $t->true(\DOF\DDD\Util\IS::entity(new \DOF\DDD\Entity));
    $t->false(\DOF\DDD\Util\IS::entity(\DOF\DDD\Entity::class));
});
