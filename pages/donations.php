
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$message = "";
$messageType = "error";

// Record a new donation
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $donorId = filter_input(INPUT_POST, 'donor_id', FILTER_VALIDATE_INT);
    $units = filter_input(INPUT_POST, 'units_donated', FILTER_VALIDATE_INT);
    $donationDate = $_POST['donation_date'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$donorId || !$units || $units < 1 || $units > 100) {
        $message = "Please select a donor and enter units between 1 and 100.";
    } elseif (!$donationDate) {
        $message = "Please select a donation date.";
    } elseif ($donationDate > date('Y-m-d')) {
        $message = "Donation date cannot be in the future.";
    } else {
        try {
            $pdo->beginTransaction();

            // Get donor blood group
            $donorStmt = $pdo->prepare(
                "SELECT blood_group FROM Donor WHERE donor_id = ?"
            );
            $donorStmt->execute([$donorId]);
            $donor = $donorStmt->fetch(PDO::FETCH_ASSOC);

            if (!$donor) {
                throw new RuntimeException("Selected donor was not found.");
            }

            $bloodGroup = $donor['blood_group'];

            // Lock the relevant stock row
            $stockStmt = $pdo->prepare(
                "SELECT stock_id FROM BloodStock
                 WHERE blood_group = ? FOR UPDATE"
            );
            $stockStmt->execute([$bloodGroup]);
            $stock = $stockStmt->fetch(PDO::FETCH_ASSOC);

            if (!$stock) {
                throw new RuntimeException(
                    "No blood stock entry exists for blood group " .
                    $bloodGroup . ". Please check the BloodStock table."
                );
            }

            // Insert donation
            $insertStmt = $pdo->prepare(
                "INSERT INTO Donation
                 (donor_id, donation_date, units_donated, blood_group, remarks)
                 VALUES (?, ?, ?, ?, ?)"
            );
            $insertStmt->execute([
                $donorId,
                $donationDate,
                $units,
                $bloodGroup,
                $remarks !== '' ? $remarks : null
            ]);

            // Increase blood stock
            $updateStock = $pdo->prepare(
                "UPDATE BloodStock
                 SET units_available = units_available + ?,
                     last_updated = CURRENT_TIMESTAMP
                 WHERE blood_group = ?"
            );
            $updateStock->execute([$units, $bloodGroup]);

            // Update the donor's latest donation date
            $updateDonor = $pdo->prepare(
                "UPDATE Donor
                 SET last_donation_date =
                     CASE
                         WHEN last_donation_date IS NULL
                              OR last_donation_date < ?
                         THEN ?
                         ELSE last_donation_date
                     END
                 WHERE donor_id = ?"
            );
            $updateDonor->execute([
                $donationDate,
                $donationDate,
                $donorId
            ]);

            $pdo->commit();

            header("Location: donations.php?added=1");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Donation error: " . $e->getMessage());
            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : "Unable to record donation. Please check the database configuration and tables.";
        }
    }
}

if (isset($_GET['added'])) {
    $message = "Donation recorded successfully and blood stock updated!";
    $messageType = "success";
}

// Get donors for dropdown
$donorsStmt = $pdo->query(
    "SELECT donor_id, full_name, blood_group
     FROM Donor
     ORDER BY full_name"
);
$donors = $donorsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get donation history
$historyStmt = $pdo->query(
    "SELECT d.donation_id, d.donation_date, d.units_donated,
            d.blood_group, d.remarks, dn.full_name
     FROM Donation d
     INNER JOIN Donor dn ON d.donor_id = dn.donor_id
     ORDER BY d.donation_date DESC, d.donation_id DESC"
);
$donations = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$totalUnits = 0;
foreach ($donations as $donation) {
    $totalUnits += (int) $donation['units_donated'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donations Management - BloodCare</title>

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
            max-width: 1100px;
            margin: 0 auto;
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
            background: white;
            padding: 24px;
            margin-bottom: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        label {
            display: block;
            margin: 15px 0 7px;
            font-weight: bold;
        }

        input, select, textarea {
            width: 100%;
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
        }

        textarea {
            resize: vertical;
        }

        button {
            margin-top: 20px;
            padding: 12px 20px;
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
            padding: 12px;
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

        .summary {
            padding: 15px;
            background: #fff3f3;
            border-left: 4px solid #b71c1c;
            margin-bottom: 20px;
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
    </style>
</head>

<body>
<div class="container">

    <a class="back-link" href="../dashboard.php">
        &larr; Back to Dashboard
    </a>

    <h1>Donations Management</h1>
    <p class="subtitle">
        Record blood donations and view donation history.
    </p>

    <?php if ($message !== ''): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="summary">
        <strong>Total recorded donation units:</strong>
        <?= $totalUnits ?>
    </div>

    <div class="card">
        <h2>Record a New Donation</h2>

        <form method="POST" action="donations.php">
            <label for="donor_id">Select Donor</label>

            <select name="donor_id" id="donor_id" required>
                <option value="">-- Select Donor --</option>

                <?php foreach ($donors as $donor): ?>
                    <option value="<?= (int) $donor['donor_id'] ?>"
                        <?= isset($_POST['donor_id']) &&
                            (int) $_POST['donor_id'] === (int) $donor['donor_id']
                            ? 'selected' : '' ?>>
                        <?= htmlspecialchars($donor['full_name']) ?>
                        (<?= htmlspecialchars($donor['blood_group']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="donation_date">Donation Date</label>
            <input
                type="date"
                name="donation_date"
                id="donation_date"
                max="<?= date('Y-m-d') ?>"
                value="<?= htmlspecialchars($_POST['donation_date'] ?? date('Y-m-d')) ?>"
                required
            >

            <label for="units_donated">Units Donated</label>
            <input
                type="number"
                name="units_donated"
                id="units_donated"
                min="1"
                max="100"
                value="<?= htmlspecialchars($_POST['units_donated'] ?? '1') ?>"
                required
            >

            <label for="remarks">Remarks (Optional)</label>
            <textarea
                name="remarks"
                id="remarks"
                rows="3"
                placeholder="Enter any additional notes"
            ><?= htmlspecialchars($_POST['remarks'] ?? '') ?></textarea>

            <button type="submit">Record Donation</button>
        </form>
    </div>

    <div class="card">
        <h2>Donation History</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Donor Name</th>
                        <th>Date</th>
                        <th>Blood Group</th>
                        <th>Units</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($donations) > 0): ?>
                    <?php foreach ($donations as $donation): ?>
                        <tr>
                            <td><?= (int) $donation['donation_id'] ?></td>
                            <td><?= htmlspecialchars($donation['full_name']) ?></td>
                            <td><?= htmlspecialchars($donation['donation_date']) ?></td>
                            <td><?= htmlspecialchars($donation['blood_group']) ?></td>
                            <td><?= (int) $donation['units_donated'] ?></td>
                            <td><?= htmlspecialchars($donation['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">No donations recorded yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
