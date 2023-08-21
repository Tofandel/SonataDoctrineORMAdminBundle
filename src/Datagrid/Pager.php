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
use Doctrine\ORM\Query\ResultSetMapping;
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
        $originalQuery = clone $this->getQuery();
        $originalQuery->resetDQLPart('orderBy');

        if (\count($this->getParameters()) > 0) {
            $originalQuery->setParameters($this->getParameters());
        }

        /** @var Query\Parameter[] $params */
        $params = $originalQuery->getParameters()->toArray();
        $sdql = preg_replace_callback('/\?/', function () use ($params) {
            return ':'.array_shift( $params)->getName();
        }, $originalQuery->getQuery()->getSQL());

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('cnt', 'cnt', 'bigint');
        $countQuery = $originalQuery->getEntityManager()
            ->createNativeQuery('SELECT count(*) as cnt FROM (' . $sdql . ') grp', $rsm);

        $countQuery->setParameters($originalQuery->getParameters());

        return $countQuery->getSingleScalarResult();
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
