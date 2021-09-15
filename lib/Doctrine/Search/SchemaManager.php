<?php

namespace Doctrine\Search;

use Doctrine\Search\Mapping\TypeMetadata;
use Elastica\Exception\ResponseException;
use Elastica\Request;
use Doctrine\Search\Mapping\ClassMetadata;
use Doctrine\Search\Mapping\IndexMetadata;
use Nette;



/**
 * @author Filip Procházka <filip@prochazka.su>
 */
interface SchemaManager
{

	/**
	 * @param array|ClassMetadata[] $classes
	 */
	public function dropMappings(array $classes);

	/**ClassMetadata $class, Index
	 * @param array|ClassMetadata[] $classes
	 * @param bool $withAliases
	 * @return array
	 */
	public function createMappings(array $classes, $withAliases = FALSE);

	/**
	 * @param array $aliases
	 * @return void
	 */
	public function createAliases(array $aliases);

	/**
	 * @param string $index
	 * @return boolean
	 */
	public function hasIndex($index);

	/**
	 * @param $indexMetadata $class
	 * @return boolean
	 */
	public function createIndex(ClassMetadata $class, IndexMetadata $indexMetadata);

	/**
	 * @param string $index
	 * @return boolean
	 */
	public function dropIndex($index);

	/**
	 * @param ClassMetadata $class
	 * @return boolean
	 */
	public function hasType(ClassMetadata $class, IndexMetadata $indexMetadata);

	/**
	 * @param ClassMetadata $class
	 * @return boolean
	 */
	public function createType(ClassMetadata $class, IndexMetadata $indexMetadata);

	/**
	 * @param ClassMetadata $class
	 * @return boolean
	 */
	public function dropType(ClassMetadata $class, IndexMetadata $indexMetadata);

	/**
	 * @param string $alias
	 * @param string $original
	 */
	public function createAlias($alias, $original);

}
