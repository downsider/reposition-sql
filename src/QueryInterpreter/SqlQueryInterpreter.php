<?php

namespace Lexide\Reposition\Sql\QueryInterpreter;

use Lexide\Reposition\Exception\QueryException;
use Lexide\Reposition\Exception\InterpretationException;
use Lexide\Reposition\Metadata\EntityMetadataProviderInterface;
use Lexide\Reposition\Normaliser\NormaliserInterface;
use Lexide\Reposition\QueryBuilder\TokenSequencerInterface;
use Lexide\Reposition\QueryBuilder\TokenParser;
use Lexide\Reposition\QueryBuilder\QueryToken\Token;
use Lexide\Reposition\QueryBuilder\QueryToken\Value;
use Lexide\Reposition\QueryBuilder\QueryToken\Reference;
use Lexide\Reposition\QueryBuilder\QueryToken\Entity;
use Lexide\Reposition\QueryInterpreter\CompiledQuery;
use Lexide\Reposition\QueryInterpreter\QueryInterpreterInterface;
use Lexide\Reposition\Sql\QueryInterpreter\Type\AbstractSqlQueryTypeInterpreter;

class SqlQueryInterpreter implements QueryInterpreterInterface
{

    /**
     * @var NormaliserInterface
     */
    protected $normaliser;

    /**
     * @var EntityMetadataProviderInterface
     */
    protected $metadataProvider;

    /**
     * @var TokenParser
     */
    protected $tokenParser;

    /**
     * @var array
     */
    protected $queryTypeInterpreters;

    protected $fields = [];

    protected $identifierDelimiter = "";

    /**
     * Switch between PDO style substitution and mysqli escaping
     *
     * @var bool
     */
    protected $useSubstitution = true;

    public function __construct(TokenParser $parser, array $queryTypeInterpreters, $identifierDelimiter)
    {
        $this->tokenParser = $parser;
        $this->identifierDelimiter = $identifierDelimiter;
        $this->setQueryTypeInterpreters($queryTypeInterpreters);
    }

    /**
     * {@inheritDoc}
     */
    public function setNormaliser(NormaliserInterface $normaliser)
    {
        $this->normaliser = $normaliser;
    }

    /**
     * {@inheritDoc}
     */
    public function setEntityMetadataProvider(EntityMetadataProviderInterface $provider)
    {
        $this->metadataProvider = $provider;
    }

    public function setQueryTypeInterpreters(array $interpreters)
    {
        $this->queryTypeInterpreters = [];
        foreach ($interpreters as $interpreter) {
            if ($interpreter instanceof AbstractSqlQueryTypeInterpreter) {
                $interpreter->setIdentifiedDelimiter($this->identifierDelimiter);
                $this->addQueryTypeInterpreter($interpreter);
            }
        }
    }

    public function addQueryTypeInterpreter(AbstractSqlQueryTypeInterpreter $interpreter)
    {
        $this->queryTypeInterpreters[] = $interpreter;
    }

    /**
     * @param TokenSequencerInterface $query
     *
     * @throws InterpretationException
     * @return CompiledQuery
     */
    public function interpret(TokenSequencerInterface $query)
    {
        if (empty($this->metadataProvider)) {
            throw new InterpretationException("Cannot interpret any queries without an Entity Metadata Provider");
        }

        $this->tokenParser->parseTokenSequence($query);

        // select interpreter
        $selectedInterpreter = null;
        $queryType = $query->getType();
        foreach ($this->queryTypeInterpreters as $interpreter) {
            /** @var AbstractSqlQueryTypeInterpreter $interpreter */
            if ($interpreter->supportedQueryType() == $queryType) {
                $selectedInterpreter = $interpreter;
                break;
            }
        }
        if (empty($selectedInterpreter)) {
            throw new InterpretationException("Cannot interpret query. The query type '$queryType' is not supported by any of the installed QueryTypeInterpreters");
        }

        $compiledQuery = new CompiledQuery();
        $compiledQuery->setQuery($selectedInterpreter->interpretQuery($query));
        $compiledQuery->setArguments($selectedInterpreter->getValues());
        $compiledQuery->setPrimaryKeySequence($selectedInterpreter->getPrimaryKeySequence());

        return $compiledQuery;
    }

} 
