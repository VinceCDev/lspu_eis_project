 // Function to navigate back to the previous page
 function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = 'home';
    }
}

// Dark mode toggle functionality
document.addEventListener('DOMContentLoaded', function() {
    const darkModeToggle = document.getElementById('darkModeToggle');
    const htmlElement = document.documentElement;
    
    // Check for saved dark mode preference
    const isDarkMode = localStorage.getItem('darkMode') === 'true';
    
    // Apply dark mode if previously selected
    if (isDarkMode) {
        htmlElement.classList.add('dark');
    }
    
    // Toggle dark mode on button click
    darkModeToggle.addEventListener('click', function() {
        htmlElement.classList.toggle('dark');
        
        // Save preference to localStorage
        const isNowDarkMode = htmlElement.classList.contains('dark');
        localStorage.setItem('darkMode', isNowDarkMode);
        
        // Add animation class
        this.classList.add('rotate-animation');
        
        // Remove animation class after animation completes
        setTimeout(() => {
            this.classList.remove('rotate-animation');
        }, 1000);
    });
});