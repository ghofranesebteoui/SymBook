// Gestion du dropdown utilisateur
const userDropdown = document.getElementById('userDropdown');
if (userDropdown) {
    const dropdown = new bootstrap.Dropdown(userDropdown);

    // Ouvrir le dropdown au survol sur desktop
    const mediaQuery = window.matchMedia('(min-width: 992px)');
    if (mediaQuery.matches) {
        const dropdownParent = userDropdown.parentElement;

        dropdownParent.addEventListener('mouseenter', function() {
            dropdown.show();
        });

        dropdownParent.addEventListener('mouseleave', function() {
            dropdown.hide();
        });
    }
}