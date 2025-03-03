<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php?page=login_register");
    exit();
}

$user = $_SESSION['user'];
$plan_id = isset($_GET['plan_id']) ? intval($_GET['plan_id']) : null;

if (!$plan_id) {
    echo "Error: No valid plan ID provided.";
    exit();
}

// Fetch the current exposure planning data using the $plan_id
try {
    $stmt = $conn->prepare("SELECT * FROM Exposure_Plannings WHERE id = :plan_id AND user_id = :user_id");
    $stmt->bindParam(':plan_id', $plan_id);
    $stmt->bindParam(':user_id', $user['id']);
    $stmt->execute();
    $exposure_planning = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$exposure_planning) {
        echo "Error: No exposure planning found.";
        exit();
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}

try {
    $stmt = $conn->prepare("SELECT * FROM Exposure_Plannings_Meta WHERE planning_id = :plan_id");
    $stmt->bindParam(':plan_id', $plan_id, PDO::PARAM_INT);
    $stmt->execute();
    $meta_data = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
?>

<h2 class="more_space">Employer Details</h2>
<p>Your company is referred to as the <strong>Employer</strong> throughout this exposure control planning and in your ECP document. As the Employer, it is your workers who will be performing the work activity. Please review and update your company details below as necessary. These details will be included in your ECP document.</p>

<form id="employer-details-form" method="post">
    <input type="hidden" name="plan_id" value="<?php echo $plan_id; ?>">

    <div class="row white_row_no_padding padding_top_text">
        <div class="large-6 medium-6 small-12 columns">
            <div class="row">
                <div class="large-12 columns">
                    <label>Company Name:</label>
                    <input type="text" name="ecp_company_name" value="<?php echo htmlspecialchars($user['company_name']); ?>" readonly/>
                </div>

                <div class="large-12 columns">
                    <label>Street Address:</label>
                    <input type="text" name="ecp_company_street_address" value="<?php echo htmlspecialchars($meta_data['employer_address'] ?? ''); ?>" placeholder="Enter your Street Address"/>
                </div>

                <div class="large-4 medium-4 small-12 columns">
                    <label>City:</label>
                    <input type="text" name="ecp_company_city" value="<?php echo htmlspecialchars($meta_data['employer_address_city'] ?? ''); ?>" placeholder="Enter your City"/>
                </div>

                <div class="large-4 medium-4 small-12 columns">
                    <label>Region/Province/State:</label>
                    <input type="text" name="ecp_company_region" value="<?php echo htmlspecialchars($meta_data['employer_address_region'] ?? ''); ?>" placeholder="Enter your Region, Province, or State"/>
                </div>

                <div class="large-4 columns">
                    <label>Postal Code:</label>
                    <input type="text" name="ecp_company_postal_code" value="<?php echo htmlspecialchars($meta_data['employer_address_postal_code'] ?? ''); ?>" placeholder="Enter your Postal Code"/>
                </div>

                <div class="large-12 columns">
                    <label>Country:</label>
                    <input type="text" name="ecp_company_country" value="<?php echo htmlspecialchars($meta_data['employer_address_country'] ?? ''); ?>" placeholder="Enter your Country"/>
                </div>

                <div class="large-12 columns">
                    <label>Phone:</label>
                    <input type="text" name="ecp_company_phone" value="<?php echo htmlspecialchars($meta_data['employer_phone'] ?? ''); ?>" placeholder="Enter your Company Phone Number"/>
                </div>

                <div class="large-12 columns">
                    <label>E-mail:</label>
                    <input type="text" name="ecp_company_email" value="<?php echo htmlspecialchars($meta_data['employer_email'] ?? ''); ?>" placeholder="Enter your Company E-mail"/>
                </div>

                <div class="large-12 columns">
                    <label>Website:</label>
                    <input type="text" name="ecp_company_website" value="<?php echo htmlspecialchars($meta_data['employer_website'] ?? ''); ?>" placeholder="Enter your Company Website Address"/>
                </div>

                <!-- New row for Number of Employees and Trade -->

                    <div class="large-12 columns">
                        <label>Number of Employees:</label>
                        <div>
                            <label>
                                <input type="radio" name="number_of_employees" value="1-10" <?php echo (isset($meta_data['number_of_employees']) && $meta_data['number_of_employees'] == "1-10") ? 'checked' : ''; ?> />
                                1-10
                            </label>
                            <label>
                                <input type="radio" name="number_of_employees" value="11-30" <?php echo (isset($meta_data['number_of_employees']) && $meta_data['number_of_employees'] == "11-30") ? 'checked' : ''; ?> />
                                11-30
                            </label>
                            <label>
                                <input type="radio" name="number_of_employees" value="31-100" <?php echo (isset($meta_data['number_of_employees']) && $meta_data['number_of_employees'] == "31-100") ? 'checked' : ''; ?> />
                                31-100
                            </label>
                            <label>
                                <input type="radio" name="number_of_employees" value="100+" <?php echo (isset($meta_data['number_of_employees']) && $meta_data['number_of_employees'] == "100+") ? 'checked' : ''; ?> />
                                100+
                            </label>
                        </div>
                    </div>
                    <div class="large-12 columns">
                        <label>Trade (eg builder, plumber etc):</label>
                        <input type="text" name="jobsite_sector" value="<?php echo htmlspecialchars($meta_data['jobsite_sector'] ?? ''); ?>" placeholder="Enter trade"/>
                    </div>

            </div>
        </div>

        <div class="large-6 medium-6 small-12 columns">
            <div class="row">
                <div class="large-12 columns">
                    <h5 class="more_space_top"><i class="fa fa-user"></i>&nbsp;&nbsp;ECP Contact</h5>
                    <p><strong>Enter the name and contact details for the person in your organization who will be the main point of contact for the ECP.</strong> This person will be responsible for overseeing the documentation, including securing any ECP support materials, arranging for implementation of the ECP at the jobsite, and following up on any issues or questions from workers or on inspections.</p>
                </div>

                <div class="large-12 columns">
                    <label>ECP Contact Name:</label>
                    <input type="text" name="ecp_contact_name" value="<?php echo htmlspecialchars($meta_data['ecp_contact_name'] ?? ''); ?>" placeholder="Enter name"/>
                </div>

                <div class="large-12 columns">
                    <label>Position:</label>
                    <input type="text" name="ecp_contact_position" value="<?php echo htmlspecialchars($meta_data['ecp_contact_position'] ?? ''); ?>" placeholder="Enter job title"/>
                </div>

                <div class="large-12 columns">
                    <label>Contact phone:</label>
                    <input type="text" name="ecp_contact_phone" value="<?php echo htmlspecialchars($meta_data['ecp_contact_phone'] ?? ''); ?>" placeholder="Enter phone number"/>
                </div>

                <div class="large-12 columns">
                    <label>Contact e-mail:</label>
                    <input type="text" name="ecp_contact_email" value="<?php echo htmlspecialchars($meta_data['ecp_contact_email'] ?? ''); ?>" placeholder="Enter e-mail address"/>
                </div>
            </div>
        </div>
    </div>

    <div class="large-12 columns">
        <h5 class="more_space_top"><i class="fa fa-user"></i>&nbsp;&nbsp;Site Contact</h5>
        <p><em>If the ECP contact is <strong>not the site contact</strong>, please include the site contact's name and telephone number:</em></p>
    </div>
    <div class="large-12 columns">
        <label>Site Contact Name:</label>
        <input type="text" name="site_contact_name" value="<?php echo htmlspecialchars($meta_data['site_contact_name'] ?? ''); ?>" placeholder="Enter name"/>
    </div>
    <div class="large-12 columns">
        <label>Site Contact Phone:</label>
        <input type="text" name="site_contact_phone" value="<?php echo htmlspecialchars($meta_data['site_contact_phone'] ?? ''); ?>" placeholder="Enter phone number"/>
    </div>

    <div class="clearfix">
        <a href="javascript:void(0)" class="button secondary left employer-details-save-step" data-step="get_prepared">Back</a>
        <a href="javascript:void(0)" class="button save_continue secondary right employer-details-save-step" data-step="silica_process_get_prepared">Continue&nbsp;&nbsp;<i class="fa fa-caret-right"></i></a>
    </div>
</form>

<script>

    

</script>
