
<?php
session_start();

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    require_once __DIR__ . "/config/database.php";

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter your email and password.";
    } else {
        $stmt = $pdo->prepare(
            "SELECT user_id, full_name, email, password_hash, role
             FROM Users
             WHERE email = :email
             LIMIT 1"
        );
        $stmt->execute(["email" => $email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user["password_hash"])) {
            session_regenerate_id(true);
            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["role"] = $user["role"];

            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | BloodCare</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            font-family: Arial, sans-serif;
            background: #fff5f5;
            color: #252525;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 36px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 12px 40px rgba(110, 20, 30, .10);
        }
        .logo {
            width: 58px;
            height: 58px;
            display: grid;
            place-items: center;
            background: #c92738;
            color: white;
            border-radius: 16px;
            font-size: 28px;
            margin-bottom: 22px;
        }
        h1 { margin: 0 0 8px; font-size: 28px; }
        .subtitle { margin: 0 0 28px; color: #777; line-height: 1.5; }
        label { display: block; margin: 18px 0 8px; font-weight: 600; }
        input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 15px;
        }
        input:focus {
            outline: 2px solid #f3b4bb;
            border-color: #c92738;
        }
        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            border: 0;
            border-radius: 10px;
            background: #c92738;
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
        button:hover { background: #a91e2d; }
        .error {
            padding: 12px;
            border-radius: 8px;
            background: #fff0f0;
            color: #a91e2d;
            font-size: 14px;
        }
        .footer { margin-top: 24px; text-align: center; color: #888; font-size: 12px; }
    </style>
</head>
<body>
    <main class="login-card">
        <div class="logo" aria-hidden="true">♥</div>
        <h1>Welcome back</h1>
        <p class="subtitle">Sign in to your BloodCare management dashboard.</p>

        <?php if ($error !== ""): ?>
            <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="email">Email address</label>
            <input id="email" name="email" type="email"
                   autocomplete="username" required>

            <label for="password">Password</label>
            <input id="password" name="password" type="password"
                   autocomplete="current-password" required>

            <button type="submit">Sign in</button>
        </form>

        <p class="footer">BloodCare · Blood Bank Management System</p>
    </main>
</body>
</html>
