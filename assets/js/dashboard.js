document.addEventListener('DOMContentLoaded', function() {
    // Add event listener to the "Start new planning from scratch" link
    const newPlanningLink = document.getElementById('newPlanningFromScratchLink');
    const scratchBox = document.getElementById('scratch_box');
    const closeScratchBoxLink = document.getElementById('scratch_close');

    newPlanningLink.addEventListener('click', function(event) {
        event.preventDefault(); // Prevent the default action of the link

        // Show the scratch_box section
        scratchBox.style.display = 'block';

        // Hide the newPlanningFromScratchLink
        newPlanningLink.style.display = 'none';
    });

    // Add event listener to the close link inside the scratch_box
    closeScratchBoxLink.addEventListener('click', function(event) {
        event.preventDefault(); // Prevent the default action of the link

        // Hide the scratch_box section
        scratchBox.style.display = 'none';

        // Show the newPlanningFromScratchLink again
        newPlanningLink.style.display = 'block';
    });
});
