<?php
/**
 * OTP Flow Diagnostic Script
 * Place this in your root directory and run it to test OTP generation and sending
 */

require_once '../includes/bootstrap.php';

echo "<h2>OTP Flow Diagnostic</h2>";
echo "<pre>";

// 1. Check if email functions are loaded
echo "1. Checking email functions...\n";
if (function_exists('App\email\send_otp_email')) {
    echo "   ✓ send_otp_email function exists\n";
} else {
    echo "   ✗ send_otp_email function NOT found\n";
}

if (function_exists('App\email\get_mailer')) {
    echo "   ✓ get_mailer function exists\n";
} else {
    echo "   ✗ get_mailer function NOT found\n";
}

// 2. Check current OTPs in database for the test user
echo "\n2. Checking existing OTPs for adminemp user...\n";
try {
    $stmt = $db->prepare("
        SELECT 
            otp_id,
            code,
            expires_at,
            consumed,
            created_at,
            CASE 
                WHEN expires_at > NOW() THEN 'Valid'
                ELSE 'Expired'
            END as status
        FROM login_otp 
        WHERE user_id = '3d3edeb7-4f36-473e-953c-9b1afb33c51b'
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $otps = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($otps) {
        foreach ($otps as $otp) {
            echo "   OTP: {$otp['code']} | Status: {$otp['status']} | Consumed: " . ($otp['consumed'] ? 'Yes' : 'No') . " | Created: {$otp['created_at']}\n";
        }
    } else {
        echo "   No OTPs found\n";
    }
} catch (Exception $e) {
    echo "   Error: " . $e->getMessage() . "\n";
}

// 3. Generate a new test OTP
echo "\n3. Generating a new test OTP...\n";
$test_otp = sprintf('%06d', random_int(0, 999999));
$otp_id = App\auth\generate_uuid();
$expires_at = date('Y-m-d H:i:s', time() + 600);

try {
    $stmt = $db->prepare("
        INSERT INTO login_otp (
            otp_id, user_id, code, expires_at, consumed, created_at
        ) VALUES (
            :otp_id, '3d3edeb7-4f36-473e-953c-9b1afb33c51b', :code, :expires_at, 0, NOW()
        )
    ");
    
    $stmt->execute([
        'otp_id' => $otp_id,
        'code' => $test_otp,
        'expires_at' => $expires_at
    ]);
    
    echo "   ✓ Generated OTP: $test_otp (expires at: $expires_at)\n";
} catch (Exception $e) {
    echo "   ✗ Error generating OTP: " . $e->getMessage() . "\n";
}

// 4. Try sending the OTP email
echo "\n4. Attempting to send OTP email...\n";
if (function_exists('App\email\send_otp_email')) {
    $result = App\email\send_otp_email('wesyielding1@gmail.com', $test_otp, 'adminemp');
    if ($result['success']) {
        echo "   ✓ Email sent successfully!\n";
        echo "   Message: " . $result['message'] . "\n";
    } else {
        echo "   ✗ Email failed: " . $result['message'] . "\n";
        if (isset($result['error'])) {
            echo "   Error details: " . $result['error'] . "\n";
        }
    }
} else {
    echo "   ✗ send_otp_email function not available\n";
}

// 5. Show the current time for comparison
echo "\n5. Time Information:\n";
echo "   Server Time: " . date('Y-m-d H:i:s') . "\n";
echo "   Timezone: " . date_default_timezone_get() . "\n";

// 6. Database time check
try {
    $stmt = $db->query("SELECT NOW() as db_time");
    $result = $stmt->fetch();
    echo "   Database Time: " . $result['db_time'] . "\n";
} catch (Exception $e) {
    echo "   Error getting DB time: " . $e->getMessage() . "\n";
}

echo "</pre>";

// 7. Manual OTP entry form for testing
?>
<hr>
<h3>Test OTP Verification</h3>
<p>The test OTP code is: <strong><?php echo $test_otp; ?></strong></p>
<p>You should also receive it via email at wesyielding1@gmail.com</p>

<form method="POST" action="test_verify_otp.php">
    <label>Enter OTP Code:</label>
    <input type="text" name="otp_code" maxlength="6" required>
    <input type="hidden" name="expected_otp" value="<?php echo $test_otp; ?>">
    <button type="submit">Verify OTP</button>
</form>

<hr>
<h3>Next Steps:</h3>
<ol>
    <li>Check if you received the email at wesyielding1@gmail.com</li>
    <li>Note the OTP code shown above: <strong><?php echo $test_otp; ?></strong></li>
    <li>Try logging in again with username: <strong>adminemp</strong></li>
    <li>When prompted for OTP, use the code: <strong><?php echo $test_otp; ?></strong></li>
</ol>