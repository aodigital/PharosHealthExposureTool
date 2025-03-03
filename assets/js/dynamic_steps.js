$(document).ready(function () {
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded.');
} else {
    console.log('jQuery loaded successfully.');
}


    // Automatically load the first step when the page is loaded
    var initialStep = window.currentStep && window.currentStep !== 'undefined' ? window.currentStep : 'welcome';
    console.log('Debug: Initial step to load:', initialStep); // Debugging log to check initial step

    if (initialStep) {
        console.log('Debug: Loading initial step:', initialStep); // Debugging log to confirm what we're passing to loadStep()
        loadStep(initialStep);
    } else {
        console.error('Error: Initial step is undefined, loading welcome as fallback'); // Debugging log in case something goes wrong
        loadStep('welcome');
    }

    // Function to load a specific step via AJAX
    function loadStep(step) {
        console.log('Loading step:', step); // Debugging log to check step being loaded
        $.ajax({
            url: '../ajax/load_step.php',
            type: 'GET',
            data: { step: step, plan_id: window.planId },
            success: function (response) {
                $('#content-area').html(response);

                // Highlight the active step
                $('#controltool-sidebar a').removeClass('active');
                $('#controltool-sidebar a[data-step="' + step + '"]').addClass('active');

                // Expand the relevant section and collapse others
                $('#controltool-sidebar .active_heading').each(function () {
                    const $section = $(this);
                    const isCurrentStepInSection = $section.find('a[data-step="' + step + '"]').length > 0;

                    if (isCurrentStepInSection) {
                        $section.removeClass('collapsed');
                        $section.find('ul.side-nav').stop(true, true).slideDown();
                    } else {
                        $section.addClass('collapsed');
                        $section.find('ul.side-nav').stop(true, true).slideUp();
                    }
                });
		saveCurrentStep(step);
            },
            error: function () {
                alert('Failed to load the step. Please try again.');
            }
        });
    }

function saveCurrentStep(step) {
    $.ajax({
        url: '../ajax/update_step.php',
        type: 'POST',
        data: {
            step: step,
            plan_id: window.planId
        },
        success: function (response) {
            console.log(response); // Log the response to see if it's successful
        },
        error: function () {
            console.error('Failed to update the current step in the database.');
        }
    });
}




    // Toggle the sidebar section on click - Attach event listener only once
    $('#controltool-sidebar').off('click', '.active_heading > a').on('click', '.active_heading > a', function (e) {
        e.preventDefault();
        var parentLi = $(this).parent();

        if (!parentLi.hasClass('collapsed')) {
            console.log('Collapsing section:', parentLi.text()); // Debugging log for collapsing section
            parentLi.addClass('collapsed');
            parentLi.find('ul.side-nav').stop(true, true).slideUp();
        } else {
            console.log('Collapsing all sections'); // Debugging log for collapsing all sections
            $('#controltool-sidebar .active_heading').addClass('collapsed').find('ul.side-nav').stop(true, true).slideUp();
            console.log('Expanding section:', parentLi.text()); // Debugging log for expanding section
            parentLi.removeClass('collapsed').find('ul.side-nav').stop(true, true).slideDown();
        }
    });

    // Load step content when clicking on a step link
    $('#controltool-sidebar').off('click', 'a[data-step]').on('click', 'a[data-step]', function (e) {
        e.preventDefault();
        var step = $(this).data('step');
        console.log('Debug: Loading step from click:', step); // Debugging log for step loaded on click
        loadStep(step);
    });

    function saveEmployerDetailsFormData(callback) {
        // Check if the current form is the Employer Details form
        if ($('#employer-details-form').length === 0) {
            if (callback) callback(); // If the form is not present, proceed without saving
            return;
        }

        // Gather data from the form
        const formData = {
            planning_id: window.planId, // This should come from the global variable set by PHP
            employer_name: $('input[name="ecp_company_name"]').val(),
            employer_address: $('input[name="ecp_company_street_address"]').val(),
            employer_address_city: $('input[name="ecp_company_city"]').val(),
            employer_address_region: $('input[name="ecp_company_region"]').val(),
            employer_address_postal_code: $('input[name="ecp_company_postal_code"]').val(),
            employer_address_country: $('input[name="ecp_company_country"]').val(),
            employer_phone: $('input[name="ecp_company_phone"]').val(),
            employer_email: $('input[name="ecp_company_email"]').val(),
            employer_website: $('input[name="ecp_company_website"]').val(),
            number_of_employees: $('input[name="number_of_employees"]:checked').val(),
            jobsite_sector: $('input[name="jobsite_sector"]').val(),
            ecp_contact_name: $('input[name="ecp_contact_name"]').val(),
            ecp_contact_position: $('input[name="ecp_contact_position"]').val(),
            ecp_contact_phone: $('input[name="ecp_contact_phone"]').val(),
            ecp_contact_email: $('input[name="ecp_contact_email"]').val(),
            site_contact_name: $('input[name="site_contact_name"]').val(),
            site_contact_phone: $('input[name="site_contact_phone"]').val()
        };

        // Send the data via AJAX
        $.ajax({
            url: '../ajax/update_employer_details.php',
            type: 'POST',
            data: formData,
            success: function (response) {
                console.log(response); // For debugging purposes
                if (callback) {
                    callback();
                }
            },
            error: function () {
                alert('Failed to save form data. Please try again.');
            }
        });
    }

    // Add event listener for buttons on Employer Details step with the correct class name
    $(document).on('click', '.employer-details-save-step', function (e) {
        e.preventDefault();
        const step = $(this).data('step');
        console.log('Debug: Loading step from Employer Details button click:', step); // Debugging log for step loaded from the button

        // Save the form data only if the Employer Details form is present
        saveEmployerDetailsFormData(function () {
            loadStep(step);
        });
    });

function saveWorkAreaDurationFormData(callback) {
    if ($('#work-area-duration-form').length === 0) {
        console.warn('Form not found. Exiting save function.');
        if (callback) callback();
        return;
    }

    let workAreas = [];
    let durations = [];

    console.log('Starting Work Area & Duration Save...');
    console.log('-----------------------------------');

    // Loop through each .work-area-entry to collect data
    $('.work-area-entry').each(function (index) {
        let workAreaField = $(this).find('.work_area');
        let durationField = $(this).find('.avg_hr_per_shift');

        // Ensure the fields exist
        if (workAreaField.length === 0 || durationField.length === 0) {
            console.error(`Missing inputs for Activity ${index + 1}.`);
            return;
        }

        // Get values from the fields
        let workArea = workAreaField.val() || 'N/A';
        let duration = durationField.val() || '0';

        // Log the collected values
        console.log(`Activity ${index + 1}: Work Area: ${workArea}, Duration: ${duration}`);

        // Push the values into arrays
        workAreas.push(workArea.trim());
        durations.push(duration.trim());
    });

    // Ensure we actually collected data before proceeding
    if (workAreas.length === 0 || durations.length === 0) {
        console.error('No valid data collected from the form.');
        alert('Error: No data found in form.');
        return;
    }

    // Final data object to send
    const formData = {
        plan_id: $('input[name="plan_id"]').val().trim(),
        work_area: workAreas.join(','), // Comma-separated string
        avg_hr_per_shift: durations.join(',') // Comma-separated string
    };

    // Log the final data before sending
    console.log('Final Data Object to Send via AJAX:', formData);

    // Perform AJAX save
    $.ajax({
        url: '../ajax/update_work_area_duration.php',
        type: 'POST',
        data: formData,
        success: function (response) {
            console.log('AJAX Response:', response);

            try {
                const parsedResponse = JSON.parse(response);

                if (parsedResponse.status === 'success') {
                    console.log('Save successful:', parsedResponse.message);

                    // Only proceed if save succeeds
                    if (typeof callback === 'function') {
                        console.log('Proceeding to next step...');
                        callback();
                    }
                } else {
                    console.error('Save failed:', parsedResponse.message);
                }
            } catch (err) {
                console.error('Error parsing response:', response);
            }
        },
        error: function (xhr, status, error) {
            console.error('AJAX Error:', status, error, xhr.responseText);
        }
    });
}

// Attach click handler to the save button
$(document).on('click', '.silica-work-area-duration-save', function (e) {
    e.preventDefault();

    const step = $(this).data('step');
    console.log('Save button clicked. Target step:', step);

    saveWorkAreaDurationFormData(function () {
        console.log('Save process completed. Loading next step:', step);
        loadStep(step);
    });
});





// Add event listener for Jobsite Details buttons
$(document).on('click', '.load-step-jobsite', function (e) {
    e.preventDefault();
    const step = $(this).data('step');
    console.log('Debug: Loading step from Jobsite Details button click:', step); // Debugging log for step loaded from the button

    // Save the form data only if the Jobsite Details form is present
    saveJobsiteDetailsFormData(function () {
        loadStep(step);
    });
});


// Add this click handler for the Continue button
$(document).on('click', '.load-step', function (e) {
    e.preventDefault();
    var step = $(this).data('step');
    console.log('Debug: Loading step from button click:', step); // Debugging log for step loaded from the button
    loadStep(step);
});

function saveWorkActivityFormData(callback) {
    const activities = [];

    // Loop through each activity row and collect its data
    $('.work-activity').each(function () {
        const material = $(this).find('.material-dropdown').val();
        const task = $(this).find('.task-dropdown').val();
        const tool = $(this).find('.tool-dropdown').val();

        // Only include rows with valid selections
        if (material && task && tool) {
            activities.push({
                material: material,
                task: task,
                tool: tool,
            });
        }
    });

    // Send the data via AJAX
    $.ajax({
        url: '../ajax/update_work_activity.php', // The backend script
        type: 'POST',
        data: {
            planning_id: window.planId, // Assuming `planId` is globally set
            activities: JSON.stringify(activities), // Send as JSON
        },
        success: function (response) {
            console.log('Save successful:', response);
            if (callback) callback(); // Call the next action (e.g., navigate)
        },
        error: function (xhr, status, error) {
            console.error('Error saving work activities:', error);
            alert('Failed to save work activities. Please try again.');
        },
    });
}



// Add event listener for Work Activity buttons (Continue and Back)
$(document).on('click', '.silica-process-work-activity-save', function (e) {
    e.preventDefault();
    const step = $(this).data('step');
    console.log('Debug: Loading step from Work Activity button click:', step); // Debugging log for step loaded from the button

    // Save the form data only if the Work Activity form is present
    saveWorkActivityFormData(function () {
        loadStep(step);
    });
});



$(document).on('click', '.elimination-back-button', function (e) {
    e.preventDefault();
    var step = $(this).data('step');
    console.log('Debug: Navigating back to step:', step); // Debugging log for back button
    loadStep(step);
});

$(document).on('click', '.elimination-next-button', function (e) {
    e.preventDefault();
    var step = $(this).data('step');
    console.log('Debug: Navigating forward to step:', step); // Debugging log for next button
    loadStep(step);
});

$(document).on('click', '.engineering-controls-back-button', function (e) {
    e.preventDefault();
    var step = $(this).data('step');
    console.log('Debug: Navigating back to step:', step); // Debugging log for back button
    saveEngineeringControlsData(function () {
        loadStep(step);
    });
});

// Event listener for the next button
$(document).on('click', '.engineering-controls-next-button', function (e) {
    e.preventDefault();
    var step = $(this).data('step');
    console.log('Debug: Navigating forward to step:', step); // Debugging log for next button
    saveEngineeringControlsData(function () {
        loadStep(step);
    });
});

// Placeholder function for saving engineering controls data
function saveEngineeringControlsData(callback) {
    console.log("Saving Engineering Controls Data...");
    // Add AJAX or other logic here to save data

    if (typeof callback === "function") {
        callback();
    }
}

// Event handler for the Administrative Controls Back button
$(document).on('click', '.load-step-administrative-controls-back', function (e) {
    e.preventDefault();
    var step = $(this).data('step');
    console.log('Debug: Navigating to the previous step:', step);
    // Placeholder for data saving logic
    console.log('Placeholder: Save data for Administrative Controls page before navigating back.');
    loadStep(step);
});

// Event handler for the Administrative Controls Next button
$(document).on('click', '.load-step-administrative-controls-next', function (e) {
    e.preventDefault();
    var step = $(this).data('step');
    console.log('Debug: Navigating to the next step:', step);
    // Placeholder for data saving logic
    console.log('Placeholder: Save data for Administrative Controls page before navigating forward.');
    loadStep(step);
});

// Event listener for the Back button
$(document).on('click', '.save-and-load-respirators-back', function (e) {
    e.preventDefault();

    // Extract the step from the button's data attribute
    const step = $(this).data('step');
    console.log(`Navigating to step: ${step} via Back button`);

    // Placeholder for save logic
    saveRespiratorsPPEData(function () {
        loadStep(step);
    });
});

// Event listener for the Next button
$(document).on('click', '.save-and-load-respirators-next', function (e) {
    e.preventDefault();

    // Extract the step from the button's data attribute
    const step = $(this).data('step');
    console.log(`Navigating to step: ${step} via Next button`);

    // Placeholder for save logic
    saveRespiratorsPPEData(function () {
        loadStep(step);
    });
});


// Placeholder save function for Respirators & PPE data
function saveRespiratorsPPEData(callback) {
    console.log('Saving Respirators & PPE data...');

    // Add AJAX call or any other saving logic here
    /*
    $.ajax({
        url: '/path-to-save-endpoint', // Replace with your save endpoint
        type: 'POST',
        data: {
            // Add the data to be saved here
            exampleKey: 'exampleValue'
        },
        success: function (response) {
            console.log('Data saved successfully:', response);
            if (callback) callback(); // Trigger the callback after save completes
        },
        error: function (error) {
            console.error('Error saving data:', error);
        }
    });
    */

    // For now, directly invoke the callback as a placeholder
    if (callback) callback();
}


});
