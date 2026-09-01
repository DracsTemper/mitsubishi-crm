<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','Dashboard') · DHS Motors CRM</title>
    <script>
        (function () {
            const queryTheme = new URLSearchParams(location.search).get('theme');
            const savedTheme = localStorage.getItem('mitsubishi-crm-theme');
            const theme = ['light', 'dark'].includes(queryTheme) ? queryTheme : (['light', 'dark'].includes(savedTheme) ? savedTheme : 'dark');
            document.documentElement.dataset.theme = theme;
            document.documentElement.style.colorScheme = theme;
            if (['light', 'dark'].includes(queryTheme)) localStorage.setItem('mitsubishi-crm-theme', queryTheme);
        })();
    </script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>{!! file_get_contents(resource_path('css/app.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/dashboard-fixes.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/overhaul.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/density.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/refinement.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/themes.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/analytics.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/showroom.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/page-loader.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/roles.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/crm-polish.css')) !!}</style>
    <style>{!! file_get_contents(resource_path('css/branding.css')) !!}</style>
</head>
<body>
    @include('components.page-loader')
    <div class="app-shell">
        @include('components.sidebar')
        <main class="main">
            @include('components.topbar')
            <div class="content">@yield('content')</div>
        </main>
    </div>
    <div class="chart-tip"></div>
    <div class="toast-demo">Action completed</div>
    @include('components.dynamic-actions')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
    <script>{!! file_get_contents(resource_path('js/page-loader.js')) !!}</script>
    <script>{!! file_get_contents(resource_path('js/crm/ajax.js')) !!}</script>
    <script>{!! str_replace("import './bootstrap';", '', file_get_contents(resource_path('js/app.js'))) !!}</script>
    @stack('scripts')
</body>
</html>
