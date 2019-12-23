<?php

\class_alias(\DOF\DDD\ModelManager::class, 'ModelManager');
\class_alias(\DOF\DDD\EntityManager::class, 'EntityManager');
\class_alias(\DOF\DDD\StorageManager::class, 'StorageManager');
\class_alias(\DOF\DDD\RepositoryManager::class, 'RepositoryManager');
\class_alias(\DOF\DDD\EventManager::class, 'EventManager');

ModelManager::load();
EntityManager::load();
StorageManager::load();
RepositoryManager::load();
EventManager::load();
