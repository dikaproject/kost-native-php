<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aula Kost - Modern & Affordable Boarding House</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2E3A59;
            --accent-color: #333333;
            --accent-gradient: linear-gradient(135deg, #333333 0%, #111111 100%);
            --text-color: #444444;
            --text-secondary: #777777;
            --light-bg: #f8f9fa;
            --white: #ffffff;
            --card-shadow: 0 10px 30px rgba(0,0,0,0.05);
            --transition: all 0.3s ease;
            --border-radius: 12px;
            --border-radius-sm: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            color: var(--text-color);
            background-color: var(--light-bg);
            line-height: 1.7;
            overflow-x: hidden;
        }

        a {
            text-decoration: none;
            color: inherit;
        }

        /* Container */
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Button Styles */
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 50px;
            font-weight: 500;
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .btn:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
        }

        .btn:before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 100%;
            transition: all .3s;
            z-index: -1;
        }

        .btn:hover {
            color: #fff;
            transform: translateY(-3px);
        }

        .btn:hover:before {
            width: 100%;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: var(--white);
        }

        .btn-primary:before {
            background: var(--primary-color);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--primary-color);
        }

        .btn-secondary:before {
            background: var(--accent-gradient);
        }

        /* Header Styles */
        header {
            position: fixed;
            width: 100%;
            top: 0;
            left: 0;
            z-index: 1000;
            background-color: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            box-shadow: 0 2px 20px rgba(0,0,0,0.03);
            transition: var(--transition);
        }

        header.scrolled {
            padding: 15px 0;
            background-color: var(--primary-color);
        }

        header.scrolled .logo-text, header.scrolled .nav-links a {
            color: var(--white);
        }

        header.scrolled .nav-links a.btn-secondary {
            color: var(--primary-color);
        }

        header.scrolled .nav-links a:hover {
            color: var(--accent-color);
        }

        header.scrolled .hamburger div {
            background-color: var(--white);
        }

        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px 0;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-gradient);
            color: var(--white);
            border-radius: 12px;
            font-size: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .logo-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, rgba(255,255,255,0.2) 0%, rgba(255,255,255,0) 60%);
        }

        .logo-icon i {
            font-size: 22px;
            z-index: 1;
        }

        .logo-text {
            font-size: 26px;
            font-weight: 700;
            color: var(--primary-color);
            letter-spacing: -0.5px;
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }

        .logo-text .brand-name {
            display: flex;
            align-items: center;
        }

        .logo-text span {
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo-tagline {
            font-size: 10px;
            font-weight: 500;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        nav {
            display: flex;
            align-items: center;
        }

        .nav-links {
            display: flex;
            list-style: none;
        }

        .nav-links li {
            margin-left: 35px;
            font-weight: 500;
            font-size: 15px;
        }

        .nav-links a {
            color: var(--text-color);
            transition: var(--transition);
            position: relative;
            padding: 5px 0;
        }

        .nav-links a:hover {
            color: var(--accent-color);
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--accent-gradient);
            transition: var(--transition);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-buttons {
            display: flex;
            gap: 12px;
            margin-left: 35px;
        }

        .nav-buttons .btn {
            padding: 8px 20px;
            font-size: 14px;
        }

        .hamburger {
            display: none;
            cursor: pointer;
        }

        .hamburger div {
            width: 25px;
            height: 2px;
            margin: 6px;
            transition: var(--transition);
            background-color: var(--primary-color);
        }

        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)), url('https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
            background-size: cover;
            background-position: center;
            color: var(--white);
            padding: 220px 0 150px;
            margin-bottom: 100px;
            position: relative;
            overflow: hidden;
        }

        .hero:before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 150px;
            background: linear-gradient(to top, var(--light-bg), transparent);
            z-index: 1;
        }

        .hero-content {
            max-width: 650px;
            position: relative;
            z-index: 2;
            animation: fadeInUp 1s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 24px;
            line-height: 1.2;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .hero p {
            font-size: 1.1rem;
            margin-bottom: 35px;
            opacity: 0.9;
            max-width: 90%;
            text-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        .hero-buttons {
            display: flex;
            gap: 15px;
        }

        /* Features Section */
        .section {
            padding: 100px 0;
        }

        .section-title {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            color: var(--primary-color);
            position: relative;
            display: inline-block;
        }

        .section-title h2:after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 50px;
            height: 3px;
            background: var(--accent-gradient);
            border-radius: 3px;
        }

        .section-title p {
            color: var(--text-secondary);
            max-width: 650px;
            margin: 0 auto;
            font-size: 1.1rem;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .feature-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            padding: 40px 30px;
            text-align: center;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            z-index: 1;
        }

        .feature-card:hover {
            transform: translateY(-15px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .feature-card:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--accent-gradient);
            transition: var(--transition);
        }

        .feature-card:hover:before {
            height: 10px;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            color: var(--white);
            border-radius: 50%;
            background: var(--accent-gradient);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .feature-card h3 {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 1.4rem;
        }

        .feature-card p {
            color: var(--text-secondary);
            font-size: 0.95rem;
            line-height: 1.6;
        }

        /* Rooms Section */
        .rooms-section {
            background-color: var(--white);
            position: relative;
            overflow: hidden;
        }

        .rooms-section:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://www.transparenttextures.com/patterns/cubes.png');
            opacity: 0.03;
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 30px;
        }

        .room-card {
            background-color: var(--white);
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--card-shadow);
            transition: var(--transition);
            position: relative;
        }

        .room-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .room-img {
            height: 240px;
            overflow: hidden;
            position: relative;
        }

        .room-img:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to bottom, transparent 60%, rgba(0,0,0,0.7));
            z-index: 1;
            opacity: 0;
            transition: var(--transition);
        }

        .room-card:hover .room-img:before {
            opacity: 1;
        }

        .room-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .room-card:hover .room-img img {
            transform: scale(1.08);
        }

        .room-content {
            padding: 25px;
            position: relative;
        }

        .room-type {
            color: var(--accent-color);
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 10px;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .room-title {
            margin-bottom: 15px;
            color: var(--primary-color);
            font-size: 1.4rem;
        }

        .room-price {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--primary-color);
            display: flex;
            align-items: baseline;
        }

        .room-price span {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 400;
            margin-left: 5px;
        }

        .room-features {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 25px;
        }

        .room-feature {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 0.9rem;
            color: var(--text-secondary);
            background-color: var(--light-bg);
            padding: 6px 12px;
            border-radius: 30px;
        }

        .room-feature i {
            color: var(--accent-color);
        }

        .room-btn {
            display: block;
            text-align: center;
            padding: 12px;
            background: var(--accent-gradient);
            color: var(--white);
            border-radius: var(--border-radius-sm);
            transition: var(--transition);
            font-weight: 500;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .room-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
        }

        /* Testimonials */
        .testimonials-slider {
            max-width: 850px;
            margin: 0 auto;
            overflow: hidden;
            position: relative;
        }

        .testimonials {
            display: flex;
            transition: transform 0.5s ease;
        }

        .testimonial {
            min-width: 100%;
            padding: 20px;
        }

        .testimonial-content {
            background-color: var(--white);
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--card-shadow);
            position: relative;
        }

        .testimonial-content::before {
            content: '\201C';
            position: absolute;
            top: 25px;
            left: 25px;
            font-size: 5rem;
            color: var(--accent-color);
            opacity: 0.15;
            line-height: 1;
            font-family: serif;
        }

        .testimonial p {
            margin-bottom: 25px;
            font-style: italic;
            color: var(--text-color);
            position: relative;
            z-index: 1;
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .author-img {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--white);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .author-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .author-info h4 {
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .author-info p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
            font-style: normal;
        }

        .slider-dots {
            display: flex;
            justify-content: center;
            margin-top: 35px;
            gap: 10px;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #dddddd;
            cursor: pointer;
            transition: var(--transition);
        }

        .dot.active {
            background: var(--accent-gradient);
            transform: scale(1.2);
        }

        /* CTA Section */
        .cta-section {
            background: linear-gradient(rgba(17, 17, 17, 0.95), rgba(34, 34, 34, 0.95)), url('https://images.unsplash.com/photo-1560448204-603b3fc33ddc?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8NDd8fGtvc3R8ZW58MHx8MHx8fDA%3D&auto=format&fit=crop&w=500&q=60');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            color: var(--white);
            text-align: center;
            padding: 120px 0;
            position: relative;
            overflow: hidden;
        }

        .cta-section:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('https://www.transparenttextures.com/patterns/cubes.png');
            opacity: 0.1;
        }

        .cta-content {
            max-width: 750px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .cta-content h2 {
            font-size: 2.8rem;
            margin-bottom: 25px;
            line-height: 1.3;
        }

        .cta-content p {
            margin-bottom: 35px;
            opacity: 0.9;
            font-size: 1.1rem;
            max-width: 80%;
            margin-left: auto;
            margin-right: auto;
        }

        .cta-section .btn {
            padding: 15px 35px;
            font-size: 1.1rem;
        }

        /* Footer */
        footer {
            background-color: #1a1a1a;
            color: var(--white);
            padding: 80px 0 20px;
            position: relative;
        }

        footer:before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: var(--accent-gradient);
        }

        .footer-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 40px;
            margin-bottom: 60px;
        }

        .footer-col h3 {
            color: white;
            margin-bottom: 25px;
            font-size: 1.3rem;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-col h3:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background: var(--accent-gradient);
        }

        .footer-col ul {
            list-style: none;
        }

        .footer-col ul li {
            margin-bottom: 12px;
        }

        .footer-col ul li a {
            color: var(--white);
            opacity: 0.75;
            transition: var(--transition);
            display: inline-block;
        }

        .footer-col ul li a:hover {
            opacity: 1;
            color: var(--accent-color);
            transform: translateX(5px);
        }

        .footer-contact p {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            opacity: 0.75;
        }

        .footer-contact p i {
            color: var(--accent-color);
            font-size: 1.2rem;
        }

        .social-links {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            color: var(--white);
        }

        .social-link:hover {
            background: var(--accent-gradient);
            transform: translateY(-5px);
        }

        .copyright {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            opacity: 0.7;
        }

        /* Mobile Navigation */
        @media (max-width: 992px) {
            .nav-links {
                position: fixed;
                top: 80px;
                right: -100%;
                flex-direction: column;
                background-color: var(--white);
                width: 100%;
                height: calc(100vh - 80px);
                padding: 50px 40px;
                transition: var(--transition);
                z-index: 100;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }

            .nav-links.active {
                right: 0;
            }

            .nav-links li {
                margin: 20px 0;
            }

            .hamburger {
                display: block;
                z-index: 101;
            }

            .hamburger.active div:nth-child(1) {
                transform: rotate(-45deg) translate(-5px, 6px);
            }

            .hamburger.active div:nth-child(2) {
                opacity: 0;
            }

            .hamburger.active div:nth-child(3) {
                transform: rotate(45deg) translate(-5px, -6px);
            }

            .nav-buttons {
                margin-top: 20px;
                flex-direction: column;
                width: 100%;
            }

            .nav-buttons .btn {
                width: 100%;
                margin: 5px 0;
            }
        }

        /* Responsive Styles */
        @media (max-width: 992px) {
            .features {
                grid-template-columns: repeat(2, 1fr);
            }

            .rooms-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .footer-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hero h1 {
                font-size: 2.8rem;
            }
            
            .cta-content h2 {
                font-size: 2.4rem;
            }
        }

        @media (max-width: 768px) {
            .hero {
                padding: 180px 0 100px;
            }

            .hero h1 {
                font-size: 2.4rem;
            }
            
            .hero p {
                font-size: 1rem;
            }

            .section {
                padding: 70px 0;
            }

            .section-title h2 {
                font-size: 2rem;
            }
            
            .cta-content h2 {
                font-size: 2rem;
            }
            
            .cta-content p {
                max-width: 95%;
            }
            
            .testimonial-content {
                padding: 30px 25px;
            }
        }

        @media (max-width: 576px) {
            .features {
                grid-template-columns: 1fr;
            }

            .rooms-grid {
                grid-template-columns: 1fr;
            }

            .footer-container {
                grid-template-columns: 1fr;
            }

            .hero-buttons {
                flex-direction: column;
                width: 100%;
                gap: 15px;
            }
            
            .hero-buttons .btn {
                width: 100%;
                text-align: center;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .section-title h2 {
                font-size: 1.8rem;
            }
            
            .cta-content h2 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header id="header">
        <div class="container header-container">
            <!-- Improved Logo Structure -->
            <a href="#" class="logo">
                <div class="logo-icon">
                    <i class="fas fa-home"></i>
                </div>
                <div class="logo-text">
                    <div class="brand-name">Aula<span>Kost</span></div>
                    <div class="logo-tagline">Hunian Modern</div>
                </div>
            </a>
            <nav>
                <ul class="nav-links">
                    <li><a href="#home">Beranda</a></li>
                    <li><a href="#about">Tentang Kami</a></li>
                    <li><a href="#rooms">Kamar</a></li>
                    <li><a href="#testimonials">Testimoni</a></li>
                    <li><a href="#contact">Kontak</a></li>
                    <div class="nav-buttons">
                        <a href="login.php" class="btn btn-secondary">Masuk</a>
                        <a href="register.php" class="btn btn-primary">Daftar</a>
                    </div>
                </ul>
                <div class="hamburger">
                    <div class="line1"></div>
                    <div class="line2"></div>
                    <div class="line3"></div>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <h1>Hunian Modern & Nyaman</h1>
                <p>Temukan perpaduan sempurna antara kenyamanan, kemudahan, dan komunitas di Aula Kost. Fasilitas modern kami dan lokasi strategis menjadikan kami pilihan ideal untuk mahasiswa dan profesional muda.</p>
                <div class="hero-buttons">
                    <a href="#rooms" class="btn btn-primary">Lihat Kamar</a>
                    <a href="register.php" class="btn btn-secondary">Pesan Sekarang</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="section" id="about">
        <div class="container">
            <div class="section-title">
                <h2>Mengapa Memilih Aula Kost?</h2>
                <p>Kami menyediakan fasilitas dan layanan terbaik untuk memastikan kenyamanan dan kemudahan Anda.</p>
            </div>
            <div class="features">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-map-marker-alt"></i>
                    </div>
                    <h3>Lokasi Strategis</h3>
                    <p>Terletak dekat dengan universitas, pusat perbelanjaan, dan transportasi umum untuk akses mudah ke semua yang Anda butuhkan.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-wifi"></i>
                    </div>
                    <h3>WiFi Kecepatan Tinggi</h3>
                    <p>Tetap terhubung dengan koneksi internet cepat dan andal yang tersedia di semua kamar.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Keamanan 24/7</h3>
                    <p>Merasa aman dengan sistem keamanan 24 jam termasuk CCTV dan akses yang terjamin.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-couch"></i>
                    </div>
                    <h3>Kamar Nyaman</h3>
                    <p>Kamar dengan desain bagus, furnitur berkualitas, dan tempat tidur nyaman untuk tidur yang nyenyak.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-hands-helping"></i>
                    </div>
                    <h3>Staf yang Membantu</h3>
                    <p>Staf kami yang ramah selalu siap membantu Anda dengan segala kebutuhan atau pertanyaan.</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3>Semua Tagihan Termasuk</h3>
                    <p>Tidak ada biaya tambahan - listrik, air, dan internet sudah termasuk dalam biaya sewa bulanan Anda.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Rooms Section -->
    <section class="section rooms-section" id="rooms">
        <div class="container">
            <div class="section-title">
                <h2>Kamar Kami</h2>
                <p>Pilih dari berbagai kamar yang dirancang dengan baik sesuai kebutuhan dan anggaran Anda.</p>
            </div>
            <div class="rooms-grid">
                <div class="room-card">
                    <div class="room-img">
                        <img src="https://images.unsplash.com/photo-1595526114035-0d45ed16cfbf?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8Nnx8ZG9ybXxlbnwwfHwwfHx8MA%3D%3D&auto=format&fit=crop&w=500&q=60" alt="Kamar Standar">
                    </div>
                    <div class="room-content">
                        <div class="room-type">STANDAR</div>
                        <h3 class="room-title">Kamar Single Standar</h3>
                        <div class="room-price">Rp 500.000 <span>/ bulan</span></div>
                        <div class="room-features">
                            <div class="room-feature">
                                <i class="fas fa-bed"></i> Tempat Tidur Single
                            </div>
                            <div class="room-feature">
                                <i class="fas fa-warehouse"></i> 9m²
                            </div>
                            <div class="room-feature">
                                <i class="fas fa-user"></i> 1 Orang
                            </div>
                        </div>
                        <a href="login.php" class="room-btn">Pesan Sekarang</a>
                    </div>
                </div>
                <div class="room-card">
                    <div class="room-img">
                        <img src="https://images.unsplash.com/photo-1590490360182-c33d57733427?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8OXx8ZG9ybXxlbnwwfHwwfHx8MA%3D%3D&auto=format&fit=crop&w=500&q=60" alt="Kamar Deluxe">
                    </div>
                    <div class="room-content">
                        <div class="room-type">DELUXE</div>
                        <h3 class="room-title">Kamar Single Deluxe</h3>
                        <div class="room-price">Rp 650.000 <span>/ bulan</span></div>
                        <div class="room-features">
                            <div class="room-feature">
                                <i class="fas fa-bed"></i> Tempat Tidur Single
                            </div>
                            <div class="room-feature">
                                <i class="fas fa-warehouse"></i> 12m²
                            </div>
                            <div class="room-feature">
                                <i class="fas fa-user"></i> 1 Orang
                            </div>
                        </div>
                        <a href="login.php" class="room-btn">Pesan Sekarang</a>
                    </div>
                </div>
                <div class="room-card">
                    <div class="room-img">
                        <img src="https://images.unsplash.com/photo-1555854877-bab0e564b8d5?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MzR8fGRvcm18ZW58MHx8MHx8fDA%3D&auto=format&fit=crop&w=500&q=60" alt="Kamar Premium">
                    </div>
                    <div class="room-content">
                        <div class="room-type">PREMIUM</div>
                        <h3 class="room-title">Kamar Single Premium</h3>
                        <div class="room-price">Rp 800.000 <span>/ bulan</span></div>
                        <div class="room-features">
                            <div class="room-feature">
                                <i class="fas fa-bed"></i> Tempat Tidur Queen
                            </div>
                            <div class="room-feature">
                                <i class="fas fa-warehouse"></i> 15m²
                            </div>
                            <div class="room-feature">
                                <i class="fas fa-user"></i> 1-2 Orang
                            </div>
                        </div>
                        <a href="login.php" class="room-btn">Pesan Sekarang</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="section" id="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>Apa Kata Penghuni Kami</h2>
                <p>Dengarkan dari orang-orang yang telah merasakan tinggal di Aula Kost.</p>
            </div>
            <div class="testimonials-slider">
                <div class="testimonials">
                    <div class="testimonial">
                        <div class="testimonial-content">
                            <p>"Tinggal di Aula Kost adalah pengalaman yang luar biasa. Kamarnya nyaman, fasilitasnya bagus, dan stafnya selalu membantu. Lokasinya sempurna untuk saya sebagai mahasiswa - hanya berjalan kaki singkat ke kampus!"</p>
                            <div class="testimonial-author">
                                <div class="author-img">
                                    <img src="https://randomuser.me/api/portraits/women/33.jpg" alt="Sarah">
                                </div>
                                <div class="author-info">
                                    <h4>Sarah Wijaya</h4>
                                    <p>Mahasiswa</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial">
                        <div class="testimonial-content">
                            <p>"Saya telah tinggal di Aula Kost selama lebih dari setahun, dan saya sangat puas dengan pilihan saya. Keamanannya membuat saya merasa aman, dan adanya internet kecepatan tinggi sangat membantu untuk setup kerja dari rumah saya."</p>
                            <div class="testimonial-author">
                                <div class="author-img">
                                    <img src="https://randomuser.me/api/portraits/men/52.jpg" alt="Budi">
                                </div>
                                <div class="author-info">
                                    <h4>Budi Santoso</h4>
                                    <p>Pengembang Software</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="testimonial">
                        <div class="testimonial-content">
                            <p>"Suasana komunitas di Aula Kost adalah yang paling saya sukai. Saya telah mendapatkan teman-teman baru di sini, dan area bersama sangat cocok untuk bersosialisasi. Tim manajemen menjaga semuanya bersih dan terawat. Sangat direkomendasikan!"</p>
                            <div class="testimonial-author">
                                <div class="author-img">
                                    <img src="https://randomuser.me/api/portraits/women/68.jpg" alt="Dewi">
                                </div>
                                <div class="author-info">
                                    <h4>Dewi Anggraini</h4>
                                    <p>Profesional Muda</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="slider-dots">
                    <div class="dot active" data-index="0"></div>
                    <div class="dot" data-index="1"></div>
                    <div class="dot" data-index="2"></div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Siap Merasakan Aula Kost?</h2>
                <p>Bergabunglah dengan komunitas penghuni yang bahagia dan nikmati kenyamanan serta kemudahan Aula Kost. Kamar tersedia terbatas, jangan sampai ketinggalan!</p>
                <a href="register.php" class="btn btn-primary">Daftar Sekarang</a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer id="contact">
        <div class="container">
            <div class="footer-container">
                <div class="footer-col">
                    <h3>Aula Kost</h3>
                    <p>Kost modern, nyaman, dan terjangkau yang terletak di area strategis, sempurna untuk mahasiswa dan profesional muda.</p>
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="footer-col">
                    <h3>Link Cepat</h3>
                    <ul>
                        <li><a href="#home">Beranda</a></li>
                        <li><a href="#about">Tentang Kami</a></li>
                        <li><a href="#rooms">Kamar</a></li>
                        <li><a href="#testimonials">Testimoni</a></li>
                        <li><a href="#contact">Kontak</a></li>
                    </ul>
                </div>
                <div class="footer-col">
                    <h3>Kamar Kami</h3>
                    <ul>
                        <li><a href="#rooms">Kamar Single Standar</a></li>
                        <li><a href="#rooms">Kamar Single Deluxe</a></li>
                        <li><a href="#rooms">Kamar Single Premium</a></li>
                        <li><a href="#rooms">Kamar Berbagi Standar</a></li>
                        <li><a href="#rooms">Paket Spesial</a></li>
                    </ul>
                </div>
                <div class="footer-col footer-contact">
                    <h3>Hubungi Kami</h3>
                    <p><i class="fas fa-map-marker-alt" style="color: white;"></i> Jl. Kost Nyaman No. 123, Jakarta</p>
                    <p><i class="fas fa-phone-alt" style="color: white;"></i> +62 812-3456-7890</p>
                    <p><i class="fas fa-envelope" style="color: white;"></i> info@aulakost.com</p>
                    <p><i class="fas fa-clock" style="color: white;"></i> Jam Kerja: 9 Pagi - 6 Sore</p>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 Aula Kost. Hak Cipta Dilindungi.</p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile Navigation
        const hamburger = document.querySelector('.hamburger');
        const navLinks = document.querySelector('.nav-links');
        
        hamburger.addEventListener('click', () => {
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
        });
        
        // Header Scroll Effect
        const header = document.getElementById('header');
        
        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });
        
        // Testimonials Slider
        const testimonials = document.querySelector('.testimonials');
        const dots = document.querySelectorAll('.dot');
        let currentIndex = 0;
        
        dots.forEach(dot => {
            dot.addEventListener('click', () => {
                const index = parseInt(dot.getAttribute('data-index'));
                goToSlide(index);
            });
        });
        
        function goToSlide(index) {
            testimonials.style.transform = `translateX(-${index * 100}%)`;
            dots.forEach(d => d.classList.remove('active'));
            dots[index].classList.add('active');
            currentIndex = index;
        }
        
        // Auto slide every 5 seconds
        setInterval(() => {
            currentIndex = (currentIndex + 1) % dots.length;
            goToSlide(currentIndex);
        }, 5000);
        
        // Smooth Scroll for Navigation Links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                const targetId = this.getAttribute('href');
                if (targetId === '#') return;
                
                const targetElement = document.querySelector(targetId);
                if (targetElement) {
                    window.scrollTo({
                        top: targetElement.offsetTop - 80,
                        behavior: 'smooth'
                    });
                    
                    // Close mobile menu if open
                    hamburger.classList.remove('active');
                    navLinks.classList.remove('active');
                }
            });
        });

        // Add animation on scroll
        const animateOnScroll = () => {
            const elements = document.querySelectorAll('.feature-card, .room-card, .testimonial-content');
            
            elements.forEach(element => {
                const elementPosition = element.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.2;
                
                if (elementPosition < screenPosition) {
                    element.style.opacity = 1;
                    element.style.transform = 'translateY(0)';
                }
            });
        };
        
        // Initially set elements to be animated
        document.addEventListener('DOMContentLoaded', () => {
            const elements = document.querySelectorAll('.feature-card, .room-card, .testimonial-content');
            elements.forEach(element => {
                element.style.opacity = 0;
                element.style.transform = 'translateY(30px)';
                element.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            });
            
            // Trigger animation for elements in view on page load
            setTimeout(animateOnScroll, 300);
        });
        
        // Listen for scroll to animate elements
        window.addEventListener('scroll', animateOnScroll);
    </script>
</body>
</html>

