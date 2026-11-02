import { Controller } from '@hotwired/stimulus';

// Contrôleur de recherche des formateurs (filtrage dans le navigateur).
export default class extends Controller {

    // input : champ de recherche
    // item  : colonne qui contient une carte (masquée en entier pour ne pas laisser de trou)
    // count : compteur des résultats
    // empty : message affiché quand rien ne correspond
    // clear : bouton pour effacer la recherche
    static targets = ['input', 'item', 'count', 'empty', 'clear'];

    connect() {
        // Reprend une recherche passée dans l'URL (?q=...)
        const query = new URLSearchParams(window.location.search).get('q');

        if (query) {
            this.inputTarget.value = query;
        }

        this.filter();
    }

    // Affiche les cartes qui contiennent le texte saisi
    filter() {
        const query = this.inputTarget.value
            .trim()
            .toLocaleLowerCase('fr');

        let visibleCount = 0;

        this.itemTargets.forEach((item) => {
            const matches = (item.dataset.formateurSearch || '')
                .toLocaleLowerCase('fr')
                .includes(query);

            item.hidden = !matches;

            if (matches) {
                visibleCount++;
            }
        });

        const isSearching = query.length > 0;

        this.countTarget.hidden = !isSearching;
        this.countTarget.textContent = `${visibleCount} formateur${visibleCount > 1 ? 's' : ''} trouvé${visibleCount > 1 ? 's' : ''}`;

        this.emptyTarget.hidden = visibleCount > 0;
        this.clearTarget.hidden = !isSearching;
    }

    // Vide le champ de recherche et replace le curseur dedans
    clear() {
        this.inputTarget.value = '';
        this.filter();
        this.inputTarget.focus();
    }
}
