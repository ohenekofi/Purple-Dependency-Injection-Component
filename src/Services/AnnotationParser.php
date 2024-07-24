<?php
namespace Purple\Core\Services;
use Symfony\Component\Finder\Finder;
use ReflectionClass;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

class AnnotationParser
{
    private $logger;
    private $container;

    public function __construct(Logger $logger, Container $container)
    {
        $this->logger = $logger;
        $this->container = $container;
    }

    public function parse(string $className): void
    {
        $reflectionClass = new ReflectionClass($className);


        // Parse class-level annotations
        $classAttributes = $reflectionClass->getAttributes();
        
        foreach ($classAttributes as $attribute) {
            
            if ($attribute->getArguments() !== []) {
                $serviceName = $attribute->getArguments()['name'] ?? $className;
                //print_r($serviceName);
                $this->container->set($serviceName, $className);
                
                // Set asGlobal and asShared
                $this->container->asGlobal($serviceName, $attribute->getArguments()['asGlobal'] ?? false);
                $this->container->asShared($serviceName, $attribute->getArguments()['asShared'] ?? true);

                // Add tags
                $tags = $attribute->getArguments()['tags'] ?? [];
                if (!empty($tags)) {
                    $this->container->addTag($serviceName, $tags);
                }

                // Set autowire
                if (isset($attribute->getArguments()['autowire'])) {
                    $this->container->autowire($serviceName);
                }

                // Set autowire
                if (isset($attribute->getArguments()['annotwire'])) {
                    $this->container->annotwire($serviceName);
                }
            }
        }
    }

    private function parseMethodParameters(ReflectionMethod $method): array
    {
        $parameters = [];
        foreach ($method->getParameters() as $param) {
            $paramAttributes = $param->getAttributes();
            foreach ($paramAttributes as $attribute) {
                if ($attribute->getName() === 'Inject') {
                    $parameters[] = new Reference($attribute->getArguments()[0] ?? $param->getName());
                }
            }
        }
        return $parameters;
    }
}


