<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masukkan PIN — Hibiscus Efsya</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            background:
                radial-gradient(ellipse 100% 60% at 0% 30%, rgba(37, 99, 235, 0.2), transparent 70%),
                radial-gradient(ellipse 80% 50% at 100% 70%, rgba(236, 72, 153, 0.18), transparent 60%),
                linear-gradient(135deg, #1e3a5f 0%, #2563eb 35%, #7c3aed 65%, #db2777 100%);
            font-family: 'Plus Jakarta Sans', 'Segoe UI', sans-serif;
        }

        .login-card {
            width: min(100%, 430px);
            margin: auto;
        }

        .card {
            border-radius: 1.15rem;
            border: 1px solid rgba(255, 255, 255, 0.18);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            box-shadow: 0 24px 70px rgba(30, 58, 95, 0.28);
        }

        .card-body {
            padding: 2.25rem 2rem;
        }

        .brand-logo {
            width: 4rem;
            height: 4rem;
            object-fit: contain;
            margin-bottom: 0.8rem;
        }

        h4 {
            font-weight: 800;
            color: #0f172a;
        }

        .pin-input {
            min-height: 3.4rem;
            border-radius: 0.75rem;
            border-color: #e2e8f0;
            box-shadow: none;
            font-size: 1.35rem;
            font-weight: 800;
            letter-spacing: 0.65rem;
            text-align: center;
        }

        .pin-input:focus {
            border-color: #2563eb;
            box-shadow: 0 0 0 0.18rem rgba(37, 99, 235, 0.14);
        }

        .btn {
            min-height: 3.1rem;
            border-radius: 0.75rem;
            font-weight: 800;
        }

        .btn-primary {
            border-color: #2563eb;
            background: #2563eb;
        }

        .btn-primary:hover {
            border-color: #1d4ed8;
            background: #1d4ed8;
        }

        .alert {
            border-radius: 0.75rem;
            border: 0;
        }

        @media (max-width: 480px) {
            .card-body {
                padding: 1.5rem 1.25rem;
            }

            .pin-input {
                letter-spacing: 0.45rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-card">
            <div class="card shadow-lg border-0">
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img src="{{ asset('assets/img/logoHE1.png') }}" alt="Hibiscus Efsya" class="brand-logo">
                        <h4 class="font-weight-bold">Halo, {{ $nama ?? '' }}!</h4>
                        <p class="text-muted small">Masukkan PIN 6 digit Anda</p>
                    </div>

                    @if(isset($error))
                        <div class="alert alert-danger">{{ $error }}</div>
                    @endif

                    <form action="{{ route('customer.login.submit') }}" method="POST">
                        @csrf
                        <input type="hidden" name="no_telp" value="{{ $no_telp }}">
                        <div class="form-group">
                            <input type="password" name="pin" class="form-control pin-input"
                                maxlength="6" inputmode="numeric" pattern="[0-9]{6}"
                                placeholder="• • • • • •" required autofocus>
                            <small class="text-muted d-block text-center mt-2">PIN terdiri dari 6 angka</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            Masuk <i class="fas fa-sign-in-alt ml-1"></i>
                        </button>
                    </form>

                    <div class="text-center mt-3">
                        <a href="{{ route('customer.login') }}" class="text-muted small">
                            ← Ganti nomor telepon
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
