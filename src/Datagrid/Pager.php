<?php

declare(strict_types=1);

/*
 * This file is part of the Sonata Project package.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\DoctrineORMAdminBundle\Datagrid;

use Doctrine\ORM\Query;
use Sonata\AdminBundle\Datagrid\Pager as BasePager;

/**
 * Doctrine pager class.
 *
 * @author Jonathan H. Wage <jonwage@gmail.com>
 */
class Pager extends BasePager
{
    /**
     * NEXT_MAJOR: remove this property.
     *
     * @deprecated since sonata-project/doctrine-orm-admin-bundle 2.4 and will be removed in 4.0
     */
    protected $queryBuilder = null;

    public function computeNbResult()
    {
        $countQuery = clone $this->getQuery();

        if (\count($this->getParameters()) > 0) {
            $countQuery->setParameters($this->getParameters());
        }

        /** @var Query\Expr\GroupBy[] $groupBys */
        $groupBys = $countQuery->getQueryBuilder()->getDQLPart('groupBy');
        if (!empty($groupBys)) {
            /** @var Query\Expr\Select[] $prevSelects */
            $prevSelects = $countQuery->getQueryBuilder()->getDQLPart('select');
            $aliases = [];
            foreach ($prevSelects as $prevSelect) {
                $selects = preg_split("/\s*,(?![^(]+\))\s*/", (string)$prevSelect);
                foreach ($selects as $select) {
                    if (preg_match('/\s+as\s+`?([^`]+)`?/i', $select, $matches)) {
                        $aliases[$matches[1]] = $select;
                    }
                }
            }
        }

        $countQuery->select(sprintf(
            'count(%s %s.%s) as _pager_cnt',
            $countQuery instanceof ProxyQuery && !$countQuery->isDistinct() ? null : 'DISTINCT',
            current($countQuery->getRootAliases()),
            current($this->getCountColumn())
        ));

        if (!empty($prevSelect)) {
            foreach ($groupBys as $groupBy) {
                $parts = preg_split("/\s*,(?![^(]+\))\s*/", (string)$groupBy);
                foreach ($parts as $part) {
                    if (isset($aliases[$part])) {
                        $countQuery->addSelect($aliases[$part]);
                    }
                }
            }
        }

        return array_sum(array_column(
            $countQuery->resetDQLPart('orderBy')->getQuery()->getResult(Query::HYDRATE_SCALAR),
            '_pager_cnt'
        ));
    }

    public function getResults($hydrationMode = Query::HYDRATE_OBJECT)
    {
        return $this->getQuery()->execute([], $hydrationMode);
    }

    public function getQuery()
    {
        return $this->query;
    }

    public function init()
    {
        $this->resetIterator();

        $this->setNbResults($this->computeNbResult());

        $this->getQuery()->setFirstResult(null);
        $this->getQuery()->setMaxResults(null);

        if (\count($this->getParameters()) > 0) {
            $this->getQuery()->setParameters($this->getParameters());
        }

        if (0 === $this->getPage() || 0 === $this->getMaxPerPage() || 0 === $this->getNbResults()) {
            $this->setLastPage(0);
        } else {
            $offset = ($this->getPage() - 1) * $this->getMaxPerPage();

            $this->setLastPage((int) ceil($this->getNbResults() / $this->getMaxPerPage()));

            $this->getQuery()->setFirstResult($offset);
            $this->getQuery()->setMaxResults($this->getMaxPerPage());
        }
    }
}
