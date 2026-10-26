<?php

declare(strict_types=1);

error_reporting(E_ALL);
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    }
    return false;
});

require getenv('COMPAT_AUTOLOAD') ?: __DIR__ . '/vendor/autoload.php';
spl_autoload_register(static function (string $class): void {
    $prefix = 'Doctrine\\Search\\';
    if (strpos($class, $prefix) === 0) {
        $file = __DIR__ . '/../../lib/Doctrine/Search/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
}, true, true);
require __DIR__ . '/../../lib/Doctrine/Search/exceptions.php';

$checks = 0;
function compatSame($expected, $actual): void
{
    global $checks;
    if ($expected !== $actual) {
        throw new RuntimeException('Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
    $checks++;
}

function compatThrows(string $class, callable $callback): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        if (!$e instanceof $class) {
            throw $e;
        }
        compatSame(true, true);
        return;
    }
    throw new RuntimeException('Expected ' . $class);
}

$manager = (new ReflectionClass(Doctrine\Search\SearchManager::class))->newInstanceWithoutConstructor();
$metadata = new Doctrine\Search\Mapping\ClassMetadata(stdClass::class);
$repository = new Doctrine\Search\EntityRepository($manager, $metadata);
$collection = new Doctrine\Search\EntityRepositoryCollection($manager);
$collection->addRepository($repository);
compatSame(stdClass::class, $repository->getClassName());
compatSame($manager, $repository->getSearchManager());
compatSame($metadata, $repository->getClassMetadata());
compatSame([stdClass::class], $collection->getClassName());

foreach ([$repository, $collection] as $instance) {
    compatSame(true, $instance instanceof Doctrine\Persistence\ObjectRepository);
    compatThrows(Doctrine\Search\NotImplementedException::class, static function () use ($instance): void {
        $instance->findBy([]);
    });
    compatThrows(Doctrine\Search\NotImplementedException::class, static function () use ($instance): void {
        $instance->findBy([], null);
    });
    compatThrows(Doctrine\Search\NotImplementedException::class, static function () use ($instance): void {
        $instance->findBy([], ['id' => 'ASC'], 10, 0);
    });
    compatThrows(TypeError::class, static function () use ($instance): void {
        $instance->findBy([], 'id');
    });
}

class CompatibilityDocument
{
    public int $productId = 123;
    public string $name = 'Cat food';
}

$document = new CompatibilityDocument();
foreach ([new Doctrine\Search\Serializer\JMSSerializer(), new Doctrine\Search\Serializer\JMSSerializer(null, null)] as $serializer) {
    compatSame(['productId' => 123, 'name' => 'Cat food'], $serializer->serialize($document));
}

$context = JMS\Serializer\SerializationContext::create()->setSerializeNull(true);
$inner = JMS\Serializer\SerializerBuilder::create()
    ->setPropertyNamingStrategy(new JMS\Serializer\Naming\IdenticalPropertyNamingStrategy())
    ->build();
$serializer = new Doctrine\Search\Serializer\JMSSerializer($context, $inner);
compatSame(['productId' => 123, 'name' => 'Cat food'], $serializer->serialize($document));
compatSame(['productId' => 123, 'name' => 'Cat food'], $serializer->serialize($document));
$restored = $serializer->deserialize(CompatibilityDocument::class, '{"productId":456,"name":"Dog food"}');
compatSame(CompatibilityDocument::class, get_class($restored));
compatSame(456, $restored->productId);
compatSame('Dog food', $restored->name);
compatThrows(TypeError::class, static function () use ($inner): void {
    new Doctrine\Search\Serializer\JMSSerializer(new stdClass(), $inner);
});
compatThrows(TypeError::class, static function (): void {
    new Doctrine\Search\Serializer\JMSSerializer(null, new stdClass());
});

echo 'OK: ' . $checks . " doctrine-search compatibility assertions under E_ALL\n";
