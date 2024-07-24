<?php
namespace Purple\Core\Services;

use ReflectionClass;
use ReflectionMethod;
use Exception;
use Purple\Core\Services\Exception\ServiceNotFoundException;
use Purple\Core\Services\Exception\DependencyResolutionException;
use Symfony\Component\Yaml\Yaml;
use Purple\Core\Services\Container;
use Purple\Core\Services\DependencyResolver;
use Purple\Core\Services\ContainerConfigurator;
use Purple\Core\Services\ServiceDiscovery;
use Purple\Core\Services\Interface\MiddlewareInterface;

class ConfigurationLoader
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
       
    }

    public function loadEnv($file)
    {
        if (!file_exists($file)) {
            throw new Exception("Env file $file not found");
        }
        

        $env = parse_ini_file($file);
        foreach ($env as $key => $value) {
            $this->container->setParameter($key, $value);
        }
        //print_r($this->parameters);
    }


    public function loadConfigurationFromFile($filePath)
    {
        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        switch ($extension) {
            case 'yaml':
            case 'yml':
                $config = Yaml::parseFile($filePath);
                $this->processYamlConfig($config);
                break;

            case 'php':
                $phpServices = include $filePath;
                if (is_callable($phpServices)) {
                    $configurator = new ContainerConfigurator($this->container );
                    $phpServices($configurator);
                } else {
                    throw new DependencyResolutionException("PHP configuration file must return a callable");
                }
                break;

            default:
                throw new DependencyResolutionException("Unsupported configuration file format: " . $extension);
        }

    }

    private function processYamlConfig(array $config)
    {
        if (isset($config['parameters'])) {
            foreach ($config['parameters'] as $key => $value) {
                $this->container->setParameter($key, $value);
            }
        }

        //fetching defaults
        if (isset($config['_defaults'])) {
            foreach ($config['_defaults'] as $default => $state) {
                //print_r($default);
                if ($default === 'setAsGlobal') {
                    $this->container->setAsGlobal($state);
                }
                if ($default === 'setAutowire') {
                    $this->container->setAutowire($state);
                }
                if ($default === 'setAnnotwire') {
                    $this->container->setAnnotwire($state);
                }
                if ($default === 'wireType') {
                    $this->container->wireType($state);
                }
                if ($default === 'addGlobalMiddleware') {
                    $class_name = str_replace("'", "", $state);
                    //var_dump($class_name);
                    $middleware = '';
                    if (class_exists($class_name)) {
                        $middleware_instance = new $class_name();
                         // Verify that the middleware implements the MiddlewareInterface
                        if ($middleware_instance instanceof MiddlewareInterface) {
                            $middleware =  $middleware_instance;
                        }
                    }
                    $this->container->addGlobalMiddleware($middleware);
                }
            }
        }
        

        //fetching services
        if (isset($config['services'])) {
            //var_dump($this->container->setAsGlobal);
            foreach ($config['services'] as $id => $serviceConfig) {
                if (is_string($serviceConfig)) {
                    // If the service config is just a string, assume it's the class name
                    $this->container->set($id, $serviceConfig);
                } elseif (is_array($serviceConfig)) {
                    // If it's an array, process the configuration
                    $this->container->set($id, $serviceConfig['class'] ?? null);
                    $this->configureService($id, $serviceConfig);
                } else {
                    $this->container->logger->warning("Invalid service configuration for '$id'");
                }
            }
        }


        // New discovery mode
        if (isset($config['discovery'])) {
            $discovery = new ServiceDiscovery($this->container);
            $services = $discovery->discoverFromConfig($config['discovery']);
            foreach ($services as $id => $definition) {
                $this->container->set($id, $definition['class']);
                if (!empty($definition['arguments'])) {
                    $this->container->arguments($id, $definition['arguments']);
                }
                if ($definition['autowire']) {
                    $this->container->autowire($id);
                }
                if (isset($config['annotwire'])) {
                    $this->container->annotwire($id);
                }
                if (isset($config['lazy'])) {
                    $this->container->setLazy($id, true);
                }
                if (isset($config['decorate'])) {
                    $this->container->decorate($id, $config['decorate'], $innerServiceId);
                }
                if (isset($config['addGlobalMiddleware'])) {
                    $this->container->addGlobalMiddleware($config['addGlobalMiddleware'] );
                }
                if (isset($config['addServiceMiddleware'])) {
                    $class_name = str_replace("'", "", $config['addServiceMiddleware']);
                    //var_dump($class_name);
                    $middleware = '';
                    if (class_exists($class_name)) {
                        $middleware_instance = new $class_name();
                         // Verify that the middleware implements the MiddlewareInterface
                        if ($middleware_instance instanceof MiddlewareInterface) {
                            $middleware =  $middleware_instance;
                        }
                    }
                    $this->container->addServiceMiddleware($id, $middleware);
                }
                if (!empty($definition['tags'])) {
                    //print_r($definition['tags']);
                    $this->container->addTag($id, $definition['tags']);
                }
                if (isset($config['asGlobal'])) {
                    $this->container->asGlobal($id, $config['asGlobal']);
                }else{
                    $this->container->asGlobal($id, false);
                }
                if (isset($config['asShared'])) {
                    $this->container->asShared($id, $config['asShared']);
                }else{
                    $this->container->asShared($id, true);
                }
            }
        }
        

    }

    private function configureService($id, array $config)
    {
        //print_r($this->container->wireType);
        if (isset($config['arguments'])) {
            $this->container->arguments($id, $config['arguments']);
        }
        if (isset($config['method_calls'])) {
            foreach ($config['method_calls'] as $call) {
                $this->container->addMethodCall($id, $call[0], $call[1] ?? []);
            }
        }
        if (isset($config['tags'])) {
            $this->container->addTag($id, $config['tags']);
         
        }
        if (isset($config['autowire'])) {
            $this->container->autowire($id);
        }
        if (isset($config['lazy'])) {
            $this->container->setLazy($id, true);
        }
        if (isset($config['annotwire'])) {
            $this->container->annotwire($id);
        }
        if (isset($config['alias'])) {
            $this->container->setAlias($config['alias'], $id);
        }
        if (isset($config['factory'])) {
            $this->container->factory($id, $config['factory']);
        }
        if (isset($config['implements'])) {
            $this->container->implements($id, $config['implements']);
        }
        if (isset($config['decorate'])) {
            $this->container->decorate($id, $config['decorate'], $innerServiceId);
        }
        if (isset($config['addGlobalMiddleware'])) {
            $this->container->addGlobalMiddleware($config['addGlobalMiddleware'] );
        }
        if (isset($config['addServiceMiddleware'])) {
            $class_name = str_replace("'", "", $config['addServiceMiddleware']);
            //var_dump($class_name);
            $middleware = '';
            if (class_exists($class_name)) {
                $middleware_instance = new $class_name();
                 // Verify that the middleware implements the MiddlewareInterface
                if ($middleware_instance instanceof MiddlewareInterface) {
                    $middleware =  $middleware_instance;
                }
            }
            $this->container->addServiceMiddleware($id, $middleware);
        }
        if (isset($config['scope'])) {
            $this->container->scope($id, $config['scope']);
        }else{
            $this->container->scope($id, $this->container::SCOPE_SINGLETON);
        }
        if (isset($config['asGlobal'])) {
            $this->container->asGlobal($id, $config['asGlobal']);
        }else{
            $this->container->asGlobal($id, false);
        }
        //print_r($this->container->services);
        if (isset($config['asShared'])) {
            $this->container->asShared($id, $config['asShared']);
        }else{
            $this->container->asShared($id, true);
        }
    }

}