<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Panel</title>

    @vite(['resources/css/app.css'])
</head>
<style>
/* ===== DARK HACKER GLOBAL THEME ===== */
.nav-sidebar .nav-link.logout:hover {
    background: #ff000033 !important;
    color: #ff4444 !important;
}
body {
    background: #0b0b0b !important;
    color: #c6ffdd !important;
    font-family: "Consolas","Courier New",monospace;
}

.main-header.navbar {
    background: #000 !important;
    border-bottom: 1px solid #00ff99;
    box-shadow: 0 0 10px #00ff9955;
}

.main-header .nav-link, .main-header i {
    color: #00ffcc !important;
}

.main-sidebar {
    background: #0d0d0d !important;
    border-right: 1px solid #00ff99;
    box-shadow: 0 0 20px #00ff4433;
}

.sidebar {
    background: transparent !important;
}

.brand-link {
    background: #111 !important;
    border-bottom: 1px solid #00ff99 !important;
}

.brand-link .brand-text {
    color: #00ffcc !important;
}

.nav-sidebar .nav-link {
    color: #c6ffdd !important;
    font-weight: 600;
}

.nav-sidebar .nav-link.active {
    background: #00ff9944 !important;
    color: #00ffcc !important;
    border-left: 3px solid #00ffaa;
}

.nav-sidebar .nav-link:hover {
    background: #00ffa933 !important;
    color: #fff !important;
    text-shadow: 0 0 8px #00ffcc;
}

.nav-sidebar i {
    color: #00ffaa !important;
}

.content-wrapper {
    background: #0b0b0b !important;
}

.card {
    background: #111 !important;
    border: 1px solid #00ff7744 !important;
    box-shadow: 0 0 15px #00ff7733;
}

table, tr, td, th {
    background: #111 !important;
    color: #c6ffdd !important;
    border-color: #00ff7744 !important;
}

::-webkit-scrollbar { width: 8px; }
::-webkit-scrollbar-track { background: #000; }
::-webkit-scrollbar-thumb {
    background: #00ff99;
    border-radius: 4px;
}

.btn {
    font-family: "Consolas","Courier New",monospace;
}
.btn-primary {
    background: #00ffaa !important;
    border: none;
    color: #000 !important;
}
.btn-primary:hover {
    background: #66ffcc !important;
    box-shadow: 0 0 12px #00ffaa;
}
</style>

<body class="hold-transition sidebar-mini">
<div class="wrapper">

    {{-- Navbar --}}
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#">
                    <i class="fas fa-bars"></i>
                </a>
            </li>
        </ul>
    </nav>

    {{-- Sidebar --}}
    <aside class="main-sidebar sidebar-dark-primary elevation-4">

        <a href="#" class="brand-link">
            <span class="brand-text font-weight-light">Result Panel</span>
        </a>

        <div class="sidebar">


      <ul class="nav nav-pills nav-sidebar flex-column">

    <li class="nav-item">
        <a href="/dashboard" class="nav-link">
            <i class="nav-icon fas fa-home"></i>
            <p>Dashboard</p>
        </a>
    </li>

    <li class="nav-item">
        <a href="/tokens" class="nav-link">
            <i class="fas fa-user-check nav-icon"></i>
            <p>Captured Token</p>
        </a>
    </li>
@if(!auth()->user()->isSubUser())
    <li class="nav-item">
    <a href="/workers" class="nav-link">
        <i class="fas fa-cloud nav-icon"></i>
        <p>URL</p>
    </a>
</li>
@endif
   @if(!auth()->user()->isSubUser())
    <li class="nav-item">
    <a href="/sub-users" class="nav-link">
        <i class="fas fa-users nav-icon"></i>
        <p>Sub Users</p>
    </a>
</li>
@endif
   @if(!auth()->user()->isSubUser())
<li class="nav-item">
    <a href="/settings" class="nav-link">
        <i class="fas fa-cog nav-icon"></i>
        <p>Settings</p>
    </a>
</li>
@endif
    <li class="nav-item mt-3">
    <form method="POST" action="{{ route('logout') }}">
        @csrf

        <button type="submit" class="nav-link" style="width:100%; text-align:left; border:none; background:none;">
            <i class="fas fa-sign-out-alt nav-icon"></i>
            <p>Logout</p>
        </button>
    </form>
</li>

</ul>
</div> 
    </aside>

    {{-- Content Wrapper --}}
    <div class="content-wrapper p-4">
        @yield('content')
    </div>

</div>
</body>
</html>