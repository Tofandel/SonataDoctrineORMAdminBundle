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

namespace Sonata\DoctrineORMAdminBundle\Tests\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Sonata\DoctrineORMAdminBundle\Datagrid\ProxyQuery;

abstract class FilterTestCase extends TestCase
{
    /**
     * @param string[]           $expected
     * @param ProxyQuery<object> $proxyQuery
     */
    final protected function assertSameQuery(array $expected, ProxyQuery $proxyQuery): void
    {
        $queryBuilder = $proxyQuery->getQueryBuilder();
        if (!$queryBuilder instanceof TestQueryBuilder) {
            throw new \InvalidArgumentException('The query builder should be build with "createQueryBuilderStub()".');
        }

        static::assertSame($expected, $queryBuilder->query);
    }

    /**
     * @param mixed[]            $expected
     * @param ProxyQuery<object> $proxyQuery
     */
    final protected function assertSameQueryParameters(array $expected, ProxyQuery $proxyQuery): void
    {
        $queryBuilder = $proxyQuery->getQueryBuilder();
        if (!$queryBuilder instanceof TestQueryBuilder) {
            throw new \InvalidArgumentException('The query builder should be build with "createQueryBuilderStub()".');
        }

        static::assertSame($expected, $queryBuilder->queryParameters);
    }

    final protected function createQueryBuilderStub(): TestQueryBuilder
    {
        $queryBuilder = $this->createStub(TestQueryBuilder::class);

        $queryBuilder->method('getEntityManager')->willReturnCallback(
            fn (): EntityManagerInterface => $this->createEntityManagerStub()
        );

        $queryBuilder->method('setParameter')->willReturnCallback(
            static function (string $name, mixed $value) use ($queryBuilder): void {
                $queryBuilder->queryParameters[$name] = $value;
            }
        );

        $queryBuilder->method('andWhere')->willReturnCallback(
            static function (mixed $query) use ($queryBuilder): void {
                $queryBuilder->query[] = sprintf('WHERE %s', $query);
            }
        );

        $queryBuilder->method('andHaving')->willReturnCallback(
            static function (mixed $query) use ($queryBuilder): void {
                $queryBuilder->query[] = sprintf('HAVING %s', $query);
            }
        );

        $queryBuilder->method('addGroupBy')->willReturnCallback(
            static function (string $groupBy) use ($queryBuilder): void {
                $queryBuilder->query[] = sprintf('GROUP BY %s', $groupBy);
            }
        );

        $queryBuilder->method('expr')->willReturnCallback(
            fn (): Expr => $this->createExprStub()
        );

        $queryBuilder->method('getRootAliases')->willReturnCallback(
            static fn (): array => ['o']
        );

        $queryBuilder->method('getDQLPart')->willReturnCallback(
            static fn (): array => []
        );

        $queryBuilder->method('leftJoin')->willReturnCallback(
            static function (string $parameter, string $alias) use ($queryBuilder): void {
                $queryBuilder->query[] = sprintf('LEFT JOIN %s AS %s', $parameter, $alias);
            }
        );

        return $queryBuilder;
    }

    private function createEntityManagerStub(): EntityManagerInterface
    {
        $classMetadata = $this->createStub(ClassMetadata::class);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $classMetadata->method('getIdentifierValues')->willReturnCallback(
            static fn (mixed $value): array => ['id' => $value]
        );

        $entityManager->method('getClassMetadata')->willReturnCallback(
            static fn (string $class): ClassMetadata => $classMetadata
        );

        return $entityManager;
    }

    private function createExprStub(): Expr
    {
        $expr = $this->createStub(Expr::class);

        $expr->method('orX')->willReturnCallback(
            static fn (): Orx => new Orx(\func_get_args())
        );

        $expr->method('andX')->willReturnCallback(
            static fn (): Andx => new Andx(\func_get_args())
        );

        $expr->method('in')->willReturnCallback(
            static function (string $alias, mixed $parameter): string {
                if (\is_array($parameter)) {
                    return sprintf('%s IN ("%s")', $alias, implode(', ', $parameter));
                }

                return sprintf('%s IN %s', $alias, $parameter);
            }
        );

        $expr->method('notIn')->willReturnCallback(
            static function (string $alias, mixed $parameter): string {
                if (\is_array($parameter)) {
                    return sprintf('%s NOT IN ("%s")', $alias, implode(', ', $parameter));
                }

                return sprintf('%s NOT IN %s', $alias, $parameter);
            }
        );

        $expr->method('isNull')->willReturnCallback(
            static fn (string $queryPart): string => $queryPart.' IS NULL'
        );

        $expr->method('isNotNull')->willReturnCallback(
            static fn (string $queryPart): string => $queryPart.' IS NOT NULL'
        );

        $expr->method('eq')->willReturnCallback(
            static fn (string $alias, mixed $parameter): string => sprintf('%s = %s', $alias, $parameter)
        );

        $expr->method('not')->willReturnCallback(
            static fn (mixed $restriction): string => sprintf('NOT (%s)', $restriction)
        );

        return $expr;
    }
}

class TestQueryBuilder extends QueryBuilder
{
    /** @var string[] */
    public $query = [];

    /** @var mixed[] */
    public $queryParameters = [];
}
