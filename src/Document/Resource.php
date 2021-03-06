<?php

namespace Jad\Document;

use Jad\Exceptions\MappingException;
use Jad\Serializers\RelationshipSerializer;
use Jad\Serializers\Serializer;
use Jad\Common\Text;
use Jad\Common\ClassHelper;
use Doctrine\Common\Collections\Collection;

/**
 * Class Resource
 * @package Klirr\JsonApi\Response
 */
class Resource implements \JsonSerializable
{
    /**
     * @var
     */
    private $entity;

    /**
     * @var Serializer $serializer
     */
    private $serializer;

    /**
     * @var null
     */
    private $fields = null;

    /**
     * @var null
     */
    private $included = null;

    /**
     * @var null
     */
    private $includedParams = null;

    /**
     * Resource constructor.
     * @param $entity
     * @param Serializer $serializer
     */
    public function __construct($entity, Serializer $serializer)
    {
        $this->entity = $entity;
        $this->serializer = $serializer;
    }

    /**
     * @return mixed
     */
    public function getEntity()
    {
        return $this->entity;
    }

    /**
     * @param $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * @param $included
     */
    public function setIncluded($included)
    {
        $this->included = $included;
    }

    /**
     * @return bool
     */
    public function hasIncluded()
    {
        return !empty($this->includedParams);
    }

    /**
     * @param null $includedParams
     */
    public function setIncludedParams($includedParams)
    {
        $this->includedParams = $includedParams;
    }

    /**
     * @return \stdClass
     */
    public function jsonSerialize()
    {
        $resource = new \stdClass();
        $included = null;

        $entity = $this->entity;
        $type = $this->serializer->getType($entity);

        $fields = null;

        if(is_array($this->fields)) {
            $fields = array_key_exists($type, $this->fields) ? $this->fields[$type] : null;
        }

        $resource->id = $this->serializer->getId($entity);
        $resource->type = $type;

        if($this->serializer instanceof RelationshipSerializer) {
            $relationship = $this->serializer->getRelationship();

            if($relationship['view'] !== 'list') {
                $resource->attributes = $this->serializer->getAttributes($entity, $fields);
            }
        } else {

            $resource->attributes = $this->serializer->getAttributes($entity, $fields);

            $relationships = $this->serializer->getRelationships($entity);

            if(!empty($relationships)) {
                $resource->relationships = $relationships;
            }
        }

        return $resource;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getIncluded()
    {
        $included = [];

        foreach ($this->includedParams as $includes) {
            foreach ($includes as $includedType => $relation) {
                if (empty($relation)) {
                    $include = $this->serializer->getIncluded($includedType, $this->entity, $this->fields);

                    if(!is_array($include)) {
                        throw new MappingException('Included type [' . $includedType . '] not available, check if resource type is mapped correctly.');
                    }

                    $included = array_merge($included, $include);
                } else {
                    $path = explode('.', $relation);
                    array_unshift($path, $includedType);
                    $result = $this->crawlRelations($this->entity, $path);
                    $include = $this->serializer->getIncludedResources($result['type'], $result['collection']);
                    $included = array_merge($included, $include);
                }
            }
        }

        return $included;
    }

    /**
     * Crawl entities
     *
     * @param $entity
     * @param $relations
     * @return array
     * @throws \Jad\Exceptions\JadException
     */
    public function crawlRelations($entity, $relations)
    {
        $collection = array($entity);
        $type = end($relations);

        while($relation = array_shift($relations)) {
            $newCollection = [];
            $property = Text::deKebabify($relation);

            foreach($collection as $entity) {
                $result = ClassHelper::getPropertyValue($entity, $property);
                if($result instanceof Collection) {
                    $newCollection = array_merge($newCollection, $result->toArray());
                } else {
                    $newCollection =  array_merge($newCollection, array($result));
                }
            }

            $collection = $newCollection;
        }

        return array('type' => $type, 'collection' => $collection);
    }

}