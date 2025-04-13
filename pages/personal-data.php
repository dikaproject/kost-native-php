<?php
// Redirect if not coming from register page
if (!isset($_SESSION['register_data'])) {
    header('Location: index.php?page=register');
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get registration data from session
    $register_data = $_SESSION['register_data'];
    
    // Get personal data from form
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $country = $_POST['country'];
    $city = $_POST['city'];
    $village = $_POST['village'];
    $postal_code = $_POST['postal_code'];
    
    // Validate input
    $errors = [];
    
    if (empty($first_name)) {
        $errors[] = "First name is required.";
    }
    
    if (empty($last_name)) {
        $errors[] = "Last name is required.";
    }
    
    if (empty($phone)) {
        $errors[] = "Phone number is required.";
    }
    
    // If no errors, create user account
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($register_data['password'], PASSWORD_DEFAULT);
        
        // Insert user into database
        $stmt = $pdo->prepare("
            INSERT INTO users (
                email, password, first_name, last_name, phone, 
                country, city, village, postal_code, role, 
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, ?, ?, 'user', 
                NOW()
            )
        ");
        
        $result = $stmt->execute([
            $register_data['email'], $hashed_password, $first_name, $last_name, $phone,
            $country, $city, $village, $postal_code
        ]);
        
        if ($result) {
            // Get the new user ID
            $user_id = $pdo->lastInsertId();
            
            // Set session variables
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_role'] = 'user';
            $_SESSION['user_name'] = $first_name . ' ' . $last_name;
            
            // Clear registration data
            unset($_SESSION['register_data']);
            
            // Redirect to dashboard
            header('Location: index.php?page=dashboard');
            exit;
        } else {
            $errors[] = "An error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Personal Data - Aula Kost</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <style>
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body class="auth-page">
    <div class="auth-container">
        <div class="image-container">
            <img src="assets/images/login-image.jpg" alt="Personal Data Image">
        </div>
        <div class="form-container">
            <h2>Personal Information</h2>
            
            <?php if (isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <form action="index.php?page=personal-data" method="post" class="auth-form">
                <div class="form-grid">
                    <div>
                        <input type="text" name="first_name" placeholder="First Name" required>
                    </div>
                    <div>
                        <input type="text" name="last_name" placeholder="Last Name" required>
                    </div>
                </div>
                
                <input type="tel" name="phone" placeholder="Phone Number" required>
                
                <div class="form-grid">
                    <div>
                        <input type="text" name="country" placeholder="Country" required>
                    </div>
                    <div>
                        <input type="text" name="city" placeholder="City" required>
                    </div>
                </div>
                
                <div class="form-grid">
                    <div>
                        <input type="text" name="village" placeholder="Village/District" required>
                    </div>
                    <div>
                        <input type="text" name="postal_code" placeholder="Postal Code" required>
                    </div>
                </div>
                
                <button type="submit" class="auth-btn">Complete Registration</button>
            </form>
        </div>
    </div>
</body>
</html>

