<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Sign In · Mitsubishi CRM</title>
    <script>
        (function(){const saved=localStorage.getItem('mitsubishi-crm-theme');const theme=['light','dark'].includes(saved)?saved:'dark';document.documentElement.dataset.theme=theme;document.documentElement.style.colorScheme=theme})();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>{!! file_get_contents(resource_path('css/app.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/themes.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/login.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/page-loader.css')) !!}</style>
</head>
<body class="login-body">
@include('components.page-loader')
<main class="login-page login-page-refined">
    <section class="login-visual" aria-label="Mitsubishi CRM introduction">
        <img class="login-vehicle" src="/images/outlander-command-hero.png" alt="Silver Mitsubishi Outlander">
        <div class="login-visual-shade"></div>
        <div class="login-brand">
            <img src="/assets/images/brand/mitsubishi-mark.svg" alt="Mitsubishi">
            <div><strong>MITSUBISHI</strong><span>CRM / DEALER MANAGEMENT</span></div>
        </div>
        <div class="login-copy">
            <span class="login-kicker">MITSUBISHI MOTORS BANGLADESH</span>
            <h1>Every customer journey.<br><span>One command center.</span></h1>
            <p>Manage customer relationships, dealer operations, bookings and vehicle inventory through one focused automotive workspace.</p>
            <div class="login-capabilities"><span><i class="bi bi-people" aria-hidden="true"></i> Customer intelligence</span><span><i class="bi bi-car-front" aria-hidden="true"></i> Vehicle operations</span><span><i class="bi bi-graph-up-arrow" aria-hidden="true"></i> Network performance</span></div>
        </div>
        <div class="login-visual-footer"><span>PHASE 1 PROTOTYPE</span><span>Dhaka · Bangladesh</span></div>
    </section>

    <section class="login-form-side">
        <div class="login-topbar">
            <span>SECURE WORKSPACE</span>
            <button class="theme-toggle" id="loginThemeToggle" type="button" aria-label="Switch theme"><span class="theme-toggle-icon"><i class="bi bi-sun-fill"></i></span><span class="theme-toggle-icon"><i class="bi bi-moon-stars-fill"></i></span></button>
        </div>
        <div class="login-card">
            <div class="login-mobile-brand"><img src="/assets/images/brand/mitsubishi-mark.svg" alt=""><strong>MITSUBISHI CRM</strong></div>
            <div class="eyebrow mb-2">WELCOME BACK</div>
            <h2>{{ isset($loginContext) && $loginContext ? 'Sign in to the '.ucfirst($loginContext->value).' workspace' : 'Choose your workspace' }}</h2>
            <p class="login-intro">Enter your account credentials. Your verified account role determines the workspace you can access.</p>
            <form action="{{ route('login.submit') }}" method="POST">
                @csrf
                <div class="mb-3"><label class="form-label" for="loginEmail">Email address</label><div class="login-input"><i class="bi bi-envelope"></i><input id="loginEmail" name="email" type="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}" autocomplete="email" required autofocus></div>@error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="mb-2"><div class="d-flex justify-content-between"><label class="form-label" for="loginPassword">Password</label></div><div class="login-input"><i class="bi bi-shield-lock"></i><input id="loginPassword" name="password" type="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required><button type="button" class="password-toggle" id="passwordToggle" aria-label="Show password"><i class="bi bi-eye"></i></button></div>@error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror</div>
                <div class="form-check mb-3"><input id="remember" name="remember" class="form-check-input" type="checkbox" value="1" @checked(old('remember'))><label class="form-check-label" for="remember">Remember me</label></div>
                <button class="btn btn-primary login-primary w-100" type="submit"><span>Sign In</span><i class="bi bi-arrow-right"></i></button>
            </form>
            <div class="login-divider"><span>OR CONTINUE AS</span></div>
            <a href="{{ route('login', ['context' => 'dealer']) }}" class="dealer-entry"><span class="dealer-entry-icon"><i class="bi bi-buildings"></i></span><span><strong>Continue as Dealer</strong><small>Sign in to a dealer account</small></span><i class="bi bi-arrow-right ms-auto"></i></a>
            <a href="{{ route('login', ['context' => 'salesman']) }}" class="dealer-entry mt-2"><span class="dealer-entry-icon"><i class="bi bi-person-badge"></i></span><span><strong>Continue as Salesman</strong><small>Sign in to a salesman account</small></span><i class="bi bi-arrow-right ms-auto"></i></a>
            <a href="{{ route('customer.login') }}" class="dealer-entry mt-2"><span class="dealer-entry-icon"><i class="bi bi-person"></i></span><span><strong>Continue as Customer</strong><small>Rahim Ahmed · My Mitsubishi</small></span><i class="bi bi-arrow-right ms-auto"></i></a>
            <div class="login-notice"><i class="bi bi-info-circle"></i><span>Use the account credentials provided by your CRM administrator.</span></div>
        </div>
        <footer class="login-footer"><span>© 2026 Mitsubishi Motors Bangladesh</span><span>CRM Prototype v1.0</span></footer>
    </section>
</main>
<script>{!! file_get_contents(resource_path('js/page-loader.js')) !!}</script>
<script>
    const root=document.documentElement,toggle=document.querySelector('#loginThemeToggle');
    toggle.addEventListener('click',()=>{const theme=root.dataset.theme==='dark'?'light':'dark';root.dataset.theme=theme;root.style.colorScheme=theme;localStorage.setItem('mitsubishi-crm-theme',theme)});
    document.querySelector('#passwordToggle').addEventListener('click',function(){const input=document.querySelector('#loginPassword'),visible=input.type==='text';input.type=visible?'password':'text';this.innerHTML=`<i class="bi bi-eye${visible?'':'-slash'}"></i>`;this.setAttribute('aria-label',visible?'Show password':'Hide password')});
</script>
</body>
</html>
