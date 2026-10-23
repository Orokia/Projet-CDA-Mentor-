<?php

namespace App\Tests\Integration\Services;

use App\Entity\Disponibilite;
use App\Entity\Formateur;
use App\Entity\Formation;
use App\Entity\Reservation;
use App\Entity\Student;
use App\Repository\DisponibiliteRepository;
use App\Service\ReservationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;


final class ReservationServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ReservationService $reservationService;

    private Disponibilite $disponibilite;

    protected function setUp(): void
    {
         parent::setUp();
        self::bootKernel();

        $container = static::getContainer();



        $this->entityManager = $container->get(
            EntityManagerInterface::class
        );


        $this->reservationService = $container->get(
            ReservationService::class
        );
    }



    public function testServiceEstDisponibleDansLeConteneur(): void
    {
        self::assertInstanceOf(
            ReservationService::class,
            $this->reservationService
        );
    }

    public function testDisponibiliteIntrouvableDeclencheUneException(): void
    {

     $student = $this->createStub(Student::class);

     
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disponibilité introuvable.');
        // Vérifie que l'identifiant 999 n'existe pas en base.
        $disponibilite = $this -> reservationService -> creerReservations(
            $student ,
            999,
            'lundi'
        );

       
    }

     public function testCreerReservationsAucuneDateCorrespondante(): void
    {

        $disponibilite = new Disponibilite();
        $disponibilite->setId(2);


        $disponibilite->setDateDebut(new \DateTime('2026-10-20'));
        $disponibilite->setDateFin(new \DateTime('2026-10-25'));
        $disponibilite->setHeureDebut(new \DateTime('14:30:00'));
        $disponibilite->setHeureFin(new \DateTime('15:30:00'));
        $disponibilite->setJoursSemaine(['lundi', 'mardi']);
        $formateur = new Formateur();
        $formateur->setId(2);
        $formateur->setNom('Jeans');
        $formateur->setPrenom('Dupont');
        $formateur->setEmail('jeans@gmail.com');
        $formateur->setExperience('5ans');
        $formateur->setLocalisation('paris');
        $formateur->setLangue('Français');
        $formateur->setVideoPresentation('video');
        $this->entityManager->persist($formateur);
        $disponibilite->setFormateur( $formateur );

        $formation = new Formation();
        $formation->setId(2);

        $formation->setTitre('nodejs');
        $formation->setDuree(2);
        $formation->setDescription('cours');
        $formation->setPrix(500);
        $formation->setImage('image.png');
    
        $this->entityManager->persist($formation);
        $disponibilite->setFormation($formation);

        $student = new Student();
        $student->setNom('Alice');
         
        $this->entityManager->persist($disponibilite);
        $this->entityManager->flush();
        self::assertNotNull($disponibilite->getId());
            
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage(
            'Aucune séance ne correspond au jour choisi.'
        );

        $this->reservationService->creerReservations(
           $student,
             $disponibilite->getId(),
            'lundi'
        );
    }
  
}
