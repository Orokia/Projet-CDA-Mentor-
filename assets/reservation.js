document.addEventListener('DOMContentLoaded', () => {

    const boutonsChoisir = document.querySelectorAll('.disponibilite-jour');
    const reservationAction = document.getElementById('reservation-action');
    const selection = document.getElementById('disponibilite-selection');
    const selectionText = document.getElementById('disponibilite-selection-text');
    const boutonReserver = document.getElementById('btn-reserver');

    if (
        !boutonsChoisir.length ||
        !reservationAction ||
        !selection ||
        !selectionText ||
        !boutonReserver
    ) {
        return;
    }

    let disponibiliteSelectionnee = null;
    let jourSelectionne = null;


    /*
     * =========================================================
     * CHOIX DU JOUR
     * =========================================================
     */

    boutonsChoisir.forEach((bouton) => {

        bouton.addEventListener('click', () => {

            // Retirer la sélection précédente
            boutonsChoisir.forEach((item) => {
                item.classList.remove('selected');
            });

            // Sélectionner le bouton actuel
            bouton.classList.add('selected');


            // Récupération des informations
            const jour = bouton.dataset.jour;
            const disponibiliteId = bouton.dataset.disponibiliteId;

            const horaire = bouton
                .querySelector('.disponibilite-jour-horaire')
                ?.textContent
                .trim();


            // Mémorisation
            jourSelectionne = jour;
            disponibiliteSelectionnee = disponibiliteId;


            // Affichage de la sélection
            selectionText.textContent = `${jour} — ${horaire}`;

            selection.style.display = 'block';

            reservationAction.style.display = 'block';


            // Debug temporaire
            console.log('Jour sélectionné :', jourSelectionne);
            console.log(
                'Disponibilité sélectionnée :',
                disponibiliteSelectionnee
            );
        });
    });


    /*
     * =========================================================
     * BOUTON RÉSERVER
     * =========================================================
     */

    boutonReserver.addEventListener('click', () => {

        console.log('Réservation...');
        console.log('Jour :', jourSelectionne);
        console.log(
            'Disponibilité :',
            disponibiliteSelectionnee
        );


        /*
         * Vérification du jour
         */

        if (!jourSelectionne) {

            alert(
                'Veuillez sélectionner un jour avant de réserver.'
            );

            return;
        }


        /*
         * Vérification de la disponibilité
         */

        if (!disponibiliteSelectionnee) {

            alert(
                'Veuillez sélectionner une disponibilité.'
            );

            return;
        }


        /*
         * Création du formulaire
         */

        const form = document.createElement('form');

        form.method = 'POST';

        form.action = '/reservation/create';


        /*
         * ID disponibilité
         */

        const inputDisponibilite =
            document.createElement('input');

        inputDisponibilite.type = 'hidden';

        inputDisponibilite.name = 'disponibilite';

        inputDisponibilite.value =
            disponibiliteSelectionnee;


        /*
         * Jour sélectionné
         */

        const inputJour =
            document.createElement('input');

        inputJour.type = 'hidden';

        inputJour.name = 'jour';

        inputJour.value = jourSelectionne;


        /*
         * Ajout au formulaire
         */

        form.appendChild(inputDisponibilite);

        form.appendChild(inputJour);


        /*
         * Ajout du formulaire à la page
         */

        document.body.appendChild(form);


        /*
         * Debug avant envoi
         */

        console.log(
            'POST disponibilite =',
            inputDisponibilite.value
        );

        console.log(
            'POST jour =',
            inputJour.value
        );


        /*
         * Envoi
         */

        form.submit();
    });
});