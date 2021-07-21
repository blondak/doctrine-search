<?php

namespace Doctrine\Search\Serializer;

use Doctrine\Common\Util\ClassUtils;
use Doctrine\Search\DefaultSerializerNotProvidedException;
use Doctrine\Search\SerializerInterface;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class ChainSerializer implements SerializerInterface
{

	/**
	 * @var SerializerInterface[]
	 */
	private $serializers = [];

	/**
	 * @var SerializerInterface
	 */
	private $defaultSerializer;



	public function addSerializer($classType, SerializerInterface $serializer)
	{
		$this->serializers[$classType] = $serializer;
	}



	public function setDefaultSerializer(SerializerInterface $serializer)
	{
		$this->defaultSerializer = $serializer;
	}



	/**
	 * @param object $object
	 * @param mixed $indexOptions
	 * @throws DefaultSerializerNotProvidedException
	 * @return string
	 */
	public function serialize($object, $indexOptions = NULL)
	{
		foreach ($this->serializers as $classType => $serializer) {
			if ($object instanceof $classType) {
				return $serializer->serialize(...func_get_args());
			}
		}

		if (!$this->defaultSerializer) {
			throw new DefaultSerializerNotProvidedException;
		}

		return $this->defaultSerializer->serialize(...func_get_args());
	}



	/**
	 * @param string $entityName
	 * @param string $data
	 * @throws DefaultSerializerNotProvidedException
	 * @return object
	 */
	public function deserialize($entityName, $data)
	{
		$classType = $entityName;
		if (isset($this->serializers[$classType])) {
			return $this->serializers[$classType]->deserialize($entityName, $data);
		}

		if (!$this->defaultSerializer) {
			throw new DefaultSerializerNotProvidedException;
		}

		return $this->defaultSerializer->deserialize($entityName, $data);
	}

}
