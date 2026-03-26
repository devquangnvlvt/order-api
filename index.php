<?php
require_once 'auth.php';

if (isAuthenticated()) {
    header('Location: main.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $userData = json_decode(file_get_contents('user.json'), true);

    if ($email === $userData['email'] && $password === $userData['password']) {
        $_SESSION['user_id'] = $email;
        header('Location: main.php');
        exit;
    } else {
        $error = 'Email hoặc mật khẩu không chính xác!';
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - SQL Generator</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #3b82f6;
            --bg: #0f172a;
            --card: #1e293b;
            --text: #f8fafc;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }
        .login-card {
            background-color: var(--card);
            padding: 2.5rem;
            border-radius: 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 400px;
        }
        h1 { font-size: 1.5rem; margin-bottom: 2rem; text-align: center; color: var(--primary); }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; color: #94a3b8; }
        input {
            width: 100%;
            padding: 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid #334155;
            background: #0f172a;
            color: white;
            box-sizing: border-box;
            outline: none;
        }
        input:focus { border-color: var(--primary); }
        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
            margin-top: 1rem;
            transition: opacity 0.2s;
        }
        .btn:hover { opacity: 0.9; }
        .error { color: #f87171; font-size: 0.875rem; margin-bottom: 1rem; text-align: center; }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Đăng nhập</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        <form method="POST">
            <div class="form-group">
                <label>Email hoặc Username</label>
                <input type="text" name="email" required autofocus placeholder="nhập email/username">
            </div>
            <div class="form-group">
                <label>Mật khẩu</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn">Đăng nhập</button>
        </form>
    </div>
</body>
</html>
