<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Logowanie do Panelu Poczty</title>
    <link href="{{ asset('assets/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/font-awesome/css/all.min.css') }}" rel="stylesheet">
    <link href="{{ asset('assets/inter/inter.css') }}" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="card shadow-sm" style="width: 380px;">
        <div class="card-body p-4">
            <h4 class="text-center mb-3"><i class="fa-solid fa-lock text-primary me-2"></i>MailPanel</h4>
            <h5 class="text-center mb-3">Panel zarządzania serwerem pocztowym</h5>

            @if($errors->any())
                <div class="alert alert-danger py-2 small">
                    {{ $errors->first() }}
                </div>
            @endif

            <form action="{{ route('login.submit') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label for="username" class="form-label small">Login administratora</label>
                    <input type="text" class="form-control form-control-sm" id="username" name="username" value="{{ old('username') }}" required autofocus>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label small">Hasło</label>
                    <input type="password" class="form-control form-control-sm" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-sm w-100">Zaloguj się</button>
            </form>
        </div>
    </div>
</body>
</html>