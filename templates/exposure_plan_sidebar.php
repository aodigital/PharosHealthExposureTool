<?php
$current_step = $current_step ?? 'welcome'; // Default to 'welcome' if not set
$sections = [
    [
        "name" => "INTRODUCTION",
        "steps" => [
            ["step" => "welcome", "label" => "Welcome", "completed" => false],
            ["step" => "get_prepared", "label" => "Get Prepared", "completed" => false],
            ["step" => "employer_details", "label" => "Employer Details", "completed" => false],
        ],
    ],
    [
        "name" => "SILICA PROCESS",
        "steps" => [
            ["step" => "silica_process_get_prepared", "label" => "Get Prepared", "completed" => false],
            ["step" => "silica_process_jobsite_details", "label" => "Jobsite Details", "completed" => false],
            ["step" => "silica_process_work_activity", "label" => "Work Activity", "completed" => false],
            ["step" => "silica_process_work_area_duration", "label" => "Work Area & Duration", "completed" => false],
            ["step" => "silica_process_silica_process_summary", "label" => "Silica Process Summary", "completed" => false],
        ],
    ],
    [
        "name" => "SILICA EXPOSURE (NO CONTROLS)",
        "steps" => [
            ["step" => "silica_exposure_no_controls_get_prepared", "label" => "Get Prepared", "completed" => false],
            ["step" => "silica_exposure_no_controls_exposure_analysis", "label" => "Exposure Analysis (No Controls)", "completed" => false],
        ],
    ],
    [
        "name" => "EXPOSURE CONTROL",
        "steps" => [
            ["step" => "exposure_control_get_prepared", "label" => "Get Prepared", "completed" => false],
            ["step" => "exposure_control_risk_elimination_substitution", "label" => "Risk Elimination & Substitution", "completed" => false],
            ["step" => "exposure_control_engineering_controls", "label" => "Engineering Controls", "completed" => false],
            ["step" => "exposure_control_administrative_controls", "label" => "Administrative Controls", "completed" => false],
            ["step" => "exposure_control_exposure_control_summary", "label" => "Exposure Control Summary", "completed" => false],
        ],
    ],
    [
        "name" => "SILICA EXPOSURE (WITH CONTROLS)",
        "steps" => [
            ["step" => "silica_exposure_with_controls_get_prepared", "label" => "Get Prepared", "completed" => false],
            ["step" => "silica_exposure_with_controls_exposure_analysis_with_controls", "label" => "Exposure Analysis (With Controls)", "completed" => false],
        ],
    ],
    [
        "name" => "RESIDUAL EXPOSURE CONTROL",
        "steps" => [
            ["step" => "residual_exposure_control_get_prepared", "label" => "Get Prepared", "completed" => false],
            ["step" => "residual_exposure_control_respirators_ppe", "label" => "Respirators & Other PPE", "completed" => false],
        ],
    ],
    [
        "name" => "DOCUMENTATION",
        "steps" => [
            ["step" => "documentation_get_prepared", "label" => "Get Prepared", "completed" => false],
            ["step" => "documentation_ecp_summary", "label" => "ECP Summary", "completed" => false],
            ["step" => "documentation_generate_ecp", "label" => "Generate ECP", "completed" => false],
        ],
    ],
    [
        "name" => "CONCLUSION",
        "steps" => [
            ["step" => "completed_ecp", "label" => "Completed ECP", "completed" => false],
        ],
    ],
];

?>

<ul class="side-nav" id="controltool-sidebar">
    <?php foreach ($sections as $section): ?>
        <li class="active_heading <?php echo isset($current_step) && in_array($current_step, array_column($section['steps'], 'step')) ? '' : 'collapsed'; ?>">
            <a href="javascript:void(0)">
                <i class="fa fa-map-marker fa-fw"></i>&nbsp;<?php echo htmlspecialchars($section['name']); ?>
            </a>
            <ul class="side-nav" style="<?php echo isset($current_step) && in_array($current_step, array_column($section['steps'], 'step')) ? 'display: block;' : 'display: none;'; ?>">
                <?php foreach ($section['steps'] as $step): ?>
                    <li class="active_section">
                        <a href="javascript:void(0)" data-step="<?php echo htmlspecialchars($step['step']); ?>"
                           class="<?php echo isset($current_step) && $step['step'] === $current_step ? 'active' : ''; ?>">
                            <i class="fa fa-circle fa-fw"></i>&nbsp;<?php echo htmlspecialchars($step['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </li>
    <?php endforeach; ?>
</ul>
