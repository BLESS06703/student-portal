<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal — Empowering Future Leaders</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'Inter', -apple-system, sans-serif; color: #1a1a1a; background: #fff; overflow-x: hidden; -webkit-font-smoothing: antialiased; }
        :root { --accent: #f15a24; --accent-dark: #d44a1a; --accent-light: #fff5f0; }
        
        .header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 2rem; background: rgba(255,255,255,0.95); backdrop-filter: blur(20px); border-bottom: 1px solid #f0f0f0; position: fixed; top: 0; left: 0; right: 0; z-index: 100; }
        .logo { font-size: 1.25rem; font-weight: 800; display: flex; align-items: center; gap: 0.5rem; text-decoration: none; color: #1a1a1a; }
        .logo-icon { width: 34px; height: 34px; background: var(--accent); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; font-weight: 800; font-size: 0.95rem; }
        .nav { display: flex; gap: 2rem; align-items: center; }
        .nav a { color: #4b5563; text-decoration: none; font-size: 0.875rem; font-weight: 500; }
        .nav a:hover { color: var(--accent); }
        .btn { padding: 0.5rem 1.2rem; border-radius: 10px; font-weight: 600; font-size: 0.875rem; text-decoration: none !important; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s; }
        .btn-register { background: var(--accent); color: white; }
        .btn-register:hover { background: var(--accent-dark); color: white; }
        .btn-login { border: 1.5px solid #d1d5db; color: #1a1a1a; background: transparent; }
        .btn-login:hover { border-color: var(--accent); color: var(--accent); }
        .btn-primary { background: var(--accent); color: white; box-shadow: 0 4px 20px rgba(241,90,36,0.25); padding: 0.85rem 1.75rem; border-radius: 14px; font-size: 0.95rem; font-weight: 600; text-decoration: none !important; }
        .btn-primary:hover { background: var(--accent-dark); transform: translateY(-2px); box-shadow: 0 8px 30px rgba(241,90,36,0.35); }
        .btn-ghost { border: 1.5px solid #d1d5db; color: #1a1a1a; background: white; padding: 0.85rem 1.75rem; border-radius: 14px; font-size: 0.95rem; font-weight: 600; text-decoration: none !important; }
        .btn-ghost:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-2px); }
        .hamburger { display: none; background: none; border: none; cursor: pointer; flex-direction: column; gap: 5px; z-index: 101; }
        .hamburger span { display: block; width: 22px; height: 2px; background: #1a1a1a; border-radius: 2px; }
        .mobile-nav { display: none; position: fixed; inset: 0; background: #fff; z-index: 99; flex-direction: column; align-items: center; justify-content: center; gap: 2rem; }
        .mobile-nav.open { display: flex; }
        .mobile-nav a { font-size: 1.2rem; color: #1a1a1a; text-decoration: none; font-weight: 600; }
        
        .hero-wrapper { position: relative; overflow: hidden; background: linear-gradient(160deg, rgba(255,245,240,0.85) 0%, rgba(255,255,255,0.9) 30%, rgba(255,248,245,0.85) 50%, rgba(255,255,255,0.9) 70%, rgba(255,245,240,0.85) 100%), url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="800" viewBox="0 0 1200 800"><rect fill="%23fff5f0" width="1200" height="800"/><circle cx="200" cy="150" r="80" fill="%23fcd5c4" opacity="0.5"/><circle cx="1000" cy="600" r="120" fill="%23fcd5c4" opacity="0.4"/><circle cx="600" cy="400" r="60" fill="%23fcd5c4" opacity="0.3"/><rect x="300" y="500" width="400" height="8" rx="4" fill="%23fcd5c4" opacity="0.4"/><rect x="350" y="530" width="300" height="8" rx="4" fill="%23fcd5c4" opacity="0.3"/><rect x="400" y="560" width="200" height="8" rx="4" fill="%23fcd5c4" opacity="0.2"/></svg>'); background-size: cover; background-position: center; min-height: 90vh; display: flex; align-items: center; padding-top: 60px; }
        .hero-wrapper::before { content: ''; position: absolute; top: -30%; right: -15%; width: 500px; height: 500px; background: radial-gradient(circle, rgba(241,90,36,0.1) 0%, transparent 70%); border-radius: 50%; }
        .hero { position: relative; z-index: 10; max-width: 1100px; margin: 0 auto; padding: 4rem 2rem; display: grid; grid-template-columns: 1fr 1fr; gap: 3rem; align-items: center; }
        .hero-text h1 { font-size: 3rem; font-weight: 900; line-height: 1.15; letter-spacing: -1.5px; margin-bottom: 1.25rem; color: #1a1a1a; }
        .hero-text h1 span { color: var(--accent); }
        .hero-text p { font-size: 1.1rem; color: #6b7280; margin-bottom: 2rem; line-height: 1.7; max-width: 460px; }
        .hero-btns { display: flex; gap: 1rem; flex-wrap: wrap; }
        .hero-badge { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0.9rem; background: white; border: 1px solid #fcd5c4; border-radius: 30px; font-size: 0.78rem; font-weight: 600; color: var(--accent); margin-bottom: 1.5rem; }
        .hero-badge .dot { width: 7px; height: 7px; background: #16a34a; border-radius: 50%; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1;} 50%{opacity:0.4;} }
        @keyframes float { 0%,100%{transform:translateY(0);} 50%{transform:translateY(-12px);} }
        .hero-card { background: white; border: 1px solid #f0f0f0; border-radius: 24px; padding: 2rem; box-shadow: 0 20px 60px rgba(0,0,0,0.06); animation: float 6s ease-in-out infinite; }
        .hero-card .card-row { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.25rem; }
        .hero-card .card-avatar { width: 42px; height: 42px; background: var(--accent); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; }
        .hero-card .card-title { font-weight: 600; font-size: 0.95rem; }
        .hero-card .card-subtitle { font-size: 0.78rem; color: #6b7280; }
        .hero-card .tags { display: flex; gap: 0.4rem; flex-wrap: wrap; margin-bottom: 1.25rem; }
        .hero-card .tag { padding: 0.3rem 0.6rem; background: var(--accent-light); border-radius: 6px; font-size: 0.7rem; color: var(--accent); font-weight: 500; }
        .hero-card .stats { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; text-align: center; padding-top: 1rem; border-top: 1px solid #f0f0f0; }
        .hero-card .stat-value { font-size: 1.25rem; font-weight: 800; }
        .hero-card .stat-label { font-size: 0.65rem; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 0.15rem; }
        
        .section { padding: 5rem 2rem; max-width: 1100px; margin: 0 auto; }
        .section-label { text-align: center; font-size: 0.78rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: var(--accent); margin-bottom: 0.75rem; }
        .section h2 { font-size: 2.2rem; font-weight: 800; text-align: center; margin-bottom: 0.75rem; }
        .section .subtitle { text-align: center; color: #6b7280; margin-bottom: 3rem; font-size: 1.05rem; max-width: 500px; margin-left: auto; margin-right: auto; }
        
        .programs-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
        .program-card { background: #fff; padding: 1.5rem; border-radius: 16px; text-align: center; border: 1px solid #f0f0f0; box-shadow: 0 4px 16px rgba(0,0,0,0.03); transition: all 0.3s; cursor: pointer; min-height: 130px; display: flex; flex-direction: column; justify-content: center; }
        .program-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(241,90,36,0.1); border-color: #fcd5c4; }
        .program-card:active { transform: scale(0.97); }
        .program-icon { width: 44px; height: 44px; background: var(--accent-light); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 0.75rem; font-size: 1.1rem; color: var(--accent); }
        .program-card h3 { font-size: 0.9rem; font-weight: 700; margin-bottom: 0.2rem; }
        .program-card p { font-size: 0.78rem; color: #6b7280; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 2rem; text-align: center; max-width: 900px; margin: 0 auto; }
        .stat-item h3 { font-size: 2.5rem; font-weight: 900; color: var(--accent); }
        .stat-item p { font-size: 0.9rem; color: #4b5563; font-weight: 500; }
        
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.25rem; }
        .feature-card { background: #fff; padding: 2rem 1.5rem; border-radius: 20px; text-align: center; border: 1px solid #f0f0f0; box-shadow: 0 4px 16px rgba(0,0,0,0.03); transition: all 0.3s; }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(241,90,36,0.1); }
        .feature-icon { width: 52px; height: 52px; background: var(--accent-light); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem; font-size: 1.3rem; color: var(--accent); }
        .feature-card h3 { font-weight: 700; margin-bottom: 0.5rem; font-size: 1rem; }
        .feature-card p { font-size: 0.88rem; color: #6b7280; line-height: 1.6; }
        
        .testimonial-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; }
        .testimonial-card { background: #fff; padding: 1.75rem; border-radius: 20px; border: 1px solid #f0f0f0; box-shadow: 0 4px 16px rgba(0,0,0,0.03); text-align: center; }
        .testimonial-card .quote { font-size: 0.9rem; color: #4b5563; line-height: 1.7; margin-bottom: 1rem; font-style: italic; }
        .testimonial-card .author { display: flex; align-items: center; gap: 0.75rem; justify-content: center; }
        .testimonial-card .author-avatar { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.8rem; }
        .testimonial-card .author-name { font-weight: 600; font-size: 0.85rem; }
        .testimonial-card .author-role { font-size: 0.72rem; color: #9ca3af; }
        
        .partners-grid { display: flex; justify-content: center; align-items: center; gap: 3rem; flex-wrap: wrap; }
        .partner-logo { width: 100px; height: 60px; background: #f0f0f0; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; color: #9ca3af; font-weight: 600; }
        
        .faq-item { background: #fff; border: 1px solid #f0f0f0; border-radius: 14px; margin-bottom: 0.5rem; overflow: hidden; }
        .faq-question { padding: 1rem 1.25rem; font-weight: 600; font-size: 0.9rem; cursor: pointer; display: flex; justify-content: space-between; align-items: center; }
        .faq-answer { padding: 0 1.25rem 1rem; font-size: 0.85rem; color: #6b7280; display: none; }
        .faq-item.open .faq-answer { display: block; }
        
        .news-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.25rem; }
        .news-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #f0f0f0; box-shadow: 0 4px 16px rgba(0,0,0,0.03); }
        .news-card .news-img { height: 160px; background: var(--accent-light); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--accent); }
        .news-card .news-body { padding: 1.25rem; }
        .news-card .news-date { font-size: 0.7rem; color: #9ca3af; margin-bottom: 0.25rem; }
        .news-card h4 { font-weight: 700; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .news-card p { font-size: 0.82rem; color: #6b7280; line-height: 1.5; }
        
        .cta-section { background: var(--accent); padding: 4rem 2rem; text-align: center; color: white; }
        .cta-section h2 { font-size: 2rem; font-weight: 800; margin-bottom: 0.75rem; }
        .cta-section p { color: rgba(255,255,255,0.85); margin-bottom: 2rem; font-size: 1.05rem; }
        .btn-white { background: white; color: var(--accent); padding: 0.9rem 2rem; border-radius: 14px; font-weight: 600; font-size: 1rem; text-decoration: none !important; display: inline-flex; align-items: center; gap: 0.5rem; }
        
        .footer { background: #1a1a1a; color: rgba(255,255,255,0.5); padding: 3rem 2rem 2rem; font-size: 0.85rem; }
        .footer a { color: rgba(255,255,255,0.6); text-decoration: none; font-size: 0.8rem; line-height: 2; transition: color 0.2s; }
        .footer a:hover { color: white; }
        .footer-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 2rem; max-width: 900px; margin: 0 auto 2rem; text-align: left; }
        .footer h4 { color: white; margin-bottom: 0.75rem; font-size: 0.85rem; }
        
        .back-to-top { position: fixed; bottom: 90px; right: 20px; width: 44px; height: 44px; background: var(--accent); color: white; border: none; border-radius: 50%; cursor: pointer; z-index: 80; font-size: 1.2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
        .back-to-top:hover { background: var(--accent-dark); transform: translateY(-2px); }
        
        .mobile-cta { display: none; position: fixed; bottom: 0; left: 0; right: 0; padding: 0.75rem 1rem; background: white; border-top: 1px solid #f0f0f0; z-index: 90; gap: 0.5rem; }
        .mobile-cta .btn { flex: 1; justify-content: center; }
        .spacer { padding-bottom: 80px; }
        
        @media (max-width: 768px) {
            .nav { display: none; }
            .hamburger { display: flex; }
            .hero { grid-template-columns: 1fr; padding: 2rem 1rem 3rem; text-align: center; }
            .hero-text h1 { font-size: 2rem; }
            .hero-text p { max-width: 100%; }
            .hero-btns { justify-content: center; }
            .hero-card { display: none; }
            .section { padding: 3rem 1rem; }
            .section h2 { font-size: 1.6rem; }
            .features-grid { grid-template-columns: 1fr 1fr; }
            .mobile-cta { display: flex; }
        }
    </style>
</head>
<body>
    <header class="header">
        <a href="home.php" class="logo"><div class="logo-icon">SP</div>Student Portal</a>
        <button class="hamburger" onclick="toggleMenu()"><span></span><span></span><span></span></button>
        <nav class="nav">
            <a href="#programs">Programs</a>
            <a href="#features">Features</a>
            <a href="#testimonials">Testimonials</a>
            <a href="#news">News</a>
            <a href="#faq">FAQ</a>
            <a href="register.php" class="btn btn-register">Register</a>
            <a href="index.php" class="btn btn-login">Sign In</a>
        </nav>
    </header>
    
    <div class="mobile-nav" id="mobileNav">
        <a href="#programs" onclick="toggleMenu()">Programs</a>
        <a href="#features" onclick="toggleMenu()">Features</a>
        <a href="#testimonials" onclick="toggleMenu()">Testimonials</a>
        <a href="register.php" class="btn btn-register">Register</a>
        <a href="index.php" class="btn btn-login">Sign In</a>
    </div>
    
    <div class="hero-wrapper">
        <div class="hero">
            <div class="hero-text">
                <div class="hero-badge"><span class="dot"></span> Applications Open for 2026</div>
                <h1>Build Your <span>Future</span> With Practical Skills</h1>
                <p>Access course materials, submit assignments, track your performance, and connect with instructors — all in one powerful platform designed for vocational excellence.</p>
                <div class="hero-btns">
                    <a href="register.php" class="btn-primary"><i class="fas fa-rocket"></i> Get Started</a>
                    <a href="#programs" class="btn-ghost"><i class="fas fa-graduation-cap"></i> View Programs</a>
                </div>
            </div>
            <div class="hero-card">
                <div class="card-row"><div class="card-avatar"><i class="fas fa-book-open"></i></div><div><div class="card-title">Digital Learning Platform</div><div class="card-subtitle">Vocational Training Portal</div></div></div>
                <div class="tags"><span class="tag">Assignments</span><span class="tag">Course Notes</span><span class="tag">GPA Tracking</span><span class="tag">Attendance</span></div>
                <div class="stats"><div><div class="stat-value">11+</div><div class="stat-label">Programs</div></div><div><div class="stat-value">24/7</div><div class="stat-label">Access</div></div><div><div class="stat-value">Free</div><div class="stat-label">For Students</div></div></div>
            </div>
        </div>
    </div>
    
    <!-- Stats Section -->
    <section class="section" id="stats" style="background:var(--accent-light);max-width:100%;">
        <div class="stats-grid">
            <div class="stat-item"><h3>11+</h3><p>Vocational Programs</p></div>
            <div class="stat-item"><h3>500+</h3><p>Active Students</p></div>
            <div class="stat-item"><h3>50+</h3><p>Qualified Instructors</p></div>
            <div class="stat-item"><h3>95%</h3><p>Employment Rate</p></div>
        </div>
    </section>
    
    <!-- Programs -->
    <section class="section" id="programs">
        <div class="section-label">Academic Programs</div>
        <h2>Vocational Training That Leads to Careers</h2>
        <p class="subtitle">Industry-aligned courses designed to equip you with practical, employable skills.</p>
        <div class="programs-grid">
            <?php
            try {
                $pdo = new PDO("mysql:host=" . getenv('DB_HOST') . ";port=" . getenv('DB_PORT') . ";dbname=" . getenv('DB_NAME') . ";charset=utf8mb4", getenv('DB_USER'), getenv('DB_PASS'));
                $courses = $pdo->query('SELECT name, code FROM courses ORDER BY name')->fetchAll();
                $icons = ['ICT' => 'fa-laptop-code', 'Automobile' => 'fa-car', 'Electrical' => 'fa-bolt', 'Brick' => 'fa-hard-hat', 'Carpentry' => 'fa-hammer', 'Plumbing' => 'fa-wrench', 'Health' => 'fa-heartbeat', 'Community' => 'fa-users', 'Administrative' => 'fa-briefcase', 'Accountancy' => 'fa-calculator', 'Entrepreneurship' => 'fa-lightbulb'];
                foreach ($courses as $c):
                    $icon = 'fa-graduation-cap';
                    foreach ($icons as $key => $val) { if (strpos($c['name'], $key) !== false) { $icon = $val; break; } }
            ?>
                <div class="program-card"><div class="program-icon"><i class="fas <?php echo $icon; ?>"></i></div><h3><?php echo htmlspecialchars($c['name']); ?></h3><p><?php echo htmlspecialchars($c['code']); ?></p></div>
            <?php endforeach; } catch (PDOException $e) { ?>
                <div class="program-card"><div class="program-icon"><i class="fas fa-graduation-cap"></i></div><h3>Loading programs...</h3></div>
            <?php } ?>
        </div>
    </section>
    
    <!-- Features -->
    <section class="section" id="features" style="background:#fafafa;max-width:100%;">
        <div style="max-width:1100px;margin:0 auto;">
            <div class="section-label">Platform Features</div>
            <h2>Everything You Need to Succeed</h2>
            <p class="subtitle">A complete learning management system built for vocational students and teachers.</p>
            <div class="features-grid">
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-file-alt"></i></div><h3>Course Materials</h3><p>Access lecture notes and past papers anytime.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-tasks"></i></div><h3>Assignments & Grading</h3><p>Submit work and receive detailed teacher feedback.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-chart-line"></i></div><h3>Performance Analytics</h3><p>Monitor GPA and track academic progress.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-bullhorn"></i></div><h3>Announcements</h3><p>Stay informed with course announcements.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-user-check"></i></div><h3>Attendance Tracking</h3><p>Track your attendance records.</p></div>
                <div class="feature-card"><div class="feature-icon"><i class="fas fa-shield-alt"></i></div><h3>Secure & Private</h3><p>Enterprise-grade security protecting your data.</p></div>
            </div>
        </div>
    </section>
    
    <!-- Partners -->
    <section class="section" id="partners">
        <div class="section-label">Our Partners</div>
        <h2>Trusted By Leading Organizations</h2>
        <p class="subtitle">We collaborate with industry partners to ensure our training meets professional standards.</p>
        <div class="partners-grid">
            <div class="partner-logo">TEVETA</div>
            <div class="partner-logo">Ministry of Education</div>
            <div class="partner-logo">NACTE</div>
            <div class="partner-logo">Industry Partner</div>
        </div>
    </section>
    
    <!-- Testimonials -->
    <section class="section" id="testimonials" style="background:#fafafa;max-width:100%;">
        <div style="max-width:1100px;margin:0 auto;">
            <div class="section-label">Success Stories</div>
            <h2>What Our Students Say</h2>
            <p class="subtitle">Real feedback from students using the platform daily.</p>
            <div class="testimonial-grid">
                <div class="testimonial-card"><div class="quote">"This portal made it so easy to access my course materials and submit assignments."</div><div class="author"><div class="author-avatar" style="background:var(--accent);">BK</div><div><div class="author-name">Blessings Kaunda</div><div class="author-role">ICT Student</div></div></div></div>
                <div class="testimonial-card"><div class="quote">"As a class rep, I can now post announcements and track attendance in minutes."</div><div class="author"><div class="author-avatar" style="background:var(--accent-dark);">AC</div><div><div class="author-name">Angie Chawinga</div><div class="author-role">Class Representative</div></div></div></div>
                <div class="testimonial-card"><div class="quote">"The GPA tracking helped me improve from C's to A's in one semester."</div><div class="author"><div class="author-avatar" style="background:#e07a3d;">NS</div><div><div class="author-name">Nafe Sumba</div><div class="author-role">Automobile Mechanics</div></div></div></div>
            </div>
        </div>
    </section>
    
    <!-- News -->
    <section class="section" id="news">
        <div class="section-label">Latest News</div>
        <h2>What's Happening</h2>
        <p class="subtitle">Stay updated with the latest events and announcements.</p>
        <div class="news-grid">
            <div class="news-card"><div class="news-img"><i class="fas fa-calendar-alt"></i></div><div class="news-body"><div class="news-date">May 15, 2026</div><h4>New ICT Program Launched</h4><p>We are excited to announce our expanded ICT curriculum with new specializations.</p></div></div>
            <div class="news-card"><div class="news-img"><i class="fas fa-trophy"></i></div><div class="news-body"><div class="news-date">May 10, 2026</div><h4>Students Win National Competition</h4><p>Our Automobile Mechanics students took first place at the national skills competition.</p></div></div>
            <div class="news-card"><div class="news-img"><i class="fas fa-handshake"></i></div><div class="news-body"><div class="news-date">May 5, 2026</div><h4>New Industry Partnership</h4><p>We have partnered with leading companies for student internship opportunities.</p></div></div>
        </div>
    </section>
    
    <!-- FAQ -->
    <section class="section" id="faq" style="background:#fafafa;max-width:100%;">
        <div style="max-width:700px;margin:0 auto;">
            <div class="section-label">FAQ</div>
            <h2>Frequently Asked Questions</h2>
            <p class="subtitle">Quick answers to common questions about our platform.</p>
            <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)">How do I register for an account? <i class="fas fa-chevron-down"></i></div><div class="faq-answer">Click the "Register" button and fill in your student details. Your account will be reviewed by an administrator before approval.</div></div>
            <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)">Is the platform free to use? <i class="fas fa-chevron-down"></i></div><div class="faq-answer">Yes! The Student Portal is completely free for all registered students.</div></div>
            <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)">How do I submit assignments? <i class="fas fa-chevron-down"></i></div><div class="faq-answer">Navigate to the Assignments page, download the question paper, complete your work, and upload it before the due date.</div></div>
            <div class="faq-item"><div class="faq-question" onclick="toggleFAQ(this)">Can I access the portal on my phone? <i class="fas fa-chevron-down"></i></div><div class="faq-answer">Absolutely! The portal is fully mobile-responsive and works on any device with a browser.</div></div>
        </div>
    </section>
    
    <!-- CTA -->
    <section class="cta-section">
        <h2>Ready to Start Your Journey?</h2>
        <p>Join students already using the platform to achieve their academic goals.</p>
        <a href="register.php" class="btn-white"><i class="fas fa-user-plus"></i> Get Started</a>
    </section>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="footer-grid">
            <div><h4>Quick Links</h4><a href="index.php">Sign In</a><br><a href="register.php">Register</a><br><a href="#programs">Programs</a><br><a href="#features">Features</a></div>
            <div><h4>Downloads</h4><a href="#">Student Handbook (PDF)</a><br><a href="#">Academic Calendar</a><br><a href="#">Application Form</a><br><a href="#">Course Brochure</a></div>
            <div><h4>Connect With Us</h4><div style="display:flex;gap:1rem;font-size:1.3rem;"><a href="#" title="Facebook"><i class="fab fa-facebook"></i></a><a href="#" title="Twitter"><i class="fab fa-twitter"></i></a><a href="#" title="Instagram"><i class="fab fa-instagram"></i></a><a href="#" title="LinkedIn"><i class="fab fa-linkedin"></i></a><a href="#" title="WhatsApp"><i class="fab fa-whatsapp"></i></a></div></div>
            <div style="display:none;"><h4>Contact</h4><p style="font-size:0.8rem;"><i class="fas fa-map-marker-alt"></i> P.O Box 1234, Lilongwe</p><p style="font-size:0.8rem;"><i class="fas fa-phone"></i> +265 888 000 000</p><p style="font-size:0.8rem;"><i class="fas fa-envelope"></i> info@school.ac.mw</p></div>
        </div>
        <p style="border-top:1px solid rgba(255,255,255,0.1);padding-top:1.5rem;">&copy; 2026 Student Portal. All rights reserved.</p>
    </footer>
    
    <button class="back-to-top" onclick="window.scrollTo({top:0,behavior:'smooth'})" title="Back to top"><i class="fas fa-arrow-up"></i></button>
    
    <div class="spacer"></div>
    <div class="mobile-cta">
        <a href="register.php" class="btn-primary">Get Started</a>
        <a href="index.php" class="btn-ghost">Sign In</a>
    </div>
    
    <script>
        function toggleMenu() { document.getElementById('mobileNav').classList.toggle('open'); }
        function toggleFAQ(el) { el.parentElement.classList.toggle('open'); }
        const observer = new IntersectionObserver((entries) => { entries.forEach(entry => { if (entry.isIntersecting) { entry.target.style.opacity = '1'; entry.target.style.transform = 'translateY(0)'; } }); }, { threshold: 0.15 });
        document.querySelectorAll('.program-card, .feature-card, .testimonial-card, .news-card, .stat-item').forEach(el => { el.style.opacity = '0'; el.style.transform = 'translateY(20px)'; el.style.transition = 'all 0.6s ease-out'; observer.observe(el); });
    </script>
</body>
</html>
