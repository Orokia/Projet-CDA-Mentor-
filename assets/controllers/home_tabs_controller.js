import { Controller } from '@hotwired/stimulus';

// Onglets de spécialités de la page d'accueil.
// Un clic sur un onglet n'affiche que les formations de cette spécialité ;
// l'onglet « Toutes » (data-categorie vide) les affiche toutes.
export default class extends Controller {

    // tab  : boutons d'onglet (data-categorie = nom de la spécialité)
    // item : colonnes qui contiennent une carte formation
    static targets = ['tab', 'item'];

    // Nombre maximum de cartes affichées sur l'accueil
    static values = { limit: { type: Number, default: 8 } };

    connect() {
        this.show('');
    }

    select(event) {
        this.show(event.currentTarget.dataset.categorie || '');
    }

    show(categorie) {
        this.tabTargets.forEach((tab) => {
            tab.setAttribute('aria-selected', String((tab.dataset.categorie || '') === categorie));
        });

        let visibles = 0;

        this.itemTargets.forEach((item) => {
            const correspond = categorie === '' || item.dataset.categorie === categorie;
            const affiche = correspond && visibles < this.limitValue;

            item.hidden = !affiche;

            if (affiche) {
                visibles++;
            }
        });
    }
}
