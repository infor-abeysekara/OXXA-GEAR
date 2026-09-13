<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - OXXA GEAR</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .admin-header {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border-radius: 20px 20px 0 0;
        }
        .form-control:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.2rem rgba(40, 167, 69, 0.25);
        }
        .btn-admin {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
        }
        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(40, 167, 69, 0.3);
        }
        .alert {
            border-radius: 10px;
            border: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card admin-login-card border-0">
                    <div class="admin-header text-center py-4">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h3 class="mb-0">Admin Portal</h3>
                        <p class="mb-0 opacity-75">OXXA GEAR Management</p>
                    </div>
                    <div class="card-body p-5">
                        <?php if(isset($_GET['error'])): ?>
                            <div class="alert alert-danger mb-4">
                                <?php
                                switch($_GET['error']) {
                                    case 'username':
                                        echo '<i class="fas fa-exclamation-triangle me-2"></i>Username is required!';
                                        break;
                                    case 'password':
                                        echo '<i class="fas fa-exclamation-triangle me-2"></i>Password is required!';
                                        break;
                                    case 'invalid':
                                        echo '<i class="fas fa-times-circle me-2"></i>Invalid admin credentials!';
                                        break;
                                    default:
                                        echo '<i class="fas fa-exclamation-triangle me-2"></i>Login error occurred!';
                                }
                                ?>
                            </div>
                        <?php endif; ?>

                        <?php if(isset($_GET['logout']) && $_GET['logout'] == 'success'): ?>
                            <div class="alert alert-success mb-4">
                                <i class="fas fa-check-circle me-2"></i>Successfully logged out!
                            </div>
                        <?php endif; ?>

                        <form action="include/admin-login-backend.php" method="POST">
                            <div class="mb-4">
                                <label for="username" class="form-label fw-semibold">
                                    <i class="fas fa-user me-2 text-success"></i>Username or Email
                                </label>
                                <input type="text" class="form-control form-control-lg" id="username" name="username" 
                                       placeholder="Enter admin username or email" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="password" class="form-label fw-semibold">
                                    <i class="fas fa-lock me-2 text-success"></i>Password
                                </label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" 
                                       placeholder="Enter admin password" required>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" name="login" class="btn btn-admin btn-lg text-white">
                                    <i class="fas fa-sign-in-alt me-2"></i>Login to Dashboard
                                </button>
                            </div>
                        </form>
                        
                        <div class="text-center mt-4">
                            <a href="../index.php" class="text-decoration-none text-success">
                                <i class="fas fa-arrow-left me-2"></i>Back to Website
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

