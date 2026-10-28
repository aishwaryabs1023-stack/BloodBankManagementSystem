
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = 'error';

$allowedGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];

$allowedStatuses = ['Pending', 'Approved', 'Rejected', 'Fulfilled'];

// Register a new blood request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patientId = filter_input(INPUT_POST, 'patient_id', FILTER_VALIDATE_INT);
    $bloodGroup = $_POST['requested_blood_group'] ?? '';
    $units = filter_input(INPUT_POST, 'units_requested', FILTER_VALIDATE_INT);
    $requiredDate = $_POST['required_date'] ?? '';
    $hospitalName = trim($_POST['hospital_name'] ?? '');

    if (!$patientId || !$units || $units < 1 || $units > 100) {
        $message = 'Please select a patient and enter units between 1 and 100.';
    } elseif (!in_array($bloodGroup, $allowedGroups, true)) {
        $message = 'Please select a valid blood group.';
    } elseif ($requiredDate === '') {
        $message = 'Please select the required date.';
    } elseif ($requiredDate < date('Y-m-d')) {
        $message = 'Required date cannot be in the past.';
    } elseif ($hospitalName === '') {
        $message = 'Please enter the hospital name.';
    } else {
        try {
            // Verify patient exists
            $patientStmt = $pdo->prepare(
                "SELECT patient_id FROM Patient WHERE patient_id = ?"
            );
            $patientStmt->execute([$patientId]);

            if (!$patientStmt->fetch()) {
                throw new RuntimeException('Selected patient was not found.');
            }

            $stmt = $pdo->prepare(
                "INSERT INTO BloodRequest
                (patient_id, requested_blood_group, units_requested,
                 required_date, hospital_name, request_status)
                VALUES (?, ?, ?, ?, ?, 'Pending')"
            );

            $stmt->execute([
                $patientId,
                $bloodGroup,
                $units,
                $requiredDate,
                $hospitalName
            ]);

            header("Location: requests.php?added=1");
            exit;

        } catch (Throwable $e) {
            error_log("Blood request error: " . $e->getMessage());
            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to register the blood request. Check your database schema.';
        }
    }
}

// Show success after redirect
if (isset($_GET['added'])) {
    $message = 'Blood request registered successfully!';
    $messageType = 'success';
}

// Fetch patients for dropdown
$patientStmt = $pdo->query(
    "SELECT patient_id, full_name, blood_group, hospital_name
     FROM Patient
     ORDER BY full_name"
);
$patients = $patientStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all blood requests
$requestStmt = $pdo->query(
    "SELECT br.request_id, br.patient_id,
            br.requested_blood_group, br.units_requested,
            br.request_date, br.required_date,
            br.hospital_name, br.request_status,
            p.full_name AS patient_name
     FROM BloodRequest br
     INNER JOIN Patient p ON br.patient_id = p.patient_id
     ORDER BY br.request_id DESC"
);
$requests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Requests - BloodCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f4f6f9;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        h1 {
            color: #b71c1c;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #b71c1c;
            text-decoration: none;
        }

        .back-link:hover {
            text-decoration: underline;
        }

        .card {
            background: white;
            padding: 24px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0 20px;
        }

        .full-width {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-weight: bold;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
            background: white;
        }

        button {
            margin-top: 20px;
            padding: 12px 22px;
            background: #b71c1c;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover {
            background: #8e1515;
        }

        .message {
            padding: 13px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .success {
            background: #e8f5e9;
            color: #1b5e20;
        }

        .error {
            background: #ffebee;
            color: #b71c1c;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
            white-space: nowrap;
        }

        th {
            background: #b71c1c;
            color: white;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending {
            background: #fff3cd;
            color: #856404;
        }

        .approved {
            background: #d4edda;
            color: #155724;
        }

        .rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .fulfilled {
            background: #d1ecf1;
            color: #0c5460;
        }

        @media (max-width: 650px) {
            body {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .full-width {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back-link" href="../dashboard.php">
        &larr; Back to Dashboard
    </a>

    <h1>Blood Requests Management</h1>
    <p class="subtitle">
        Register requests for patients and monitor their status.
    </p>

    <?php if ($message !== ''): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Register a Blood Request</h2>

        <form method="POST" action="requests.php">
            <div class="form-grid">

                <div class="full-width">
                    <label for="patient_id">Select Patient *</label>
                    <select name="patient_id" id="patient_id" required>
                        <option value="">-- Select Patient --</option>

                        <?php foreach ($patients as $patient): ?>
                            <option value="<?= (int) $patient['patient_id'] ?>">
                                <?= htmlspecialchars($patient['full_name']) ?>
                                — <?= htmlspecialchars($patient['blood_group']) ?>
                                (<?= htmlspecialchars($patient['hospital_name'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="requested_blood_group">Required Blood Group *</label>
                    <select
                        name="requested_blood_group"
                        id="requested_blood_group"
                        required
                    >
                        <option value="">-- Select Blood Group --</option>
                        <?php foreach ($allowedGroups as $group): ?>
                            <option value="<?= htmlspecialchars($group) ?>">
                                <?= htmlspecialchars($group) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="units_requested">Units Required *</label>
                    <input
                        type="number"
                        name="units_requested"
                        id="units_requested"
                        min="1"
                        max="100"
                        value="1"
                        required
                    >
                </div>

                <div>
                    <label for="required_date">Required Date *</label>
                    <input
                        type="date"
                        name="required_date"
                        id="required_date"
                        min="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div>
                    <label for="hospital_name">Hospital Name *</label>
                    <input
                        type="text"
                        name="hospital_name"
                        id="hospital_name"
                        maxlength="150"
                        required
                    >
                </div>

            </div>

            <button type="submit">Register Blood Request</button>
        </form>
    </div>

    <div class="card">
        <h2>Blood Request History</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Request ID</th>
                        <th>Patient</th>
                        <th>Blood Group</th>
                        <th>Units</th>
                        <th>Request Date</th>
                        <th>Required Date</th>
                        <th>Hospital</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($requests) > 0): ?>
                    <?php foreach ($requests as $request): ?>
                        <?php
                        $status = $request['request_status'];
                        $statusClass = strtolower($status);
                        ?>
                        <tr>
                            <td><?= (int) $request['request_id'] ?></td>
                            <td><?= htmlspecialchars($request['patient_name']) ?></td>
                            <td><?= htmlspecialchars($request['requested_blood_group']) ?></td>
                            <td><?= (int) $request['units_requested'] ?></td>
                            <td><?= htmlspecialchars($request['request_date'] ?? '') ?></td>
                            <td><?= htmlspecialchars($request['required_date'] ?? '') ?></td>
                            <td><?= htmlspecialchars($request['hospital_name'] ?? '') ?></td>
                            <td>
                                <span class="status <?= htmlspecialchars($statusClass) ?>">
                                    <?= htmlspecialchars($status) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No blood requests found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
