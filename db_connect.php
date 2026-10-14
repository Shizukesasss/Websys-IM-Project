<?php
/**
 * Database connection handler
 * Supports local MySQL defaults and Cloud MySQL providers (TiDB, Aiven, Railway, PlanetScale)
 * via Environment Variables configured in Vercel or .env.
 */

$host     = getenv('DB_HOST')     ?: (getenv('MYSQLHOST')     ?: ($_ENV['DB_HOST'] ?? 'localhost'));
$username = getenv('DB_USER')     ?: (getenv('MYSQLUSER')     ?: ($_ENV['DB_USER'] ?? 'root'));
$password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : (getenv('MYSQLPASSWORD') !== false ? getenv('MYSQLPASSWORD') : ($_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? ''))));
$database = getenv('DB_NAME')     ?: (getenv('MYSQLDATABASE') ?: ($_ENV['DB_NAME'] ?? 'binamira_clinic'));
$port     = (int)(getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: ($_ENV['DB_PORT'] ?? 3306)));
$ssl      = (getenv('DB_SSL') === 'true' || getenv('MYSQL_SSL') === 'true');

// Initialize mysqli object
$conn = mysqli_init();

// Set a 5-second timeout so serverless executions do not hang indefinitely
if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) {
    $conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);
}

// If connecting to remote cloud host with non-standard port or SSL explicitly requested, configure SSL
$isRemote = ($host !== 'localhost' && $host !== '127.0.0.1');
$useSsl = $ssl || ($isRemote && $port !== 3306);

if ($useSsl) {
    $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
}

// Attempt primary connection
$connected = @$conn->real_connect(
    $host,
    $username,
    $password,
    $database,
    $port,
    NULL,
    $useSsl ? MYSQLI_CLIENT_SSL : 0
);

// Fallback attempt without SSL flag if remote server does not require SSL
if (!$connected && $useSsl) {
    $connected = @$conn->real_connect($host, $username, $password, $database, $port);
}

if (!$connected) {
    die("Database Connection failed: " . ($conn->connect_error ?: "Unable to connect to MySQL host ($host:$port). Check database credentials and ensure remote database is running."));
}

$conn->set_charset("utf8mb4");

// PDO Connection
try {
    $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_TIMEOUT => 5,
    ];
    if ($useSsl) {
        $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
    $pdo = new PDO($dsn, $username, $password, $pdoOptions);
} catch (PDOException $e) {
    // PDO connection optional if mysqli succeeds
}
?>
