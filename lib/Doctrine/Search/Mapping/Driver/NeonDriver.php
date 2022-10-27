<?php

namespace Doctrine\Search\Mapping\Driver;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Search\InvalidArgumentException;
use Doctrine\Search\Mapping\IndexMetadata;
use Doctrine\Search\Mapping\TypeMetadata;
use Nette\Neon\Neon;
use Nette\DI\Config;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class NeonDriver implements MappingDriver
{

	/**
	 * @var array
	 */
	private $classNames;

	/**
	 * @var string
	 */
	private $metadataDirectory;

	/**
	 * @var array
	 */
	private $typesMapping;

	/**
	 * @var array
	 */
	private $indexesMapping;



	public function __construct($metadataDirectory)
	{
		$this->metadataDirectory = $metadataDirectory;
	}



	/**
	 * Loads the metadata for the specified class into the provided container.
	 *
	 * @param string $className
	 * @param ClassMetadata|\Doctrine\Search\Mapping\ClassMetadata $metadata
	 *
	 * @return void
	 */
	public function loadMetadataForClass($className, ClassMetadata $metadata)
	{
		if (!$typeMapping = $this->getTypeMapping($className)) {
			return;
		}

		$this->loadTypeMapping($metadata->type, $typeMapping);

		foreach ($typeMapping['index'] as $index){
    		$indexMapping = $this->getIndexMapping($index);
    		$this->loadIndexMapping($metadata, $index, $indexMapping);
		}

		if (!empty($typeMapping['river'])) {
			$metadata->riverImplementation = $typeMapping['river'];
		}
	}



	protected function loadTypeMapping(TypeMetadata $type, $typeMapping)
	{
		$type->name = $typeMapping['type'];

		if (!empty($typeMapping['properties'])) {
			$type->setProperties($typeMapping['properties']);
		}

		if (!empty($typeMapping['parameters'])) {
			$type->setParameters($typeMapping['parameters']);
		}

		unset($typeMapping['properties'], $typeMapping['parameters']);

		$type->source = !empty($typeMapping['source']);
		$type->boost = !empty($typeMapping['boost']) ? $typeMapping['boost'] : NULL;

		unset($typeMapping['class'], $typeMapping['subclasses'], $typeMapping['river'], $typeMapping['index'], $typeMapping['source'], $typeMapping['type']);
		$type->setSettings((array) $typeMapping);
	}



	/**
	 *
	 * @param IndexMetadata[] $index
	 * @param mixed $indexMapping
	 */
	protected function loadIndexMapping(ClassMetadata $metadata, $index, $indexMapping)
	{
	    if (!array_key_exists($index, $metadata->index)){
	        $metadata->index[$index] = new IndexMetadata();
	    }
		foreach (array('name', 'numberOfShards', 'numberOfReplicas', 'charFilter', 'filter', 'analyzer', 'tokenizer', 'indexOptions') as $key) {
			if (!array_key_exists($key, $indexMapping)) {
				continue;
			}

			$metadata->index[$index]->{$key} = $indexMapping[$key];
		}
	}



	public function getAllClassNames()
	{
		if ($this->classNames !== NULL) {
			return $this->classNames;
		}

		$classes = array();
		foreach ($this->getTypesMapping() as $meta) {
			$classes[] = $meta['class'];
		}

		return $this->classNames = $classes;
	}



	/**
	 * Returns whether the class with the specified name should have its metadata loaded.
	 *
	 * @param string $className
	 * @return boolean
	 */
	public function isTransient($className)
	{
		return ! $this->getTypeMapping($className);
	}



	/**
	 * @param string $className
	 * @return array
	 */
	protected function getTypeMapping($className)
	{
		foreach ($this->getTypesMapping() as $mapping) {
			if ($mapping['class'] === $className) {
				return $mapping;
			}

			if (isset($mapping['subclasses']) && in_array($className, $mapping['subclasses'])) {
				return $mapping;
			}
		}

		return NULL;
	}



	/**
	 * @return array
	 */
	protected function getTypesMapping()
	{
		if ($this->typesMapping !== NULL) {
			return $this->typesMapping;
		}

		$this->typesMapping = array();
		foreach (glob($this->metadataDirectory . '/*.type.neon') as $file) {
			$meta = Neon::decode(file_get_contents($file));
			if (!isset($meta['class'])) {
				throw new \InvalidArgumentException("The metadata file $file is missing a required field 'class' with entity name.");
			}

			$meta['type'] = basename($file, '.type.neon');
			$this->typesMapping[$meta['type']] = $meta;
		}
		return $this->typesMapping;
	}



	/**
	 * @param string $indexName
	 * @return array
	 */
	protected function getIndexMapping($indexName)
	{
		foreach ($this->getIndexesMapping() as $mapping) {
			if ($mapping['name'] === $indexName) {
				return $mapping;
			}
		}

		throw new InvalidArgumentException(sprintf('Metadata of index %s not found', $indexName));
	}



	/**
	 * @return array
	 */
	protected function getIndexesMapping()
	{
		if ($this->indexesMapping !== NULL) {
			return $this->indexesMapping;
		}

		// todo: refactor away usage of Nette\DI
		$adapter = new Config\Adapters\NeonAdapter();

		$this->indexesMapping = array();
		foreach (glob($this->metadataDirectory . '/*.index.neon') as $file) {
			$meta = $adapter->load($file);
			$meta['name'] = basename($file, '.index.neon');

			// $indexConfig = \Nette\Schema\Helpers::merge($meta, $this->indexDefaults);
			unset($analysisSection);
			foreach (array('charFilter', 'filter', 'analyzer', 'tokenizer') as $analysisType) {
			    if (!array_key_exists($analysisType, $meta)){
			        continue;
			    }
				$analysisSection = $meta[$analysisType];
				unset($setup);
				foreach ($analysisSection as $name => $setup) {
				    if (preg_match('/^(?<name>[^\s<]+)[\s]*<[\s]*(?<parent>.*)$/', $name, $matches)){
				        if (!isset($analysisSection[$matches['parent']])) {
				            throw new \Nette\Utils\AssertionException(sprintf('The %s.%s cannot inherit undefined %s.%s', $analysisType, $name, $analysisType, $matches['parent']));
				        }
				        $analysisSection[$matches['name']] = \Nette\Schema\Helpers::merge($setup, $analysisSection[$matches['parent']]);
				        unset($analysisSection[$name]);
				    }
				}

				$meta[$analysisType] = $analysisSection;
			}

			$this->indexesMapping[$meta['name']] = $meta;
		}
		return $this->indexesMapping;
	}

}
