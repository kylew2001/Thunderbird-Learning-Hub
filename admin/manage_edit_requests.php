<?php
/**
 * Manage Edit Requests Page
 * Admin interface for reviewing and approving/declining edit requests
 * Only accessible by admin users
 * Created: 2025-11-03 (Edit Request Management)
 */

require_once dirname(__DIR__) . '/includes/include_path.php';
require_app_file('auth_check.php');
require_app_file('db_connect.php');
require_app_file('user_helpers.php');

// Only allow admin users
if (!is_admin()) {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

$page_title = 'Manage Edit Requests';
$success_message = '';
$error_message = '';

// Check if edit requests table exists
$edit_requests_table_exists = false;
try {
    $pdo->query("SELECT id FROM edit_requests LIMIT 1");
    $edit_requests_table_exists = true;
} catch (PDOException $e) {
    $edit_requests_table_exists = false;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $edit_requests_table_exists) {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

    switch ($action) {
        case 'approve':
            $new_name = isset($_POST['new_name']) ? trim($_POST['new_name']) : '';
            $admin_note = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : '';

            if ($request_id <= 0) {
                $error_message = 'Invalid request ID.';
            } else {
                // If new_name is empty, use the requested name from the database
                if (empty($new_name)) {
                    try {
                        $stmt = $pdo->prepare("SELECT requested_name FROM edit_requests WHERE id = ?");
                        $stmt->execute([$request_id]);
                        $request_data = $stmt->fetch();
                        if ($request_data) {
                            $new_name = $request_data['requested_name'];
                        }
                    } catch (PDOException $e) {
                        $error_message = 'Error fetching requested name: ' . $e->getMessage();
                    }
                }

                // Now validate the new_name (after potentially fetching from database)
                if (empty($new_name)) {
                    $error_message = 'New name is required.';
                } elseif (strlen($new_name) > 255) {
                    $error_message = 'New name must be 255 characters or less.';
                } else {
                    try {
                        // Start transaction
                        $pdo->beginTransaction();

                        // Get the request details
                        $stmt = $pdo->prepare("SELECT * FROM edit_requests WHERE id = ?");
                        $stmt->execute([$request_id]);
                        $request = $stmt->fetch();

                        if (!$request) {
                            $error_message = 'Edit request not found.';
                            $pdo->rollBack();
                        } else {
                            // Update the request status
                            $stmt = $pdo->prepare("
                                UPDATE edit_requests
                                SET status = 'approved',
                                    admin_note = ?,
                                    reviewed_by = ?,
                                    reviewed_at = CURRENT_TIMESTAMP
                                WHERE id = ?
                            ");
                            $stmt->execute([$admin_note, $_SESSION['user_id'], $request_id]);

                            // Update the actual item name
                            if ($request['item_type'] === 'category') {
                                $update_stmt = $pdo->prepare("UPDATE categories SET name = ? WHERE id = ?");
                                $update_stmt->execute([$new_name, $request['item_id']]);
                            } elseif ($request['item_type'] === 'subcategory') {
                                $update_stmt = $pdo->prepare("UPDATE subcategories SET name = ? WHERE id = ?");
                                $update_stmt->execute([$new_name, $request['item_id']]);
                            }

                            $pdo->commit();
                            $success_message = 'Edit request approved successfully! The ' . $request['item_type'] . ' name has been updated.';
                        }
                    } catch (PDOException $e) {
                        $pdo->rollBack();
                        error_log("Database Error: " . $e->getMessage());
                        $error_message = 'Error approving request: ' . $e->getMessage();
                    }
                }
            }
            break;

        case 'decline':
            $admin_note = isset($_POST['admin_note']) ? trim($_POST['admin_note']) : '';

            if ($request_id <= 0) {
                $error_message = 'Invalid request ID.';
            } elseif (strlen($admin_note) > 500) {
                $error_message = 'Admin note must be 500 characters or less.';
            } else {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE edit_requests
                        SET status = 'declined',
                            admin_note = ?,
                            reviewed_by = ?,
                            reviewed_at = CURRENT_TIMESTAMP
                        WHERE id = ?
                    ");
                    $stmt->execute([$admin_note, $_SESSION['user_id'], $request_id]);
                    $success_message = 'Edit request declined successfully.';
                } catch (PDOException $e) {
                    error_log("Database Error: " . $e->getMessage());
                    $error_message = 'Error declining request: ' . $e->getMessage();
                }
            }
            break;
    }
}

// Fetch all edit requests
$requests = [];
if ($edit_requests_table_exists) {
    try {
        $stmt = $pdo->query("
            SELECT er.*,
                   u.name as requester_name, u.color as requester_color,
                   reviewer.name as reviewer_name,
                   (SELECT COUNT(*) FROM edit_requests er2 WHERE er2.item_type = er.item_type AND er2.item_id = er.item_id AND er2.status = 'pending') as other_pending_count
            FROM edit_requests er
            LEFT JOIN users u ON er.user_id = u.id
            LEFT JOIN users reviewer ON er.reviewed_by = reviewer.id
            ORDER BY
                CASE WHEN er.status = 'pending' THEN 1 ELSE 2 END,
                er.created_at DESC
        ");
        $requests = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        $error_message = 'Error fetching requests: ' . $e->getMessage();
    }
}

require_app_file('header.php');
?>

<div class="container">
    <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span>></span>
        <span class="current">Manage Edit Requests</span>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📝 Manage Edit Requests</h2>
            <div class="card-actions">
                <a href="index.php" class="btn btn-secondary">← Return to Home</a>
            </div>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="success-message" style="margin: 20px; padding: 12px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 6px; color: #155724;">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="error-message" style="margin: 20px; padding: 12px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24;">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!$edit_requests_table_exists): ?>
            <div class="card-content" style="padding: 20px;">
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; padding: 16px; margin-bottom: 20px;">
                    <h3 style="color: #856404; margin: 0 0 8px 0;">⚠️ Edit Requests System Not Available</h3>
                    <p style="color: #856404; margin: 0;">The edit requests table doesn't exist in your database. Please import the following SQL file first:</p>
                    <div style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; margin: 8px 0; font-family: monospace; font-size: 12px;">
                        database/create_edit_requests_table.sql
                    </div>
                </div>
            </div>
        <?php elseif (empty($requests)): ?>
            <div class="card-content" style="padding: 40px; text-align: center;">
                <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
                <h3>No Edit Requests Found</h3>
                <p>There are no edit requests to review. Users will see edit requests here when they submit them.</p>
            </div>
        <?php else: ?>
            <div class="card-content" style="padding: 0;">
                <?php
                $status_colors = [
                    'pending' => '#ffc107',
                    'approved' => '#28a745',
                    'declined' => '#dc3545'
                ];
                $status_icons = [
                    'pending' => '⏳️',
                    'approved' => '✅',
                    'declined' => '❌'
                ];
                ?>

                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">ID</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Type</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Current Name</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Requested Name</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Reason</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Requester</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Status</th>
                            <th style="padding: 12px; text-align: left; font-weight: 600; color: #495057;">Created</th>
                            <th style="padding: 12px; text-align: center; font-weight: 600; color: #495057;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr style="border-bottom: 1px solid #dee2e6; <?php echo $request['status'] === 'pending' ? 'background: #fff3cd;' : ''; ?>">
                                <td style="padding: 12px;"><?php echo $request['id']; ?></td>
                                <td style="padding: 12px;">
                                    <span style="text-transform: capitalize; background: #e9ecef; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                        <?php echo htmlspecialchars($request['item_type']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; font-weight: 500;"><?php echo htmlspecialchars($request['current_name']); ?></td>
                                <td style="padding: 12px; font-weight: 500;"><?php echo htmlspecialchars($request['requested_name']); ?></td>
                                <td style="padding: 12px; max-width: 200px;">
                                    <div style="font-size: 12px; color: #6c757d; word-break: break-word;" title="<?php echo htmlspecialchars($request['reason'] ?? ''); ?>">
                                        <?php
                                        $reason = $request['reason'] ?? '';
                                        if (strlen($reason) > 50) {
                                            echo htmlspecialchars(substr($reason, 0, 47)) . '...';
                                        } else {
                                            echo htmlspecialchars($reason ?: 'No reason provided');
                                        }
                                        ?>
                                    </div>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="color: <?php echo htmlspecialchars($request['requester_color']); ?>;">
                                        <?php echo htmlspecialchars($request['requester_name']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px;">
                                    <span style="background: <?php echo $status_colors[$request['status']]; ?>; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">
                                        <?php echo $status_icons[$request['status']]; ?> <?php echo ucfirst($request['status']); ?>
                                    </span>
                                </td>
                                <td style="padding: 12px; color: #6c757d; font-size: 12px;">
                                    <?php echo date('M j, Y H:i', strtotime($request['created_at'])); ?>
                                </td>
                                <td style="padding: 12px; text-align: center;">
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <div style="display: flex; gap: 4px; justify-content: center;">
                                            <button type="button" class="btn btn-sm" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showApproveModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['item_type']); ?>', '<?php echo htmlspecialchars($request['current_name']); ?>', '<?php echo htmlspecialchars($request['requested_name']); ?>', '<?php echo htmlspecialchars($request['reason'] ?? ''); ?>')">Approve</button>
                                            <button type="button" class="btn btn-sm" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 4px; font-size: 11px;" onclick="showDeclineModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['item_type']); ?>', '<?php echo htmlspecialchars($request['current_name']); ?>', '<?php echo htmlspecialchars($request['requested_name']); ?>', '<?php echo htmlspecialchars($request['requester_name']); ?>')">Decline</button>
                                        </div>
                                    <?php elseif ($request['status'] === 'approved'): ?>
                                        <span style="color: #28a745; font-size: 11px;">
                                            Approved by <?php echo htmlspecialchars($request['reviewer_name'] ?? 'Unknown'); ?>
                                            <?php if ($request['reviewed_at']): ?>
                                                (<?php echo date('M j, Y', strtotime($request['reviewed_at'])); ?>)
                                            <?php endif; ?>
                                        </span>
                                    <?php elseif ($request['status'] === 'declined'): ?>
                                        <span style="color: #dc3545; font-size: 11px;">
                                            Declined by <?php echo htmlspecialchars($request['reviewer_name'] ?? 'Unknown'); ?>
                                            <?php if ($request['reviewed_at']): ?>
                                                (<?php echo date('M j, Y', strtotime($request['reviewed_at'])); ?>)
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (count($requests) > 0): ?>
                    <div style="padding: 16px; background: #f8f9fa; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">
                        <strong>📋 Request Summary:</strong>
                        <?php
                        $pending_count = count(array_filter($requests, fn($r) => $r['status'] === 'pending'));
                        $approved_count = count(array_filter($requests, fn($r) => $r['status'] === 'approved'));
                        $declined_count = count(array_filter($requests, fn($r) => $r['status'] === 'declined'));
                        echo $pending_count . ' pending, ' . $approved_count . ' approved, ' . $declined_count . ' declined';
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Approve Request Modal -->
<div id="approveModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 16px 0;">✅ Approve Edit Request</h3>
        <form method="POST" action="manage_edit_requests.php">
            <input type="hidden" name="action" value="approve">
            <input type="hidden" id="approve_request_id" name="request_id">

            <div style="margin-bottom: 16px; background: #f8f9fa; padding: 12px; border-radius: 4px;">
                <div style="margin-bottom: 8px;"><strong>Item Type:</strong> <span id="approve_item_type"></span></div>
                <div style="margin-bottom: 8px;"><strong>Current Name:</strong> <span id="approve_current_name"></span></div>
                <div style="margin-bottom: 8px;"><strong>Requested Name:</strong> <span id="approve_requested_name" style="color: #28a745; font-weight: 500;"></span></div>
                <div><strong>Reason:</strong> <span id="approve_reason"></span></div>
            </div>

            <div style="margin-bottom: 16px;">
                <label for="new_name" class="form-label">Final Name:</label>
                <input type="text" id="new_name" name="new_name" class="form-input" placeholder="Enter the final name for this item">
                <div class="form-hint">Pre-filled with the requested name. You can modify it if needed.</div>
            </div>

            <div style="margin-bottom: 16px;">
                <label for="admin_note" class="form-label">Admin Note (optional):</label>
                <textarea id="admin_note" name="admin_note" class="form-input" rows="3" placeholder="Add a note about this approval..."></textarea>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideApproveModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Approve Request</button>
            </div>
        </form>
    </div>
</div>

<!-- Decline Request Modal -->
<div id="declineModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
    <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 24px; border-radius: 8px; width: 90%; max-width: 500px;">
        <h3 style="margin: 0 0 16px 0;">❌ Decline Edit Request</h3>
        <form method="POST" action="manage_edit_requests.php">
            <input type="hidden" name="action" value="decline">
            <input type="hidden" id="decline_request_id" name="request_id">

            <div style="margin-bottom: 16px; background: #fff3cd; border: 1px solid #ffeaa7; padding: 12px; border-radius: 4px;">
                <div style="margin-bottom: 8px;"><strong>Item Type:</strong> <span id="decline_item_type"></span></div>
                <div style="margin-bottom: 8px;"><strong>Current Name:</strong> <span id="decline_current_name"></span></div>
                <div style="margin-bottom: 8px;"><strong>Requested Name:</strong> <span id="decline_requested_name" style="color: #dc3545; font-weight: 500;"></span></div>
                <div><strong>Requester:</strong> <span id="decline_requester_name"></span></div>
            </div>

            <div style="margin-bottom: 16px;">
                <label for="decline_admin_note" class="form-label">Reason for Decline *</label>
                <textarea id="decline_admin_note" name="admin_note" class="form-input" rows="3" required placeholder="Explain why this request is being declined..."></textarea>
            </div>

            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button type="button" onclick="hideDeclineModal()" style="padding: 8px 16px; border: 1px solid #ddd; background: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                <button type="submit" style="padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer;">Decline Request</button>
            </div>
        </form>
    </div>
</div>

<script>
function showApproveModal(requestId, itemType, currentName, requestedName, reason) {
    document.getElementById('approve_request_id').value = requestId;
    document.getElementById('approve_item_type').textContent = itemType;
    document.getElementById('approve_current_name').textContent = currentName;
    document.getElementById('approve_requested_name').textContent = requestedName;
    document.getElementById('approve_reason').textContent = reason || 'No reason provided';
    document.getElementById('new_name').value = requestedName;
    document.getElementById('approveModal').style.display = 'block';
}

function hideApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
}

function showDeclineModal(requestId, itemType, currentName, requestedName, requesterName) {
    document.getElementById('decline_request_id').value = requestId;
    document.getElementById('decline_item_type').textContent = itemType;
    document.getElementById('decline_current_name').textContent = currentName;
    document.getElementById('decline_requested_name').textContent = requestedName;
    document.getElementById('decline_requester_name').textContent = requesterName;
    document.getElementById('declineModal').style.display = 'block';
}

function hideDeclineModal() {
    document.getElementById('declineModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target.id === 'approveModal') {
        hideApproveModal();
    } else if (event.target.id === 'declineModal') {
        hideDeclineModal();
    }
}
</script>

<?php require_app_file('footer.php'); ?>