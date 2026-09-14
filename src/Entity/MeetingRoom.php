<?php

namespace App\Entity;

use App\Repository\MeetingRoomRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MeetingRoomRepository::class)]
class MeetingRoom
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $roomName = null;

    #[ORM\Column(length: 255)]
    private ?string $roomUrl = null;

    #[ORM\Column]
    private ?\DateTime $DateRoom = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $DateCreation = null;

    #[ORM\OneToOne(mappedBy: 'MeetingRoom', cascade: ['persist', 'remove'])]
    private ?Reservation $reservation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoomName(): ?string
    {
        return $this->roomName;
    }

    public function setRoomName(string $roomName): static
    {
        $this->roomName = $roomName;

        return $this;
    }

    public function getRoomUrl(): ?string
    {
        return $this->roomUrl;
    }

    public function setRoomUrl(string $roomUrl): static
    {
        $this->roomUrl = $roomUrl;

        return $this;
    }

    public function getDateRoom(): ?\DateTime
    {
        return $this->DateRoom;
    }

    public function setDateRoom(\DateTime $DateRoom): static
    {
        $this->DateRoom = $DateRoom;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->DateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $DateCreation): static
    {
        $this->DateCreation = $DateCreation;

        return $this;
    }

    public function getReservation(): ?Reservation
    {
        return $this->reservation;
    }

    public function setReservation(Reservation $reservation): static
    {
        // set the owning side of the relation if necessary
        if ($reservation->getMeetingRoom() !== $this) {
            $reservation->setMeetingRoom($this);
        }

        $this->reservation = $reservation;

        return $this;
    }
}
