
document.addEventListener('DOMContentLoaded', function () {

    const profileButton = document.getElementById('profileMenuButton');
    const profileMenu = document.querySelector('.profile-dropdown');
    const profileContainer = document.querySelector('.navbar-profile');

    // Vérifier que les éléments existent dans la page
    if (!profileButton || !profileMenu || !profileContainer) {
        return;
    }

    // Ouvrir ou fermer le menu au clic sur l'avatar
    profileButton.addEventListener('click', function (event) {
        event.stopPropagation();

        const isOpen = profileContainer.classList.toggle('open');

        profileButton.setAttribute('aria-expanded', String(isOpen));
    });

    // Fermer le menu au clic en dehors du profil
    document.addEventListener('click', function (event) {
        if (!profileContainer.contains(event.target)) {
            profileContainer.classList.remove('open');
            profileButton.setAttribute('aria-expanded', 'false');
        }
    });

});
