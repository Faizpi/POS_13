<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Portal Customer — Hibiscus Efsya</title>
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

        .form-control {
            min-height: 3.1rem;
            border-radius: 0.75rem;
            border-color: #e2e8f0;
            box-shadow: none;
            font-weight: 600;
        }

        .form-control:focus {
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
                        <h4 class="font-weight-bold">Hibiscus Efsya</h4>
                        <p class="text-muted small">Portal Customer</p>
                    </div>

                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif

                    <form action="{{ route('customer.check.phone') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label><strong>Nomor Telepon</strong></label>
                            <input type="tel" name="no_telp" class="form-control form-control-lg @error('no_telp') is-invalid @enderror"
                                placeholder="08xxxxxxxxxx atau 628xxxxxxxxxx"
                                value="{{ old('no_telp') }}" required autofocus>
                            @error('no_telp')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Masukkan nomor telepon yang terdaftar</small>
                        </div>
                        <button type="submit" class="btn btn-primary btn-block btn-lg">
                            Lanjut <i class="fas fa-arrow-right ml-1"></i>
                        </button>
                    </form>

                    <div class="text-center mt-4">
                        <small class="text-muted">
                            Belum punya akun? Hubungi sales kami
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
