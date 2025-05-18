<?php
// src/Repository/CommandeLivresRepository.php

namespace App\Repository;

use App\Entity\CommandeLivres;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandeLivres>
 */
class CommandeLivresRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandeLivres::class);
    }

    // Ajoutez vos méthodes personnalisées ici
}