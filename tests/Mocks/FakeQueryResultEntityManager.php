<?php

namespace Tests\Mocks;

class FakeQueryResultEntityManager
{
    public function __construct(private mixed $result) {}

    public function createQueryBuilder(): FakeQueryResultQueryBuilder
    {
        return new FakeQueryResultQueryBuilder($this->result);
    }
}

class FakeQueryResultQueryBuilder
{
    public function __construct(private mixed $result) {}

    public function from($entity, $alias): self
    {
        return $this;
    }

    public function select($select): self
    {
        return $this;
    }

    public function where($where): self
    {
        return $this;
    }

    public function andWhere($where): self
    {
        return $this;
    }

    public function setParameter($name, $value): self
    {
        return $this;
    }

    public function getQuery(): self
    {
        return $this;
    }

    public function getOneOrNullResult(): mixed
    {
        return $this->result;
    }

    public function getArrayResult(): array
    {
        return $this->result;
    }
}
