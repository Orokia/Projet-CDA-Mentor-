
import { Controller } from '@hotwired/stimulus';

// Création du contrôleur de recherche des formateurs
export default class extends Controller {

    // Déclaration des éléments HTML utilisés par le contrôleur
    // input : champ de recherche
    // card  : cartes des formateurs
    // count : compteur des résultats
    // clear : bouton pour effacer la recherche
    static targets = ['input', 'card', 'count', 'clear'];

    // Méthode exécutée lorsque Stimulus connecte le contrôleur à la page
    connect() {
        // Initialise l'affichage sans montrer le compteur
        this.filter();
    }

    // Méthode qui filtre les formateurs selon le texte saisi
    filter() {

        // Récupère le texte saisi, retire les espaces inutiles
        // et convertit le texte en minuscules
        const query = this.inputTarget.value
            .trim()
            .toLocaleLowerCase('fr');

        // Vérifie si l'utilisateur a saisi une recherche
        const isSearching = query.length > 0;

        // Initialise le nombre de cartes correspondantes
        let visibleCount = 0;

        // Parcourt toutes les cartes des formateurs
        this.cardTargets.forEach((card) => {

            // Récupère le nom, le prénom et les spécialités
            // associés à la carte
            const text = card.dataset.formateurSearch || '';

            // Vérifie si les informations contiennent la recherche
            const matches = text
                .toLocaleLowerCase('fr')
                .includes(query);

            // Affiche ou masque la carte selon le résultat
            card.hidden = !matches;

            // Compte les cartes correspondantes
            if (matches) {
                visibleCount++;
            }
        });

        // Affiche le compteur uniquement si une recherche est saisie
        this.countTarget.hidden = !isSearching;

        // Met à jour le message seulement pendant une recherche
        if (isSearching) {
            this.countTarget.textContent = visibleCount === 0
                ? 'Aucun formateur trouvé'
                : `${visibleCount} formateur${visibleCount > 1 ? 's' : ''} trouvé${visibleCount > 1 ? 's' : ''}`;
        }

        // Affiche le bouton d'effacement uniquement si le champ contient du texte
        this.clearTarget.hidden = !isSearching;
    }

    // Méthode appelée lorsque l'utilisateur clique sur le bouton d'effacement
    clear() {

        // Vide le champ de recherche
        this.inputTarget.value = '';

        // Réaffiche toutes les cartes et masque le compteur
        this.filter();

        // Replace le curseur dans le champ de recherche
        this.inputTarget.focus();
    }
}
