document.addEventListener('DOMContentLoaded', function() {
    const hamburgerBtn = document.getElementById('hamburgerBtn');
    const dropdownNav = document.getElementById('dropdownNav');
    const dropdownLinks = document.querySelectorAll('.dropdown-nav-list a');
    const ctaButton = document.getElementById('ctaButton');
    const sectionBeranda = document.getElementById('beranda');

    let isScrollUnlocked = false;

    // Toggle Dropdown
    hamburgerBtn.addEventListener('click', () => {
        dropdownNav.classList.toggle('active');
    });

    // Tutup dropdown saat klik link
    dropdownLinks.forEach(link => {
        link.addEventListener('click', () => {
            dropdownNav.classList.remove('active');
        });
    });

    // Tutup dropdown saat klik di luar
    document.addEventListener('click', (e) => {
        if (!hamburgerBtn.contains(e.target) && !dropdownNav.contains(e.target)) {
            dropdownNav.classList.remove('active');
        }
    });

    // Tombol CTA
    if (ctaButton) {
        ctaButton.addEventListener('click', function(e) {
            e.preventDefault();
            isScrollUnlocked = true;
            document.body.style.overflow = 'auto';
            sectionBeranda.scrollIntoView({ behavior: 'smooth' });
        });
    }

    // Unlock scroll untuk semua anchor links
    document.querySelectorAll('a[href^="#"]').forEach(link => {
        link.addEventListener('click', function() {
            if (!isScrollUnlocked) {
                isScrollUnlocked = true;
                document.body.style.overflow = 'auto';
            }
        });
    });
});