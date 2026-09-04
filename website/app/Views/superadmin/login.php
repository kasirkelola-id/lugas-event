<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Superadmin Login - Karang Taruna App</title>
    <link rel="icon" href="/assets/images/logo.png" type="image/png">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { 
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 40px 30px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            color: white;
            animation: fadeIn 0.8s ease-out;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-card h4 {
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .form-control {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        .form-control:focus {
            background: rgba(255, 255, 255, 0.25);
            border-color: #fff;
            color: white;
            box-shadow: 0 0 10px rgba(255,255,255,0.2);
        }
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        .btn-login {
            background: #fff;
            color: #1e3c72;
            font-weight: 600;
            border-radius: 10px;
            padding: 12px;
            border: none;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #e2e2e2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .form-label {
            font-weight: 300;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container d-flex justify-content-center">
        <div class="login-card">
            <div class="text-center mb-4">
                <img src="/assets/images/logo.png" alt="Logo" width="64" height="64" class="mb-2" style="object-fit: contain;">
                <h4>Superadmin</h4>
                <p class="text-white-50 mb-0">Portal Manajemen Karang Taruna App</p>
            </div>
            


            <form action="/superadmin/login" method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" placeholder="Masukkan username..." required autofocus>
                </div>
                <div class="mb-4">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Masukkan password..." required>
                </div>
                <button type="submit" class="btn btn-login w-100">LOGIN KE DASHBOARD</button>
            </form>
        </div>
    </div>
    <?= $this->include('superadmin/partials/sweetalert') ?>
</body>
</html>
