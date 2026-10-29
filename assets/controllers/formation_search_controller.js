import { Controller } from '@hotwired/stimulus';

// Contrôleur de recherche et de filtre du catalogue des formations.
// Le filtrage se fait dans le navigateur : la barre de recherche de la navbar
// envoie ?q=... vers /formations, et ce contrôleur reprend cette valeur.
export default class extends Controller {

    // input    : champ de recherche de la page
    // item     : colonne qui contient une carte (masquée en entier pour ne pas laisser de trou)
    // category : boutons de filtre par spécialité
    // count    : compteur des résultats
    // empty    : message affiché quand rien ne correspond
    static targets = ['input', 'item', 'category', 'count', 'empty'];

    connect() {
        // Reprend la recherche envoyée depuis la navbar
        const query = new URLSearchParams(window.location.search).get('q');

        if (query) {
            this.inputTarget.value = query;
        }

        this.categorie = '';
        this.filter();
    }

    // Sélectionne une spécialité (un second clic la désélectionne)
    selectCategory(event) {
        const valeur = event.currentTarget.dataset.categorie;

        this.categorie = this.categorie === valeur ? '' : valeur;

        this.categoryTargets.forEach((bouton) => {
            bouton.setAttribute('aria-pressed', String(bouton.dataset.categorie === this.categorie));
        });

        this.filter();
    }

    // Affiche les cartes qui correspondent au texte et à la spécialité choisie
    filter() {
        const query = this.inputTarget.value
            .trim()
            .toLocaleLowerCase('fr');

        let visibleCount = 0;

        this.itemTargets.forEach((item) => {
            const texte = (item.dataset.formationSearch || '').toLocaleLowerCase('fr');
            const categorie = item.dataset.categorie || '';

            const matches = texte.includes(query)
                && (this.categorie === '' || categorie === this.categorie);

            item.hidden = !matches;

            if (matches) {
                visibleCount++;
            }
        });

        const isFiltering = query.length > 0 || this.categorie !== '';

        this.countTarget.hidden = !isFiltering;
        this.countTarget.textContent = `${visibleCount} formation${visibleCount > 1 ? 's' : ''} trouvée${visibleCount > 1 ? 's' : ''}`;

        this.emptyTarget.hidden = visibleCount > 0;
    }
}
