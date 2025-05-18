<?php

namespace App\Entity;

use App\Repository\CommandeLivresRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;

#[ORM\Entity(repositoryClass: CommandeLivresRepository::class)]
class CommandeLivres
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commandeLivres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Commandes $commande = null;

    #[ORM\ManyToOne(inversedBy: 'commandeLivres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Livres $livre = null;

    #[ORM\Column]
    private ?int $quantite = 1;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?float $price = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commandes
    {
        return $this->commande;
    }

    public function setCommande(?Commandes $commande): static
    {
        $this->commande = $commande;
        return $this;
    }

    public function getLivre(): ?Livres
    {
        return $this->livre;
    }

    public function setLivre(?Livres $livre): static
    {
        $this->livre = $livre;
        return $this;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(float $price): static
    {
        $this->price = $price;
        return $this;
    }

    /**
     * Calcule le montant total de la ligne (prix * quantité)
     *
     * @return float|null
     */
    public function getMontantTotal(): ?float
    {
        if ($this->price === null || $this->quantite === null) {
            return null;
        }

        return $this->price * $this->quantite;
    }
}