<aside class="main-sidebar sidebar-dark-primary elevation-4">
    <a href="#" class="brand-link text-center">
        <span class="brand-text font-weight-light">Admin Panel</span>
    </a>

    <div class="sidebar">
        <nav>
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">

                {{-- Dashboard --}}
                <li class="nav-item">
                    <a href="/admin" class="nav-link {{ request()->is('admin') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-home"></i>
                        <p>Dashboard</p>
                    </a>
                </li>

                {{-- Valid Visitor --}}
                <li class="nav-item">
                    <a href="{{ route('valid.visitors.index') }}" 
                       class="nav-link {{ request()->is('admin/valid-visitors') ? 'active' : '' }}">
                        <i class="nav-icon fas fa-user-check"></i>
                        <p>Valid Visitor</p>
                    </a>
                </li>

            </ul>
        </nav>
    </div>
</aside>
