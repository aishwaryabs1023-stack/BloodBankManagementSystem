
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

require_once __DIR__ . '/../config/database.php';

// Fetch blood stock
$stmt = $pdo->query(
    "SELECT stock_id, blood_group, units_available, last_updated
     FROM BloodStock
     ORDER BY
        CASE blood_group
            WHEN 'A+' THEN 1
            WHEN 'A-' THEN 2
            WHEN 'B+' THEN 3
            WHEN 'B-' THEN 4
            WHEN 'AB+' THEN 5
            WHEN 'AB-' THEN 6
            WHEN 'O+' THEN 7
            WHEN 'O-' THEN 8
            ELSE 9
        END"
);

$stocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUnits = 0;
foreach ($stocks as $stock) {
    $totalUnits += (int) $stock['units_available'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blood Stock Management - BloodCare</title>

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

        .summary {
            background: #b71c1c;
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
        }

        .summary-label {
            font-size: 16px;
        }

        .summary-number {
            font-size: 36px;
            font-weight: bold;
            margin-top: 10px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 18px;
        }

        .card {
            background: white;
            padding: 22px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .blood-group {
            font-size: 26px;
            font-weight: bold;
            color: #b71c1c;
        }

        .units {
            font-size: 25px;
            font-weight: bold;
            margin: 12px 0 5px;
        }

        .unit-label {
            color: #777;
            font-size: 14px;
        }

        .updated {
            color: #888;
            font-size: 12px;
            margin-top: 15px;
        }

        .table-card {
            background: white;
            padding: 24px;
            margin-top: 28px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #b71c1c;
            color: white;
        }

        .low-stock {
            color: #b71c1c;
            font-weight: bold;
        }

        .available {
            color: #2e7d32;
            font-weight: bold;
        }

        @media (max-width: 800px) {
            .grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 15px;
            }

            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <a class="back-link" href="../dashboard.php">
        &larr; Back to Dashboard
    </a>

    <h1>Blood Stock Management</h1>
    <p class="subtitle">
        View available blood units by blood group.
    </p>

    <div class="summary">
        <div class="summary-label">Total Available Blood Units</div>
        <div class="summary-number">
            <?= number_format($totalUnits) ?>
        </div>
    </div>

    <div class="grid">
        <?php foreach ($stocks as $stock): ?>
            <div class="card">
                <div class="blood-group">
                    <?= htmlspecialchars($stock['blood_group']) ?>
                </div>

                <div class="units">
                    <?= number_format((int) $stock['units_available']) ?>
                </div>

                <div class="unit-label">units available</div>

                <div class="updated">
                    Last updated:
                    <?= htmlspecialchars($stock['last_updated'] ?? 'Not available') ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="table-card">
        <h2>Blood Stock Details</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Stock ID</th>
                        <th>Blood Group</th>
                        <th>Available Units</th>
                        <th>Stock Status</th>
                        <th>Last Updated</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (count($stocks) > 0): ?>
                    <?php foreach ($stocks as $stock): ?>
                        <tr>
                            <td><?= (int) $stock['stock_id'] ?></td>

                            <td>
                                <?= htmlspecialchars($stock['blood_group']) ?>
                            </td>

                            <td>
                                <?= (int) $stock['units_available'] ?>
                            </td>

                            <td class="<?= (int) $stock['units_available'] < 5
                                ? 'low-stock' : 'available' ?>">
                                <?= (int) $stock['units_available'] < 5
                                    ? 'Low Stock' : 'Available' ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($stock['last_updated'] ?? 'Not available') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            No blood stock records found in the database.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>
