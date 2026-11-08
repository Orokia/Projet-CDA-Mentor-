import { Controller } from '@hotwired/stimulus';

// Affiche ou masque le mot de passe d'un champ.
// <div data-controller="password-visibility">
//   <input type="password" data-password-visibility-target="input">
//   <button type="button" data-password-visibility-target="button"
//           data-action="password-visibility#toggle">…</button>
// </div>
export default class extends Controller {

    static targets = ['input', 'button'];

    connect() {
        this.buttonTarget.hidden = false;
    }

    toggle() {
        const visible = this.inputTarget.type === 'password';

        this.inputTarget.type = visible ? 'text' : 'password';
        this.buttonTarget.setAttribute('aria-pressed', String(visible));
        this.buttonTarget.setAttribute('aria-label', visible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
        this.buttonTarget.querySelector('.bi').className = visible ? 'bi bi-eye-slash' : 'bi bi-eye';
    }
}
