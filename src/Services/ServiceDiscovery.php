<?php
namespace Purple\Core\Services;
use Symfony\Component\Finder\Finder;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class ServiceDiscovery
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function discover(string $directory, string $namespace): array
    {
        $services = [];
        $files = $this->getPhpFiles($directory);

        foreach ($files as $file) {
            $filePath = $file[0];
            $className = $this->getFullyQualifiedClassName($filePath, $directory, $namespace);
            if ($className && $this->isInstantiableClass($className)) {
                $serviceId = $this->getServiceId($className);
                $services[$serviceId] = $this->createServiceDefinition($className);
            }
        }
        //echo "<pre>";
        //print_r($services);
        return $services;
    }
    
    public function discoverFromConfig(array $config): array
    {
        $services = [];

        foreach ($config as $namespace => $settings) {
            $finder = new Finder();
            $finder->files()->in($settings['resource']);

            if (isset($settings['exclude'])) {
                $finder->exclude($settings['exclude']);
            }

            foreach ($finder as $file) {
                $className = $this->getFullyQualifiedClassNameFromFile($file, $namespace);
                if ($className && $this->isInstantiableClass($className)) {
                    $serviceId = $this->getServiceId($className);
                    $services[$serviceId] = $this->createServiceDefinition($className);
                }
            }
        }

        return $services;
    }

    private function getPhpFiles(string $directory): RegexIterator
    {
        $directory = realpath($directory);
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        return new RegexIterator($iterator, '/^.+\.php$/i', RegexIterator::GET_MATCH);
    }

    private function getFullyQualifiedClassName(string $file, string $directory, string $namespace): ?string
    {
        $directory = realpath($directory);
        $relativePath = substr($file, strlen($directory) + 1, -4);
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '\\', $relativePath);
        return $namespace . '\\' . $relativePath;
    }

    private function isInstantiableClass(string $className): bool
    {
        if (!class_exists($className)) {
            return false;
        }

        $reflection = new ReflectionClass($className);
        return !$reflection->isAbstract() && !$reflection->isInterface() && !$reflection->isTrait();
    }

    private function getServiceId(string $className): string
    {
        $parts = explode('\\', $className);
        return strtolower(end($parts));
    }

    private function createServiceDefinition(string $className): array
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        $tags = $this->generateTags($className);
        $definition = [
            'class' => $className,
            'arguments' => [],
            'autowire' => true,
            'tags' => $tags,
        ];

        if ($constructor) {
            foreach ($constructor->getParameters() as $param) {
                if ($param->getType()) {
                    $definition['arguments'][] = '@' . $this->getServiceId($param->getType()->getName());
                }
            }
        }
        return $definition;
    }

    
    private function generateTags(string $className): array
    {
        $tags = [];
        $parts = explode('\\', $className);
        $namespace = implode('.', array_slice($parts, 0, -1));
        $tags[] = strtolower($namespace . '.autowired');
        return $tags;
    }
}