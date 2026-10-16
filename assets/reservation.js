
/*
 * =========================================================
 * GESTION DES DISPONIBILITÉS ET DE LA RÉSERVATION
 * =========================================================
 *
 * Ce script permet :
 * 1. De sélectionner un jour de formation.
 * 2. De mémoriser le jour et la disponibilité choisis.
 * 3. D'afficher un récapitulatif de la sélection.
 * 4. D'afficher le bouton Réserver.
 * 5. D'envoyer les informations au contrôleur Symfony.
 *
 * Le script fonctionne que le fichier soit chargé avant
 * ou après le chargement du HTML.
 */

// Fonction principale qui initialise les disponibilités.
function initialiserDisponibilites() {

    /*
     * =====================================================
     * 1. RÉCUPÉRATION DES ÉLÉMENTS HTML
     * =====================================================
     */

    // Récupérer tous les boutons correspondant aux jours.
    const boutonsChoisir = document.querySelectorAll(
        '.disponibilite-jour'
    );

    // Récupérer le bloc qui contient le bouton Réserver.
    const reservationAction = document.getElementById(
        'reservation-action'
    );

    // Récupérer le bloc qui affiche le récapitulatif.
    const selection = document.getElementById(
        'disponibilite-selection'
    );

    // Récupérer la zone où sera affiché le jour choisi.
    const selectionText = document.getElementById(
        'disponibilite-selection-text'
    );

    // Récupérer le bouton Réserver.
    const boutonReserver = document.getElementById(
        'btn-reserver'
    );


    /*
     * =====================================================
     * 2. VÉRIFICATION DES ÉLÉMENTS
     * =====================================================
     */

    // Arrêter le script si les éléments nécessaires
    // ne sont pas présents dans la page.
    if (
        boutonsChoisir.length === 0 ||
        !reservationAction ||
        !selection ||
        !selectionText ||
        !boutonReserver
    ) {
        console.warn(
            'Les éléments de réservation sont introuvables.'
        );

        return;
    }


    /*
     * =====================================================
     * 3. VARIABLES DE SÉLECTION
     * =====================================================
     */

    // Contiendra l'identifiant de la disponibilité choisie.
    let disponibiliteSelectionnee = null;

    // Contiendra le jour sélectionné.
    let jourSelectionne = null;


    /*
     * =====================================================
     * 4. GESTION DU CHOIX DU JOUR
     * =====================================================
     */

    // Parcourir tous les boutons des jours disponibles.
    boutonsChoisir.forEach((bouton) => {

        // Détecter le clic sur un jour.
        bouton.addEventListener('click', () => {

            // Retirer la classe selected de tous les boutons.
            // Cela permet de n'avoir qu'un seul jour sélectionné.
            boutonsChoisir.forEach((item) => {
                item.classList.remove('selected');
            });


            // Ajouter la classe selected au bouton cliqué.
            // Le CSS peut ainsi mettre le jour en évidence.
            bouton.classList.add('selected');


            /*
             * Récupérer les données du bouton.
             */

            // Lire le jour défini dans data-jour.
            const jour = bouton.dataset.jour;

            // Lire l'identifiant défini dans
            // data-disponibilite-id.
            const disponibiliteId =
                bouton.dataset.disponibiliteId;

            // Récupérer les horaires affichés dans le bouton.
            const horaireElement = bouton.querySelector(
                '.disponibilite-jour-horaire'
            );

            // Lire le texte des horaires sans espaces inutiles.
            const horaire = horaireElement
                ? horaireElement.textContent.trim()
                : 'Horaire non renseigné';


            /*
             * Mémoriser le choix de l'utilisateur.
             */

            jourSelectionne = jour;

            disponibiliteSelectionnee = disponibiliteId;


            /*
             * Afficher le récapitulatif de la sélection.
             */

            // Utiliser textContent pour afficher le texte
            // sans interpréter de code HTML.
            selectionText.textContent =
                `${jour} — ${horaire}`;

            // Afficher le bloc récapitulatif.
            selection.style.display = 'block';

            // Afficher le bouton Réserver.
            reservationAction.style.display = 'block';


            /*
             * Afficher les informations dans la console
             * pendant les tests.
             */

            console.log(
                'Jour sélectionné :',
                jourSelectionne
            );

            console.log(
                'Disponibilité sélectionnée :',
                disponibiliteSelectionnee
            );

        });

    });


    /*
     * =====================================================
     * 5. GESTION DU BOUTON RÉSERVER
     * =====================================================
     */

    // Détecter le clic sur le bouton Réserver.
    boutonReserver.addEventListener('click', () => {

        // Vérifier qu'un jour a été sélectionné.
        if (!jourSelectionne) {

            alert(
                'Veuillez sélectionner un jour avant de réserver.'
            );

            return;
        }


        // Vérifier qu'une disponibilité a été sélectionnée.
        if (!disponibiliteSelectionnee) {

            alert(
                'Veuillez sélectionner une disponibilité.'
            );

            return;
        }


        /*
         * =================================================
         * 6. CRÉATION DU FORMULAIRE
         * =================================================
         */

        // Créer un formulaire HTML qui sera envoyé à Symfony.
        const form = document.createElement('form');

        // Utiliser POST pour transmettre les données.
        form.method = 'POST';

        // Définir l'URL du contrôleur de réservation.
        // Vérifie que cette route correspond bien à ton projet.
        form.action = '/reservation/create';


        /*
         * Ajouter l'identifiant de la disponibilité.
         */

        const inputDisponibilite =
            document.createElement('input');

        inputDisponibilite.type = 'hidden';

        inputDisponibilite.name = 'disponibilite';

        inputDisponibilite.value =
            disponibiliteSelectionnee;


        /*
         * Ajouter le jour sélectionné.
         */

        const inputJour =
            document.createElement('input');

        inputJour.type = 'hidden';

        inputJour.name = 'jour';

        inputJour.value = jourSelectionne;


        /*
         * Ajouter les champs au formulaire.
         */

        form.appendChild(inputDisponibilite);

        form.appendChild(inputJour);


        /*
         * Ajouter le formulaire à la page.
         */

        document.body.appendChild(form);


        /*
         * Afficher les données avant l'envoi,
         * pour faciliter le débogage.
         */

        console.log(
            'Envoi de la réservation :',
            {
                jour: jourSelectionne,
                disponibilite: disponibiliteSelectionnee
            }
        );


        /*
         * =================================================
         * 7. ENVOI VERS SYMFONY
         * =================================================
         */

        // Envoyer le formulaire au contrôleur Symfony.
        form.submit();

    });

}


/*
 * =========================================================
 * 8. INITIALISATION DU SCRIPT
 * =========================================================
 */

// Si le HTML est encore en cours de chargement,
// attendre l'événement DOMContentLoaded.
if (document.readyState === 'loading') {

    document.addEventListener(
        'DOMContentLoaded',
        initialiserDisponibilites
    );

// Si le HTML est déjà chargé, initialiser immédiatement.
} else {

    initialiserDisponibilites();

}
