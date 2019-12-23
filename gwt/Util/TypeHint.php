<?php

$gwt->unit('Test \DOF\DDD\Util\TypeHint::entity()', function ($t) {
    $t->false(\DOF\DDD\Util\TypeHint::entity([]));
    $t->false(\DOF\DDD\Util\TypeHint::entity(0));
    $t->false(\DOF\DDD\Util\TypeHint::entity(''));
    $t->false(\DOF\DDD\Util\TypeHint::entity('0'));
    $t->false(\DOF\DDD\Util\TypeHint::entity(null));
    $t->true(\DOF\DDD\Util\TypeHint::entity(new \DOF\DDD\Entity));
    $t->false(\DOF\DDD\Util\TypeHint::entity(new class extends \DOF\DDD\Model {
    }));
});

$gwt->unit('Test \DOF\DDD\Util\TypeHint::model()', function ($t) {
    $t->false(\DOF\DDD\Util\TypeHint::model([]));
    $t->false(\DOF\DDD\Util\TypeHint::model(0));
    $t->false(\DOF\DDD\Util\TypeHint::model(''));
    $t->false(\DOF\DDD\Util\TypeHint::model('0'));
    $t->false(\DOF\DDD\Util\TypeHint::model(null));
    $t->true(\DOF\DDD\Util\TypeHint::model(new \DOF\DDD\Entity));
    $t->true(\DOF\DDD\Util\TypeHint::model(new class extends \DOF\DDD\Model {
    }));
});
