<?php
/**
 * MOSAIC Demo Portal
 * Landing page for demo applications
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MOSAIC Demo Portal</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        :root {
            --primary-dark: #0D47A1;
            --accent-blue: #1976D2;
            --brand-teal: #1565C0;
        }
        
        body {
            background: linear-gradient(135deg, #1976D2 0%, #1565C0 50%, #0D47A1 100%);
            min-height: 100vh;
            font-family: 'Source Sans Pro', sans-serif;
        }
        
        .hero-section {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 20px;
            padding: 60px 40px;
            margin: 40px auto;
            max-width: 1200px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            color: var(--primary-dark);
            text-align: center;
            margin-bottom: 20px;
        }
        
        .hero-subtitle {
            font-size: 1.5rem;
            color: #555;
            text-align: center;
            margin-bottom: 40px;
        }
        
        .demo-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin: 40px 0;
        }
        
        .demo-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: block;
            border-top: 5px solid var(--accent-blue);
        }
        
        .demo-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            text-decoration: none;
            color: inherit;
        }
        
        .demo-card-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--accent-blue), var(--brand-teal));
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin-bottom: 20px;
        }
        
        .demo-card h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-dark);
            margin-bottom: 15px;
        }
        
        .demo-card p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        
        .demo-card-features {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        
        .demo-card-features li {
            padding: 5px 0;
            color: #555;
            font-size: 0.9rem;
        }
        
        .demo-card-features li i {
            color: var(--accent-blue);
            margin-right: 10px;
            width: 20px;
        }
        
        .btn-demo {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent-blue), var(--brand-teal));
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .btn-demo:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(25, 118, 210, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .tech-badge {
            display: inline-block;
            background: #f0f0f0;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.85rem;
            color: #666;
            margin: 5px;
        }
        
        .tech-section {
            text-align: center;
            margin-top: 40px;
            padding-top: 40px;
            border-top: 2px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container" style="max-width: 1400px;">
        <div class="hero-section">
            <div class="text-center mb-4">
                <i class="fas fa-chart-line" style="font-size: 4rem; color: var(--accent-blue);"></i>
            </div>
            
            <h1 class="hero-title">MOSAIC Demo</h1>
            <p class="hero-subtitle">Experience the Modular Outcomes System for Achievement and Institutional Compliance</p>
            
            <div class="demo-cards">
                <!-- Dashboard -->
                <a href="dashboard.php" class="demo-card">
                    <div class="demo-card-icon">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <h3>Analytics Dashboard</h3>
                    <p>
                        Comprehensive analytics and reporting interface for administrators and assessment coordinators.
                    </p>
                    <ul class="demo-card-features">
                        <li><i class="fas fa-check-circle"></i> Multi-dimensional filtering</li>
                        <li><i class="fas fa-check-circle"></i> Real-time visual analytics</li>
                        <li><i class="fas fa-check-circle"></i> Course-level breakdowns</li>
                        <li><i class="fas fa-check-circle"></i> Interactive data tables</li>
                    </ul>
                    <span class="btn-demo">
                        <i class="fas fa-arrow-right"></i> Launch Dashboard
                    </span>
                </a>
                
                <!-- SLO Management -->
                <a href="admin_slo.php" class="demo-card">
                    <div class="demo-card-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>SLO Management</h3>
                    <p>
                        Manage Student Learning Outcomes and view alignment with program and institutional outcomes.
                    </p>
                    <ul class="demo-card-features">
                        <li><i class="fas fa-check-circle"></i> Outcomes hierarchy mapping</li>
                        <li><i class="fas fa-check-circle"></i> Course-level SLO tracking</li>
                        <li><i class="fas fa-check-circle"></i> Assessment method display</li>
                        <li><i class="fas fa-check-circle"></i> Bulk upload capabilities</li>
                    </ul>
                    <span class="btn-demo">
                        <i class="fas fa-arrow-right"></i> Manage SLOs
                    </span>
                </a>
                
                <!-- Student Management -->
                <a href="admin_users.php" class="demo-card">
                    <div class="demo-card-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Student Management</h3>
                    <p>
                        View and manage student enrollments across all courses with powerful search and filter tools.
                    </p>
                    <ul class="demo-card-features">
                        <li><i class="fas fa-check-circle"></i> Enrollment tracking</li>
                        <li><i class="fas fa-check-circle"></i> Term-based filtering</li>
                        <li><i class="fas fa-check-circle"></i> Bulk import/export</li>
                        <li><i class="fas fa-check-circle"></i> Student data management</li>
                    </ul>
                    <span class="btn-demo">
                        <i class="fas fa-arrow-right"></i> View Students
                    </span>
                </a>
                
                <!-- LTI Endpoint -->
                <a href="lti_endpoint.php" class="demo-card">
                    <div class="demo-card-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <h3>Instructor Assessment</h3>
                    <p>
                        Simulated LTI-integrated interface for instructors to enter student outcome assessments.
                    </p>
                    <ul class="demo-card-features">
                        <li><i class="fas fa-check-circle"></i> LTI launch simulation</li>
                        <li><i class="fas fa-check-circle"></i> Bulk outcome entry</li>
                        <li><i class="fas fa-check-circle"></i> Quick action buttons</li>
                        <li><i class="fas fa-check-circle"></i> Optional score tracking</li>
                    </ul>
                    <span class="btn-demo">
                        <i class="fas fa-arrow-right"></i> Enter Assessments
                    </span>
                </a>
            </div>
            
            <div class="tech-section">
                <h4 style="color: var(--primary-dark); margin-bottom: 20px;">
                    <i class="fas fa-cog"></i> Built With Modern Technologies
                </h4>
                <div>
                    <span class="tech-badge"><i class="fab fa-php"></i> PHP 8.1+</span>
                    <span class="tech-badge"><i class="fas fa-database"></i> MySQL 8.0+</span>
                    <span class="tech-badge"><i class="fab fa-bootstrap"></i> AdminLTE 3</span>
                    <span class="tech-badge"><i class="fas fa-chart-bar"></i> Chart.js</span>
                    <span class="tech-badge"><i class="fas fa-table"></i> DataTables</span>
                    <span class="tech-badge"><i class="fas fa-plug"></i> LTI 1.1/1.3</span>
                </div>
            </div>
            
            <div class="alert alert-info mt-4" role="alert">
                <i class="fas fa-info-circle"></i> <strong>Demo Mode:</strong> 
                All pages use sample data from a CSV file (~50K assessment records). 
                No authentication required. Changes are not persisted.
            </div>
            
            <div class="text-center mt-4">
                <a href="../../README.md" class="text-muted">
                    <i class="fas fa-book"></i> View Documentation
                </a>
            </div>
        </div>
        
        <div style="text-align: center; padding: 30px 0; color: rgba(255,255,255,0.9);">
            <p style="margin: 0;">
                <strong>MOSAIC</strong> &copy; <?php echo date('Y'); ?> | 
                Modular Outcomes System for Achievement and Institutional Compliance
            </p>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
