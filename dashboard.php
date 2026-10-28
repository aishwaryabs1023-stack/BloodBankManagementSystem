
<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . "/config/database.php";

// Dashboard statistics
$donorCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Donor"
)->fetchColumn();

$patientCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM Patient"
)->fetchColumn();

$requestCount = (int) $pdo->query(
    "SELECT COUNT(*) FROM BloodRequest
     WHERE request_status = 'Pending'"
)->fetchColumn();

// Blood inventory
$stockRows = $pdo->query(
    "SELECT blood_group, units_available
     FROM BloodStock
     ORDER BY FIELD(
        blood_group,
        'O-', 'O+', 'A-', 'A+', 'B-', 'B+', 'AB-', 'AB+'
     )"
)->fetchAll(PDO::FETCH_ASSOC);

$totalUnits = array_sum(
    array_column($stockRows, "units_available")
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Dashboard | BloodCare</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #f7f8fc;
    color: #262b3d;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* Sidebar */
.sidebar {
    width: 245px;
    flex-shrink: 0;
    background: #fff;
    border-right: 1px solid #eee;
    padding: 25px 16px;
}

.brand {
    font-size: 23px;
    font-weight: 800;
    color: #c92738;
    margin: 5px 10px 35px;
}

.brand span {
    display: block;
    font-size: 12px;
    font-weight: 400;
    color: #888;
    margin-top: 6px;
}

.nav {
    display: block;
    padding: 13px 14px;
    margin: 6px 0;
    border-radius: 10px;
    color: #626779;
    text-decoration: none;
    transition: 0.2s;
}

.nav.active,
.nav:hover {
    background: #fff0f1;
    color: #c92738;
}

/* Main area */
.main {
    flex: 1;
    min-width: 0;
    padding: 32px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 30px;
}

h1 {
    margin: 0 0 8px;
    font-size: 28px;
}

.muted {
    color: #85899a;
    font-size: 14px;
}

.user {
    background: #fff;
    padding: 12px 16px;
    border-radius: 12px;
    font-size: 14px;
}

.logout {
    color: #c92738;
    text-decoration: none;
    font-size: 14px;
}

.logout:hover {
    text-decoration: underline;
}

/* Dashboard cards */
.cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 28px;
}

.card,
.panel {
    background: #fff;
    border: 1px solid #eee;
    border-radius: 16px;
    padding: 22px;
}

.card-label {
    color: #85899a;
    font-size: 14px;
}

.metric {
    font-size: 32px;
    font-weight: 800;
    margin-top: 15px;
}

.icon {
    font-size: 22px;
    color: #c92738;
}

/* Inventory and quick actions */
.content {
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 20px;
}

h2 {
    font-size: 18px;
    margin: 0 0 20px;
}

.stock-row {
    display: grid;
    grid-template-columns: 55px 1fr 35px;
    align-items: center;
    gap: 12px;
    margin: 17px 0;
}

.blood {
    background: #fff0f1;
    color: #c92738;
    padding: 8px;
    border-radius: 8px;
    text-align: center;
    font-weight: bold;
}

.bar {
    height: 8px;
    background: #f0f0f3;
    border-radius: 10px;
    overflow: hidden;
}

.fill {
    height: 100%;
    background: #d43a49;
    border-radius: 10px;
}

.stock-value {
    text-align: right;
    font-weight: bold;
}

.actions a {
    display: block;
    padding: 14px 0;
    border-bottom: 1px solid #eee;
    color: #444;
    text-decoration: none;
}

.actions a:last-child {
    border-bottom: 0;
}

.actions a:hover {
    color: #c92738;
}

/* Responsive layout */
@media (max-width: 900px) {
    .cards {
        grid-template-columns: 1fr;
    }

    .content {
        grid-template-columns: 1fr;
    }

    .sidebar {
        width: 190px;
    }

    .main {
        padding: 20px;
    }
}

@media (max-width: 600px) {
    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
        border-right: 0;
        border-bottom: 1px solid #eee;
    }

    .main {
        padding: 16px;
    }

    .topbar {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
</head>

<body>
<div class="layout">

    <!-- Sidebar navigation -->
    <aside class="sidebar">

        <div class="brand">
            ♥ BloodCare
            <span>Management System</span>
        </div>

        <a class="nav active" href="dashboard.php">
            ▦ Dashboard
        </a>

        <a class="nav" href="pages/donors.php">
            ♙ Donors
        </a>

        <a class="nav" href="pages/donations.php">
            ＋ Donations
        </a>

        <a class="nav" href="pages/patients.php">
            ♧ Patients
        </a>

        <a class="nav" href="pages/blood_stock.php">
            ♥ Blood Stock
        </a>

        <a class="nav" href="pages/requests.php">
            ▤ Blood Requests
        </a>

        <a class="nav" href="pages/blood_issues.php">
            ✓ Blood Issues
        </a>

        <!-- Newly added Staff Management link -->
        <a class="nav" href="pages/staff.php">
            ♙ Staff Management
        </a>

    </aside>

    <!-- Main dashboard -->
    <main class="main">

        <header class="topbar">

            <div>
                <h1>Dashboard</h1>
                <div class="muted">
                    Welcome to your blood bank overview.
                </div>
            </div>

            <div class="user">
                <?= htmlspecialchars(
                    $_SESSION["full_name"] ?? "User",
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>

                &nbsp; · &nbsp;

                <a class="logout" href="logout.php">
                    Logout
                </a>
            </div>

        </header>

        <!-- Statistics cards -->
        <section class="cards">

            <article class="card">
                <div class="icon">♙</div>
                <div class="card-label">Registered Donors</div>
                <div class="metric">
                    <?= $donorCount ?>
                </div>
            </article>

            <article class="card">
                <div class="icon">♧</div>
                <div class="card-label">Registered Patients</div>
                <div class="metric">
                    <?= $patientCount ?>
                </div>
            </article>

            <article class="card">
                <div class="icon">▤</div>
                <div class="card-label">Pending Requests</div>
                <div class="metric">
                    <?= $requestCount ?>
                </div>
            </article>

        </section>

        <!-- Blood inventory and quick actions -->
        <section class="content">

            <article class="panel">

                <h2>Blood Inventory</h2>

                <div class="muted">
                    Current units recorded in stock
                </div>

                <div class="metric" style="margin:10px 0 24px">
                    <?= $totalUnits ?>
                    <span class="muted">units total</span>
                </div>

                <?php foreach ($stockRows as $row): ?>

                    <?php
                    $units = (int) $row["units_available"];

                    $width = $totalUnits > 0
                        ? min(100, ($units / $totalUnits) * 100)
                        : 0;
                    ?>

                    <div class="stock-row">

                        <div class="blood">
                            <?= htmlspecialchars(
                                $row["blood_group"],
                                ENT_QUOTES,
                                "UTF-8"
                            ) ?>
                        </div>

                        <div class="bar">
                            <div
                                class="fill"
                                style="width:<?= $width ?>%"
                            ></div>
                        </div>

                        <div class="stock-value">
                            <?= $units ?>
                        </div>

                    </div>

                <?php endforeach; ?>

            </article>

            <!-- Quick action links -->
            <article class="panel actions">

                <h2>Quick Actions</h2>

                <a href="pages/donors.php">
                    ＋ Register a donor
                </a>

                <a href="pages/donations.php">
                    ＋ Record a donation
                </a>

                <a href="pages/patients.php">
                    ＋ Register a patient
                </a>

                <a href="pages/requests.php">
                    ＋ View blood requests
                </a>

                <a href="pages/blood_issues.php">
                    ＋ Record issued blood
                </a>

                <!-- Newly added Staff Management action -->
                <a href="pages/staff.php">
                    ＋ Manage staff and users
                </a>

            </article>

        </section>

    </main>
</div>
</body>
</html>
