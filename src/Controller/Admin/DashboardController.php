<?php

namespace App\Controller\Admin;
use App\Controller\Admin\FormateurCrudController;
use App\Controller\Admin\UserCrudController;
use App\Entity\Paiement;
use App\Entity\Student;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
class DashboardController extends AbstractDashboardController
{
    public function index(): Response
    {
        // return parent::index();
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        return $this->redirect($adminUrlGenerator->setController(UserCrudController::class)->generateUrl());

        // Option 1. You can make your dashboard redirect to some common page of your backend
        //
        // return $this->redirectToRoute('admin_user_index');

        // Option 2. You can make your dashboard redirect to different pages depending on the user
        //
        // if ('jane' === $this->getUser()->getUsername()) {
        //     return $this->redirectToRoute('...');
        // }

        // Option 3. You can render some custom template to display a proper dashboard with widgets, etc.
        // (tip: it's easier if your template extends from @EasyAdmin/page/content.html.twig)
        //
        // return $this->render('some/path/my-dashboard.html.twig');
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Mentor');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::linkTo(UserCrudController::class, 'Utilisateurs', 'fas fa-users');
        yield MenuItem::linkTo(FormateurCrudController::class, 'Formateurs', 'fas fa-chalkboard-teacher');
        yield MenuItem::linkTo(StudentCrudController::class, 'Students', 'fas fa-user-graduate');
        yield MenuItem::linkTo(FormationCrudController::class, 'Formations', 'fas fa-graduation-cap');
        yield MenuItem::linkTo(SpecialiteCrudController::class, 'Specialité', 'fas fa-tags');
        yield MenuItem::linkTo(DisponibiliteCrudController::class, 'Disponibilité', 'fas fa-calendar-alt');
        yield MenuItem::linkTo(ReservationCrudController::class, 'Reservations', 'fas fa-calendar-check');
        yield MenuItem::linkTo(PaiementCrudController::class, 'Paiement', 'fas fa-credit-card');
        
    }
}
