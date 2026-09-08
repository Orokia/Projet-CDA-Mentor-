<?php

namespace App\Service;

use App\Entity\Meeting;
use App\Entity\MeetingRoom;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MeetingService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function createMeeting(): MeetingRoom
    {
        // Génération du nom de salle
        $roomName = 'mentor-' . bin2hex(random_bytes(8));

        // Création du Meeting
        $meeting = new MeetingRoom();

        $meeting->setRoomName($roomName);
        
        $meeting->setDateCreation(new \DateTimeImmutable());
        $meeting->setDateRoom(new \DateTime());

        // Génération de l'URL de ton application
        $meetingUrl = $this->urlGenerator->generate(
            'app_meeting',
            [
                'room' => $roomName
            ],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        $meeting->setroomUrl($meetingUrl);

        $this->entityManager->persist($meeting);
        $this->entityManager->flush();
        

        return $meeting;
    }
}