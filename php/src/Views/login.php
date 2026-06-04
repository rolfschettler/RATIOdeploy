<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Anmeldung – RATIO Server</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
        }
        body {
            background: linear-gradient(135deg, #1a3a5c 0%, #2d6a9f 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
        }
        .login-card .card {
            border: none;
            border-radius: 1rem;
            box-shadow: 0 1rem 3rem rgba(0,0,0,.5);
        }
        .login-card .card-header {
            background: #1a3a5c;
            border-radius: 1rem 1rem 0 0 !important;
            padding: 2rem 2rem 1rem;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,.15);
        }
        .login-card .card-body {
            padding: 2rem;
        }
        .login-icon {
            font-size: 2.5rem;
            margin-bottom: .5rem;
        }
        .btn-login {
            padding: .65rem;
            font-size: 1rem;
            letter-spacing: .05em;
        }
        .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
            border-width: .2em;
        }
        .error-placeholder {
            visibility: hidden;
            margin-bottom: 1rem;
        }
        .error-placeholder.visible {
            visibility: visible;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="card">
        <div class="card-header">
            <div class="login-icon">🔐</div>
            <h5 class="text-white mb-0">RATIO Server</h5>
            <small class="text-secondary">Bitte melden Sie sich an</small>
        </div>
        <div class="card-body bg-white">

            <div id="errorAlert" class="alert alert-danger error-placeholder" role="alert">&nbsp;</div>

            <form id="loginForm" novalidate autocomplete="off">
                <div class="mb-3">
                    <label for="user" class="form-label fw-semibold">Benutzer</label>
                    <input type="text" class="form-control form-control-lg" id="user"
                           name="user" autocomplete="off" autofocus>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">Passwort</label>
                    <input type="password" class="form-control form-control-lg" id="password"
                           name="password" autocomplete="new-password">
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-login" id="submitBtn" style="background:#2d6a9f;color:#fff;border:none;"
                            onmouseover="this.style.background='#1a3a5c'" onmouseout="this.style.background='#2d6a9f'">
                        <span id="btnText">Anmelden</span>
                        <span id="btnSpinner" class="spinner-border text-light d-none ms-2" role="status"></span>
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script>
    const lastUser = localStorage.getItem('app_user');
    if (lastUser) {
        document.getElementById('user').value = lastUser;
        document.getElementById('password').focus();
    }

    document.getElementById('loginForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const user     = document.getElementById('user').value.trim();
        const password = document.getElementById('password').value;
        const errorEl  = document.getElementById('errorAlert');
        const spinner  = document.getElementById('btnSpinner');
        const btnText  = document.getElementById('btnText');
        const submitBtn = document.getElementById('submitBtn');

        errorEl.classList.remove('visible');
        errorEl.textContent = '\u00a0';
        spinner.classList.remove('d-none');
        btnText.textContent = 'Anmelden …';
        submitBtn.disabled = true;

        try {
            const response = await fetch('/php/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user, password })
            });

            const json = await response.json();

            if (response.ok && json.data?.token) {
                localStorage.setItem('app_token', json.data.token);
                localStorage.setItem('app_user', user);
                window.location.href = '/php/adressen2';
            } else {
                const msg = json.message ?? json.data?.message ?? 'Anmeldung fehlgeschlagen';
                errorEl.textContent = msg;
                errorEl.classList.add('visible');
            }
        } catch (err) {
            errorEl.textContent = 'Server nicht erreichbar';
            errorEl.classList.add('visible');
        } finally {
            spinner.classList.add('d-none');
            btnText.textContent = 'Anmelden';
            submitBtn.disabled = false;
        }
    });
</script>

</body>
</html>
