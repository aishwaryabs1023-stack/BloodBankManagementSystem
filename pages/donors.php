
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.php");
    exit;
}

require_once "../config/database.php";

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $fullName = trim($_POST["full_name"] ?? "");
    $dateOfBirth = $_POST["date_of_birth"] ?? "";
    $gender = $_POST["gender"] ?? "";
    $bloodGroup = $_POST["blood_group"] ?? "";
    $phone = trim($_POST["phone"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $address = trim($_POST["address"] ?? "");

    $allowedGenders = ["Male", "Female", "Other"];
    $allowedBloodGroups = [
        "A+", "A-", "B+", "B-", "AB+", "AB-", "O+", "O-"
    ];

    if (
        $fullName === "" ||
        $dateOfBirth === "" ||
        !in_array($gender, $allowedGenders, true) ||
        !in_array($bloodGroup, $allowedBloodGroups, true) ||
        $phone === ""
    ) {
        $message = "Please complete all required fields correctly.";
        $messageType = "error";
    } elseif (
        !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)
    ) {
        $message = "Please enter a valid phone number.";
        $messageType = "error";
    } elseif (
        $email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)
    ) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } elseif (strtotime($dateOfBirth) === false ||
              $dateOfBirth > date("Y-m-d")) {
        $message = "Please enter a valid date of birth.";
        $messageType = "error";
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO Donor
                 (full_name, date_of_birth, gender, blood_group,
                  phone, email, address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $fullName,
                $dateOfBirth,
                $gender,
                $bloodGroup,
                $phone,
                $email !== "" ? $email : null,
                $address !== "" ? $address : null
            ]);

            header("Location: donors.php?added=1");
            exit;
        } catch (PDOException $e) {
            if ($e->getCode() === "23000") {
                $message = "This phone number may already be registered.";
            } else {
                error_log("Donor registration failed: " . $e->getMessage());
                $message = "Unable to register donor. Please try again.";
            }

            $messageType = "error";
        }
    }
}

if (isset($_GET["added"])) {
    $message = "Donor registered successfully!";
    $messageType = "success";
}

$stmt = $pdo->query(
    "SELECT donor_id, full_name, date_of_birth, gender, blood_group,
            phone, email, address, last_donation_date
     FROM Donor
     ORDER BY donor_id DESC"
);

$donors = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Management | BloodCare</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #f8f9fb;
            color: #252525;
        }

        h1 {
            color: #c62828;
            margin-bottom: 8px;
        }

        .subtitle {
            color: #666;
            margin-bottom: 25px;
        }

        .card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #0000000d;
            margin-bottom: 25px;
            min-width: 0;
        }

        .card h2 {
            margin-top: 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .field {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        label {
            font-weight: 600;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d5d5d5;
            border-radius: 7px;
            font: inherit;
            background: white;
        }

        input:focus, select:focus, textarea:focus {
            outline: 2px solid #f4b8be;
            border-color: #c62828;
        }

        textarea {
            resize: vertical;
            min-height: 80px;
        }

        .btn {
            background: #c62828;
            color: white;
            border: none;
            border-radius: 7px;
            padding: 13px 20px;
            cursor: pointer;
            font-weight: bold;
            font-size: 15px;
        }

        .btn:hover {
            background: #a91f1f;
        }

        .message {
            padding: 13px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .success {
            background: #e8f5e9;
            color: #216b2b;
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
            border-bottom: 1px solid #eee;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: #c62828;
            color: white;
        }

        tbody tr:hover {
            background: #fff5f5;
        }

        .back {
            display: inline-block;
            margin-bottom: 18px;
            color: #c62828;
            text-decoration: none;
        }

        .empty {
            padding: 20px;
            color: #666;
        }

        @media (max-width: 650px) {
            body {
                padding: 15px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
            }
        }
    </style>
</head>

<body>
    <a class="back" href="../dashboard.php">&larr; Back to Dashboard</a>

    <h1>Donor Management</h1>
    <p class="subtitle">Register donors and view existing donor records.</p>

    <?php if ($message !== ""): ?>
        <div class="message <?= $messageType ?>" role="status">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Register New Donor</h2>

        <form method="POST" action="donors.php">
            <div class="form-grid">
                <div class="field">
                    <label for="full_name">Full Name *</label>
                    <input id="full_name" name="full_name"
                           maxlength="150" required>
                </div>

                <div class="field">
                    <label for="date_of_birth">Date of Birth *</label>
                    <input type="date" id="date_of_birth"
                           name="date_of_birth"
                           max="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="field">
                    <label for="gender">Gender *</label>
                    <select id="gender" name="gender" required>
                        <option value="">Select gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="field">
                    <label for="blood_group">Blood Group *</label>
                    <select id="blood_group" name="blood_group" required>
                        <option value="">Select blood group</option>
                        <option>A+</option>
                        <option>A-</option>
                        <option>B+</option>
                        <option>B-</option>
                        <option>AB+</option>
                        <option>AB-</option>
                        <option>O+</option>
                        <option>O-</option>
                    </select>
                </div>

                <div class="field">
                    <label for="phone">Phone Number *</label>
                    <input id="phone" name="phone"
                           type="tel" maxlength="20" required>
                </div>

                <div class="field">
                    <label for="email">Email Address</label>
                    <input id="email" name="email"
                           type="email" maxlength="150">
                </div>

                <div class="field full">
                    <label for="address">Address</label>
                    <textarea id="address" name="address"
                              maxlength="500"></textarea>
                </div>

                <div class="field full">
                    <button class="btn" type="submit">
                        Register Donor
                    </button>
                </div>
            </div>
        </form>
    </div>

    <div class="card">
        <h2>Registered Donors (<?= count($donors) ?>)</h2>

        <?php if (count($donors) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Date of Birth</th>
                            <th>Gender</th>
                            <th>Blood Group</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Last Donation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($donors as $donor): ?>
                            <tr>
                                <td><?= htmlspecialchars((string)$donor["donor_id"]) ?></td>
                                <td><?= htmlspecialchars($donor["full_name"] ?? "") ?></td>
                                <td><?= htmlspecialchars($donor["date_of_birth"] ?? "") ?></td>
                                <td><?= htmlspecialchars($donor["gender"] ?? "") ?></td>
                                <td><?= htmlspecialchars($donor["blood_group"] ?? "") ?></td>
                                <td><?= htmlspecialchars($donor["phone"] ?? "") ?></td>
                                <td><?= htmlspecialchars($donor["email"] ?? "") ?></td>
                                <td><?= htmlspecialchars($donor["address"] ?? "") ?></td>
                                <td><?= htmlspecialchars($donor["last_donation_date"] ?? "Not recorded") ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="empty">No donors found in the database.</p>
        <?php endif; ?>
    </div>
</body>
</html>
