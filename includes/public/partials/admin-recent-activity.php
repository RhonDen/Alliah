<?php
$recentLimit = isset($recentLimit) ? max(1, (int) $recentLimit) : 15;
$recentAppointments = is_array($recentAppointments ?? null) ? $recentAppointments : [];

$statusLabels = [
    'pending' => 'Pending',
    'approved' => 'Approved',
    'rejected' => 'Rejected',
    'completed' => 'Completed',
    'no_show' => 'No-Show',
];
$primaryStatuses = ['pending', 'approved', 'rejected'];
$secondaryStatuses = ['completed', 'no_show'];

$groupedAppointments = [];
foreach ($statusLabels as $status => $label) {
    $groupedAppointments[$status] = [];
}

foreach ($recentAppointments as $appointment) {
    $status = $appointment['status'] ?? '';
    if (!isset($groupedAppointments[$status])) {
        $groupedAppointments[$status] = [];
        $statusLabels[$status] = ucfirst(str_replace('_', ' ', $status));
    }
    $groupedAppointments[$status][] = $appointment;
}

$hasRecentAppointments = false;
foreach ($groupedAppointments as $appointments) {
    if (!empty($appointments)) {
        $hasRecentAppointments = true;
        break;
    }
}
?>
<div class="recent-status-sections">
    <h3>Today's Appointments — <?php echo e(date('F j, Y')); ?></h3>

    <?php if (!$hasRecentAppointments): ?>
        <p>No appointments scheduled for today.</p>
    <?php else: ?>
        <?php foreach ($primaryStatuses as $status): ?>
            <?php $label = $statusLabels[$status]; ?>
            <div class="recent-status-group" style="margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <h4 style="margin: 0;"><?php echo e($label); ?></h4>
                    <span class="status-badge status-<?php echo e($status); ?>"><?php echo count($groupedAppointments[$status]); ?></span>
                </div>
                <?php if (empty($groupedAppointments[$status])): ?>
                    <p style="color: var(--gray-600); margin: 0;">No <?php echo strtolower(e($label)); ?> appointments today.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table class="appointments-table">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Date</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($groupedAppointments[$status] as $appointment): ?>
                                    <tr>
                                        <td><?php echo e($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                        <td><?php echo e($appointment['service_name']); ?></td>
                                        <td><?php echo e($appointment['appointment_date']); ?></td>
                                        <td><?php echo e(formatAppointmentTime($appointment['appointment_time'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($secondaryStatuses as $status): ?>
            <?php if (empty($groupedAppointments[$status])) continue; ?>
            <?php $label = $statusLabels[$status]; ?>
            <div class="recent-status-group" style="margin-bottom: 1.5rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.75rem;">
                    <h4 style="margin: 0;"><?php echo e($label); ?></h4>
                    <span class="status-badge status-<?php echo e($status); ?>"><?php echo count($groupedAppointments[$status]); ?></span>
                </div>
                <div style="overflow-x: auto;">
                    <table class="appointments-table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Service</th>
                                <th>Date</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($groupedAppointments[$status] as $appointment): ?>
                                <tr>
                                    <td><?php echo e($appointment['first_name'] . ' ' . $appointment['last_name']); ?></td>
                                    <td><?php echo e($appointment['service_name']); ?></td>
                                    <td><?php echo e($appointment['appointment_date']); ?></td>
                                    <td><?php echo e(formatAppointmentTime($appointment['appointment_time'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
