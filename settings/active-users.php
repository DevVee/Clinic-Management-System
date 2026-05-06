<?php
try {
    $stmt = $conn->prepare("SELECT u.id, u.name, u.admin_category, s.last_active 
                            FROM sessions s 
                            JOIN users u ON s.user_id = u.id 
                            WHERE s.last_active >= NOW() - INTERVAL 30 MINUTE");
    $stmt->execute();
    $active_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("[SSCMS Active Users] Fetch error: " . $e->getMessage());
    $active_users = [];
}
?>

<div class="tab-pane fade" id="active-users" role="tabpanel" aria-labelledby="active-users-tab">
    <div class="card fade-in">
        <div class="card-header">
            <i class="fas fa-users"></i> Active Users
        </div>
        <div class="card-body">
            <?php if (empty($active_users)): ?>
                <p class="text-muted">No active users at this time.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Last Active</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_users as $user): ?>
                                <tr>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['admin_category']) ?></td>
                                    <td><?= date('Y-m-d H:i:s', strtotime($user['last_active'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>