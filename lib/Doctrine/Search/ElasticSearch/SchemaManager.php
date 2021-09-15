<?php

namespace Doctrine\Search\ElasticSearch;

use Doctrine;
use Doctrine\Search\Mapping\ClassMetadata;
use Doctrine\Search\Mapping\TypeMetadata;
use Doctrine\Search\SearchManager;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Elastica\Type\Mapping as ElasticaTypeMapping;
use Doctrine\Search\Mapping\IndexMetadata;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
class SchemaManager implements Doctrine\Search\SchemaManager
{

	/**
	 * @var \Doctrine\Search\ElasticSearch\Client
	 */
	private $client;

	/**
	 * @var \Elastica\Client
	 */
	private $elastica;



	public function __construct(Client $client)
	{
		$this->client = $client;
		$this->elastica = $client->getClient();
	}



	/**
	 * @param ClassMetadata $class
	 * @return \Elastica\Index
	 */
	protected function getIndex(ClassMetadata $class, IndexMetadata $index)
	{
		return $this->elastica
			->getIndex($index->name);
	}



	/**
	 * @param ClassMetadata $class
	 * @return \Elastica\Type
	 */
	protected function getType(ClassMetadata $class, IndexMetadata $index)
	{
		return $this->getIndex($class, $index)
			->getType($class->getTypeName());
	}



	/**
	 * @param array|ClassMetadata[] $classes
	 */
	public function dropMappings(array $classes)
	{
		foreach ($classes as $class) {
		    foreach ($class->getIndexes() as $indexName => $index){

		        if (!$this->hasIndex($indexName)) {
    				continue;
    			}

    			if (!$this->hasType($class, $index)) {
    				continue;
    			}

    			$this->dropType($class, $index);
		    }
		}

		foreach ($classes as $class) {
		    foreach ($class->getIndexes() as $indexName => $index){
		        if (!$this->hasIndex($indexName)) {
				    continue;
			    }

			    $this->dropIndex($indexName);
		    }
		}
	}



	/**
	 * @param array|ClassMetadata[] $classes
	 * @param bool $withAliases
	 * @return array
	 */
	public function createMappings(array $classes, $withAliases = FALSE)
	{
		$aliases = [];
		$date = date('YmdHis');
		foreach ($classes as $class) {
		    foreach ($class->getIndexes() as $indexName => $index){
    		    if ($withAliases) {
    				$indexAlias = $index->name . '_' . $date;
    				$aliases[$indexAlias] = $index->name;

    				$fakeMetadata = clone $class;
    				$fakeMetadata->index[$indexName]->name = $indexAlias;

    				$class = $fakeMetadata;
    			}

    			if (!$this->hasIndex($index->name)) {
    				$this->createIndex($class, $index);
    			}

    			$this->createType($class, $index);
		    }
		}
		return $aliases;
	}



	public function createAliases(array $aliases)
	{
		foreach ($aliases as $alias => $original) {
			try {
				$this->createAlias($alias, $original);
			} catch (ResponseException $e) {
			}
		}
	}



	public function hasIndex($index)
	{
		return $this->elastica->getIndex($index)->exists();
	}



	public function createIndex(ClassMetadata $class, IndexMetadata $indexMetadata)
	{
	    $index = $this->elastica->getIndex($indexMetadata->name);
		$response = $index->create(['settings' => [
		    'number_of_shards' => $indexMetadata->numberOfShards,
		    'number_of_replicas' => $indexMetadata->numberOfReplicas,
			'analysis' => [
			    'char_filter' => $indexMetadata->charFilter,
			    'analyzer' => $indexMetadata->analyzer,
			    'filter' => $indexMetadata->filter,
			    'tokenizer' => $indexMetadata->tokenizer,
			]],
		], TRUE);

		return $response;
	}



	public function dropIndex($index)
	{
		return $this->elastica->getIndex($index)->delete();
	}



	public function hasType(ClassMetadata $class, IndexMetadata $indexMetadata)
	{
		return $this->getType($class, $indexMetadata)->exists();
	}



	public function createType(ClassMetadata $class, IndexMetadata $indexMetadata)
	{
		$mapping = new ElasticaTypeMapping($this->getType($class, $indexMetadata), self::settingsToUnderscore($class->type->properties));
		$mapping->disableSource($class->type->source);

		if ($class->type->boost !== NULL) {
			$mapping->setParam('_boost', array('name' => '_boost', 'null_value' => $class->type->boost));
		}

		if ($class->parent !== NULL) {
			$mapping->setParent($class->parent);
		}

		foreach ($class->type->settings as $key => $value) {
			$mapping->setParam($key, $value);
		}

		return $mapping->send(['include_type_name' => true]);
	}



	public function dropType(ClassMetadata $class, IndexMetadata $indexMetadata)
	{
	    $type = $this->getType($class);
		return $type->delete();
	}



	public function createAlias($alias, $original)
	{
		try {
			$this->elastica->request(sprintf('_all/_alias/%s', $original), Request::DELETE);

		} catch (ResponseException $e) {
			if (stripos($e->getMessage(), 'AliasesMissingException') === FALSE) {
				throw $e;
			}
		}

		$this->elastica->request(sprintf('/%s/_alias/%s', $alias, $original), Request::PUT);
	}



	private static function settingsToUnderscore($properties)
	{
		foreach ($properties as $name => $settings) {
			$fixed = [];
			foreach ($settings as $key => $value) {
				$fixed[self::toUnderscore($key)] = $value;
			}

			if (isset($settings['properties'])) {
				$fixed['properties'] = self::settingsToUnderscore($settings['properties']);
			}

			$properties[$name] = $fixed;
		}

		return $properties;
	}



	/**
	 * camelCaseField name -> underscore_separated.
	 *
	 * @param string $s
	 * @return string
	 */
	private static function toUnderscore($s)
	{
		$s = preg_replace('#(.)(?=[A-Z])#', '$1_', $s);
		$s = strtolower($s);
		$s = rawurlencode($s);

		return $s;
	}

}
