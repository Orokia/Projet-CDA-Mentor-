
import { Controller } from '@hotwired/stimulus';

// Contrôleur du menu déroulant du profil dans la navbar.
// Stimulus le reconnecte après chaque navigation Turbo,
// contrairement à un script lancé sur DOMContentLoaded.
export default class extends Controller {

    // button : avatar qui ouvre ou ferme le menu
    static targets = ['button'];

    // Ouvrir ou fermer le menu au clic sur l'avatar
    toggle(event) {
        event.stopPropagation();

        const isOpen = this.element.classList.toggle('open');

        this.buttonTarget.setAttribute('aria-expanded', String(isOpen));
    }

    // Fermer le menu au clic en dehors du profil
    closeOnClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }

    // Fermer le menu (aussi appelé avant la mise en cache Turbo,
    // pour ne pas réafficher un menu ouvert au retour arrière)
    close() {
        this.element.classList.remove('open');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
    }
}
