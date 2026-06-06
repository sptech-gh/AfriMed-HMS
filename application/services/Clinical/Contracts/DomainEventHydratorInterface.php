<?php

interface DomainEventHydratorInterface
{
    public function hydrate(array $events): array;

    public function hydrateOne(array $event): array;
}
