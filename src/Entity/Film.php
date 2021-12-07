<?php

namespace App\Entity;

use App\Repository\FilmsRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=FilmsRepository::class)
 * @ORM\Table(name="film",indexes={
 *     @ORM\Index(name="search_idx",columns={"title","genre","production_company"}),
 *     @ORM\Index(name="find_one_idx",columns={"imdb_title_id"})
 * })
 */
class Film
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
    private $imdbTitleId;

    /**
     * @ORM\Column(type="string", length=255)
     */
    private $title;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    private $datePublished;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $genre;

    /**
     * @ORM\Column(type="smallint", nullable=true)
     */
    private $duration;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     */
    private $productionCompany;

    /**
     * @ORM\ManyToMany(targetEntity=Actor::class, inversedBy="films", fetch="EXTRA_LAZY")
     */
    private $actors;

    /**
     * @ORM\ManyToMany(targetEntity=Director::class, inversedBy="films", fetch="EXTRA_LAZY")
     */
    private $director;

    public function __construct()
    {
        $this->actors = new ArrayCollection();
        $this->director = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDatePublished(): ?string
    {
        return $this->datePublished;
    }

    public function setDatePublished(?string $datePublished): self
    {

        // datePublished could be passed as year
        $dateTime = date_create_from_format('Y', $datePublished);
        if (!$dateTime) {
            $timestamp = strtotime($datePublished);
        } else {
            $timestamp = $dateTime->getTimestamp();
        }
        $this->datePublished = (int)$timestamp;

        return $this;
    }

    public function getGenre(): ?string
    {
        return $this->genre;
    }

    public function setGenre(?string $genre): self
    {
        $this->genre = $genre;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getProductionCompany(): ?string
    {
        return $this->productionCompany;
    }

    public function setProductionCompany(?string $productionCompany): self
    {
        $this->productionCompany = $productionCompany;

        return $this;
    }

    /**
     * @return Collection
     */
    public function getActors(): Collection
    {
        return $this->actors;
    }

    public function addActor(Actor $actor): self
    {
        if (!$this->actors->contains($actor)) {
            $this->actors[] = $actor;
        }

        return $this;
    }

    public function removeActor(Actor $actor): self
    {
        $this->actors->removeElement($actor);

        return $this;
    }

    /**
     * @return Collection
     */
    public function getDirector(): Collection
    {
        return $this->director;
    }

    public function addDirector(Director $director): self
    {
        if (!$this->director->contains($director)) {
            $this->director[] = $director;
        }

        return $this;
    }

    public function removeDirector(Director $director): self
    {
        $this->director->removeElement($director);

        return $this;
    }

    public function getImdbTitleId(): ?string
    {
        return $this->imdbTitleId;
    }

    public function setImdbTitleId(string $imdbTitleId): self
    {
        $this->imdbTitleId = $imdbTitleId;

        return $this;
    }

}
