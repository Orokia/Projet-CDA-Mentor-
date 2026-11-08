import { Controller } from '@hotwired/stimulus';

// Contrôleur de recherche des formateurs (filtrage dans le navigateur).
export default class extends Controller {

    // input    : champ de recherche
    // item     : colonne qui contient une carte (masquée en entier pour ne pas laisser de trou)
    // category : boutons de filtre par spécialité (facultatifs)
    // count    : compteur des résultats
    // empty    : message affiché quand rien ne correspond
    // clear    : bouton pour effacer la recherche
    // more     : bouton « Afficher plus » (facultatif)
    static targets = ['input', 'item', 'category', 'count', 'empty', 'clear', 'more'];

    // Nombre de cartes affichées au départ, puis à chaque clic sur « Afficher plus »
    static values = { perPage: { type: Number, default: 8 } };

    connect() {
        this.categorie = '';
        this.limit = this.perPageValue;

        // Reprend une recherche passée dans l'URL (?q=...)
        const query = new URLSearchParams(window.location.search).get('q');

        if (query && this.hasInputTarget) {
            this.inputTarget.value = query;
        }

        this.filter();
    }

    // Sélectionne une spécialité (« Toutes » a une valeur vide)
    selectCategory(event) {
        this.categorie = event.currentTarget.dataset.categorie || '';

        this.categoryTargets.forEach((bouton) => {
            bouton.setAttribute('aria-pressed', String((bouton.dataset.categorie || '') === this.categorie));
        });

        this.limit = this.perPageValue;
        this.filter();
    }

    // Saisie dans la recherche : on repart du premier paquet
    search() {
        this.limit = this.perPageValue;
        this.filter();
    }

    // Affiche le paquet de cartes suivant
    showMore() {
        this.limit += this.perPageValue;
        this.filter();
    }

    // Affiche les cartes qui contiennent le texte saisi et la spécialité choisie
    filter() {
        if (!this.hasInputTarget) {
            return;
        }

        const query = this.inputTarget.value
            .trim()
            .toLocaleLowerCase('fr');

        let matchCount = 0;

        this.itemTargets.forEach((item) => {
            const texte = (item.dataset.formateurSearch || '').toLocaleLowerCase('fr');
            const specialites = (item.dataset.specialites || '').split('|');

            const matches = texte.includes(query)
                && (this.categorie === '' || specialites.includes(this.categorie));

            // Sans bouton « Afficher plus », toutes les cartes trouvées sont visibles
            item.hidden = !matches || (this.hasMoreTarget && matchCount >= this.limit);

            if (matches) {
                matchCount++;
            }
        });

        const isFiltering = query.length > 0 || this.categorie !== '';

        if (this.hasCountTarget) {
            this.countTarget.textContent = isFiltering
                ? `${matchCount} formateur${matchCount > 1 ? 's' : ''} trouvé${matchCount > 1 ? 's' : ''}`
                : `${matchCount} formateur${matchCount > 1 ? 's' : ''}`;
        }

        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = matchCount > 0;
        }

        if (this.hasClearTarget) {
            this.clearTarget.hidden = query.length === 0;
        }

        if (this.hasMoreTarget) {
            this.moreTarget.hidden = matchCount <= this.limit;
        }
    }

    // Vide le champ de recherche et replace le curseur dedans
    clear() {
        this.inputTarget.value = '';
        this.search();
        this.inputTarget.focus();
    }
}
