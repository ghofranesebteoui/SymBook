<?php

namespace App\Entity;

use App\Repository\CommandesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandesRepository::class)]
class Commandes
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'commandes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column]
    private ?float $montantTotal = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;

    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: CommandeLivres::class, cascade: ['persist', 'remove'])]
    private Collection $commandeLivres;

    public function __construct()
    {
        $this->commandeLivres = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getDateCommande(): ?\DateTimeInterface
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTimeInterface $dateCommande): static
    {
        $this->dateCommande = $dateCommande;
        return $this;
    }

    public function getMontantTotal(): ?float
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(float $montantTotal): static
    {
        $this->montantTotal = $montantTotal;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    /**
     * @return Collection<int, CommandeLivres>
     */
    public function getCommandeLivres(): Collection
    {
        return $this->commandeLivres;
    }

    public function addCommandeLivre(CommandeLivres $commandeLivre): static
    {
        if (!$this->commandeLivres->contains($commandeLivre)) {
            $this->commandeLivres->add($commandeLivre);
            $commandeLivre->setCommande($this);
        }
        return $this;
    }

    public function removeCommandeLivre(CommandeLivres $commandeLivre): static
    {
        if ($this->commandeLivres->removeElement($commandeLivre)) {
            if ($commandeLivre->getCommande() === $this) {
                $commandeLivre->setCommande(null);
            }
        }
        return $this;
    }
}