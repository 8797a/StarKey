<?php
require __DIR__ . '/../includes/lib.php';
send_admin_security_headers();
ensure_session_started();

if (admin_is_logged_in()) {
    header('Location: admin.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $username = post_value('username');
    $password = post_value('password');
    $loginResult = admin_login($username, $password);
    if ($loginResult === true) {
        header('Location: admin.php');
        exit;
    }
    $error = is_string($loginResult) ? $loginResult : '账号或密码错误';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>后台登录 - <?php echo htmlspecialchars(site_name(), ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        :root {
            --bg-color: #f8fafc;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --primary: #3b82f6;
            --primary-hover: #2563eb;
            --glass-bg: rgba(255, 255, 255, 0.7);
            --glass-border: 1px solid rgba(255, 255, 255, 0.8);
            --glass-shadow: 0 10px 40px rgba(31, 38, 135, 0.05);
            --radius-lg: 20px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, "PingFang SC", sans-serif;
            color: var(--text-main); background-color: var(--bg-color); min-height: 100vh;
            display: flex; align-items: center; justify-content: center; padding: 20px;
        }

        .aurora-bg { position: fixed; inset: 0; z-index: -1; background: var(--bg-color); overflow: hidden; pointer-events: none; }
        .aurora-blob { position: absolute; filter: blur(120px); border-radius: 50%; opacity: 0.6; animation: move 20s infinite alternate ease-in-out; }
        .blob1 { width: 600px; height: 600px; background: #93c5fd; top: -200px; left: -100px; animation-duration: 25s; }
        .blob2 { width: 500px; height: 500px; background: #c4b5fd; bottom: -150px; right: -150px; animation-delay: -5s; animation-duration: 22s; }
        .blob3 { width: 400px; height: 400px; background: #86efac; top: 30%; left: 40%; animation-delay: -10s; animation-duration: 28s; opacity: 0.5; }
        @keyframes move { 0% { transform: translate(0, 0) scale(1) rotate(0deg); } 33% { transform: translate(5vw, -5vh) scale(1.05) rotate(15deg); } 66% { transform: translate(-5vw, 5vh) scale(0.95) rotate(-10deg); } 100% { transform: translate(2vw, 2vh) scale(1) rotate(5deg); } }

        .box {
            width: 100%; max-width: 400px; padding: 48px 40px; position: relative;
            background: var(--glass-bg); backdrop-filter: blur(24px) saturate(150%); -webkit-backdrop-filter: blur(24px) saturate(150%);
            border: var(--glass-border); box-shadow: var(--glass-shadow); border-radius: var(--radius-lg);
        }

        h1 { margin: 0 0 32px; font-size: 28px; text-align: center; font-weight: 800; color: var(--text-main); letter-spacing: -0.02em; }

        .form-group { margin-bottom: 20px; }

        input {
            width: 100%; background: rgba(255, 255, 255, 0.6); border: 1px solid rgba(0, 0, 0, 0.08);
            color: var(--text-main); padding: 14px 16px; border-radius: 12px; font-size: 15px; outline: none; transition: all 0.2s ease;
        }
        input:focus { border-color: var(--primary); background: rgba(255, 255, 255, 0.9); box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15); }

        button {
            width: 100%; padding: 14px; background: var(--primary); border: 1px solid #2563eb;
            color: #fff; font-size: 16px; font-weight: 600; border-radius: 12px; cursor: pointer;
            transition: all 0.2s ease; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2); margin-top: 12px;
        }
        button:hover { background: var(--primary-hover); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(59, 130, 246, 0.3); }

        .error {
            background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626; padding: 12px 16px; border-radius: 10px; margin-bottom: 24px;
            font-size: 14px; text-align: center; font-weight: 600;
        }
    </style>
</head>
<body>

<div class="aurora-bg"><div class="aurora-blob blob1"></div><div class="aurora-blob blob2"></div><div class="aurora-blob blob3"></div></div>

<div class="box">
    <h1>管理后台登录</h1>
    <?php if ($error !== ''): ?>
        <div class="error"><?php echo h($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <?php echo csrf_input(); ?>
        <div class="form-group">
            <input type="text" name="username" placeholder="后台账号" required>
        </div>
        <div class="form-group">
            <input type="password" name="password" placeholder="后台密码" required>
        </div>
        <button type="submit">登录后台</button>
    </form>
</div>
</body>
</html>