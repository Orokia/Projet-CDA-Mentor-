
import { Controller } from '@hotwired/stimulus';

// Contrôleur de sélection d'une disponibilité et de réservation
// sur le profil d'un formateur.
//
// Il permet :
// 1. De sélectionner un jour de formation.
// 2. D'afficher un récapitulatif de la sélection et le bouton Réserver.
// 3. D'envoyer le jour et la disponibilité choisis au contrôleur Symfony.
export default class extends Controller {

    // jour          : boutons des jours disponibles
    // selection     : bloc du récapitulatif
    // selectionText : texte du jour et de l'horaire choisis
    // action        : bloc qui contient le bouton Réserver
    // submit        : bouton Réserver (porte l'URL et le jeton CSRF)
    static targets = ['jour', 'selection', 'selectionText', 'action', 'submit'];

    // Réinitialise la sélection à chaque connexion du contrôleur
    connect() {
        this.jourSelectionne = null;
        this.disponibiliteSelectionnee = null;
    }

    // Méthode appelée au clic sur un jour
    choisir(event) {
        const bouton = event.currentTarget;

        // N'avoir qu'un seul jour sélectionné à la fois
        this.jourTargets.forEach((item) => {
            item.classList.remove('selected');
            item.setAttribute('aria-pressed', 'false');
        });

        bouton.classList.add('selected');
        bouton.setAttribute('aria-pressed', 'true');

        // Mémoriser le choix de l'utilisateur
        this.jourSelectionne = bouton.dataset.jour;
        this.disponibiliteSelectionnee = bouton.dataset.disponibiliteId;

        // Récupérer les horaires affichés dans le bouton
        const horaireElement = bouton.querySelector('.disponibilite-jour-horaire');

        const horaire = horaireElement
            ? horaireElement.textContent.trim().replace(/\s+/g, ' ')
            : 'Horaire non renseigné';

        // textContent évite d'interpréter du HTML
        this.selectionTextTarget.textContent = `${bouton.dataset.jour} — ${horaire}`;

        // Afficher le récapitulatif et le bouton Réserver
        this.selectionTarget.hidden = false;
        this.actionTarget.hidden = false;
    }

    // Méthode appelée au clic sur le bouton Réserver
    reserver() {
        if (!this.jourSelectionne || !this.disponibiliteSelectionnee) {
            alert('Veuillez sélectionner un jour avant de réserver.');

            return;
        }

        // Créer un formulaire POST envoyé à la route app_reservation_create
        const form = document.createElement('form');

        form.method = 'POST';
        form.action = this.submitTarget.dataset.actionUrl;

        this.ajouterChamp(form, 'disponibilite', this.disponibiliteSelectionnee);
        this.ajouterChamp(form, 'jour', this.jourSelectionne);
        this.ajouterChamp(form, '_token', this.submitTarget.dataset.csrfToken);

        document.body.appendChild(form);

        form.submit();
    }

    // Ajoute un champ caché au formulaire
    ajouterChamp(form, name, value) {
        const input = document.createElement('input');

        input.type = 'hidden';
        input.name = name;
        input.value = value;

        form.appendChild(input);
    }
}
