import { Controller } from '@hotwired/stimulus';

// Intègre une salle Jitsi Meet dans la page (IFrame API).
export default class extends Controller {

    // domaine, salle et jeton : paramètres de JitsiMeetExternalAPI
    // script : adresse de external_api.js
    // retour : page affichée quand l'utilisateur quitte la salle
    static values = {
        domaine: String,
        salle: String,
        jeton: String,
        script: String,
        retour: String,
    };

    async connect() {
        try {
            await this.chargerScript();
        } catch {
            this.element.textContent = 'Impossible de charger la visioconférence. Veuillez réessayer.';
            return;
        }

        // L'utilisateur a pu quitter la page pendant le chargement
        if (!this.element.isConnected) {
            return;
        }

        this.api = new window.JitsiMeetExternalAPI(this.domaineValue, {
            roomName: this.salleValue,
            jwt: this.jetonValue,
            parentNode: this.element,
            width: '100%',
            height: '100%',
            lang: 'fr',
        });

        this.api.addListener('readyToClose', () => {
            window.location.assign(this.retourValue);
        });
    }

    // Quitte la salle quand Turbo change de page
    disconnect() {
        this.api?.dispose();
        this.api = null;
    }

    // external_api.js n'est pas un module : il est chargé à la demande,
    // et une seule fois même si Turbo revient sur la page.
    chargerScript() {
        if (window.JitsiMeetExternalAPI) {
            return Promise.resolve();
        }

        return new Promise((resolve, reject) => {
            const script = document.createElement('script');

            script.src = this.scriptValue;
            script.onload = resolve;
            script.onerror = reject;

            document.head.append(script);
        });
    }
}
