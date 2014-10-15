<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Model\ModelTrait;

use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\ModelTrait\BaseTrait;
use PommProject\ModelManager\Model\Model;
use PommProject\Foundation\Where;

/**
 * ReadQueries
 *
 * Basic read queries for model instances.
 *
 * @package ModelManager
 * @copyright 2014 Grégoire HUBERT
 * @author Grégoire HUBERT
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 */
trait ReadQueries
{
    use BaseTrait;

    /**
     * findAll
     *
     * Return all elements from a relation. If a suffix is given, it is append
     * to the query. This is mainly useful for "order by" statements.
     * NOTE: suffix is inserted as is with NO ESCAPING. DO NOT use it to place
     * "where" condition nor any untrusted params.
     *
     * @access public
     * @param  string     $suffix
     * @return CollectionIterator
     */
    public function findAll($suffix = null)
    {
        $sql = strtr(
            "select :fields from :table :suffix",
            [
                ':fields' => $this->createProjection()->formatFields(),
                ':table'  => $this->getStructure()->getRelation(),
                ':suffix' => $suffix,
            ]
        );

        return $this->query($sql);
    }

    /**
     * findWhere
     *
     * Perform a simple select on a given condition
     * NOTE: suffix is inserted as is with NO ESCAPING. DO NOT use it to place
     * "where" condition nor any untrusted params.
     *
     * @access public
     * @param  mixed      $where
     * @param  array      $values
     * @param  string     $suffix order by, limit, etc.
     * @return CollectionIterator
     */
    public function findWhere($where, array $values = [], $suffix = '')
    {
        if ($where instanceof Where) {
            $values = $where->getValues();
        }

        $sql = strtr(
            "select :fields from :table where :condition :suffix",
            [
                ':fields'    => $this->createProjection()->formatFieldsWithFieldAlias(),
                ':table'     => $this->getStructure()->getRelation(),
                ':condition' => (string) $where,
                ':suffix'    => $suffix,
            ]
        );

        return $this->query($sql, $values);
    }

    /**
     * findByPK
     *
     * Return an entity upon its primary key. If no entities are found, null is
     * returned.
     *
     * @access public
     * @param  array $primary_key
     * @return FlexibleEntity
     */
    public function findByPK(array $primary_key)
    {
        $where = $this
            ->checkPrimaryKey($primary_key)
            ->getWhereFrom($primary_key)
            ;

        $iterator = $this->findWhere($where);

        return $iterator->isEmpty() ? null : $iterator->current();
    }

    /**
     * countWhere
     *
     * Return the number of records matching a condition.
     *
     * @access public
     * @param  string|Where $where
     * @param  array $values
     * @return int
     */
    public function countWhere($where, array $values = [])
    {
        if ($where instanceof Where) {
            $values = $where->getValues();
        }

        $sql = sprintf("select count(*) as count from :table where %s", (string) $where);
        $sql = strtr($sql, [
            ':table' => $this->getStructure()->getRelation(),
            ]);

        return (int) $this
            ->session
            ->getClientUsingPooler('prepared_query', $sql)
            ->execute($values)
            ->fetchColumn('count')[0];
    }

    /**
     * checkPrimaryKey
     *
     * Check if the given values fully describe a primary key. Throw a
     * ModelException if not.
     *
     * @access private
     * @param  array $values
     * @return Model $this
     */
    private function checkPrimaryKey(array $values)
    {
        foreach($this->getStructure()->getPrimaryKey() as $key) {
            if (!isset($values[$key])) {
                throw new ModelException(
                    sprintf(
                        "Key '%s' is missing to fully describes the primary key {%s}.",
                        $key,
                        join(', ', $this->getStructure()->getPrimaryKey())
                    )
                );
            }
        }

        return $this;
    }

    /**
     * getWhereFrom
     *
     * Build a condition on given values.
     *
     * @access protected
     * @param  array $values
     * @return Where
     */
    protected function getWhereFrom(array $values)
    {
        $where = new Where();

        foreach($values as $field => $value) {
            $where->andWhere(sprintf("%s = $*", $this->escapeIdentifier($field)), [$value]);
        }

        return $where;
    }
}