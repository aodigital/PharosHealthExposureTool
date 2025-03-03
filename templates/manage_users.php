<?php
// Ensure $user is defined
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    // Redirect to dashboard if the user is not an admin
    header("Location: ?page=dashboard");
    exit();
}

// Connect to the database and fetch all users
try {
    $stmt = $conn->prepare("SELECT id, first_name, last_name, company_name, email, phone, role FROM users");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "<p class='failureMessage'>Error fetching user data: " . htmlspecialchars($e->getMessage()) . "</p>";
    exit();
}
?>
<div class="container">
    <?php include 'sidebar.php'; ?>
    <div class="content">
        <h1>Manage Users</h1>
        <table class="manage-users-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Company Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Role</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $row_user): ?>
                    <tr data-user-id="<?php echo htmlspecialchars($row_user['id']); ?>">
                        <td><?php echo htmlspecialchars($row_user['id']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row_user['first_name']); ?>
                            <?php if ($row_user['id'] !== $user['id']): ?>
                                <span class="edit-icon" data-field="first_name" title="Edit">&#9998;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row_user['last_name']); ?>
                            <?php if ($row_user['id'] !== $user['id']): ?>
                                <span class="edit-icon" data-field="last_name" title="Edit">&#9998;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row_user['company_name']); ?>
                            <?php if ($row_user['id'] !== $user['id']): ?>
                                <span class="edit-icon" data-field="company_name" title="Edit">&#9998;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row_user['email']); ?>
                            <?php if ($row_user['id'] !== $user['id']): ?>
                                <span class="edit-icon" data-field="email" title="Edit">&#9998;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row_user['phone']); ?>
                            <?php if ($row_user['id'] !== $user['id']): ?>
                                <span class="edit-icon" data-field="phone" title="Edit">&#9998;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($row_user['role']); ?>
                            <?php if ($row_user['id'] !== $user['id']): ?>
                                <span class="edit-icon" data-field="role" title="Edit">&#9998;</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row_user['id'] !== $user['id']): ?>
                            	<span class="save-icon">
							        <i class="fa fa-save"></i>
							    </span>
                                <span class="delete-icon">
							        <i class="fa fa-trash"></i>
							    </span>
                            <?php else: ?>
                                <span title="No actions available for your own account.">N/A</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    document.querySelectorAll('.edit-icon[data-field]').forEach(icon => {
        icon.addEventListener('click', function () {
            const field = this.getAttribute('data-field'); // Get the field being edited
            const row = this.closest('tr'); // Get the parent row of the clicked icon
            const cell = this.parentElement; // Get the parent cell (TD)

            // Check if an input or select field already exists to prevent duplication
            if (cell.querySelector('input') || cell.querySelector('select')) return;

            // Get the current text value
            const currentValue = cell.textContent.trim();

            // Create appropriate input element
            let input;
            if (field === 'role') {
                // Create a dropdown for the role field
                input = document.createElement('select');
                input.classList.add('edit-select');
                input.setAttribute('data-user-id', row.getAttribute('data-user-id')); // Associate user ID for later saving
                input.setAttribute('data-field', field); // Associate field for later saving

                // Add options to the dropdown
                const options = ['admin', 'user'];
                options.forEach(option => {
                    const opt = document.createElement('option');
                    opt.value = option;
                    opt.textContent = option.charAt(0).toUpperCase() + option.slice(1);
                    if (option === currentValue.toLowerCase()) opt.selected = true;
                    input.appendChild(opt);
                });
            } else {
                // Create a text input for other fields
                input = document.createElement('input');
                input.type = 'text';
                input.value = currentValue;
                input.classList.add('edit-input');
                input.setAttribute('data-user-id', row.getAttribute('data-user-id')); // Associate user ID for later saving
                input.setAttribute('data-field', field); // Associate field for later saving
            }

            // Hide the pencil icon
            this.style.display = 'none';

            // Replace the text with the input/select field
            cell.innerHTML = '';
            cell.appendChild(input);
            cell.appendChild(this); // Re-add the pencil icon to the cell (hidden for now)

            // Focus the input/select field
            input.focus();
        });
    });
    document.querySelectorAll('.save-icon').forEach(icon => {
        icon.addEventListener('click', function () {
            const row = this.closest('tr'); // Get the row of the clicked save icon
            const userId = row.getAttribute('data-user-id'); // Get the user ID
            const inputs = row.querySelectorAll('input, select'); // Get all editable fields

            // Collect data to send in the AJAX request
            const data = { user_id: userId };

            inputs.forEach(input => {
                const field = input.getAttribute('data-field'); // Get the field name
                data[field] = input.value; // Assign the value to the field
            });

            // Send AJAX request to update the user details
            fetch('../ajax/update_user.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data),
            })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        // Update the table to reflect the saved values
                        inputs.forEach(input => {
                            const field = input.getAttribute('data-field');
                            const cell = input.parentElement;

                            // Replace input field with text value
                            cell.innerHTML = input.value;

                            // Re-add the pencil icon
                            const pencilIcon = document.createElement('span');
                            pencilIcon.classList.add('edit-icon');
                            pencilIcon.setAttribute('data-field', field);
                            pencilIcon.innerHTML = '<i class="fa fa-pencil"></i>';
                            cell.appendChild(pencilIcon);
                        });

                        // Show a success notification
                        const notification = document.createElement('div');
                        notification.classList.add('notification', 'success');
                        notification.textContent = `User #${userId} has been updated successfully.`;
                        document.body.appendChild(notification);

                        // Remove the notification after 3 seconds
                        setTimeout(() => {
                            notification.remove();
                        }, 3000);
                    } else {
                        alert('Failed to update user. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error updating user:', error);
                    alert('An error occurred. Please try again.');
                });
        });
    });
    document.querySelectorAll('.delete-icon').forEach(icon => {
        icon.addEventListener('click', function () {
            const row = this.closest('tr'); // Get the row of the clicked delete icon
            const userId = row.getAttribute('data-user-id'); // Get the user ID

            // First confirmation
            if (confirm('Are you sure you want to delete this user? This action is irreversible.')) {
                // Second confirmation
                if (confirm('This will permanently delete the user. Are you absolutely sure?')) {
                    // Send AJAX request to delete the user
                    fetch('../ajax/delete_user.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ user_id: userId }),
                    })
                        .then(response => response.json())
                        .then(result => {
                            if (result.success) {
                                // Remove the row from the table
                                row.remove();

                                // Show a success notification
                                const notification = document.createElement('div');
                                notification.classList.add('notification', 'success');
                                notification.textContent = `User #${userId} has been deleted successfully.`;
                                document.body.appendChild(notification);

                                // Remove the notification after 3 seconds
                                setTimeout(() => {
                                    notification.remove();
                                }, 3000);
                            } else {
                                alert('Failed to delete the user. Please try again.');
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting user:', error);
                            alert('An error occurred. Please try again.');
                        });
                }
            }
        });
    });
</script>