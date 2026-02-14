    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="dashboard.php" class="brand-link">
            <i class="fas fa-chart-line brand-image ml-3"></i>
            <span class="brand-text font-weight-light">MOSAIC</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <i class="fas fa-user-circle fa-2x text-white"></i>
                </div>
                <div class="info">
                    <a href="#" class="d-block">Demo Admin</a>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    
                    <!-- SLO Management -->
                    <li class="nav-item">
                        <a href="admin_slo.php" class="nav-link <?= ($currentPage ?? '') === 'admin_slo' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-graduation-cap"></i>
                            <p>SLO Management</p>
                        </a>
                    </li>
                    
                    <!-- Student Management -->
                    <li class="nav-item">
                        <a href="admin_users.php" class="nav-link <?= ($currentPage ?? '') === 'admin_users' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-users"></i>
                            <p>Student Management</p>
                        </a>
                    </li>
                    
                    <li class="nav-header">INSTRUCTOR TOOLS</li>
                    
                    <!-- LTI Endpoint -->
                    <li class="nav-item">
                        <a href="lti_endpoint.php" class="nav-link <?= ($currentPage ?? '') === 'lti_endpoint' ? 'active' : '' ?>">
                            <i class="nav-icon fas fa-clipboard-check"></i>
                            <p>
                                Assessment Entry
                                <span class="badge badge-info right">LTI</span>
                            </p>
                        </a>
                    </li>
                    
                    <li class="nav-header">DEMO PORTAL</li>
                    
                    <!-- Back to Portal -->
                    <li class="nav-item">
                        <a href="index.php" class="nav-link">
                            <i class="nav-icon fas fa-home"></i>
                            <p>Demo Portal</p>
                        </a>
                    </li>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>
