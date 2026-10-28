
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $dateOfBirth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $bloodGroup = $_POST['blood_group'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $hospitalName = trim($_POST['hospital_name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    $allowedGenders = ['Male', 'Female', 'Other'];
    $allowedBloodGroups = [
        'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'
    ];

    if (
        $fullName === '' ||
        $dateOfBirth === '' ||
        $gender === '' ||
        $bloodGroup === '' ||
        $hospitalName === ''
    ) {
        $message = 'Please fill in all required fields.';
    } elseif (!in_array($gender, $allowedGenders, true)) {
        $message = 'Please select a valid gender.';
    } elseif (!in_array($bloodGroup, $allowedBloodGroups, true)) {
        $message = 'Please select a valid blood group.';
    } elseif ($dateOfBirth > date('Y-m-d')) {
        $message = 'Date of birth cannot be in the future.';
    } elseif ($phone !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $message = 'Please enter a valid phone number.';
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO Patient
                (full_name, date_of_birth, gender, blood_group,
                 phone, hospital_name, address)
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $fullName,
                $dateOfBirth,
                $gender,
                $bloodGroup,
                $phone !== '' ? $phone : null,
                $hospitalName,
                $address !== '' ? $address : null
            ]);

            header("Location: patients.php?added=1");
            exit;

        } catch (PDOException $e) {
            error_log("Patient registration error: " . $e->getMessage());
            $message = 'Unable to register patient. Please check the database table and try again.';
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Patient registered successfully!';
    $messageType = 'success';
}

$stmt = $pdo->query(
    "SELECT patient_id, full_name, date_of_birth, gender,
            blood_group, phone, hospital_name, address
     FROM Patient
     ORDER BY patient_id DESC"
);

$patients = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Patient Management - BloodCare</title>

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
            max-width: 1150px;
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

        .card {
            background: #fff;
            padding: 25px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
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

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
            background: #fff;
        }

        textarea {
            resize: vertical;
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
        }

        th {
            background: #b71c1c;
            color: #fff;
        }

        tr:hover {
            background: #fafafa;
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

    <h1>Patient Management</h1>
    <p class="subtitle">
        Register patients and view their details.
    </p>

    <?php if ($message !== ''): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Register a New Patient</h2>

        <form method="POST" action="patients.php">
            <div class="form-grid">

                <div>
                    <label for="full_name">Full Name *</label>
                    <input
                        type="text"
                        id="full_name"
                        name="full_name"
                        maxlength="150"
                        required
                    >
                </div>

                <div>
                    <label for="date_of_birth">Date of Birth *</label>
                    <input
                        type="date"
                        id="date_of_birth"
                        name="date_of_birth"
                        max="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

                <div>
                    <label for="gender">Gender *</label>
                    <select id="gender" name="gender" required>
                        <option value="">-- Select Gender --</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div>
                    <label for="blood_group">Blood Group *</label>
                    <select id="blood_group" name="blood_group" required>
                        <option value="">-- Select Blood Group --</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>

                <div>
                    <label for="phone">Phone Number</label>
                    <input
                        type="tel"
                        id="phone"
                        name="phone"
                        maxlength="20"
                    >
                </div>

                <div>
                    <label for="hospital_name">Hospital Name *</label>
                    <input
                        type="text"
                        id="hospital_name"
                        name="hospital_name"
                        maxlength="150"
                        required
                    >
                </div>

                <div class="full-width">
                    <label for="address">Address</label>
                    <textarea
                        id="address"
                        name="address"
                        rows="3"
                    ></textarea>
                </div>

            </div>

            <button type="submit">Register Patient</button>
        </form>
    </div>

    <div class="card">
        <h2>Registered Patients</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Patient Name</th>
                        <th>Date of Birth</th>
                        <th>Gender</th>
                        <th>Blood Group</th>
                        <th>Phone</th>
                        <th>Hospital</th>
                        <th>Address</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($patients) > 0): ?>
                    <?php foreach ($patients as $patient): ?>
                        <tr>
                            <td><?= (int) $patient['patient_id'] ?></td>
                            <td><?= htmlspecialchars($patient['full_name']) ?></td>
                            <td><?= htmlspecialchars($patient['date_of_birth'] ?? '') ?></td>
                            <td><?= htmlspecialchars($patient['gender'] ?? '') ?></td>
                            <td><?= htmlspecialchars($patient['blood_group']) ?></td>
                            <td><?= htmlspecialchars($patient['phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($patient['hospital_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($patient['address'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No patients registered yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
