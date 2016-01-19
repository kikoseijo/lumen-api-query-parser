<?php

namespace QueryParser;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class ParserRequestAbstract
{
    /**
     * @ self::sort
     */
    const SORT_IDENTIFIER = 'sort';
    const SORT_DIRECTION_ASC = 'asc';
    const SORT_DIRECTION_DESC = 'desc';
    const SORT_DELIMITER = ',';
    const SORT_DESC_IDENTIFIER = '-';

    /**
     * @ self::filter
     */
    const FILTER_IDENTIFIER = 'filter';
    const FILTER_DELIMITER = ',';

    /**
     * @ self::column
     */
    const COLUMN_IDENTIFIER = 'columns';
    const COLUMN_DELIMITER = ',';

    /**
     * @var Request
     */
    protected $request;

    protected $model;

    protected $table;

    /**
     * @var array
     */
    protected $columnNames;

    protected $queryBuilder;

    protected $fieldErrors = [];

    /**
     * @param Request $request
     * @param $model
     * @param null $queryBuilder
     */
    public function __construct(Request $request, $model, $queryBuilder = null)
    {
        $this->request = $request;
        $this->model = $model;

        $this->table = $this->model->getTable();

        $this->queryBuilder = $queryBuilder ? $queryBuilder : DB::table($this->table);
        $this->setColumnsNames();
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    public function parser()
    {
        $data = $this->request->except('page');

        foreach ($data as $field => $value) {
            $field = $this->cleanField($field);
            $value = $this->cleanValue($value);

            if ($field == self::SORT_IDENTIFIER) {
                $this->addSort($value);
                continue;
            }

            if ($field == self::COLUMN_IDENTIFIER) {
                $this->addColumn($value);
                continue;
            }

            $this->addFilter($field, $value);

        }

        if (! empty($this->fieldErrors)) {
            throw new QueryParserException($this->fieldErrors);
        }

        return $this->queryBuilder;
    }

    /**
     * @param $field
     * @param $value
     * @throws \Exception
     */
    private function addFilter($field, $value)
    {
        $this->findErrors($field, self::FILTER_IDENTIFIER);
        $field = $this->addAliasField($field);

        $values = explode(self::FILTER_DELIMITER, $value);

        $this->queryBuilder->where(function ($query) use ($values, $field) {
            foreach ($values as $whereValue) {
                $whereValue = $this->cleanValue($whereValue);
                $query->orWhere($field, $whereValue);
            }
        });
    }

    /**
     * @param $value
     * @throws \Exception
     */
    private function addSort($value)
    {
        $fields = explode(self::SORT_DELIMITER, $value);

        foreach ($fields as $field) {
            $field = $this->cleanField($field);
            $direction = self::SORT_DIRECTION_ASC;

            if (substr($field, 0, 1) == self::SORT_DESC_IDENTIFIER) {
                $direction = self::SORT_DIRECTION_DESC;
                $field = str_replace(self::SORT_DESC_IDENTIFIER, '', $field);
            }

            $this->findErrors($field, self::SORT_IDENTIFIER);

            $fieldAlias = $this->addAliasField($field);
            $this->queryBuilder->orderBy($fieldAlias, $direction);
        }
    }

    /**
     * @param $value
     * @throws \Exception
     */
    private function addColumn($value)
    {
        $fields = explode(self::COLUMN_DELIMITER, $value);
        foreach ($fields as &$field) {
            $field = $this->cleanField($field);
            $this->findErrors($field, self::COLUMN_IDENTIFIER);
            $field = $this->addAliasField($field);
        }
        $this->queryBuilder->select($fields);
    }

    protected function findErrors($field, $type)
    {
        if (array_search($field, $this->columnNames) === false) {
            $this->fieldErrors[$type][] = 'Field ['.$field.'] not found';
        }
    }

    protected function cleanValue($string)
    {
        return trim($string);
    }

    protected function cleanField($string)
    {
        return strtolower(trim($string));
    }

    abstract protected function addAliasField($field);

    abstract protected function setColumnsNames();
}