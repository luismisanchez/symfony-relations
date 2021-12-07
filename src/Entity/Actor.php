<?php

namespace App\Entity;

use App\Repository\ActorsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ActorsRepository::class)
 */
class Actor
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $birthdate;

    /**
     * @ORM\Column(type="date", nullable=true)
     */
    private $died;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $born;

    /**
     * @ORM\ManyToMany(targetEntity=Film::class, mappedBy="actors", fetch="EXTRA_LAZY")
     */
    private $films;

    public function __construct()
    {
        $this->films = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getBirthdate(): ?\DateTimeInterface
    {
        return $this->birthdate;
    }

    public function setBirthdate(?\DateTimeInterface $birthdate): self
    {
        $this->birthdate = $birthdate;

        return $this;
    }

    public function getDied(): ?\DateTimeInterface
    {
        return $this->died;
    }

    public function setDied(?\DateTimeInterface $died): self
    {
        $this->died = $died;

        return $this;
    }

    public function getBorn(): ?string
    {
        return $this->born;
    }

    public function setBorn(?string $born): self
    {
        $this->born = $born;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getFilms(): Collection
    {
        return $this->films;
    }

    public function addFilm(Film $film): self
    {
        if (!$this->films->contains($film)) {
            $this->films[] = $film;
            $film->addActor($this);
        }

        return $this;
    }

    public function removeFilm(Film $film): self
    {
        if ($this->films->removeElement($film)) {
            $film->removeActor($this);
        }

        return $this;
    }
}
