
import { Controller } from '@hotwired/stimulus';

// Contrôleur du menu de navigation sur mobile.
// Sous 900 px, les liens sont masqués et ce bouton les affiche.
export default class extends Controller {

    // button : bouton « burger » qui ouvre ou ferme le menu
    static targets = ['button'];

    // Ouvrir ou fermer le menu
    toggle() {
        const isOpen = this.element.classList.toggle('nav-open');

        this.buttonTarget.setAttribute('aria-expanded', String(isOpen));
        this.buttonTarget.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
    }

    // Fermer le menu (aussi appelé avant la mise en cache Turbo,
    // pour ne pas réafficher un menu ouvert au retour arrière)
    close() {
        this.element.classList.remove('nav-open');
        this.buttonTarget.setAttribute('aria-expanded', 'false');
        this.buttonTarget.setAttribute('aria-label', 'Ouvrir le menu');
    }
}
