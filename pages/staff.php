
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = 'error';

// Only administrators can manage staff accounts
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'Admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isAdmin) {
        $message = 'Only administrators can register staff accounts.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $joiningDate = $_POST['joining_date'] ?? '';

        if (
            $fullName === '' ||
            $email === '' ||
            $password === '' ||
            $designation === '' ||
            $joiningDate === ''
        ) {
            $message = 'Please fill in all required fields.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = 'Please enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $message = 'Password must contain at least 8 characters.';
        } elseif ($joiningDate > date('Y-m-d')) {
            $message = 'Joining date cannot be in the future.';
        } else {
            try {
                $pdo->beginTransaction();

                // Create the login account
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $userStmt = $pdo->prepare(
                    "INSERT INTO Users (full_name, email, password_hash, role)
                     VALUES (?, ?, ?, 'Staff')"
                );

                $userStmt->execute([
                    $fullName,
                    $email,
                    $passwordHash
                ]);

                $userId = (int) $pdo->lastInsertId();

                // Create the staff profile
                $staffStmt = $pdo->prepare(
                    "INSERT INTO Staff
                     (user_id, phone, designation, joining_date)
                     VALUES (?, ?, ?, ?)"
                );

                $staffStmt->execute([
                    $userId,
                    $phone !== '' ? $phone : null,
                    $designation,
                    $joiningDate
                ]);

                $pdo->commit();

                header("Location: staff.php?added=1");
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log("Staff registration error: " . $e->getMessage());

                if (
                    $e instanceof PDOException &&
                    $e->getCode() === '23000'
                ) {
                    $message = 'This email address may already be registered.';
                } else {
                    $message = 'Unable to register staff. Please check the database tables.';
                }
            }
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Staff account registered successfully!';
    $messageType = 'success';
}

// Fetch staff profiles and their user accounts
$stmt = $pdo->query(
    "SELECT s.staff_id, s.user_id, s.phone, s.designation,
            s.joining_date, u.full_name, u.email, u.role
     FROM Staff s
     LEFT JOIN Users u ON u.user_id = s.user_id
     ORDER BY s.staff_id DESC"
);

$staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Management - BloodCare</title>

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

        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #b71c1c;
            text-decoration: none;
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

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
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
            color: white;
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

    <h1>Staff and User Management</h1>
    <p class="subtitle">
        Register staff profiles and create their login accounts.
    </p>

    <?php if ($message !== ''): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <div class="card">
        <h2>Register New Staff</h2>

        <form method="POST" action="staff.php">
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
                    <label for="email">Email / Login Username *</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        maxlength="254"
                        required
                    >
                </div>

                <div>
                    <label for="password">Initial Password *</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
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
                    <label for="designation">Designation *</label>
                    <input
                        type="text"
                        id="designation"
                        name="designation"
                        maxlength="100"
                        placeholder="For example: Blood Bank Staff"
                        required
                    >
                </div>

                <div>
                    <label for="joining_date">Joining Date *</label>
                    <input
                        type="date"
                        id="joining_date"
                        name="joining_date"
                        max="<?= date('Y-m-d') ?>"
                        required
                    >
                </div>

            </div>

            <button type="submit">Register Staff</button>
        </form>
    </div>
    <?php endif; ?>

    <div class="card">
        <h2>Registered Staff Members</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Staff ID</th>
                        <th>User ID</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Designation</th>
                        <th>Joining Date</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($staffMembers) > 0): ?>
                    <?php foreach ($staffMembers as $staff): ?>
                        <tr>
                            <td><?= (int) $staff['staff_id'] ?></td>
                            <td><?= htmlspecialchars((string) ($staff['user_id'] ?? 'N/A')) ?></td>
                            <td><?= htmlspecialchars($staff['full_name'] ?? 'Account unavailable') ?></td>
                            <td><?= htmlspecialchars($staff['email'] ?? '') ?></td>
                            <td><?= htmlspecialchars($staff['role'] ?? '') ?></td>
                            <td><?= htmlspecialchars($staff['phone'] ?? '') ?></td>
                            <td><?= htmlspecialchars($staff['designation'] ?? '') ?></td>
                            <td><?= htmlspecialchars($staff['joining_date'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No staff profiles registered yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
