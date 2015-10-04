<?php

namespace Silktide\Reposition\Sql\Storage;


use Silktide\Reposition\Hydrator\HydratorInterface;
use Silktide\Reposition\Normaliser\NormaliserInterface;
use Silktide\Reposition\Query\Query;
use Silktide\Reposition\QueryBuilder\QueryBuilderInterface;
use Silktide\Reposition\QueryBuilder\TokenSequencerInterface;
use Silktide\Reposition\Sql\QueryInterpreter\SqlQueryInterpreter;
use Silktide\Reposition\Storage\StorageInterface;

class SqlStorage implements StorageInterface
{

    protected $database;
    protected $builder;
    protected $interpreter;
    protected $hydrator;

    protected $parameters = [];

    public function __construct(
        PdoAdapter $database,
        QueryBuilderInterface $builder,
        SqlQueryInterpreter $interpreter,
        HydratorInterface $hydrator = null,
        NormaliserInterface $normaliser = null
    ) {
        $this->database = $database;
        $this->builder = $builder;
        $this->interpreter = $interpreter;
        $this->hydrator = $hydrator;

        if (!empty($normaliser)) {
            $this->interpreter->setNormaliser($normaliser);
            if (!empty($hydrator)) {
                $this->hydrator->setNormaliser($normaliser);
            }
        }

    }

    /**
     * @return QueryBuilderInterface
     */
    public function getQueryBuilder()
    {
        return $this->builder;
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
        $data = $statement->fetchAll(\PDO::FETCH_ASSOC);



        if ($this->hydrator instanceof HydratorInterface && !empty($entityClass)) {
            $response = $this->hydrator->hydrateAll($data, $entityClass);
        } else  {
            $response = $data;
        }
        return $response;
    }

}