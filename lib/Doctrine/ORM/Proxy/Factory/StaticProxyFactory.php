<?php


declare(strict_types=1);

namespace Doctrine\ORM\Proxy\Factory;

use Doctrine\ORM\Configuration\ProxyConfiguration;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Proxy\Factory\Strategy\ConditionalFileWriterProxyGeneratorStrategy;
use Doctrine\ORM\Proxy\Proxy;

/**
 * Static factory for proxy objects.
 *
 * @package Doctrine\ORM\Proxy\Factory
 * @since 3.0
 *
 * @author Benjamin Eberlei <kontakt@beberlei.de>
 * @author Guilherme Blanco <guilhermeblanco@hotmail.com>
 */
class StaticProxyFactory implements ProxyFactory
{
    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var ProxyGenerator
     */
    protected $generator;

    /**
     * @var ProxyDefinitionFactory
     */
    protected $definitionFactory;

    /**
     * @var array<string, ProxyDefinition>
     */
    private $definitions = [];

    /**
     * ProxyFactory constructor.
     *
     * @param ProxyConfiguration $configuration
     */
    public function __construct(EntityManagerInterface $entityManager, ProxyConfiguration $configuration)
    {
        $resolver          = $configuration->getResolver();
        //$autoGenerate      = $configuration->getAutoGenerate();
        $generator         = new ProxyGenerator();
        $generatorStrategy = new Strategy\ConditionalFileWriterProxyGeneratorStrategy($generator);
        $definitionFactory = new ProxyDefinitionFactory($entityManager, $resolver, $generatorStrategy);

        $generator->setPlaceholder('baseProxyInterface', Proxy::class);

        $this->entityManager     = $entityManager;
        $this->definitionFactory = $definitionFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function generateProxyClasses(array $classMetadataList) : int
    {
        $generated = 0;

        foreach ($classMetadataList as $classMetadata) {
            if ($classMetadata->isMappedSuperclass || $classMetadata->getReflectionClass()->isAbstract()) {
                continue;
            }

            $this->definitionFactory->build($classMetadata);

            $generated++;
        }

        return $generated;
    }

    /**
     * {@inheritdoc}
     */
    public function getProxy(string $className, array $identifier) : Proxy
    {
        $proxyDefinition = $this->getOrCreateProxyDefinition($className);
        $proxyInstance   = $this->createProxyInstance($proxyDefinition);
        $proxyPersister  = $proxyDefinition->entityPersister;

        $proxyPersister->setIdentifier($proxyInstance, $identifier);

        return $proxyInstance;
    }

    /**
     * @param ProxyDefinition $definition
     *
     * @return Proxy
     */
    protected function createProxyInstance(ProxyDefinition $definition) : Proxy
    {
        /** @var Proxy $classMetadata */
        $proxyClassName = $definition->proxyClassName;
        $proxyInstance  = new $proxyClassName($definition);

        return $proxyInstance;
    }

    /**
     * Create a proxy definition for the given class name.
     *
     * @param string $className
     *
     * @return ProxyDefinition
     */
    private function getOrCreateProxyDefinition(string $className) : ProxyDefinition
    {
        if (! isset($this->definitions[$className])) {
            $classMetadata = $this->entityManager->getClassMetadata($className);

            $this->definitions[$className] = $this->definitionFactory->build($classMetadata);
        }

        return $this->definitions[$className];
    }
}
