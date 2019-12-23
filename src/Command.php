<?php

declare(strict_types=1);

namespace DOF\DDD;

use Throwable;
use DOF\DOF;
use DOF\DMN;
use DOF\ENV;
use DOF\Convention;
use DOF\Container;
use DOF\CLI\Color;
use DOF\Util\IS;
use DOF\Util\FS;
use DOF\Util\Str;
use DOF\Util\JSON;
use DOF\Util\Format;
use DOF\Util\Reflect;
use DOF\DDD\Service;
use DOF\DDD\ORMStorage;
use DOF\DDD\StorageSchema;
use DOF\DDD\StorageManager;
use DOF\Storage\Driver;

class Command
{
    /**
     * @CMD(test.storage.connection)
     * @Desc(Test connection status for all storages in domains)
     * @Option(fails-only){notes=Whether output success tests info&default=false}
     * @Option(verbose){notes=Whether output exception details tests&default=false}
     */
    public function testStorageConnection($console)
    {
        $storages = StorageManager::getData();
        if (! $storages) {
            $console->info('No storages found in any domains');
            return;
        }

        $console->info('Test connection status for all storages ...');
        foreach ($storages as $namespace => $storage) {
            $file = Reflect::getNamespaceFile($namespace);
            if (!\is_file($file)) {
                $console->fail("Invalid storage namespace without file: {$namespace}");
                return;
            }

            try {
                $delay = 0;
                $connectable = $console->new($namespace)->driver()->connectable($delay);
                if (true === $connectable) {
                    if (! $console->hasOption('fails-only')) {
                        $console->line(\join(' ', [
                            $console->render('[OK]', Color::SUCCESS),
                            DOF::pathof($file),
                            $console->render(\round($delay, 8).'s', Color::TIPS)
                        ]));
                    }
                } else {
                    $console->line(\join(' ', [$console->render('[Failed]', Color::FAIL), DOF::pathof($file)]));
                    $fails['fail'][] = $namespace;
                }
            } catch (Throwable $th) {
                $console->line(\join(' ', [$console->render('[ERROR]', Color::ERROR), DOF::pathof($file)]));
                if ($console->hasOption('verbose')) {
                    $console->line($console->render(JSON::pretty(Format::throwable($th)), Color::ERROR));
                }
            }
        }
    }

    /**
     * @CMD(test.service.status)
     * @Desc(Testing instantiatable status of services in domains)
     * @Option(fails-only){notes=Whether output success tests info&default=false}
     * @Option(verbose){notes=Whether output exception details tests&default=false}
     */
    public function testServiceStatus($console)
    {
        $path = DOF::path(Convention::DIR_DOMAIN);
        if (! \is_dir($path)) {
            $console->info('No services found in any domains');
            return;
        }

        $console->info('Test initialization status for all services ...');
        FS::walkr($path, function ($path) use ($console) {
            if (! Str::eq($path->getExtension(), 'php', true)) {
                return;
            }
            $file = $path->getRealpath();
            $class = Reflect::getFileNamespace($file, true);
            if (false === $class) {
                return;
            }
            if (! \is_subclass_of($class, Service::class)) {
                return;
            }

            try {
                $console->di($class);
                if (! $console->hasOption('fails-only')) {
                    $console->line(\join(' ', [$console->render('[OK]', Color::SUCCESS), DOF::pathof($file)]));
                }
            } catch (Throwable $th) {
                $console->line(\join(' ', [$console->render('[ERROR]', Color::ERROR), DOF::pathof($file)]));
                if ($console->hasOption('verbose')) {
                    $console->line($console->render(JSON::pretty(Format::throwable($th)), Color::ERROR));
                }
            }
        });
    }

    /**
     * @CMD(ddd.compile.clear)
     * @Alias(ddd.cc)
     * @Desc(Clear all compile cache of DDD managers)
     */
    public function compileClear($console)
    {
        $console->task('Clear ModelManager compile cache', function () {
            ModelManager::removeCompileFile();
        });

        $console->task('Clear EntityManager compile cache', function () {
            EntityManager::removeCompileFile();
        });

        $console->task('Clear StorageManager compile cache', function () {
            StorageManager::removeCompileFile();
        });

        $console->task('Clear RepositoryManager compile cache', function () {
            RepositoryManager::removeCompileFile();
        });

        $console->task('Clear EventManager compile cache', function () {
            EventManager::removeCompileFile();
        });
    }

    /**
     * @CMD(ddd.compile)
     * @Alias(ddd.c)
     * @Desc(Compile DDD managers)
     */
    public function compile($console)
    {
        try {
            $console->task('Compiling ModelManager', function () {
                ModelManager::removeCompileFile();
                ModelManager::compile(true);
            });

            $console->task('Compiling EntityManager', function () {
                EntityManager::removeCompileFile();
                EntityManager::compile(true);
            });

            $console->task('Compiling StorageManager', function () {
                StorageManager::removeCompileFile();
                StorageManager::compile(true);
            });

            $console->task('Compiling RepositoryManager', function () {
                RepositoryManager::removeCompileFile();
                RepositoryManager::compile(true);
            });

            $console->task('Compiling EventManager', function () {
                EventManager::removeCompileFile();
                EventManager::compile(true);
            });
        } catch (Throwable $th) {
            $console->exceptor('DDD_COMPILE_ERROR', $th);
        }
    }

    /**
     * @CMD(orm.init)
     * @Desc(Init an ORM storage from its annotations to connected driver schema)
     * @Option(force){notes=Whether execute the dangerous operations like drop/delete&default=false}
     * @Option(dump){notes=Dump the sqls will be executed rather than execute them directly&default=false}
     * @Option(logging){notes=Logging SQLs executed during init process&default=false}
     * @Argv(#1){notes=The ORM class filepaths or namespaces to init}
     */
    public function initORM($console)
    {
        if (! ($orms = $console->getParams())) {
            $console->fail('Missing ORM to init');
        }

        $console->info('Init ORM schema ...');
        foreach ($orms as $orm) {
            $class = null;
            if (\is_file($orm)) {
                $class = Reflect::getFileNamespace($orm, true);
            } elseif (\class_exists($orm)) {
                $class = $orm;
            }

            if ((! $class) || (! \is_subclass_of($class, ORMStorage::class))) {
                $console->exceptor('Invalid ORM class', \compact('orm', 'class'));
                return;
            }

            $force = $console->hasOption('force');
            $dump  = $console->hasOption('dump');
            $logging = IS::confirm($console->getOption('logging', false));

            $res = StorageSchema::init($class, $force, $dump, $logging);

            if ($dump) {
                foreach ($res as $sql) {
                    $console->line($sql, 2);
                }
            } else {
                $_force = $force ? $console->render('(FORCE)', Color::WARNING) : '';
                $res ? $console->render('[OK] ', Color::SUCCESS, true) : $console->render('[FAILED] ', Color::FAIL, true);
                $console->line("{$class} {$_force}");
            }
        }
    }

    /**
    * @CMD(orm.sync)
    * @Desc(Sync from storage ORM annotations to storage driver schema)
    * @Option(single){notes=The single file name to sync at once}
    * @Option(force){notes=Whether execute the dangerous operations like drop/delete&default=false}
    * @Option(domain){notes=The domain name used to sync orm classes schema}
    * @Option(dump){notes=Dump the sqls will be executed rather than execute them directly&default=false}
    * @Option(skip){notes=The orm class files to exclude, using `,` to separate}
    * @Option(logging){notes=Logging SQLs executed during init process&default=false}
    */
    public function syncORM($console)
    {
        $params = $console->getParams();
        $options = $console->getOptions();
        $excludes = Str::arr($console->getOption('skip', ''), ',');
        \array_walk($excludes, function (&$skip) {
            $class = Reflect::getFileNamespace($skip, true);
            $skip = $class ? $class : '';
        });
        \array_filter($excludes);

        $syncSingle = function ($single) use ($console, $excludes) {
            $storage = null;
            if (\class_exists($single)) {
                if (! \is_subclass_of($single, ORMStorage::class)) {
                    $console->fail('SingleClassNotAnORMStorage', \compact('single'));
                }
                $storage = $single;
            } elseif (\is_file($single)) {
                $class = Reflect::getFileNamespace($single, true);
                if ((! $class) || (! \is_subclass_of($class, Storage::class))) {
                    $console->fail('InvalidSingleStorageFile', \compact('single', 'class'));
                }
                $storage = $class;
            }
            if (! $storage) {
                $console->fail('InvalidStorageSingle', \compact('single', 'storage'));
            }

            $force = $console->hasOption('force');
            $dump = $console->hasOption('dump');
            $logging = IS::confirm($console->getOption('logging', '0'));

            if (\in_array($storage, $excludes)) {
                if ($dump) {
                    return $console->line("-- SKIP: {$storage}");
                }

                return $console->info("SKIPPED: {$storage}");
            }

            $res = StorageSchema::sync($storage, $force, $dump, $logging);
            if ($dump) {
                foreach ($res as $sql) {
                    $console->line($sql, 2);
                }
            } else {
                $_force = $force ? $console->render('(FORCE)', Color::WARNING) : '';
                $res ? $console->render('[OK] ', Color::SUCCESS, true) : $console->render('[FAILED] ', Color::FAIL, true);
                $console->line("{$storage} {$_force}");
            }
        };

        $console->info('Sync ORM schema ...');
        if ($console->hasOption('single')) {
            if (! ($single = $console->getOption('single'))) {
                $console->fail('MISSING_SINGLE_TARGET');
            }
            $syncSingle($single);
        } elseif ($console->hasOption('domain')) {
            if (! ($domain = $console->getOption('domain'))) {
                $console->fail('MISSING_STORAGE_DOMAIN_TO_INIT');
            }
            if (! ($path = DMN::path($domain))) {
                $console->error('INVALID_DOMAIN', \compact('domain'));
            }

            $orms = StorageManager::getDomain();
            foreach ($orms as $orm => $_domain) {
                if (($domain === $_domain) && \is_subclass_of($orm, ORMStorage::class) && Str::end('ORM', $orm)) {
                    $syncSingle($orm);
                }
            }
        } elseif ($params) {
            foreach ($params as $single) {
                $syncSingle($single);
            }
        } else {
            if (empty($all = StorageManager::getData())) {
                $console->info('No ORMs found in any domains for synchronizing');
                return;
            }
            foreach ($all as $ns => $annotations) {
                if (\is_subclass_of($ns, ORMStorage::class) && Str::end('ORM', $ns)) {
                    $syncSingle($ns);
                }
            }
        }
    }

    /**
     * @CMD(entity.add)
     * @Desc(Add an entity class in a domain)
     * @Option(domain){notes=Domain name of entity to be created}
     * @Option(entity){notes=Name of entity to be created}
     * @Option(force){notes=Whether force recreate entity when given entity name exists}
     * @Option(with){notes=Whether the entity to be created with timestamps properties&default=TS}
     */
    public function addEntity($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('entity', null, true), CASE_UPPER));
        if (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_ENTITY, "{$name}.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->fail('EntityAlreadyExists', ['entity' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }
        if (! \is_file($template = FS::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, 'entity'))) {
            $console->error('EntityClassTemplateNotExist', \compact('template'));
        }

        $parent = 'Entity';
        switch ($with = \strtoupper($console->getOption('with', 'TS'))) {
            case 'TS':
            case 'SD':
            case 'TSSD':
                $parent .= "With{$with}";
                break;
            default:
                $console->error('INVALID_WITH_SUFFIX', \compact('with'));
                break;
        }

        $entity = \file_get_contents($template);
        $entity = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $entity);
        $entity = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $entity);
        $entity = \str_replace('__PARENT__', $parent, $entity);
        $entity = \str_replace('__NAME__', \basename($name), $entity);

        $console->task("Creating Entity: {$pathof}", function () use ($class, $entity) {
            FS::unlink($class);
            FS::save($class, $entity);
        });
    }

    /**
     * @CMD(model.add)
     * @Alias(dm.add)
     * @Desc(Add an data model class in a domain)
     * @Option(domain){notes=Domain name of model to be created}
     * @Option(model){notes=Name of data model to be created}
     * @Option(force){notes=Whether force recreate model when given model name exists}
     * @Option(with){notes=Whether the entity to be created with timestamps properties&default=TS}
     */
    public function addModel($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('model', null, true), CASE_UPPER));
        if (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_MODEL, "{$name}.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->fail('ModelAlreadyExists', ['model' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }
        if (! \is_file($template = FS::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, 'model'))) {
            $console->error('ModelClassTemplateNotExist', \compact('template'));
        }

        $parent = 'Model';
        switch ($with = \strtoupper($console->getOption('with', ''))) {
            case 'TS':
            case 'SD':
            case 'TSSD':
                $parent .= "With{$with}";
                break;
            case '':
                break;
            default:
                $console->error('INVALID_WITH_SUFFIX', \compact('with'));
                break;
        }

        $model = \file_get_contents($template);
        $model = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $model);
        $model = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $model);
        $model = \str_replace('__PARENT__', $parent, $model);
        $model = \str_replace('__NAME__', \basename($name), $model);

        $console->task("Creating Model: {$pathof}", function () use ($class, $model) {
            FS::unlink($class);
            FS::save($class, $model);
        });
    }

    /**
     * @CMD(storage.add.orm)
     * @Desc(Add an orm storage class in a domain)
     * @Option(domain){notes=Domain name of orm storage to be created}
     * @Option(storage){notes=Name of orm storage to be created}
     * @Option(driver){notes=Driver been used of ORMStorage&default=mysql}
     * @Option(force){notes=Whether force recreate orm storage when given orm storage name exists}
     * @Option(with){notes=Whether orm storage has meta timestamps: TS/TSSD/SD&default=TS}
     * @Option(impl){notes=Whether orm storage implements a repository&default=false}
     * @Option(logging){notes=Whether the orm storage is a logging storage&default=false}
     */
    public function addORMStorage($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('storage', null, true), CASE_UPPER));
        if (Str::end($name, 'ORM', true)) {
            $name = Str::shift($name, 3, true);
        } elseif (Str::end($name, 'ORM.php', true)) {
            $name = Str::shift($name, 7, true);
        } elseif (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $driver = Driver::format($console->getOption('driver', 'mysql'));
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_STORAGE, $driver, "{$name}ORM.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->error('StorageAlreadyExists', ['storage' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }
        if ($console->hasOption('logging')) {
            $tpl = 'storage-orm-logging';
        } else {
            $tpl = $console->getOption('impl', false) ? 'storage-orm-impl' : 'storage-orm';
        }

        if (! \is_file($template = FS::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, $tpl))) {
            $console->error('ORMStorageClassTemplateNotExist', \compact('template'));
        }

        $storage = 'ORMStorage';
        switch ($with = \strtoupper($console->getOption('with', 'TS'))) {
            case 'TS':
            case 'SD':
            case 'TSSD':
                $storage .= "With{$with}";
                break;
            default:
                break;
        }

        $orm = \file_get_contents($template);
        $orm = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $orm);
        $orm = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $orm);
        $orm = \str_replace('__DATABASE__', Format::c2u($domain), $orm);
        $orm = \str_replace('__TABLE__', Format::c2u(\basename($name)), $orm);
        $orm = \str_replace('__NAME__', \basename($name), $orm);
        $orm = \str_replace('__STORAGE__', $storage, $orm);
        $orm = \str_replace('__DRIVER__', $driver, $orm);

        $console->task("Creating ORM Storage: {$pathof}", function () use ($class, $orm) {
            FS::unlink($class);
            FS::save($class, $orm);
        });
    }

    /**
     * @CMD(storage.add.kv)
     * @Desc(Add an kv storage class in a domain)
     * @Option(domain){notes=Domain name of kv storage to be created}
     * @Option(storage){notes=Name of kv storage to be created}
     * @Option(force){notes=Whether force recreate kv storage when given kv storage name exists}
     * @Option(impl){notes=Whether kv storage implements a repository&default=false}
     * @Option(driver){notes=Driver been used of KVStorage&default=redis}
     */
    public function addKVStorage($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('storage', null, true), CASE_UPPER));
        if (Str::end($name, 'KV', true)) {
            $name = Str::shift($name, 2, true);
        } elseif (Str::end($name, 'KV.php', true)) {
            $name = Str::shift($name, 6, true);
        } elseif (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $driver = Driver::format($console->getOption('driver', 'redis'));
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_STORAGE, $driver, "{$name}KV.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->fail('KVStorageAlreadyExists', ['kvstorage' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }

        $tpl = $console->getOption('impl', false) ? 'storage-kv-impl' : 'storage-kv';
        if (! \is_file($template = FS::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, $tpl))) {
            $console->error('KVStorageClassTemplateNotExist', \compact('template'));
        }

        $kv = \file_get_contents($template);
        $kv = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $kv);
        $kv = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $kv);
        $kv = \str_replace('__NAME__', \basename($name), $kv);
        $kv = \str_replace('__STORAGE__', 'KVStorage', $kv);
        $kv = \str_replace('__DRIVER__', $driver, $kv);

        $console->task("Creating KV Storage: {$pathof}", function () use ($class, $kv) {
            FS::unlink($class);
            FS::save($class, $kv);
        });
    }

    /**
     * @CMD(repo.add)
     * @Desc(Add a repository interface in a domain)
     * @Option(domain){notes=Domain name of repository to be created}
     * @Option(repo){notes=Name of repository to be created}
     * @Option(force){notes=Whether force recreate repository when given repository name exists}
     * @Option(type){notes=Repository type: Entity/ORM | Model/KV | Logging&default=Entity/ORM}
     * @Option(driver){notes=Driver been used of Storage}
     * @Option(storage){notes=Storage path relative to storage base}
     * @Option(entity){notes=Entity path relative to entity base}
     * @Option(model){notes=Model path relative to model base}
     */
    public function addRepository($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->error('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('repo', null, true), CASE_UPPER));
        if (Str::end($name, 'Repository', true)) {
            $name = Str::shift($name, 10, true);
        } elseif (Str::end($name, 'Repository.php', true)) {
            $name = Str::shift($name, 14, true);
        } elseif (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $driver = Driver::format($console->getOption('driver', 'mysql'));
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_REPOSITORY, "{$name}Repository.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->fail('RepositoryAlreadyExists', ['repo' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }

        $type = $console->getOption('type', 'entity');
        if ($isLogging = Str::eq($type, 'logging', true)) {
            $tpl = 'repository-logging';
        } elseif ($isEntity = IS::ciin($type, ['entity', 'orm'])) {
            $tpl = 'repository-entity';
        } elseif ($isModel = IS::ciin($type, ['model', 'kv'])) {
            $tpl = 'repository-model';
        } else {
            $console->fail('INVALID_REPOSITORY_MODEL_TYPE', \compact('type'));
        }
        if (! \is_file($template = FS::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, $tpl))) {
            $console->error('RepositoryInterfaceTemplateNotExist', \compact('template'));
        }

        $storage = $basename = \basename($name);
        if ($_storage = $console->getOption('storage')) {
            $storage = Format::namespace($_storage, FS::DS, true, false);
        }
        if ($isModel ?? false) {
            $storage .= 'KV';
        } elseif ($isLogging || ($isEntity ?? false)) {
            $storage .= 'ORM';
        }

        $repo = \file_get_contents($template);
        $repo = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $repo);
        $repo = \str_replace('__NAMESPACE__', Format::namespace($basename, FS::DS, false, true), $repo);
        $repo = \str_replace('__NAME__', $name, $repo);
        $repo = \str_replace('__STORAGE__', $storage, $repo);
        $repo = \str_replace('__DRIVER__', $driver, $repo);

        if ($isEntity ?? false) {
            $entity = $console->getOption('entity', $name);
            $repo = \str_replace('__ENTITY__', Format::namespace($basename, FS::DS, true, false), $repo);
        } elseif ($isModel ?? false) {
            $model = $console->getOption('model', $name);
            $repo = \str_replace('__MODEL__', Format::namespace($basename, FS::DS, true, false), $repo);
        }

        $console->task("Creating Repository ({$type}): {$pathof}", function () use ($class, $repo) {
            FS::unlink($class);
            FS::save($class, $repo);
        });
    }

    /**
     * @CMD(service.add)
     * @Desc(Add a service class in a domain)
     * @Option(domain){notes=Domain name of service to be created}
     * @Option(service){notes=Name of service to be created}
     * @Option(force){notes=Whether force recreate service when given service name exists}
     * @Option(entity){notes=Entity name used for CRUD template}
     * @Option(crud){notes=CRUD template type, one of create/delete/update/show/list}
     */
    public function addService($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('service', null, true), CASE_UPPER));
        if (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_SERVICE, "{$name}.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->error('ServiceAlreadyExists', ['service' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }

        $tpl = 'service-basic';
        if ($console->hasOption('crud')) {
            $crud = \strtolower(\strval($console->getOption('crud')));
            $types = ['create', 'delete', 'update', 'show', 'list'];
            if ((! $crud) || (! \in_array($crud, $types))) {
                $console->fail('INVALID_CRUD_TYPE', \compact('crud', 'types'));
            }
            $tpl = "service-crud-{$crud}";
        }

        if (! \is_file($template = FS::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, $tpl))) {
            $console->exceptor('ServiceClassTemplateNotExist', \compact('template'));
        }

        $service = \file_get_contents($template);
        $service = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $service);
        $service = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $service);
        $service = \str_replace('__NAME__', \basename($name), $service);

        if ($entity = $console->getOption('entity')) {
            $_entity = Format::namespace($entity, FS::DS, true, false);
            $service = \str_replace('__ENTITY__', $_entity, $service);
            $service = \str_replace('__ENTITY_UPPER__', \strtoupper($_entity), $service);
        }

        $console->task("Creating Service: {$pathof}", function () use ($class, $service) {
            FS::unlink($class);
            FS::save($class, $service);
        });
    }

    /**
     * @CMD(asm.add)
     * @Desc(Add Assembler in a domain)
     * @Option(domain){notes=Domain name of assembler to be created}
     * @Option(asm){notes=Assembler name}
     * @Option(force){notes=Whether force recreate assembler when given assembler name exists}
     */
    public function addAssembler($console)
    {
        if (! ($path = DMN::path($domain = $console->getOption('domain', null, true)))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('asm', null, true), CASE_UPPER));
        if (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_ASSEMBLER, "{$name}.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->fail('AssemblerAlreadyExists', ['assembler' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }
        if (! \is_file($template = FS::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, 'assembler'))) {
            $console->error('AssemblerTemplateNotFound', \compact('template'));
        }

        $assembler = \file_get_contents($template);
        $assembler = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $assembler);
        $assembler = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $assembler);
        $assembler = \str_replace('__NAME__', \basename($name), $assembler);

        $console->task("Creating Assembler: {$pathof}", function () use ($class, $assembler) {
            FS::unlink($class);
            FS::save($class, $assembler);
        });
    }

    /**
     * @CMD(ers)
     * @Desc(Create Entity/Repository/ORMStorage at once)
     * @Option(domain){notes=Domain name of classes to be created}
     * @Option(entity){notes=Entity directory path}
     * @Option(storage){notes=ORM storage directory path}
     * @Option(driver){notes=Driver been used of ORM storage&default=mysql}
     * @Option(repo){notes=Repository directory path}
     * @Option(withts){notes=Entity and ORM storage to be created need timestamps or not&default=true}
     */
    public function ers($console)
    {
        $entity = $console->getOption('entity', null, true);
        $this->addEntity($console);

        $_entity = Format::u2c($entity, CASE_UPPER);

        $console->setOption('driver', $console->getOption('driver', 'mysql'));

        $storage = $console->hasOption('storage') ? \join('/', [$console->getOption('storage'), $_entity]) : $_entity;

        $repo = $console->hasOption('repo') ? \join('/', [$console->getOption('repo'), $_entity]) : $_entity;
        $console->setOption('storage', $storage)->setOption('repo', $repo);
        $this->addRepository($console);

        $console->setOption('impl', true)->setOption('storage', $storage);
        $this->addORMStorage($console);

        return $_entity;
    }

    /**
     * @CMD(mrs)
     * @Desc(Create Model/Repository/KV-Storage at once)
     * @Option(domain){notes=Domain name of classes to be created}
     * @Option(model){notes=Model directory path}
     * @Option(storage){notes=KV storage directory path}
     * @Option(repo){notes=Repository directory path}
     */
    public function mrs($console)
    {
        $model = $console->getOption('model', null, true);
        $this->addModel($console);

        $_model = Format::u2c($model, CASE_UPPER);

        $console->setOption('driver', $console->getOption('driver', 'redis'));

        $storage = $console->hasOption('storage') ? \join('/', [$console->getOption('storage'), $_model]) : $_model;

        $repo = $console->hasOption('repo') ? \join('/', [$console->getOption('repo'), $_model]) : $_model;
        $console->setOption('storage', $storage)->setOption('type', 'kv')->setOption('repo', $repo);
        $this->addRepository($console);

        $console->setOption('impl', true)->setOption('storage', $storage);
        $this->addKVStorage($console);

        return $_model;
    }

    /**
     * @CMD(logging.add)
     * @Desc(Add a Logging repository and storage at the same time)
     * @Option(domain){notes=Domain name of logging to be created}
     * @Option(logging){notes=Logging name}
     * @Option(force){notes=Whether force recreate logging when given logging name exists}
     */
    public function addLogging($console)
    {
        $logging = $console->getOption('logging', null, true);
        $domain = $console->getOption('domain', 'Logging');
        if (! DMN::path($domain)) {
            $console->setParams([$domain]);
            $this->addDomain($console);
        }

        $console->setOption('domain', $domain);
        if ($storage = $console->getOption('storage')) {
            $console->setOption('storage', "{$storage}/{$logging}Log");
        } else {
            $console->setOption('storage', "{$logging}Log");
        }

        $console->setOption('repo', "{$logging}Log");
        $console->setOption('type', "logging");
        $this->addRepository($console);

        $console->setOption('logging', true);
        $this->addORMStorage($console);
    }

    /**
     * @CMD(event.add)
     * @Desc(Add Event in a domain)
     * @Option(domain){notes=Domain name of event to be created}
     * @Option(event){notes=Event name}
     * @Option(force){notes=Whether force recreate event when given event name exists}
     */
    public function addEvent($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('event', null, true), CASE_UPPER));
        if (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_EVENT, "{$name}.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->fail('EventAlreadyExists', ['event' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }
        if (! \is_file($template = DOF::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, 'event'))) {
            $console->error('EventClassTemplateNotExist', \compact('template'));
        }

        $event = \file_get_contents($template);
        $event = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $event);
        $event = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $event);
        $event = \str_replace('__NAME__', \basename($name), $event);

        $console->render("Creating Event: {$pathof}", function () use ($class, $event) {
            FS::unlink($class);
            FS::save($class, $event);
        });
    }

    /**
     * @CMD(listener.add)
     * @Desc(Add Listener in a domain)
     * @Option(domain){notes=Domain name of listener to be created}
     * @Option(listener){notes=Listener name}
     * @Option(force){notes=Whether force recreate listener when given listener name exists}
     */
    public function addListener($console)
    {
        $domain = $console->getOption('domain', null, true);
        if (! ($path = DMN::path($domain))) {
            $console->fail('DomainNotExists', \compact('domain'));
        }
        $name = \str_replace('\\', FS::DS, Format::u2c($console->getOption('listener', null, true), CASE_UPPER));
        if (Str::end($name, '.php', true)) {
            $name = Str::shift($name, 4, true);
        }
        $pathof = DOF::pathof($class = FS::path($path, Convention::DIR_LISTENER, "{$name}.php"));
        if (\is_file($class) && (! $console->hasOption('force'))) {
            $console->fail('ListenerAlreadyExists', ['listener' => Reflect::getFileNamespace($class, true), 'file' => $pathof]);
        }
        if (! \is_file($template = DOF::path(\dirname(__DIR__), Convention::DIR_TEMPLATE, 'listener'))) {
            $console->error('ListenerClassTemplateNotExist', \compact('template'));
        }

        $listener = \file_get_contents($template);
        $listener = \str_replace('__DOMAIN__', Format::namespace(DMN::name($domain), '.', true), $listener);
        $listener = \str_replace('__NAMESPACE__', Format::namespace($name, FS::DS, false, true), $listener);
        $listener = \str_replace('__NAME__', \basename($name), $listener);

        $console->task("Creating Listener: {$pathof}", function () use ($class, $listener) {
            FS::unlink($class);
            FS::save($class, $listener);
        });
    }
}
