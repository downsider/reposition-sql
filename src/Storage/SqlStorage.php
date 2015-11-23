<?php

namespace Silktide\Reposition\Sql\Storage;


use Silktide\Reposition\Hydrator\HydratorInterface;
use Silktide\Reposition\Metadata\EntityMetadataProviderInterface;
use Silktide\Reposition\Normaliser\NormaliserInterface;
use Silktide\Reposition\QueryBuilder\TokenSequencerInterface;
use Silktide\Reposition\Sql\QueryInterpreter\SqlQueryInterpreter;
use Silktide\Reposition\Storage\StorageInterface;

class SqlStorage implements StorageInterface
{

    protected $database;
    protected $interpreter;
    protected $entityMetadataProvider;
    protected $hydrator;

    protected $parameters = [];

    public function __construct(
        PdoAdapter $database,
        SqlQueryInterpreter $interpreter,
        HydratorInterface $hydrator = null,
        NormaliserInterface $normaliser = null
    ) {
        $this->database = $database;
        $this->interpreter = $interpreter;
        $this->hydrator = $hydrator;

        if (!empty($normaliser)) {
            $this->interpreter->setNormaliser($normaliser);
            if (!empty($hydrator)) {
                $this->hydrator->setNormaliser($normaliser);
            }
        }

    }

    public function setEntityMetadataProvider(EntityMetadataProviderInterface $provider)
    {
        $this->entityMetadataProvider = $provider;
        $this->interpreter->setEntityMetadataProvider($this->entityMetadataProvider);
    }

    public function hasEntityMetadataProvider()
    {
        return !empty($this->entityMetadataProvider);
    }

    /**
     * @param TokenSequencerInterface $query
     * @param string $entityClass
     * @return object
     */
    public function query(TokenSequencerInterface $query, $entityClass)
    {
        $compiledQuery = $this->interpreter->interpret($query);

        $statement = $this->database->prepare($compiledQuery->getQuery());
        $statement->execute($compiledQuery->getArguments());

        // check for errors (some drivers don't throw exceptions on SQL errors)
        $errorInfo = $statement->errorInfo();
        if ($errorInfo[0] != "00000") { // ANSI SQL error code for "success"
            throw new \PDOException($errorInfo[0] . " (" . $errorInfo[1] . "): " . $errorInfo[2]);
        }

        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);

        $options = [
            "metadataProvider" => $this->entityMetadataProvider,
            "entityMap" => $query->getIncludes(),
            "entity" => $entityClass
        ];

        if ($this->hydrator instanceof HydratorInterface && !empty($entityClass)) {
            $response = $this->hydrator->hydrateAll($data, $entityClass, $options);
        } else  {
            $response = $data;
        }
        return $response;
    }

}