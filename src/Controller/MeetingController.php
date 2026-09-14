<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class MeetingController extends AbstractController
{
    #[Route('/meeting/{room}', name: 'app_meeting')]
    public function index(string $room): Response
    {
        return $this->render('meeting/index.html.twig', [
            'room' => $room,
            'username' => 'Adama'
        ]);
    }
}