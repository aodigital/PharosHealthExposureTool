document.addEventListener("DOMContentLoaded", function() {
    function openTab(event, tabName) {
        // Hide all tab content
        var tabContent = document.getElementsByClassName("tab-content");
        for (var i = 0; i < tabContent.length; i++) {
            tabContent[i].style.display = "none";
        }

        // Remove active class from all tab buttons
        var tabButtons = document.getElementsByClassName("tab-button");
        for (var i = 0; i < tabButtons.length; i++) {
            tabButtons[i].classList.remove("active");
        }

        // Show the current tab and add an active class to the button
        document.getElementById(tabName).style.display = "block";
        event.currentTarget.classList.add("active");
    }

    // Set default tab to be visible
    const registerElement = document.getElementById('register');
    if (registerElement) {
        registerElement.style.display = 'block';
    }


    // Attach openTab function to window to be accessible globally
    window.openTab = openTab;
});
