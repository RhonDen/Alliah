<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
requireRole('admin');

$flash = getFlashMessage();
$services = getServices($pdo);
$filters = [];

if (!empty($_GET['date']) && normalizeDate($_GET['date']) !== null) $filters['date'] = $_GET['date'];
if (!empty($_GET['service']) && toPositiveInt($_GET['service']) !== null) $filters['service'] = (string) toPositiveInt($_GET['service']);
if (!empty($_GET['status']) && isValidAppointmentStatus($_GET['status'])) $filters['status'] = $_GET['status'];

if (isPostRequest() && isset($_POST['action'])) {
    $appointmentId = toPositiveInt($_POST['appointment_id'] ?? null);
    $status = trim($_POST['status'] ?? '');
    $message = $_POST['message'] ?? null;

    if (!isValidCsrfToken($_POST['_token'] ?? null)) {
        setFlashMessage('error', 'Session expired. Please try again.');
    } elseif ($appointmentId === null) {
        setFlashMessage('error', 'Invalid appointment ID.');
    } else {
        $appointment = getAppointmentById($pdo, $appointmentId);
        if ($appointment === null) {
            setFlashMessage('error', 'Appointment not found.');
        } elseif ($appointment['appointment_date'] < date('Y-m-d')) {
            setFlashMessage('error', 'Cannot update appointments from past dates.');
        } else {
            $result = updateAppointmentStatus($pdo, $appointmentId, $status, $message);
            setFlashMessage($result['success'] ? 'success' : 'error', $result['success'] ? 'Appointment updated.' : implode(' ', $result['errors']));
        }
    }

    $query = http_build_query($filters);
    $redirectUrl = 'appointments.php' . ($query ? '?' . $query : '');
    header('Location: ' . $redirectUrl);
    exit;
}

$appointments = getAllAppointments($pdo, $filters);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Appointments - <?php echo e(APP_NAME); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/global.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<?php include '../../includes/public/partials/nav-admin.php'; ?>

<div class="admin-container admin-layout">
    <main>
        <?php if ($flash): ?>
            <div class="<?php echo e($flash['type']); ?>-message"><?php echo e($flash['message']); ?></div>
        <?php endif; ?>

        <form method="GET" class="filter-form">
            <input type="date" name="date" value="<?php echo e($filters['date'] ?? ''); ?>" placeholder="Filter by date">
            <select name="service">
                <option value="">All Services</option>
                <?php foreach ($services as $s): ?>
                    <option value="<?php echo e($s['id']); ?>" <?php echo (isset($filters['service']) && $filters['service'] == $s['id']) ? 'selected' : ''; ?>><?php echo e($s['name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="status">
                <option value="">All Statuses</option>
                <?php foreach (['pending', 'approved', 'rejected', 'completed', 'no_show'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo (isset($filters['status']) && $filters['status'] == $st) ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Filter</button>
            <a href="appointments.php" class="reset">Reset</a>
        </form>

        <?php if (!$appointments): ?>
            <div class="card" style="text-align: center; padding: 2rem;">
                <i class="fas fa-calendar-times" style="font-size: 2rem; color: var(--gray-400); margin-bottom: 0.5rem;"></i>
                <p>No appointments matched.</p>
            </div>
        <?php else: ?>
            <?php
                // Group appointments by date
                $appointmentsByDate = [];
                foreach ($appointments as $app) {
                    $date = $app['appointment_date'];
                    if (!isset($appointmentsByDate[$date])) {
                        $appointmentsByDate[$date] = [];
                    }
                    $appointmentsByDate[$date][] = $app;
                }
                // Sort by date in descending order (latest first)
                krsort($appointmentsByDate);
            ?>
            <style>
                .date-section { 
                    margin-bottom: 2rem; 
                }
                .date-header { 
                    background: linear-gradient(135deg, #1f816a, #48a48f);
                    color: white; 
                    padding: 1.25rem 1.5rem; 
                    border-radius: 1rem; 
                    margin-bottom: 1rem; 
                    cursor: pointer; 
                    display: flex; 
                    justify-content: space-between; 
                    align-items: center; 
                    font-weight: 700;
                    font-size: 1rem;
                    box-shadow: 0 4px 12px rgba(31, 129, 106, 0.15);
                    transition: all 0.3s ease;
                }
                .date-header:hover { 
                    transform: translateY(-2px);
                    box-shadow: 0 12px 28px rgba(31, 129, 106, 0.25);
                    background: linear-gradient(135deg, #166b57, #1f816a);
                }
                .date-header:active {
                    transform: translateY(0);
                }
                .date-header .toggle-icon { 
                    transition: transform 0.3s ease;
                    font-size: 1.1rem;
                    flex-shrink: 0;
                    margin-left: 1rem;
                }
                .date-header.collapsed .toggle-icon { 
                    transform: rotate(-90deg); 
                }
                .date-appointments { 
                    overflow-x: auto; 
                }
                .date-appointments.hidden { 
                    display: none; 
                }
                
                /* Mobile responsive – tablets and phones */
                @media (max-width: 1024px) {
                    .date-header {
                        padding: 1rem 1.25rem;
                        font-size: 0.95rem;
                    }
                }
                
                @media (max-width: 768px) {
                    .date-section { 
                        margin-bottom: 1.5rem; 
                    }
                    .date-header { 
                        padding: 1rem; 
                        font-size: 0.9rem;
                        gap: 0.75rem;
                    }
                    .date-header span:first-child {
                        flex: 1;
                        word-break: break-word;
                    }
                    .date-header .toggle-icon {
                        margin-left: 0.5rem;
                        font-size: 1rem;
                    }
                    .appointments-table {
                        font-size: 0.8rem;
                    }
                    .appointments-table th,
                    .appointments-table td {
                        padding: 0.75rem 0.5rem;
                    }
                    .appointments-table th {
                        font-size: 0.7rem;
                    }
                }
                
                @media (max-width: 480px) {
                    .date-header {
                        padding: 0.85rem 0.75rem;
                        font-size: 0.85rem;
                    }
                    .appointments-table {
                        font-size: 0.75rem;
                    }
                    .appointments-table th,
                    .appointments-table td {
                        padding: 0.5rem 0.25rem;
                    }
                    .appointments-table th {
                        font-size: 0.65rem;
                    }
                }
            </style>
            <?php foreach ($appointmentsByDate as $date => $dateAppts): ?>
                <div class="date-section">
                    <div class="date-header" onclick="toggleDateSection(this)">
                        <span><?php echo date('l, F j, Y', strtotime($date)); ?> (<?php echo count($dateAppts); ?> appointment<?php echo count($dateAppts) !== 1 ? 's' : ''; ?>)</span>
                        <span class="toggle-icon">▼</span>
                    </div>
                    <div class="date-appointments">
                        <div style="overflow-x: auto;">
                            <table class="appointments-table">
                                <thead>
                                    <tr>
                                        <th>Patient</th>
                                        <th>Service</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Admin Note</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($dateAppts as $app): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo e($app['first_name'] . ' ' . $app['last_name']); ?></strong><br>
                                            <small style="color: var(--gray-500);"><?php echo e($app['email']); ?></small>
                                        </td>
                                        <td><?php echo e($app['service_name']); ?></td>
                                        <td><?php echo e(formatAppointmentTime($app['appointment_time'])); ?></td>
                                        <td><span class="status-badge status-<?php echo e($app['status']); ?>"><?php echo e(ucfirst($app['status'])); ?></span></td>
                                        <td><?php echo e($app['admin_message'] ?? '—'); ?></td>
                                        <td>
                                            <form method="POST" style="display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center;">
                                                <?php echo csrfField(); ?>
                                                <input type="hidden" name="appointment_id" value="<?php echo e($app['id']); ?>">
                                                <?php $isPastDate = $date < date('Y-m-d'); ?>
                                                <select name="status" class="action-select" <?php echo $isPastDate ? 'disabled' : ''; ?>>
                                                    <option value="pending" <?php echo $app['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="approved" <?php echo $app['status'] === 'approved' ? 'selected' : ''; ?>>Approve</option>
                                                    <option value="rejected" <?php echo $app['status'] === 'rejected' ? 'selected' : ''; ?>>Reject</option>
                                                    <option value="completed" <?php echo $app['status'] === 'completed' ? 'selected' : ''; ?>>Complete</option>
                                                    <option value="no_show" <?php echo $app['status'] === 'no_show' ? 'selected' : ''; ?>>No‑Show</option>
                                                </select>
                                                <input type="text" name="message" class="action-input" placeholder="Optional note" value="<?php echo e($app['admin_message'] ?? ''); ?>" <?php echo $isPastDate ? 'disabled' : ''; ?>>
                                                <button type="submit" name="action" value="update" class="action-btn" <?php echo $isPastDate ? 'disabled' : ''; ?>>Update</button>
                                                <?php if ($isPastDate): ?>
                                                    <small style="color: var(--gray-500); font-size: 0.8rem;">Past date - cannot update</small>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </main>
</div>


<script>
    function toggleDateSection(headerElement) {
        const appointmentsDiv = headerElement.nextElementSibling;
        headerElement.classList.toggle('collapsed');
        appointmentsDiv.classList.toggle('hidden');
    }
</script>
</body>
</html>