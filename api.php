// FILE: api.php
<?php
session_start();
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$host = 'mysql-8.4.local';
$dbname = 'salgyn_park';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['error' => 'Ошибка подключения к БД: ' . $e->getMessage()]);
    exit;
}

$pdo->exec("CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    phone VARCHAR(50),
    services TEXT,
    total VARCHAR(50),
    status INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255),
    visit VARCHAR(255),
    rating INT,
    text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$pdo->exec("CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(100) UNIQUE,
    password_hash VARCHAR(255),
    role VARCHAR(50) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'booking') {
        $stmt = $pdo->prepare("INSERT INTO bookings (name, phone, services, total) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['name'] ?? '', $_POST['phone'] ?? '', $_POST['services'] ?? '', $_POST['total'] ?? '']);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'review') {
        $stmt = $pdo->prepare("INSERT INTO reviews (name, visit, rating, text) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_POST['name'] ?? '', $_POST['visit'] ?? '', $_POST['rating'] ?? 5, $_POST['text'] ?? '']);
        echo json_encode(['status' => 'success']);
        exit;
    }

    if ($action === 'login') {
        $login = $_POST['login'] ?? '';
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT * FROM users WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            $token = bin2hex(random_bytes(32));
            $_SESSION['token'] = $token;
            $_SESSION['user_role'] = $user['role'];
            
            echo json_encode(['status' => 'success', 'token' => $token, 'role' => $user['role']]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Неверный логин или пароль']);
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    $token = $_GET['token'] ?? '';

    if (!isset($_SESSION['token']) || $_SESSION['token'] !== $token) {
        http_response_code(403);
        echo json_encode(['error' => 'Не авторизован']);
        exit;
    }

    $role = $_SESSION['user_role'];

    if ($action === 'get_bookings') {
        $stmt = $pdo->query("SELECT * FROM bookings ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($action === 'get_reviews') {
        $stmt = $pdo->query("SELECT * FROM reviews ORDER BY id DESC");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if (in_array($role, ['admin', 'owner'])) {
        if ($action === 'delete_booking') {
            $stmt = $pdo->prepare("DELETE FROM bookings WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['status' => 'success']);
            exit;
        }

        if ($action === 'delete_review') {
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['status' => 'success']);
            exit;
        }

        if ($action === 'mark_done') {
            $stmt = $pdo->prepare("UPDATE bookings SET status = 1 WHERE id = ?");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['status' => 'success']);
            exit;
        }
    }

    if ($role === 'owner') {
        if ($action === 'get_users') {
            $stmt = $pdo->query("SELECT id, login, role, created_at FROM users ORDER BY id DESC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
            exit;
        }
        
        if ($action === 'add_user') {
            $login = $_POST['login'] ?? '';
            $pass = $_POST['password'] ?? '';
            $r = $_POST['role'] ?? 'user';
            
            $check = $pdo->prepare("SELECT id FROM users WHERE login = ?");
            $check->execute([$login]);
            if ($check->fetch()) {
                echo json_encode(['error' => 'Пользователь уже существует']);
                exit;
            }

            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (login, password_hash, role) VALUES (?, ?, ?)");
            $stmt->execute([$login, $hash, $r]);
            echo json_encode(['status' => 'success']);
            exit;
        }
        
        if ($action === 'delete_user') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'owner'");
            $stmt->execute([$_GET['id']]);
            echo json_encode(['status' => 'success']);
            exit;
        }
    }
}
?>