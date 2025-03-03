<?php
include '../includes/db.php'; // Assuming this file contains the DB connection
session_start();

if (!isset($_SESSION['user'])) {
    echo "Error: User not logged in.";
    exit();
}

// Get the planning_id (linked to Exposure_Plannings ID) and other form data
$planning_id = isset($_POST['planning_id']) ? intval($_POST['planning_id']) : null;

if (!$planning_id) {
    echo "Error: No valid planning ID provided.";
    exit();
}

$user = $_SESSION['user'];

// Prepare the data for update
$employer_name = $_POST['employer_name'] ?? '';
$employer_address = $_POST['employer_address'] ?? '';
$employer_address_city = $_POST['employer_address_city'] ?? '';
$employer_address_region = $_POST['employer_address_region'] ?? '';
$employer_address_postal_code = $_POST['employer_address_postal_code'] ?? '';
$employer_address_country = $_POST['employer_address_country'] ?? '';
$employer_phone = $_POST['employer_phone'] ?? '';
$employer_email = $_POST['employer_email'] ?? '';
$employer_website = $_POST['employer_website'] ?? '';
$number_of_employees = $_POST['number_of_employees'] ?? ''; // New field
$jobsite_sector = $_POST['jobsite_sector'] ?? ''; // New field for Trade
$ecp_contact_name = $_POST['ecp_contact_name'] ?? '';
$ecp_contact_position = $_POST['ecp_contact_position'] ?? '';
$ecp_contact_phone = $_POST['ecp_contact_phone'] ?? '';
$ecp_contact_email = $_POST['ecp_contact_email'] ?? '';
$site_contact_name = $_POST['site_contact_name'] ?? '';
$site_contact_phone = $_POST['site_contact_phone'] ?? '';

try {
    // Update the `Exposure_Plannings_Meta` table with the data using `planning_id` as the identifier
    $stmt = $conn->prepare("
        UPDATE Exposure_Plannings_Meta 
        SET 
            employer_name = :employer_name,
            employer_address = :employer_address,
            employer_address_city = :employer_address_city,
            employer_address_region = :employer_address_region,
            employer_address_postal_code = :employer_address_postal_code,
            employer_address_country = :employer_address_country,
            employer_phone = :employer_phone,
            employer_email = :employer_email,
            employer_website = :employer_website,
            number_of_employees = :number_of_employees,
            jobsite_sector = :jobsite_sector,
            ecp_contact_name = :ecp_contact_name,
            ecp_contact_position = :ecp_contact_position,
            ecp_contact_phone = :ecp_contact_phone,
            ecp_contact_email = :ecp_contact_email,
            site_contact_name = :site_contact_name,
            site_contact_phone = :site_contact_phone
        WHERE planning_id = :planning_id
    ");

    // Bind parameters
    $stmt->bindParam(':planning_id', $planning_id, PDO::PARAM_INT);
    $stmt->bindParam(':employer_name', $employer_name);
    $stmt->bindParam(':employer_address', $employer_address);
    $stmt->bindParam(':employer_address_city', $employer_address_city);
    $stmt->bindParam(':employer_address_region', $employer_address_region);
    $stmt->bindParam(':employer_address_postal_code', $employer_address_postal_code);
    $stmt->bindParam(':employer_address_country', $employer_address_country);
    $stmt->bindParam(':employer_phone', $employer_phone);
    $stmt->bindParam(':employer_email', $employer_email);
    $stmt->bindParam(':employer_website', $employer_website);
    $stmt->bindParam(':number_of_employees', $number_of_employees);
    $stmt->bindParam(':jobsite_sector', $jobsite_sector);
    $stmt->bindParam(':ecp_contact_name', $ecp_contact_name);
    $stmt->bindParam(':ecp_contact_position', $ecp_contact_position);
    $stmt->bindParam(':ecp_contact_phone', $ecp_contact_phone);
    $stmt->bindParam(':ecp_contact_email', $ecp_contact_email);
    $stmt->bindParam(':site_contact_name', $site_contact_name);
    $stmt->bindParam(':site_contact_phone', $site_contact_phone);

    // Execute the statement
    $stmt->execute();

    echo "Data saved successfully.";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

?>
