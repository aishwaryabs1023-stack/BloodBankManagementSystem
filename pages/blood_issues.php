
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

$message = '';
$messageType = 'error';

// Record a blood issue
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $unitsIssued = filter_input(INPUT_POST, 'units_issued', FILTER_VALIDATE_INT);
    $issuedBy = trim($_POST['issued_by'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');

    if (!$requestId || !$unitsIssued || $unitsIssued < 1 || $unitsIssued > 100) {
        $message = 'Select a request and enter a valid number of units.';
    } elseif ($issuedBy === '') {
        $message = 'Please enter the staff member issuing the blood.';
    } else {
        try {
            $pdo->beginTransaction();

            // Lock request to prevent concurrent issues
            $requestStmt = $pdo->prepare(
                "SELECT request_id, patient_id, requested_blood_group,
                        units_requested, request_status
                 FROM BloodRequest
                 WHERE request_id = ?
                 FOR UPDATE"
            );
            $requestStmt->execute([$requestId]);
            $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

            if (!$request) {
                throw new RuntimeException('Blood request not found.');
            }

            if (!in_array($request['request_status'], ['Approved', 'Pending'], true)) {
                throw new RuntimeException(
                    'Only Pending or Approved requests can be issued.'
                );
            }

            // Calculate units already issued for this request
            $issuedStmt = $pdo->prepare(
                "SELECT COALESCE(SUM(units_issued), 0)
                 FROM BloodIssue
                 WHERE request_id = ?"
            );
            $issuedStmt->execute([$requestId]);
            $alreadyIssued = (int) $issuedStmt->fetchColumn();

            $remainingUnits = (int) $request['units_requested'] - $alreadyIssued;

            if ($remainingUnits <= 0) {
                throw new RuntimeException(
                    'This request has already been fulfilled.'
                );
            }

            if ($unitsIssued > $remainingUnits) {
                throw new RuntimeException(
                    'You can issue at most ' . $remainingUnits .
                    ' remaining unit(s) for this request.'
                );
            }

            $bloodGroup = $request['requested_blood_group'];

            // Lock the stock row
            $stockStmt = $pdo->prepare(
                "SELECT units_available
                 FROM BloodStock
                 WHERE blood_group = ?
                 FOR UPDATE"
            );
            $stockStmt->execute([$bloodGroup]);
            $availableUnits = $stockStmt->fetchColumn();

            if ($availableUnits === false) {
                throw new RuntimeException(
                    'No stock entry exists for blood group ' . $bloodGroup . '.'
                );
            }

            $availableUnits = (int) $availableUnits;

            if ($availableUnits < $unitsIssued) {
                throw new RuntimeException(
                    'Insufficient stock. Available units: ' . $availableUnits . '.'
                );
            }

            // Save issue record
            $insertStmt = $pdo->prepare(
                "INSERT INTO BloodIssue
                 (request_id, patient_id, blood_group, units_issued,
                  issued_by, remarks)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $insertStmt->execute([
                $requestId,
                $request['patient_id'],
                $bloodGroup,
                $unitsIssued,
                $issuedBy,
                $remarks !== '' ? $remarks : null
            ]);

            // Deduct stock
            $updateStock = $pdo->prepare(
                "UPDATE BloodStock
                 SET units_available = units_available - ?,
                     last_updated = CURRENT_TIMESTAMP
                 WHERE blood_group = ?
                   AND units_available >= ?"
            );
            $updateStock->execute([
                $unitsIssued,
                $bloodGroup,
                $unitsIssued
            ]);

            if ($updateStock->rowCount() !== 1) {
                throw new RuntimeException(
                    'Stock changed during processing. Please try again.'
                );
            }

            // Determine whether the request is fully fulfilled
            $totalIssued = $alreadyIssued + $unitsIssued;

            $newStatus = ($totalIssued >= (int) $request['units_requested'])
                ? 'Fulfilled'
                : 'Approved';

            $updateRequest = $pdo->prepare(
                "UPDATE BloodRequest
                 SET request_status = ?
                 WHERE request_id = ?"
            );
            $updateRequest->execute([$newStatus, $requestId]);

            $pdo->commit();

            header("Location: blood_issues.php?added=1");
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log("Blood issue error: " . $e->getMessage());

            $message = $e instanceof RuntimeException
                ? $e->getMessage()
                : 'Unable to record the blood issue. Please check your database tables.';
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Blood issue recorded successfully and stock updated!';
    $messageType = 'success';
}

// Get Pending or Approved requests with remaining units
$requestStmt = $pdo->query(
    "SELECT br.request_id, br.patient_id, br.requested_blood_group,
            br.units_requested, br.request_status,
            p.full_name AS patient_name,
            COALESCE(issued.total_issued, 0) AS total_issued
     FROM BloodRequest br
     INNER JOIN Patient p ON p.patient_id = br.patient_id
     LEFT JOIN (
         SELECT request_id, SUM(units_issued) AS total_issued
         FROM BloodIssue
         GROUP BY request_id
     ) issued ON issued.request_id = br.request_id
     WHERE br.request_status IN ('Pending', 'Approved')
     ORDER BY br.request_id DESC"
);
$allRequests = $requestStmt->fetchAll(PDO::FETCH_ASSOC);

$requests = [];
foreach ($allRequests as $request) {
    $remaining = (int) $request['units_requested']
        - (int) $request['total_issued'];

    if ($remaining > 0) {
        $request['remaining_units'] = $remaining;
        $requests[] = $request;
    }
}

// Get issue history
$historyStmt = $pdo->query(
    "SELECT bi.issue_id, bi.request_id, bi.patient_id,
            bi.blood_group, bi.units_issued, bi.issue_date,
            bi.issued_by, bi.remarks,
            p.full_name AS patient_name
     FROM BloodIssue bi
     INNER JOIN Patient p ON p.patient_id = bi.patient_id
     ORDER BY bi.issue_id DESC"
);
$issues = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Issue Management - BloodCare</title>

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
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 15px;
            background: white;
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
            color: white;
        }

        @media (max-width: 650px) {
            body {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back-link" href="../dashboard.php">
        &larr; Back to Dashboard
    </a>

    <h1>Blood Issue Management</h1>
    <p class="subtitle">
        Issue blood against patient requests and track stock deductions.
    </p>

    <?php if ($message !== ''): ?>
        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Record a Blood Issue</h2>

        <form method="POST" action="blood_issues.php">
            <label for="request_id">Select Blood Request *</label>

            <select name="request_id" id="request_id" required>
                <option value="">-- Select Request --</option>

                <?php foreach ($requests as $request): ?>
                    <option value="<?= (int) $request['request_id'] ?>">
                        Request #<?= (int) $request['request_id'] ?>
                        — <?= htmlspecialchars($request['patient_name']) ?>
                        — <?= htmlspecialchars($request['requested_blood_group']) ?>
                        — <?= (int) $request['remaining_units'] ?> unit(s) remaining
                    </option>
                <?php endforeach; ?>
            </select>

            <label for="units_issued">Units to Issue *</label>
            <input
                type="number"
                name="units_issued"
                id="units_issued"
                min="1"
                max="100"
                value="1"
                required
            >

            <label for="issued_by">Issued By *</label>
            <input
                type="text"
                name="issued_by"
                id="issued_by"
                maxlength="150"
                placeholder="Staff member's name"
                required
            >

            <label for="remarks">Remarks (Optional)</label>
            <textarea
                name="remarks"
                id="remarks"
                rows="3"
                placeholder="Enter any additional notes"
            ></textarea>

            <button type="submit">Record Blood Issue</button>
        </form>
    </div>

    <div class="card">
        <h2>Blood Issue History</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Issue ID</th>
                        <th>Request ID</th>
                        <th>Patient</th>
                        <th>Blood Group</th>
                        <th>Units Issued</th>
                        <th>Issue Date</th>
                        <th>Issued By</th>
                        <th>Remarks</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($issues) > 0): ?>
                    <?php foreach ($issues as $issue): ?>
                        <tr>
                            <td><?= (int) $issue['issue_id'] ?></td>
                            <td><?= (int) $issue['request_id'] ?></td>
                            <td><?= htmlspecialchars($issue['patient_name']) ?></td>
                            <td><?= htmlspecialchars($issue['blood_group']) ?></td>
                            <td><?= (int) $issue['units_issued'] ?></td>
                            <td><?= htmlspecialchars($issue['issue_date'] ?? '') ?></td>
                            <td><?= htmlspecialchars($issue['issued_by'] ?? '') ?></td>
                            <td><?= htmlspecialchars($issue['remarks'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8">No blood issues recorded yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
