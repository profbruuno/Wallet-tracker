<?php
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["message" => "Method not allowed"]);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!$data || !isset($data['userInfo']) || !isset($data['holdings'])) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid data"]);
    exit;
}

$database = new Database();
$db = $database->getConnection();

$userInfo = $data['userInfo'];
$holdings = $data['holdings'];

try {
    // Store user information
    $userQuery = "INSERT INTO portfolio_users 
                  (user_id, email, created_at, last_accessed) 
                  VALUES (:user_id, :email, :created_at, :last_accessed) 
                  ON DUPLICATE KEY UPDATE 
                  email = VALUES(email),
                  last_accessed = VALUES(last_accessed)";

    $userStmt = $db->prepare($userQuery);
    $userStmt->bindParam(":user_id", $userInfo['userId']);
    $userStmt->bindParam(":email", $userInfo['userEmail']);
    $userStmt->bindParam(":created_at", $userInfo['createdAt']);
    $userStmt->bindParam(":last_accessed", $userInfo['lastAccessed']);
    $userStmt->execute();

    // Store portfolio holdings
    $successCount = 0;
    $errorCount = 0;

    foreach ($holdings as $pairId => $tokenHoldings) {
        foreach ($tokenHoldings as $holding) {
            $query = "INSERT INTO portfolio_holdings 
                      (user_id, pair_id, token_name, token_symbol, amount, 
                       buy_price, current_price, added_date) 
                      VALUES (:user_id, :pair_id, :token_name, :token_symbol, :amount, 
                              :buy_price, :current_price, :added_date) 
                      ON DUPLICATE KEY UPDATE 
                      amount = VALUES(amount),
                      buy_price = VALUES(buy_price),
                      current_price = VALUES(current_price),
                      updated_at = CURRENT_TIMESTAMP";

            $stmt = $db->prepare($query);
            
            $stmt->bindParam(":user_id", $userInfo['userId']);
            $stmt->bindParam(":pair_id", $pairId);
            $stmt->bindParam(":token_name", $holding['token']['name']);
            $stmt->bindParam(":token_symbol", $holding['token']['symbol']);
            $stmt->bindParam(":amount", $holding['amount']);
            $stmt->bindParam(":buy_price", $holding['buyPrice']);
            $stmt->bindParam(":current_price", $holding['currentPrice']);
            $stmt->bindParam(":added_date", $holding['timestamp']);

            if ($stmt->execute()) {
                $successCount++;
            } else {
                $errorCount++;
            }
        }
    }

    echo json_encode([
        "message" => "Portfolio stored successfully",
        "user_id" => $userInfo['userId'],
        "success" => $successCount,
        "errors" => $errorCount,
        "total" => $successCount + $errorCount
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        "message" => "Database error: " . $e->getMessage()
    ]);
}
?>