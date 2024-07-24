<?php

namespace Purple\Core\Services\Kernel;

use Purple\Core\Services\Container;
use Purple\Libs\Cache\Interface\CacheInterface;
use Purple\Libs\Cache\Cache\RedisCache;
use Purple\Libs\Cache\Cache\FileCache;
use Purple\Libs\Cache\Cache\InMemoryCache;
use Purple\Core\Services\Interface\CompilerPassInterface;
use Purple\Core\Services\Interface\BundleInterface;
use Purple\Core\Services\ContainerConfigurator;
use Purple\Core\Services\Kernel\PassConfig;
use Purple\Core\Services\Interface\EventDispatcherInterface;
use Purple\Core\Services\Interface\KernelExtensionInterface;


class Kernel
{
    private $environment;
    private $debug;
    private $container;
    private $booted = false;
    private $compilerPasses = [];
    private $logFilePath;
    private $configDir;
    private $resourceDir;
    private $cache;
    private $envConfigCache = [];
    private array $bundles = [];
    private EventDispatcherInterface $eventDispatcher;
    private array $extensions = [];

    public function __construct(string $environment, bool $debug, string $logFilePath, CacheInterface $cache,  string $configDir, string $resourceDir, EventDispatcherInterface $eventDispatcher)
    {
        $this->environment = $environment;
        $this->debug = $debug;
        $this->logFilePath = $logFilePath;
        $this->cache = $cache;
        $this->configDir = $configDir;
        $this->resourceDir = $resourceDir;
        $this->container = new Container($this->logFilePath, $this->cache);
        $this->eventDispatcher = $eventDispatcher;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }
        $this->eventDispatcher->dispatch('kernel.pre_boot');

        $this->loadEnvironmentConfig();
        
        $this->registerBundles();
    
        $this->initializeContainer();
        //print_r($this->container->services);
        $this->initializeErrorHandler();

        // Boot bundles after all have been built
        foreach ($this->bundles as $bundle) {
            $bundle->boot();
        }

        $this->booted = true;

        $this->eventDispatcher->dispatch('kernel.post_boot');
    }

    public function addExtension(KernelExtensionInterface $extension): void
    {
        $this->extensions[] = $extension;
    }

    private function loadExtensions(): void
    {
        foreach ($this->extensions as $extension) {
            $extension->load($this->container);
        }
    }

    public function getBundles(): array
    {
        return $this->bundles;
    }

    public function getContainer(): Container
    {
        if (!$this->booted) {
            $this->boot();
        }

        return $this->container;
    }

    private function checkPassConfig($passConfig){

        $containerConfigurator = new ContainerConfigurator($this->container);
        // Sort the compiler passes by priority in descending order (highest first)
        usort($this->compilerPasses, function( $a,  $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        foreach ($this->compilerPasses as $pass) {
            if ($pass->getType() === $passConfig) {
                $pass->process( $containerConfigurator);
            }
        }

        // Generate and resolve dependency graph
        $this->container->initiateDependancyGraph();

        // Enable service caching if needed
        $this->container->enableServiceCaching();

    }

    protected function initializeContainer(): void
    {
        $this->configureContainer();

        $containerConfigurator = new ContainerConfigurator($this->container);
        // Sort the compiler passes by priority in descending order (highest first)
        usort($this->compilerPasses, function( $a,  $b) {
            return $b->getPriority() <=> $a->getPriority();
        });

        //echo "<pre>";
        //print_r($this->compilerPasses);
        //calling compiler pass after services and parameters have loaded to services property
        foreach ($this->compilerPasses as $pass) {
            if ($pass->getType() === PassConfig::TYPE_OPTIMIZE) {
                $pass->process( $containerConfigurator);
            }
        }

        // Generate and resolve dependency graph
        $this->container->initiateDependancyGraph();
   
        // Enable service caching if needed
        $this->container->enableServiceCaching();

        // Optionally, compile the container if using compiler passes
        $this->container->compile();
    }

    public function loadConfigurationFromFile($file)
    {
        // Load service configurations from a file (e.g., YAML / PHP)
        $this->container->loadConfigurationFromFile($file);
    }

    public function autoDiscoverServices($dir, $namespace): void
    {
        // Auto-discovery of services
        $this->container->autoDiscover($dir, $namespace);
    }

    protected function loadEnvironmentConfig(): void
    {
        $envConfig = $this->getEnvironmentConfig();
        foreach ($envConfig as $key => $value) {
            $this->container->setParameter($key, $value);
        }
    }

    protected function getEnvironmentConfig(): array
    {
        if (!empty($this->envConfigCache)) {
            return $this->envConfigCache;
        }
    
        $configFile = sprintf('%s/config_%s.php', $this->configDir, $this->environment);
        if (!file_exists($configFile)) {
            throw new EnvironmentNotFoundException(sprintf('Configuration file for environment "%s" not found.', $this->environment));
        }
    
        try {
            $config = require $configFile;
            
            if (!is_array($config)) {
                throw new ConfigurationException('Configuration file must return an array.');
            }
    
            $this->envConfigCache = $config;
            return $config;
        } catch (\Exception $e) {
            throw new ConfigurationException(sprintf('Error loading configuration file: %s', $e->getMessage()));
        }
    }


    protected function registerBundles(): void
    {
        $bundlesFile = sprintf('%s/bundles.php', $this->configDir);
        if (!file_exists($bundlesFile)) {
            throw new \RuntimeException(sprintf('The bundles file "%s" does not exist.', $bundlesFile));
        }

        $bundles = require $bundlesFile;
        
        if (!is_array($bundles)) {
            throw new \RuntimeException('The bundles file must return an array of bundle class names.');
        }

        foreach ($bundles as $bundleClass) {
            if (!class_exists($bundleClass)) {
                throw new \RuntimeException(sprintf('Bundle class "%s" does not exist.', $bundleClass));
            }

            $bundle = new $bundleClass();
            
            if (!$bundle instanceof BundleInterface) {
                throw new \RuntimeException(sprintf('Bundle class "%s" must implement BundleInterface.', $bundleClass));
            }

            $containerConfigurator = new ContainerConfigurator($this->container);
            $this->bundles[] = $bundle;
            $bundle->build($containerConfigurator);
            
        }
    }

    public function runGarbageCollection(): void
    {
        if (!$this->booted) {
            $this->boot();
        }
        $this->checkPassConfig(PassConfig::TYPE_BEFORE_REMOVING);
        $this->container->runGarbageCollection();
        $this->checkPassConfig(PassConfig::TYPE_AFTER_REMOVING);
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function getLogDir(): string
    {
        return dirname($this->logFilePath);
    }

    public function getCacheDir(): string
    {
        return $this->resourceDir . '/cache/' . $this->environment;
    }

    public function getProjectDir(): string
    {
        return realpath($this->resourceDir . '/..');
    }

    public function getEnvironmentParameters(): array
    {
        return $this->getEnvironmentConfig();
    }

    protected function initializeErrorHandler(): void
    {
        //$errorHandler = $this->container->get('error_handler');
        //$errorHandler->register();
    }

    public function loadEnv($file): void
    {
        // Load environment variables
        $this->container->loadEnv($file);
    }


    protected function configureContainer(): void
    {
        $this->container->setParameter('kernel.environment', $this->environment);
        $this->container->setParameter('kernel.debug', $this->debug);
        $this->container->setParameter('kernel.config_dir', $this->configDir);
        $this->container->setParameter('kernel.resource_dir', $this->resourceDir);
    }

    public function addCompilerPass(CompilerPassInterface $pass): void
    {
        $this->compilerPasses[] = $pass;
    }
}
