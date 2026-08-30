<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Script;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Script> */
final class ScriptRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Script::class);
    }

    /** @return list<Script> */
    public function findForIndex(): array
    {
        return $this->createQueryBuilder('script')->orderBy('script.title', 'ASC')->getQuery()->getResult();
    }

    public function findWithElements(string $id): ?Script
    {
        return $this->createQueryBuilder('script')
            ->addSelect('scene', 'element', 'character', 'scriptCharacter')
            ->leftJoin('script.scenes', 'scene')
            ->leftJoin('scene.elements', 'element')
            ->leftJoin('element.character', 'character')
            ->leftJoin('script.characters', 'scriptCharacter')
            ->andWhere('script.id = :id')
            ->setParameter('id', $id)
            ->orderBy('scene.sequence', 'ASC')
            ->addOrderBy('element.sequence', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();
    }
}
