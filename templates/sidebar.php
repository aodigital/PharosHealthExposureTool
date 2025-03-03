<div class="sidebar">
    <aside class="left-off-canvas-menu">
        <div class="panel panel_left_column">
            <div class="padding_left_column">
                <div class="row">
                    <div class="large-12 columns">
                        <br>
                        <h3 class="alot_less_space">
                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                        </h3>
                        <?php if (isset($user['role'])): ?>
                            <p>User Role: <?php echo htmlspecialchars($user['role']); ?></p>
                        <?php else: ?>
                            <p>User Role: Not Defined</p>
                        <?php endif; ?>

                        <p><br><strong><?php echo htmlspecialchars($user['company_name']); ?></strong><br><br></p>
                    </div>
                </div>
            </div>
            <ul class="side-nav">
                <div class="sidebar-separator"></div>
                <h5>User Tools</h4>
                <li class="active_section dashboard_unselect_heading">
                    <a href="?page=dashboard" class="button"><i class="fa fa-tachometer fa-fw"></i>&nbsp;&nbsp;Dashboard</a>
                </li>
                <li class="active_section dashboard_unselect_heading">
                    <a href="?page=manage_account"class="button"><i class="fa fa-user fa-fw"></i>&nbsp;&nbsp;Account Information</a>
                </li>
                <div class="sidebar-separator"></div>
                <h5>Auditor Tools</h4>
                <?php if (in_array($user['role'], ['admin', 'auditor'])): ?>
                <li class="active_section dashboard_unselect_heading">
                    <a href="?page=auditor_tools" class="button"><i class="fa fa-check-square-o"></i>&nbsp;&nbsp;Plan Verifications</a>
                </li>
                <?php endif; ?>
                <div class="sidebar-separator"></div>
                <h5>Admin Tools</h4>
                <?php if ($user['role'] === 'admin'): ?>
                <li class="active_section dashboard_unselect_heading">
                    <a href="?page=create_user"class="button"><i class="fa fa-user-plus fa-fw"></i>&nbsp;&nbsp;Create New User</a>
                </li>
                <li class="active_section dashboard_unselect_heading">
                    <a href="?page=manage_users"class="button"><i class="fa fa-user fa-fw"></i>&nbsp;&nbsp;Manage Existing Users</a>
                </li>
                <li class="active_section dashboard_unselect_heading">
                    <a href="#"class="button"><i class="fa fa-cog fa-fw"></i></i>&nbsp;&nbsp;Manage Engineering Controls</a>
                </li>
                <?php endif; ?>
                
            </ul>
        </div>
    </aside>
</div>
