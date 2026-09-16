const profileButton = document.getElementById('profileMenuButton');
const profileMenu = document.querySelector('.profile-dropdown');
const profileContainer = document.querySelector('.navbar-profile');

if (profileButton && profileMenu && profileContainer) {

    profileButton.addEventListener('click', function (event) {

        event.stopPropagation();

        profileContainer.classList.toggle('open');

    });


    document.addEventListener('click', function (event) {

        if (!profileContainer.contains(event.target)) {

            profileContainer.classList.remove('open');

        }

    });

}