<?php

namespace Flame;

use Flame\Grammar\Grammar;
use Flame\QueryBuilder\InsertQuery;
use Flame\QueryBuilder\SelectQuery;
use Flame\QueryBuilder\UpdateQuery;

/**
 * @author Gusakov Nikita <dev@nkt.me>
 */
class Connection extends \PDO
{
    const PLACEHOLDER_REGEX = '~([sbilfdt]{0,1}):(\w+)~';
    const PARAM_DATE_TIME = -1;
    const PARAM_TIME = -2;
    
    protected static $typeMap = [
        ''  => self::PARAM_STR, // string by default
        's' => self::PARAM_STR,
        'i' => self::PARAM_INT,
        'f' => self::PARAM_STR,
        'b' => self::PARAM_BOOL,
        'l' => self::PARAM_LOB,
        'd' => self::PARAM_DATE_TIME,
        't' => self::PARAM_TIME
    ];

    /**
     * @var array
     */
    private $placeholders;
    
    /**
     * @var array
     */
    private $types;
    
    /**
     * @var Grammar
     */
    protected $grammar;

    /**
     * @param string  $dsn
     * @param string  $username
     * @param string  $password
     * @param array   $attributes
     * @param Grammar $grammar
     */
    public function __construct($dsn, $username = null, $password = null, array $attributes = [], Grammar $grammar = null)
    {
        parent::__construct($dsn, $username, $password, array_replace($attributes, [
            self::ATTR_ERRMODE            => self::ERRMODE_EXCEPTION,
            self::ATTR_DEFAULT_FETCH_MODE => self::FETCH_ASSOC,
            self::ATTR_STATEMENT_CLASS    => ['Flame\\Statement', [&$this->grammar, &$this->placeholders, &$this->types]],
        ]));

        if ($grammar === null) {
            $this->grammar = new Grammar();
        } else {
            $this->grammar = $grammar;
        }
    }

    /**
     * @param string $sql
     * @param array  $driverOptions
     *
     * @return Statement
     */
    public function prepare($sql, $driverOptions = [])
    {
        $this->placeholders = $this->types = [];
        $sql = preg_replace_callback(static::PLACEHOLDER_REGEX, function ($matches) {
            $name = $matches[2];
            if (!isset($this->types[$name])) {
                $this->types[$name] = static::$typeMap[$matches[1]];
            }
            $this->placeholders[] = $name;

            return '?';
        }, $sql);

        return parent::prepare($sql, $driverOptions);
    }

    /**
     * @param string $sql
     * @param array  $parameters
     *
     * @return Statement
     */
    public function query($sql, array $parameters = [])
    {
        return $this->prepare($sql)->execute($parameters);
    }

    /**
     * @param string $id
     *
     * @return string
     */
    public function quoteId($id)
    {
        return $this->grammar->buildId($id);
    }

    /**
     * @return static
     */
    public function beginTransaction()
    {
        parent::beginTransaction();

        return $this;
    }

    /**
     * @return static
     */
    public function rollback()
    {
        parent::rollBack();

        return $this;
    }

    /**
     * @return static
     */
    public function commit()
    {
        parent::commit();

        return $this;
    }

    /**
     * @param int $mode
     *
     * @return static
     */
    public function setDefaultFetchMode($mode)
    {
        $this->setAttribute(self::ATTR_DEFAULT_FETCH_MODE, $mode);

        return $this;
    }

    /**
     * @param string $column,...
     *
     * @return SelectQuery
     */
    public function select($column = null)
    {
        return new SelectQuery($this->grammar, $column === null ? [] : func_get_args());
    }

    /**
     * @param string $table
     * @param array  $columns
     *
     * @return InsertQuery
     */
    public function insert($table, array $columns = [])
    {
        return new InsertQuery($this->grammar, $table, $columns);
    }

    /**
     * @param string $table
     * @param array  $columns
     *
     * @return UpdateQuery
     */
    public function update($table, array $columns = [])
    {
        return new UpdateQuery($this->grammar, $table, $columns);
    }

    /**
     * @see prepare
     */
    public function __invoke($sql, array $driverOptions = [])
    {
        return $this->prepare($sql, $driverOptions);
    }
}
